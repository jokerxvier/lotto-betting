<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects any authenticated user with an incomplete account (missing
 * username or pin_hash — typically a Telegram-onboarded user who hasn't
 * picked a PIN yet) to /auth/setup-pin.
 *
 * The setup-pin routes themselves and the logout route are exempt so the
 * user can complete setup or log out without bouncing.
 */
final class EnsureAccountSetupIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->username !== null && $user->pin_hash !== null) {
            return $next($request);
        }

        if ($request->routeIs('auth.setup-pin', 'auth.setup-pin.store', 'logout')) {
            return $next($request);
        }

        return redirect()->route('auth.setup-pin');
    }
}
