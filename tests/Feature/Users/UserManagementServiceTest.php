<?php

namespace Tests\Feature\Users;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Users\UserManagementService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class UserManagementServiceTest extends TestCase
{
    private UserManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('User Management service tests require in-memory SQLite.');
        }

        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->string('entity_type', 40)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('description');
            $table->timestamp('created_at')->nullable();
        });
        $this->service = app(UserManagementService::class);
    }

    public function test_creation_defaults_to_active_staff_and_logs_only_safe_values(): void
    {
        $actor = $this->admin();
        $user = $this->service->create($actor, [
            'name' => 'New User', 'username' => '  NEW_USER  ', 'password' => 'secret-password-123',
        ]);

        $this->assertSame('staff', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertSame('new_user', $user->username);
        $this->assertNotSame('secret-password-123', $user->password);
        $this->assertTrue(Hash::check('secret-password-123', $user->password));
        $this->assertAudit('USER_CREATED', $actor, $user, null, [
            'name' => 'New User', 'username' => 'new_user', 'role' => 'staff', 'status' => 'active',
        ]);
    }

    public function test_explicit_admin_creation_is_active_and_unsupported_fields_and_roles_fail(): void
    {
        $actor = $this->admin();
        $user = $this->service->create($actor, [
            'name' => 'Second Admin', 'username' => 'second_admin',
            'password' => 'secret-password-123', 'role' => 'admin',
        ]);
        $this->assertSame('admin', $user->role);
        $this->assertSame('active', $user->status);

        foreach ([['role' => 'owner'], ['status' => 'disabled'], ['remember_token' => 'secret']] as $extra) {
            try {
                $this->service->create($actor, array_merge([
                    'name' => 'Unsafe', 'username' => 'unsafe', 'password' => 'secret-password-123',
                ], $extra));
                $this->fail('Unsupported create input was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseMissing('users', ['username' => 'unsafe']);
            }
        }
    }

    public function test_stale_staff_and_disabled_admin_actors_are_rejected_inside_transactions(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();
        $this->rejects(fn () => $this->service->create($staff, $this->createInput()));

        DB::table('users')->where('id', $admin->id)->update(['role' => 'staff']);
        $this->rejects(fn () => $this->service->create($admin, $this->createInput()));
        DB::table('users')->where('id', $admin->id)->update(['role' => 'admin', 'status' => 'disabled']);
        $this->rejects(fn () => $this->service->updateProfile($admin, $staff, ['name' => 'Blocked']));
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_profile_changes_only_changed_fields_and_no_op_is_silent(): void
    {
        $actor = $this->admin();
        $target = User::factory()->create(['name' => 'Old Name', 'username' => 'old_user']);
        $updated = $this->service->updateProfile($actor, $target, ['name' => 'New Name', 'username' => '  NEW_USER  ']);
        $this->assertSame('New Name', $updated->fresh()->name);
        $this->assertSame('new_user', $updated->fresh()->username);
        $this->assertAudit('USER_UPDATED', $actor, $target,
            ['name' => 'Old Name', 'username' => 'old_user'],
            ['name' => 'New Name', 'username' => 'new_user']);

        $this->service->updateProfile($actor, $target, ['name' => 'New Name', 'username' => ' NEW_USER ']);
        $this->assertSame(1, AuditLog::query()->count());
        $this->rejects(fn () => $this->service->updateProfile($actor, $target, ['role' => 'admin']));
    }

    public function test_own_profile_update_is_allowed(): void
    {
        $actor = $this->admin();
        $this->service->updateProfile($actor, $actor, ['name' => 'Renamed', 'username' => '  RENAMED_ADMIN  ']);
        $this->assertSame('Renamed', $actor->fresh()->name);
        $this->assertSame('renamed_admin', $actor->fresh()->username);
        $this->assertSame(['name', 'username'], array_keys(AuditLog::query()->firstOrFail()->after_values));
    }

    public function test_promotion_and_safe_demotion_log_only_role_transitions(): void
    {
        $actor = $this->admin();
        $target = User::factory()->create();
        $this->service->changeRole($actor, $target, 'admin');
        $this->assertSame('admin', $target->fresh()->role);
        $this->assertAudit('USER_ROLE_CHANGED', $actor, $target, ['role' => 'staff'], ['role' => 'admin']);

        $this->service->changeRole($actor, $target, 'staff');
        $this->assertSame('staff', $target->fresh()->role);
        $this->assertSame(['role' => 'admin'], AuditLog::query()->latest('id')->firstOrFail()->before_values);
        $this->assertSame(['role' => 'staff'], AuditLog::query()->latest('id')->firstOrFail()->after_values);
        $this->service->changeRole($actor, $target, 'staff');
        $this->assertSame(2, AuditLog::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'USER_UPDATED')->count());
    }

    public function test_sole_admin_and_self_demotion_are_rejected_without_audit(): void
    {
        $actor = $this->admin();
        $this->rejects(fn () => $this->service->changeRole($actor, $actor, 'staff'));
        $this->assertSame('admin', $actor->fresh()->role);

        $other = $this->admin();
        $this->rejects(fn () => $this->service->changeRole($actor, $actor, 'staff'));
        $this->assertSame('admin', $actor->fresh()->role);
        $this->assertSame(0, AuditLog::query()->count());
        $this->service->changeRole($actor, $other, 'staff');
        $this->assertSame('staff', $other->fresh()->role);
    }

    public function test_archive_and_reactivate_staff_preserve_row_and_reference(): void
    {
        $actor = $this->admin();
        $target = User::factory()->create();
        Schema::create('historical_user_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
        });
        DB::table('historical_user_references')->insert(['user_id' => $target->id]);

        $this->service->archive($actor, $target);
        $this->assertSame('disabled', $target->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('historical_user_references', ['user_id' => $target->id]);
        $this->assertAudit('USER_DISABLED', $actor, $target, ['status' => 'active'], ['status' => 'disabled']);
        $this->service->archive($actor, $target);
        $this->assertSame(1, AuditLog::query()->count());

        $this->service->reactivate($actor, $target);
        $this->assertSame('active', $target->fresh()->status);
        $this->assertSame('USER_REACTIVATED', AuditLog::query()->latest('id')->firstOrFail()->action);
        $this->assertSame(['status' => 'disabled'], AuditLog::query()->latest('id')->firstOrFail()->before_values);
        $this->service->reactivate($actor, $target);
        $this->assertSame(2, AuditLog::query()->count());
    }

    public function test_admin_archive_requires_another_active_admin_and_cannot_archive_self(): void
    {
        $actor = $this->admin();
        $this->rejects(fn () => $this->service->archive($actor, $actor));
        $other = $this->admin();
        $this->rejects(fn () => $this->service->archive($actor, $actor));
        $this->assertSame(0, AuditLog::query()->count());

        $this->service->archive($actor, $other);
        $this->assertSame('disabled', $other->fresh()->status);
        $this->assertSame('USER_DISABLED', AuditLog::query()->firstOrFail()->action);
        $this->assertSame('active', $actor->fresh()->status);
    }

    public function test_stale_actor_cannot_demote_or_archive_the_only_active_admin(): void
    {
        $actor = $this->admin();
        $other = $this->admin();
        DB::table('users')->where('id', $actor->id)->update(['role' => 'staff']);
        $this->rejects(fn () => $this->service->changeRole($actor, $other, 'staff'));
        $this->rejects(fn () => $this->service->archive($actor, $other));
        $this->assertSame('admin', $other->fresh()->role);
        $this->assertSame('active', $other->fresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_disabled_admin_can_change_role_and_be_reactivated_without_reducing_active_admins(): void
    {
        $actor = $this->admin();
        $target = User::factory()->admin()->disabled()->create();

        $this->service->changeRole($actor, $target, 'staff');
        $this->assertSame('staff', $target->fresh()->role);
        $this->assertAudit('USER_ROLE_CHANGED', $actor, $target, ['role' => 'admin'], ['role' => 'staff']);
        $this->service->reactivate($actor, $target);
        $this->assertSame('active', $target->fresh()->status);
        $this->assertSame('USER_REACTIVATED', AuditLog::query()->latest('id')->firstOrFail()->action);
        $this->assertSame('admin', $actor->fresh()->role);
    }

    public function test_password_reset_for_self_and_other_is_hashed_and_private(): void
    {
        $actor = $this->admin();
        $target = User::factory()->create();
        foreach ([$target, $actor] as $user) {
            $this->service->resetPassword($actor, $user, 'replacement-secret-123');
            $this->assertTrue(Hash::check('replacement-secret-123', $user->fresh()->password));
        }
        foreach (AuditLog::query()->get() as $log) {
            $this->assertSame('USER_PASSWORD_RESET', $log->action);
            $this->assertNull($log->before_values);
            $this->assertNull($log->after_values);
            $this->assertSame('User password reset.', $log->description);
        }
    }

    public function test_all_account_audits_use_privacy_allowlist(): void
    {
        $actor = $this->admin();
        $user = $this->service->create($actor, $this->createInput());
        $this->service->updateProfile($actor, $user, ['name' => 'Changed']);
        $this->service->changeRole($actor, $user, 'admin');
        $this->service->archive($actor, $user);
        $this->service->reactivate($actor, $user);
        $this->service->resetPassword($actor, $user, 'replacement-secret-123');

        $this->assertSame([
            'USER_CREATED', 'USER_UPDATED', 'USER_ROLE_CHANGED',
            'USER_DISABLED', 'USER_REACTIVATED', 'USER_PASSWORD_RESET',
        ], AuditLog::query()->orderBy('id')->pluck('action')->all());
        foreach (AuditLog::query()->get() as $log) {
            foreach ([$log->before_values ?? [], $log->after_values ?? []] as $values) {
                $this->assertSame([], array_diff(array_keys($values), ['name', 'username', 'role', 'status']));
            }
            $serialized = json_encode([$log->before_values, $log->after_values, $log->description]);
            foreach (['secret-password-123', 'replacement-secret-123', $user->password, $actor->remember_token, 'remember_token'] as $secret) {
                $this->assertStringNotContainsString($secret, $serialized);
            }
        }
    }

    public function test_audit_insert_failure_rolls_back_profile_mutation(): void
    {
        $actor = $this->admin();
        $target = User::factory()->create(['name' => 'Original']);
        // A SQLite trigger makes the real AuditLog INSERT fail after User::save().
        DB::statement("CREATE TRIGGER reject_account_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");

        try {
            $this->service->updateProfile($actor, $target, ['name' => 'Changed']);
            $this->fail('Audit persistence unexpectedly succeeded.');
        } catch (QueryException) {
            $this->assertSame('Original', $target->fresh()->name);
            $this->assertSame(0, AuditLog::query()->count());
        }
    }

    public function test_service_exposes_no_delete_method(): void
    {
        $this->assertFalse(method_exists($this->service, 'delete'));
        $this->assertFalse(method_exists($this->service, 'forceDelete'));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function createInput(): array
    {
        return ['name' => 'New User', 'username' => 'new_user', 'password' => 'secret-password-123'];
    }

    private function rejects(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a controlled policy rejection.');
        } catch (ValidationException) {
            // Future HTTP requests can render this as a redirect or 422 response.
        }
    }

    private function assertAudit(string $action, User $actor, User $target, ?array $before, ?array $after): void
    {
        $log = AuditLog::query()->where('action', $action)->latest('id')->firstOrFail();
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame('user', $log->entity_type);
        $this->assertSame($target->id, $log->entity_id);
        $this->assertSame($before, $log->before_values);
        $this->assertSame($after, $log->after_values);
    }
}
