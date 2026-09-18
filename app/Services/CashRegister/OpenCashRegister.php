<?php

namespace App\Services\CashRegister;

use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenCashRegister
{
    private const MONEY_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    private const MAX_OPENING_CASH = '99999999999999.99';

    public function execute(User $actor, mixed $openingCash): CashRegisterSession
    {
        $actorId = filter_var($actor->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($actorId === false) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin or Staff user is required.']);
        }

        $canonicalOpeningCash = $this->canonicalizeOpeningCash($openingCash);

        try {
            return DB::transaction(function () use ($actorId, $canonicalOpeningCash): CashRegisterSession {
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

                $activeSession = CashRegisterSession::query()
                    ->where('active_slot', 1)
                    ->lockForUpdate()
                    ->first(['id']);
                if ($activeSession !== null) {
                    throw $this->alreadyOpenException();
                }

                $session = new CashRegisterSession;
                $session->opening_cash = $canonicalOpeningCash;
                $session->opened_by = $persistedActor->getKey();
                $session->opened_at = now();
                $session->closed_by = null;
                $session->closed_at = null;
                $session->active_slot = 1;
                $session->save();

                return $session;
            });
        } catch (QueryException $exception) {
            if (! $this->isActiveSlotDuplicate($exception)) {
                throw $exception;
            }

            throw $this->alreadyOpenException();
        }
    }

    private function canonicalizeOpeningCash(mixed $value): string
    {
        if (! is_string($value) || preg_match(self::MONEY_PATTERN, trim($value)) !== 1) {
            throw ValidationException::withMessages([
                'opening_cash' => 'Enter a nonnegative ordinary decimal amount with up to two decimal places.',
            ]);
        }

        [$integer, $fraction] = array_pad(explode('.', trim($value), 2), 2, '');
        $integer = ltrim($integer, '0');
        $canonical = ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, 2, '0');

        if (strlen(strtok($canonical, '.')) > 14
            || bccomp($canonical, self::MAX_OPENING_CASH, 2) === 1) {
            throw ValidationException::withMessages([
                'opening_cash' => 'The opening cash amount is too large.',
            ]);
        }

        return $canonical;
    }

    private function alreadyOpenException(): ValidationException
    {
        return ValidationException::withMessages([
            'opening_cash' => 'The cash register is already open.',
        ]);
    }

    private function isActiveSlotDuplicate(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return ($sqlState === '23000' && $driverCode === 1062
                && str_contains($message, 'cash_register_sessions_active_slot_unique'))
            || ($driverCode === 19
                && str_contains($message, 'unique constraint failed: cash_register_sessions.active_slot'));
    }
}
