<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\InvalidTelegramPayloadException;
use Illuminate\Support\Carbon;

/**
 * Pure verification of a Telegram Mini App `initData` payload.
 *
 * The Mini App flow differs from the Login Widget on TWO important points:
 *  - **Payload shape**: `initData` is a URL-encoded querystring, not an
 *    associative array. The `user` field is itself a JSON-encoded object.
 *  - **HMAC secret**: derived as `HMAC-SHA256("WebAppData", bot_token)`,
 *    NOT `sha256(bot_token)` as the Login Widget uses.
 *
 *  1. Parse the querystring, strip `hash`, build sorted "k=v\n…" check string.
 *  2. Recompute HMAC and `hash_equals` against the supplied hash.
 *  3. Reject payloads whose `auth_date` is older than 5 minutes
 *     (rules/SECURITY.md §1.1, mirrored from the widget action).
 *  4. JSON-decode `user`, extract id + first_name + username.
 *
 * Throws InvalidTelegramPayloadException on any failure. Returns the same
 * TelegramPayload DTO the Login Widget uses, so RegisterWithTelegramAction
 * works unchanged.
 */
final class VerifyTelegramInitDataAction
{
    private const MAX_AUTH_AGE_SECONDS = 300;

    public function execute(string $initData): TelegramPayload
    {
        if ($initData === '') {
            throw new InvalidTelegramPayloadException('Telegram initData is empty.');
        }

        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            throw new InvalidTelegramPayloadException('Telegram bot token is not configured.');
        }

        // parse_str gives us a percent-decoded map but eats the `&` separators
        // we need to rebuild the check string. Reparse manually to keep raw
        // field values intact for HMAC.
        $pairs = [];
        foreach (explode('&', $initData) as $segment) {
            if ($segment === '') {
                continue;
            }
            [$rawKey, $rawValue] = array_pad(explode('=', $segment, 2), 2, '');
            $pairs[urldecode($rawKey)] = urldecode($rawValue);
        }

        $hash = $pairs['hash'] ?? null;
        if (! is_string($hash) || $hash === '') {
            throw new InvalidTelegramPayloadException('Telegram initData is missing hash.');
        }

        unset($pairs['hash']);
        ksort($pairs);

        $checkString = '';
        foreach ($pairs as $key => $value) {
            $checkString .= "{$key}={$value}\n";
        }
        $checkString = rtrim($checkString, "\n");

        $secret = hash_hmac('sha256', $token, 'WebAppData', true);
        $computed = hash_hmac('sha256', $checkString, $secret);

        if (! hash_equals($computed, $hash)) {
            throw new InvalidTelegramPayloadException('Telegram initData signature mismatch.');
        }

        $authDate = isset($pairs['auth_date']) ? (int) $pairs['auth_date'] : 0;
        if ($authDate <= 0 || Carbon::now()->timestamp - $authDate > self::MAX_AUTH_AGE_SECONDS) {
            throw new InvalidTelegramPayloadException('Telegram initData is stale.');
        }

        $userJson = $pairs['user'] ?? null;
        if (! is_string($userJson) || $userJson === '') {
            throw new InvalidTelegramPayloadException('Telegram initData is missing user.');
        }

        /** @var mixed $decoded */
        $decoded = json_decode($userJson, true);
        if (! is_array($decoded)) {
            throw new InvalidTelegramPayloadException('Telegram initData user JSON is invalid.');
        }

        $id = isset($decoded['id']) ? (int) $decoded['id'] : 0;
        if ($id <= 0) {
            throw new InvalidTelegramPayloadException('Telegram initData user is missing id.');
        }

        return new TelegramPayload(
            id: $id,
            firstName: (string) ($decoded['first_name'] ?? ''),
            username: isset($decoded['username']) ? (string) $decoded['username'] : null,
            authDate: $authDate,
        );
    }
}
