<?php

declare(strict_types=1);

use App\Actions\Auth\VerifyTelegramLoginAction;
use App\Exceptions\InvalidTelegramPayloadException;

beforeEach(function (): void {
    config()->set('services.telegram.bot_token', 'TEST-BOT-TOKEN');
    $this->action = app(VerifyTelegramLoginAction::class);
});

it('returns a payload DTO when the signature and freshness check pass', function () {
    $payload = signTelegramPayload([
        'id' => 12345678,
        'first_name' => 'Jane',
        'username' => 'jane_t',
        'auth_date' => now()->timestamp,
    ]);

    $verified = $this->action->execute($payload);

    expect($verified->id)->toBe(12345678)
        ->and($verified->firstName)->toBe('Jane')
        ->and($verified->username)->toBe('jane_t');
});

it('rejects a tampered hash', function () {
    $payload = signTelegramPayload([
        'id' => 12345678,
        'first_name' => 'Jane',
        'auth_date' => now()->timestamp,
    ]);
    $payload['hash'] = str_repeat('0', 64);

    expect(fn () => $this->action->execute($payload))
        ->toThrow(InvalidTelegramPayloadException::class);
});

it('rejects a stale auth_date older than 5 minutes', function () {
    $payload = signTelegramPayload([
        'id' => 12345678,
        'first_name' => 'Jane',
        'auth_date' => now()->subMinutes(6)->timestamp,
    ]);

    expect(fn () => $this->action->execute($payload))
        ->toThrow(InvalidTelegramPayloadException::class);
});

it('rejects a payload missing the hash field', function () {
    expect(fn () => $this->action->execute([
        'id' => 12345678,
        'first_name' => 'Jane',
        'auth_date' => now()->timestamp,
    ]))->toThrow(InvalidTelegramPayloadException::class);
});

it('rejects when the bot token is not configured', function () {
    config()->set('services.telegram.bot_token', '');

    expect(fn () => $this->action->execute([
        'id' => 12345678,
        'first_name' => 'Jane',
        'auth_date' => now()->timestamp,
        'hash' => str_repeat('a', 64),
    ]))->toThrow(InvalidTelegramPayloadException::class);
});

it('rejects a payload missing the id field', function () {
    $payload = signTelegramPayload([
        'first_name' => 'Jane',
        'auth_date' => now()->timestamp,
    ]);

    expect(fn () => $this->action->execute($payload))
        ->toThrow(InvalidTelegramPayloadException::class);
});
