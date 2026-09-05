<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $persistedStatus = $user instanceof User
            ? User::query()->whereKey($user->getAuthIdentifier())->value('status')
            : null;

        if ($persistedStatus !== User::STATUS_ACTIVE) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new AuthenticationException(
                'Unauthenticated.',
                ['web'],
                $request->expectsJson() ? null : route('login'),
            );
        }

        return $next($request);
    }
}
