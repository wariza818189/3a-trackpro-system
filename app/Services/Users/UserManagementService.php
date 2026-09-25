<?php

namespace App\Services\Users;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function create(User $actor, array $attributes): User
    {
        $this->requireKeys($attributes, ['name', 'username', 'password'], ['role']);
        $name = $this->requiredText($attributes['name'], 'name');
        $username = $this->requiredText($attributes['username'], 'username');
        $password = $this->requiredText($attributes['password'], 'password');
        $role = $this->validRole($attributes['role'] ?? 'staff');

        return DB::transaction(function () use ($actor, $name, $username, $password, $role): User {
            [$persistedActor] = $this->lockUsers($actor);

            $user = new User;
            $user->name = $name;
            $user->username = $username;
            $user->password = $password;
            $user->role = $role;
            $user->status = User::STATUS_ACTIVE;
            $user->save();

            $this->audit($persistedActor, $user, 'USER_CREATED', null, [
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'status' => $user->status,
            ], 'User account created.');

            return $user;
        });
    }

    public function updateProfile(User $actor, User $target, array $attributes): User
    {
        $this->requireKeys($attributes, [], ['name', 'username']);

        return DB::transaction(function () use ($actor, $target, $attributes): User {
            [$persistedActor, $user] = $this->lockUsers($actor, $target);
            $before = [];
            $after = [];

            foreach (['name', 'username'] as $field) {
                if (! array_key_exists($field, $attributes)) {
                    continue;
                }

                $value = $this->requiredText($attributes[$field], $field);
                $oldValue = $user->{$field};
                $user->{$field} = $value;
                if ($user->{$field} !== $oldValue) {
                    $before[$field] = $oldValue;
                    $after[$field] = $user->{$field};
                }
            }

            if ($after !== []) {
                $user->save();
                $this->audit($persistedActor, $user, 'USER_UPDATED', $before, $after, 'User profile updated.');
            }

            return $user;
        });
    }

    public function changeRole(User $actor, User $target, string $role): User
    {
        $role = $this->validRole($role);

        return DB::transaction(function () use ($actor, $target, $role): User {
            [$persistedActor, $user] = $this->lockUsers($actor, $target);

            if ($user->role === $role) {
                return $user;
            }
            if ($persistedActor->is($user) && $role === 'staff') {
                throw ValidationException::withMessages(['role' => 'You cannot demote your own Admin account.']);
            }
            if ($user->isAdmin() && $user->isActive() && $role === 'staff') {
                $this->requireAnotherActiveAdmin($user);
            }

            $before = ['role' => $user->role];
            $user->role = $role;
            $user->save();
            $this->audit($persistedActor, $user, 'USER_ROLE_CHANGED', $before, ['role' => $role], 'User role changed.');

            return $user;
        });
    }

    public function archive(User $actor, User $target): User
    {
        return DB::transaction(function () use ($actor, $target): User {
            [$persistedActor, $user] = $this->lockUsers($actor, $target);

            if ($persistedActor->is($user)) {
                throw ValidationException::withMessages(['status' => 'You cannot disable your own Admin account.']);
            }
            if (! $user->isActive()) {
                return $user;
            }
            if ($user->isAdmin()) {
                $this->requireAnotherActiveAdmin($user);
            }

            $user->status = 'disabled';
            $user->save();
            $this->audit($persistedActor, $user, 'USER_DISABLED', ['status' => 'active'], ['status' => 'disabled'], 'User account disabled.');

            return $user;
        });
    }

    public function reactivate(User $actor, User $target): User
    {
        return DB::transaction(function () use ($actor, $target): User {
            [$persistedActor, $user] = $this->lockUsers($actor, $target);

            if ($user->isActive()) {
                return $user;
            }

            $user->status = User::STATUS_ACTIVE;
            $user->save();
            $this->audit($persistedActor, $user, 'USER_REACTIVATED', ['status' => 'disabled'], ['status' => 'active'], 'User account reactivated.');

            return $user;
        });
    }

    public function resetPassword(User $actor, User $target, string $password): User
    {
        $password = $this->requiredText($password, 'password');

        return DB::transaction(function () use ($actor, $target, $password): User {
            [$persistedActor, $user] = $this->lockUsers($actor, $target);
            $user->password = $password;
            $user->save();
            $this->audit($persistedActor, $user, 'USER_PASSWORD_RESET', null, null, 'User password reset.');

            return $user;
        });
    }

    /** @return array{User, User}|array{User} */
    private function lockUsers(User $actor, ?User $target = null): array
    {
        // Every operation serializes on the oldest User before locking other Users.
        $mutex = User::query()->orderBy('id')->lockForUpdate()->first();
        $ids = array_unique(array_filter([$actor->getKey(), $target?->getKey()]));
        sort($ids, SORT_NUMERIC);

        $locked = [];
        foreach ($ids as $id) {
            $locked[$id] = (int) $mutex?->getKey() === (int) $id
                ? $mutex
                : User::query()->whereKey($id)->lockForUpdate()->first();
        }

        $persistedActor = $locked[$actor->getKey()] ?? null;
        if ($persistedActor === null || ! $persistedActor->isActive() || ! $persistedActor->isAdmin()) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin is required.']);
        }

        if ($target === null) {
            return [$persistedActor];
        }

        $persistedTarget = $locked[$target->getKey()] ?? null;
        if ($persistedTarget === null) {
            throw ValidationException::withMessages(['target' => 'The selected user no longer exists.']);
        }

        return [$persistedActor, $persistedTarget];
    }

    private function requireAnotherActiveAdmin(User $target): void
    {
        $activeAdminIds = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($activeAdminIds->count() < 2 || ! $activeAdminIds->contains($target->getKey())) {
            throw ValidationException::withMessages(['role' => 'At least one active Admin account must remain.']);
        }
    }

    private function audit(User $actor, User $target, string $action, ?array $before, ?array $after, string $description): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->getKey(),
            'action' => $action,
            'entity_type' => 'user',
            'entity_id' => $target->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'description' => $description,
        ]);
    }

    private function validRole(mixed $role): string
    {
        if (! in_array($role, [User::ROLE_ADMIN, 'staff'], true)) {
            throw ValidationException::withMessages(['role' => 'Select a valid user role.']);
        }

        return $role;
    }

    private function requiredText(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages([$field => "The {$field} field is required."]);
        }

        return $value;
    }

    private function requireKeys(array $attributes, array $required, array $optional): void
    {
        foreach ($required as $key) {
            if (! array_key_exists($key, $attributes)) {
                throw ValidationException::withMessages([$key => "The {$key} field is required."]);
            }
        }
        if (array_diff(array_keys($attributes), [...$required, ...$optional]) !== []) {
            throw ValidationException::withMessages(['attributes' => 'Unsupported account fields were provided.']);
        }
    }
}
