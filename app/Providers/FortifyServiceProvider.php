<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Auth\AuthenticateOrCreateAction;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureCredentialCheck();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Wire Fortify's POST /login to the combined login/sign-up action.
     *
     * Unknown username → account auto-created with the supplied PIN.
     * Known username + wrong PIN → ValidationException("Invalid password.").
     *
     * The form posts the PIN under the `password` field so Fortify's
     * built-in `password` required-rule keeps passing.
     */
    private function configureCredentialCheck(): void
    {
        Fortify::authenticateUsing(function (Request $request): mixed {
            return app(AuthenticateOrCreateAction::class)->execute(
                Str::lower(trim((string) $request->input('username'))),
                (string) $request->input('password'),
            );
        });
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'telegramBotUsername' => config('services.telegram.bot_username'),
            'status' => $request->session()->get('status'),
        ]));
    }

    /**
     * Compound limit per rules/SECURITY.md §1.3:
     *  - 5/min per username+IP (targeted brute-force defence)
     *  - 20/min per IP         (global flood defence)
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('pin-login', function (Request $request): array {
            $username = Str::lower((string) $request->input('username'));
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(5)->by($username.'|'.$ip),
                Limit::perMinute(20)->by($ip),
            ];
        });

        RateLimiter::for('telegram-login', function (Request $request): Limit {
            return Limit::perMinute(30)->by((string) $request->ip());
        });

        RateLimiter::for('bet-place', function (Request $request): Limit {
            return Limit::perMinute(30)->by(
                (string) ($request->user()?->id ?? $request->ip()),
            );
        });
    }
}
