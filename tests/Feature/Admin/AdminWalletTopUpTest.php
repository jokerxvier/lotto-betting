<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WalletTransaction;

it('forbids non-admins from reaching the top-up form', function () {
    $user = User::factory()->withWallet()->create();
    $this->actingAs($user);

    $this->get('/admin/wallets')->assertForbidden();
});

it('forbids non-admins from posting a top-up', function () {
    $user = User::factory()->withWallet()->create();
    $this->actingAs($user);

    $this->post('/admin/wallets/top-up', [
        'wallet_code' => 'ABCD1234',
        'amount' => '100.00',
        'idempotency_key' => 'k-attacker-1234',
    ])->assertForbidden();
});

it('lets an admin credit a wallet by wallet_code', function () {
    $admin = User::factory()->admin()->withWallet()->create();
    $player = User::factory()->withWallet('100.00')->create();

    $this->actingAs($admin)
        ->post('/admin/wallets/top-up', [
            'wallet_code' => $player->wallet_code,
            'amount' => '500.00',
            'note' => 'GCash ref 12345',
            'idempotency_key' => 'topup-00000001',
        ])
        ->assertRedirect('/admin/wallets');

    expect($player->wallet->fresh()->balance)->toEqual('600.00');
    expect(WalletTransaction::query()->where('wallet_id', $player->wallet->id)->count())->toBe(1);
});

it('is idempotent across retries with the same idempotency_key', function () {
    $admin = User::factory()->admin()->withWallet()->create();
    $player = User::factory()->withWallet('100.00')->create();

    foreach (range(1, 3) as $_) {
        $this->actingAs($admin)->post('/admin/wallets/top-up', [
            'wallet_code' => $player->wallet_code,
            'amount' => '500.00',
            'idempotency_key' => 'retry-00000001',
        ])->assertRedirect();
    }

    expect($player->wallet->fresh()->balance)->toEqual('600.00');
    expect(WalletTransaction::query()->count())->toBe(1);
});

it('rejects an unknown wallet_code', function () {
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)
        ->from('/admin/wallets')
        ->post('/admin/wallets/top-up', [
            'wallet_code' => 'NOSUCHWC',
            'amount' => '100.00',
            'idempotency_key' => 'kkkkkkkk',
        ])
        ->assertSessionHasErrors('wallet_code');
});

it('rejects a malformed amount', function (string $bad) {
    $admin = User::factory()->admin()->withWallet()->create();
    $player = User::factory()->withWallet()->create();

    $this->actingAs($admin)
        ->from('/admin/wallets')
        ->post('/admin/wallets/top-up', [
            'wallet_code' => $player->wallet_code,
            'amount' => $bad,
            'idempotency_key' => 'kkkkkkkk',
        ])
        ->assertSessionHasErrors('amount');
})->with(['100', '100.5', '-100.00', 'abc']);
