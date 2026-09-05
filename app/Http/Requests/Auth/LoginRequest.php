<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public const AUTHENTICATION_FAILED_MESSAGE = 'The provided credentials are incorrect.';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $username = $this->input('username');

        if (! is_string($username)) {
            return;
        }

        $this->merge([
            'username' => User::normalizeUsername($username),
        ]);
    }

    /** @throws ValidationException */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::guard('web')->attempt([
            'username' => $this->string('username')->toString(),
            'password' => $this->string('password')->toString(),
            'status' => User::STATUS_ACTIVE,
        ]);

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'username' => self::AUTHENTICATION_FAILED_MESSAGE,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public static function throttleKeyFor(string $username, string $ipAddress): string
    {
        $identity = User::normalizeUsername($username).'|'.$ipAddress;

        return 'login:'.hash('sha256', $identity);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    private function throttleKey(): string
    {
        return self::throttleKeyFor(
            $this->string('username')->toString(),
            (string) $this->ip(),
        );
    }
}
