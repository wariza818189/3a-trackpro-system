<?php

namespace App\Services\CashRegister;

use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseCashRegister
{
    public function execute(User $actor): CashRegisterSession
    {
        $actorId = filter_var($actor->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($actorId === false) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin or Staff user is required.']);
        }

        return DB::transaction(function () use ($actorId): CashRegisterSession {
            $persistedActor = User::query()
                ->whereKey((int) $actorId)
                ->lockForUpdate()
                ->first(['id', 'role', 'status']);
            if ($persistedActor === null
                || ! $persistedActor->isActive()
                || ! in_array($persistedActor->role, [User::ROLE_ADMIN, 'staff'], true)) {
                throw ValidationException::withMessages([
                    'actor' => 'A persisted active Admin or Staff user is required.',
                ]);
            }

            $session = CashRegisterSession::query()
                ->where('active_slot', 1)
                ->lockForUpdate()
                ->first();
            if ($session === null) {
                throw ValidationException::withMessages([
                    'register' => 'No active cash register session is available to close.',
                ]);
            }

            if (! $persistedActor->isAdmin()
                && (int) $session->opened_by !== (int) $persistedActor->getKey()) {
                throw new AuthorizationException('Staff may close only the cash register session they opened.');
            }

            $session->closed_by = $persistedActor->getKey();
            $session->closed_at = now();
            $session->active_slot = null;
            $session->save();

            return $session;
        });
    }
}
