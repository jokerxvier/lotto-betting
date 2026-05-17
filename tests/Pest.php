<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Sign a Telegram Login Widget payload exactly the way the widget does, so
 * tests can exercise VerifyTelegramLoginAction end-to-end. The check string
 * is sorted key=value pairs joined with "\n" (no trailing newline) and the
 * HMAC secret is sha256(bot_token, raw=true).
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function signTelegramPayload(array $payload, string $token = 'TEST-BOT-TOKEN'): array
{
    ksort($payload);
    $checkString = '';
    foreach ($payload as $key => $value) {
        $checkString .= "{$key}={$value}\n";
    }
    $checkString = rtrim($checkString, "\n");
    $hash = hash_hmac('sha256', $checkString, hash('sha256', $token, true));

    return [...$payload, 'hash' => $hash];
}

/**
 * Build a Telegram Mini App `initData` querystring the same way Telegram's
 * client does, so tests can exercise VerifyTelegramInitDataAction end-to-end.
 * The check string is sorted "k=v\n…" pairs and the HMAC secret derives from
 * `HMAC-SHA256("WebAppData", bot_token, raw=true)`.
 *
 * @param  array<string, mixed>  $user  full user object embedded as JSON
 * @param  array<string, scalar>  $extra  extra top-level fields, e.g. `auth_date`
 */
function signTelegramInitData(array $user, array $extra = [], string $token = 'TEST-BOT-TOKEN'): string
{
    $extra['auth_date'] ??= now()->timestamp;
    $pairs = [
        ...array_map(fn ($v): string => (string) $v, $extra),
        'user' => json_encode($user, JSON_UNESCAPED_UNICODE),
    ];

    ksort($pairs);
    $checkString = '';
    foreach ($pairs as $key => $value) {
        $checkString .= "{$key}={$value}\n";
    }
    $checkString = rtrim($checkString, "\n");

    $secret = hash_hmac('sha256', $token, 'WebAppData', true);
    $hash = hash_hmac('sha256', $checkString, $secret);

    $pairs['hash'] = $hash;

    $parts = [];
    foreach ($pairs as $key => $value) {
        $parts[] = urlencode($key).'='.urlencode($value);
    }

    return implode('&', $parts);
}
