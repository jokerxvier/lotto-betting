<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    config()->set('services.telegram.bot_token', 'TEST-BOT-TOKEN');
});

it('creates a new user from initData and redirects to setup-pin', function () {
    $initData = signTelegramInitData([
        'id' => 87654321,
        'first_name' => 'Jane',
        'username' => 'jane_t',
    ]);

    $response = $this->post('/auth/telegram/web-app', ['init_data' => $initData]);

    $response->assertRedirect(route('auth.setup-pin'));

    $user = User::query()->where('telegram_id', 87654321)->firstOrFail();
    expect($user->name)->toBe('Jane')
        ->and($user->username)->toBeNull()
        ->and($user->pin_hash)->toBeNull()
        ->and($user->wallet_code)->toHaveLength(8);
    $this->assertAuthenticatedAs($user);
});

it('logs an existing Telegram user from initData straight into /lotto', function () {
    $user = User::factory()->telegramLinked(87654321)->create();

    $initData = signTelegramInitData(['id' => 87654321, 'first_name' => 'Jane']);

    $response = $this->post('/auth/telegram/web-app', ['init_data' => $initData]);

    $response->assertRedirect(route('lotto'));
    expect(User::query()->where('telegram_id', 87654321)->count())->toBe(1);
    $this->assertAuthenticatedAs($user);
});

it('rejects a tampered initData hash', function () {
    $initData = signTelegramInitData(['id' => 87654321, 'first_name' => 'Jane']);
    $tampered = preg_replace('/hash=[a-f0-9]+/', 'hash='.str_repeat('0', 64), $initData);

    $response = $this->from('/login')->post('/auth/telegram/web-app', ['init_data' => $tampered]);

    $response->assertSessionHasErrors('telegram');
    $this->assertGuest();
    expect(User::query()->where('telegram_id', 87654321)->exists())->toBeFalse();
});

it('rejects a stale initData auth_date', function () {
    $initData = signTelegramInitData(
        ['id' => 87654321, 'first_name' => 'Jane'],
        ['auth_date' => now()->subMinutes(10)->timestamp],
    );

    $response = $this->from('/login')->post('/auth/telegram/web-app', ['init_data' => $initData]);

    $response->assertSessionHasErrors('telegram');
    $this->assertGuest();
});

it('rejects an empty init_data field', function () {
    $response = $this->from('/login')->post('/auth/telegram/web-app', ['init_data' => '']);

    $response->assertSessionHasErrors('telegram');
    $this->assertGuest();
});
