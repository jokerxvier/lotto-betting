<?php

declare(strict_types=1);

use App\Actions\Auth\VerifyTelegramInitDataAction;
use App\Exceptions\InvalidTelegramPayloadException;

beforeEach(function (): void {
    config()->set('services.telegram.bot_token', 'TEST-BOT-TOKEN');
    $this->action = app(VerifyTelegramInitDataAction::class);
});

it('returns a payload DTO when the signature and freshness check pass', function () {
    $initData = signTelegramInitData([
        'id' => 12345678,
        'first_name' => 'Jane',
        'username' => 'jane_t',
    ]);

    $verified = $this->action->execute($initData);

    expect($verified->id)->toBe(12345678)
        ->and($verified->firstName)->toBe('Jane')
        ->and($verified->username)->toBe('jane_t');
});

it('rejects a tampered hash', function () {
    $initData = signTelegramInitData(['id' => 12345678, 'first_name' => 'Jane']);
    $tampered = preg_replace('/hash=[a-f0-9]+/', 'hash='.str_repeat('0', 64), $initData);

    expect(fn () => $this->action->execute($tampered))
        ->toThrow(InvalidTelegramPayloadException::class);
});

it('rejects a stale auth_date older than 5 minutes', function () {
    $initData = signTelegramInitData(
        ['id' => 12345678, 'first_name' => 'Jane'],
        ['auth_date' => now()->subMinutes(6)->timestamp],
    );

    expect(fn () => $this->action->execute($initData))
        ->toThrow(InvalidTelegramPayloadException::class);
});

it('rejects empty initData', function () {
    expect(fn () => $this->action->execute(''))
        ->toThrow(InvalidTelegramPayloadException::class);
});

it('rejects a payload missing the hash field', function () {
    expect(fn () => $this->action->execute('auth_date=1&user='.urlencode('{"id":1,"first_name":"x"}')))
        ->toThrow(InvalidTelegramPayloadException::class);
});

it('rejects when the bot token is not configured', function () {
    config()->set('services.telegram.bot_token', '');

    $initData = signTelegramInitData(['id' => 12345678, 'first_name' => 'Jane']);

    expect(fn () => $this->action->execute($initData))
        ->toThrow(InvalidTelegramPayloadException::class);
});

it('rejects a user JSON missing id', function () {
    $initData = signTelegramInitData(['first_name' => 'NoId']);

    expect(fn () => $this->action->execute($initData))
        ->toThrow(InvalidTelegramPayloadException::class);
});

it('rejects malformed user JSON', function () {
    // Manually craft an initData whose `user` field is not valid JSON, then sign it.
    $pairs = [
        'auth_date' => (string) now()->timestamp,
        'user' => 'not-json',
    ];
    ksort($pairs);
    $checkString = '';
    foreach ($pairs as $k => $v) {
        $checkString .= "{$k}={$v}\n";
    }
    $checkString = rtrim($checkString, "\n");
    $secret = hash_hmac('sha256', 'TEST-BOT-TOKEN', 'WebAppData', true);
    $pairs['hash'] = hash_hmac('sha256', $checkString, $secret);

    $parts = [];
    foreach ($pairs as $k => $v) {
        $parts[] = urlencode($k).'='.urlencode($v);
    }

    expect(fn () => $this->action->execute(implode('&', $parts)))
        ->toThrow(InvalidTelegramPayloadException::class);
});
