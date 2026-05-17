<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WalletTransaction;

it('redirects guests to /login', function () {
    $this->get('/wallet')->assertRedirect('/login');
});

it('shows the balance and recent transactions for an authed user', function () {
    $user = User::factory()->withWallet('500.00')->create();

    WalletTransaction::factory()
        ->for($user->wallet)
        ->state(['type' => 'admin_topup', 'amount' => '500.00', 'balance_after' => '500.00'])
        ->create();

    $this->actingAs($user);

    $this->get('/wallet')->assertOk()->assertInertia(fn ($page) => $page
        ->component('wallet/index')
        ->where('wallet.balance', '500.00')
        ->where('wallet.wallet_code', $user->wallet_code)
        ->has('transactions', 1)
    );
});

it('renders zeros when the user has no wallet rows yet', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/wallet')->assertOk()->assertInertia(fn ($page) => $page
        ->component('wallet/index')
        ->where('wallet.balance', '0.00')
        ->has('transactions', 0)
    );
});
