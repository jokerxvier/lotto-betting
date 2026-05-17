<?php

declare(strict_types=1);

use App\Actions\Auth\AuthenticateOrCreateAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = app(AuthenticateOrCreateAction::class);
});

it('returns the user on a correct PIN', function () {
    $user = User::factory()->create(['username' => 'alice', 'pin_hash' => '472901']);

    $result = $this->action->execute('alice', '472901');

    expect($result->id)->toBe($user->id);
});

it('creates and returns a brand-new user when the username is unknown', function () {
    expect(User::query()->count())->toBe(0);

    $user = $this->action->execute('newcomer', '472901');

    expect($user->exists)->toBeTrue()
        ->and($user->username)->toBe('newcomer')
        ->and($user->name)->toBe('newcomer')
        ->and($user->pin_hash)->not->toBeNull()
        ->and($user->wallet_code)->toHaveLength(8);
});

it('throws "Invalid password." on a wrong PIN for an existing user', function () {
    User::factory()->create(['username' => 'alice', 'pin_hash' => '472901']);

    expect(fn () => $this->action->execute('alice', '000000'))
        ->toThrow(ValidationException::class, 'Invalid password.');
});

it('rejects weak PINs on the create path', function (string $weak) {
    expect(fn () => $this->action->execute('newcomer', $weak))
        ->toThrow(ValidationException::class);

    expect(User::query()->where('username', 'newcomer')->exists())->toBeFalse();
})->with(['111111', '000000', '123456', '654321']);

it('rejects PINs that are not exactly 6 digits on the create path', function (string $bad) {
    expect(fn () => $this->action->execute('newcomer', $bad))
        ->toThrow(ValidationException::class);
})->with(['1234', '12345', '1234567', 'abcdef']);

it('rejects reserved usernames on the create path', function (string $reserved) {
    expect(fn () => $this->action->execute($reserved, '472901'))
        ->toThrow(ValidationException::class);
})->with(['admin', 'root', 'system', 'api', 'lotto', 'pcso']);

it('rejects an invalid username format on the create path', function (string $bad) {
    expect(fn () => $this->action->execute($bad, '472901'))
        ->toThrow(ValidationException::class);
})->with(['ab', 'has spaces', 'has-dash', 'ThisIsTooLongOfAUsernameForTheRule']);

it('refuses Telegram-only accounts with a clear message', function () {
    User::factory()->telegramOnly()->create(['username' => 'telly']);

    expect(fn () => $this->action->execute('telly', '472901'))
        ->toThrow(ValidationException::class, 'Telegram');
});

it('locks the account after 5 consecutive wrong PINs', function () {
    $user = User::factory()->create(['username' => 'alice', 'pin_hash' => '472901']);

    foreach (range(1, 5) as $_) {
        try {
            $this->action->execute('alice', '000000');
        } catch (ValidationException) {
            // expected
        }
    }

    $fresh = $user->fresh();
    expect($fresh->locked_until)->not->toBeNull()
        ->and($fresh->locked_until->isFuture())->toBeTrue();

    // Sixth attempt with the right PIN now reports the lockout message.
    expect(fn () => $this->action->execute('alice', '472901'))
        ->toThrow(ValidationException::class, 'Too many attempts');
});

it('clears the failure counter on successful login', function () {
    $user = User::factory()->create(['username' => 'alice', 'pin_hash' => '472901']);

    foreach (range(1, 3) as $_) {
        try {
            $this->action->execute('alice', '000000');
        } catch (ValidationException) {
            // expected
        }
    }

    $this->action->execute('alice', '472901');

    expect(Cache::get("auth:pin:failures:{$user->id}"))->toBeNull();
});

it('clears locked_until when the user logs in after lockout expires', function () {
    $user = User::factory()->create([
        'username' => 'alice',
        'pin_hash' => '472901',
        'locked_until' => now()->subMinute(),
    ]);

    $this->action->execute('alice', '472901');

    expect($user->fresh()->locked_until)->toBeNull();
});
