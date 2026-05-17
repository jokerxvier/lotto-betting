<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterWithTelegramAction;
use App\Actions\Auth\VerifyTelegramInitDataAction;
use App\Exceptions\InvalidTelegramPayloadException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Mini-App-side sibling of TelegramLoginController.
 *
 * The browser/widget flow posts a `{id, hash, first_name, …}` map; the
 * Telegram in-app webview hands the page a single signed querystring at
 * `window.Telegram.WebApp.initData`. The frontend POSTs that raw string
 * here, we verify, find-or-create the user, sign them in.
 */
final class TelegramWebAppController extends Controller
{
    public function __invoke(
        Request $request,
        VerifyTelegramInitDataAction $verify,
        RegisterWithTelegramAction $register,
    ): RedirectResponse {
        $initData = (string) $request->input('init_data', '');

        try {
            $verified = $verify->execute($initData);
        } catch (InvalidTelegramPayloadException $e) {
            Log::channel('audit')->info('auth.telegram.failure', [
                'source' => 'web_app',
                'reason' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'telegram' => 'Telegram sign-in failed. Please try again.',
            ]);
        }

        $user = $register->execute($verified);

        Auth::login($user);
        $request->session()->regenerate();

        // Telegram identity is enough — no forced setup-pin. Users can opt
        // in to a backup username + PIN later from /auth/setup-pin.
        return redirect()->intended(route('lotto'));
    }
}
