<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the shape of a Telegram Login Widget POST. Signature + freshness
 * are enforced separately by VerifyTelegramLoginAction.
 *
 * Any field Telegram sends (first_name, last_name, username, photo_url, …)
 * is forwarded as part of the HMAC check string, so we accept them with
 * lenient `sometimes` rules.
 */
final class TelegramLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'auth_date' => ['required', 'integer', 'min:1'],
            'hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'first_name' => ['required', 'string', 'max:64'],
            'last_name' => ['sometimes', 'string', 'max:64'],
            'username' => ['sometimes', 'string', 'max:64'],
            'photo_url' => ['sometimes', 'string', 'max:512'],
        ];
    }
}
