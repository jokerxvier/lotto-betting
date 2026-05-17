<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects any authenticated user with no working sign-in method to
 * /auth/setup-pin. A user is "complete" if they have EITHER:
 *  - manual credentials: username + pin_hash both set, OR
 *  - Telegram link: telegram_id set
 *
 * Telegram-onboarded users skip setup-pin entirely — `telegram_id` already
 * proves identity. They may opt-in to set a username + PIN later as a
 * backup credential by navigating to /auth/setup-pin manually.
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

        $hasManualAuth = $user->username !== null && $user->pin_hash !== null;
        $hasTelegram = $user->telegram_id !== null;

        if ($hasManualAuth || $hasTelegram) {
            return $next($request);
        }

        if ($request->routeIs('auth.setup-pin', 'auth.setup-pin.store', 'logout')) {
            return $next($request);
        }

        return redirect()->route('auth.setup-pin');
    }
}
