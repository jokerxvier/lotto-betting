<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only sign-in. Username + password (no PIN). Separate URL so a
 * player typing their PIN at /admin/login fails generically — no leaking
 * which usernames are admins.
 *
 * Throttle (`admin-login`) is wired in FortifyServiceProvider — 5/min/IP.
 */
final class AdminLoginController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/admin-login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = [
            'username' => (string) $request->validated('username'),
            'password' => (string) $request->validated('password'),
        ];

        // Auth::attempt returns false for: unknown user, wrong password,
        // or a user without a `password` set (player accounts). All three
        // surface as a single generic error to avoid enumeration.
        if (! Auth::attempt($credentials)) {
            Log::channel('audit')->info('admin.login.failure', [
                'reason' => 'attempt_failed',
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'username' => 'Invalid credentials.',
            ]);
        }

        $user = $request->user();
        if ($user === null || $user->is_admin !== true) {
            // Authenticated, but not actually an admin. Log them out so a
            // player can't accidentally land here with a session crumb.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Log::channel('audit')->info('admin.login.failure', [
                'reason' => 'not_admin',
                'user_id' => $user?->id,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'username' => 'Invalid credentials.',
            ]);
        }

        $request->session()->regenerate();

        Log::channel('audit')->info('admin.login.success', [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip' => $request->ip(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }
}
