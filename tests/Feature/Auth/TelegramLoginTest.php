<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    config()->set('services.telegram.bot_token', 'TEST-BOT-TOKEN');
});

it('creates a new user and redirects to setup-pin on first Telegram login', function () {
    $payload = signTelegramPayload([
        'id' => 87654321,
        'first_name' => 'Jane',
        'username' => 'jane_t',
        'auth_date' => now()->timestamp,
    ]);

    $response = $this->post('/auth/telegram', $payload);

    $response->assertRedirect(route('auth.setup-pin'));
    expect(User::query()->where('telegram_id', 87654321)->exists())->toBeTrue();
    $user = User::query()->where('telegram_id', 87654321)->firstOrFail();
    expect($user->name)->toBe('Jane')
        ->and($user->username)->toBeNull()
        ->and($user->pin_hash)->toBeNull()
        ->and($user->wallet_code)->toHaveLength(8);
    $this->assertAuthenticatedAs($user);
});

it('logs an existing Telegram user straight into /lotto', function () {
    $user = User::factory()->telegramLinked(87654321)->create();

    $payload = signTelegramPayload([
        'id' => 87654321,
        'first_name' => 'Jane',
        'auth_date' => now()->timestamp,
    ]);

    $response = $this->post('/auth/telegram', $payload);

    $response->assertRedirect(route('lotto'));
    expect(User::query()->where('telegram_id', 87654321)->count())->toBe(1);
    $this->assertAuthenticatedAs($user);
});

it('rejects a tampered Telegram hash', function () {
    $payload = signTelegramPayload([
        'id' => 87654321,
        'first_name' => 'Jane',
        'auth_date' => now()->timestamp,
    ]);
    $payload['hash'] = str_repeat('0', 64);

    $response = $this->from('/login')->post('/auth/telegram', $payload);

    $response->assertSessionHasErrors('telegram');
    $this->assertGuest();
    expect(User::query()->where('telegram_id', 87654321)->exists())->toBeFalse();
});

it('rejects a stale Telegram auth_date', function () {
    $payload = signTelegramPayload([
        'id' => 87654321,
        'first_name' => 'Jane',
        'auth_date' => now()->subMinutes(10)->timestamp,
    ]);

    $response = $this->from('/login')->post('/auth/telegram', $payload);

    $response->assertSessionHasErrors('telegram');
    $this->assertGuest();
});

it('rejects a payload missing required fields', function () {
    $response = $this->from('/login')->post('/auth/telegram', [
        'id' => 87654321,
        // missing auth_date, hash, first_name
    ]);

    $response->assertSessionHasErrors(['auth_date', 'hash', 'first_name']);
    $this->assertGuest();
});

it('forces an incomplete user to /auth/setup-pin when visiting /lotto', function () {
    $user = User::factory()->telegramOnly(87654321)->create();
    $this->actingAs($user);

    $this->get('/lotto')->assertRedirect(route('auth.setup-pin'));
});
