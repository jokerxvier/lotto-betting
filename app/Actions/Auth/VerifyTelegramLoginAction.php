<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\InvalidTelegramPayloadException;
use Illuminate\Support\Carbon;

/**
 * Pure verification of a Telegram Login Widget payload.
 *
 *  1. Recompute HMAC-SHA256 over the alphabetically-sorted "k=v\n…" check
 *     string using `sha256(bot_token)` as the secret. Compare with
 *     `hash_equals` to avoid timing leaks.
 *  2. Reject payloads whose `auth_date` is older than 5 minutes — Telegram's
 *     recommended replay window (rules/SECURITY.md §1.1).
 *
 * Throws InvalidTelegramPayloadException on any failure. The caller is
 * responsible for surfacing a generic error to the user.
 */
final class VerifyTelegramLoginAction
{
    private const MAX_AUTH_AGE_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): TelegramPayload
    {
        $hash = $payload['hash'] ?? null;
        if (! is_string($hash) || $hash === '') {
            throw new InvalidTelegramPayloadException('Telegram payload is missing hash.');
        }

        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            throw new InvalidTelegramPayloadException('Telegram bot token is not configured.');
        }

        $data = $payload;
        unset($data['hash']);
        ksort($data);

        $checkString = '';
        foreach ($data as $key => $value) {
            $checkString .= "{$key}=".$this->stringify($value)."\n";
        }
        $checkString = rtrim($checkString, "\n");

        $computed = hash_hmac('sha256', $checkString, hash('sha256', $token, true));

        if (! hash_equals($computed, $hash)) {
            throw new InvalidTelegramPayloadException('Telegram payload signature mismatch.');
        }

        $authDate = isset($payload['auth_date']) ? (int) $payload['auth_date'] : 0;
        if ($authDate <= 0 || Carbon::now()->timestamp - $authDate > self::MAX_AUTH_AGE_SECONDS) {
            throw new InvalidTelegramPayloadException('Telegram payload is stale.');
        }

        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        if ($id <= 0) {
            throw new InvalidTelegramPayloadException('Telegram payload is missing id.');
        }

        return new TelegramPayload(
            id: $id,
            firstName: (string) ($payload['first_name'] ?? ''),
            username: isset($payload['username']) ? (string) $payload['username'] : null,
            authDate: $authDate,
        );
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
