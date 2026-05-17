<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(WalletService::class);
});

it('credits a wallet, writes a ledger row, and bumps version', function () {
    $user = User::factory()->withWallet('100.00')->create();

    $tx = $this->service->credit($user, '500.00', 'admin_topup', 'key-1');

    $wallet = $user->wallet->fresh();
    expect($wallet->balance)->toEqual('600.00')
        ->and($wallet->version)->toBe(1)
        ->and($tx->amount)->toEqual('500.00')
        ->and($tx->balance_after)->toEqual('600.00')
        ->and($tx->type)->toBe('admin_topup');
});

it('is idempotent on the same key (returns the original tx, does not re-credit)', function () {
    $user = User::factory()->withWallet('100.00')->create();

    $first = $this->service->credit($user, '500.00', 'admin_topup', 'same-key');
    $second = $this->service->credit($user, '500.00', 'admin_topup', 'same-key');

    expect($second->id)->toBe($first->id)
        ->and($user->wallet->fresh()->balance)->toEqual('600.00')
        ->and(WalletTransaction::query()->count())->toBe(1);
});

it('handles arithmetic with bcmath precision', function () {
    $user = User::factory()->withWallet('0.01')->create();

    $this->service->credit($user, '0.02', 'admin_topup', 'k1');
    $this->service->credit($user, '99.97', 'admin_topup', 'k2');

    expect($user->wallet->fresh()->balance)->toEqual('100.00');
});

it('accepts a Wallet target directly', function () {
    $wallet = Wallet::factory()->state(['balance' => '50.00'])->create();

    $this->service->credit($wallet, '25.00', 'refund', 'wallet-target-key');

    expect($wallet->fresh()->balance)->toEqual('75.00');
});

it('rejects negative or zero amounts', function (string $bad) {
    $user = User::factory()->withWallet('100.00')->create();

    expect(fn () => $this->service->credit($user, $bad, 'admin_topup', 'k'))
        ->toThrow(InvalidArgumentException::class);
})->with(['0.00', '-1.00', '100', '100.5', 'abc']);

it('throws when the user has no wallet', function () {
    $user = User::factory()->create(); // no withWallet

    expect(fn () => $this->service->credit($user, '100.00', 'admin_topup', 'k'))
        ->toThrow(RuntimeException::class);
});
