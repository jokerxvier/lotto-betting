<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterWithTelegramAction;
use App\Actions\Auth\VerifyTelegramLoginAction;
use App\Exceptions\InvalidTelegramPayloadException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TelegramLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class TelegramLoginController extends Controller
{
    public function __invoke(
        TelegramLoginRequest $request,
        VerifyTelegramLoginAction $verify,
        RegisterWithTelegramAction $register,
    ): RedirectResponse {
        $payload = $request->except(['_token']);

        try {
            $verified = $verify->execute($payload);
        } catch (InvalidTelegramPayloadException $e) {
            Log::channel('audit')->info('auth.telegram.failure', [
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

        if ($user->username === null || $user->pin_hash === null) {
            return redirect()->route('auth.setup-pin');
        }

        return redirect()->intended(route('lotto'));
    }
}
