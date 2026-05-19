<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Exceptions\InsufficientFundsException;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(WalletService::class);
});

it('debits a wallet, writes a signed-negative ledger row, and bumps version', function () {
    $user = User::factory()->withWallet('1000.00')->create();

    $tx = $this->service->debit($user, '100.00', 'bet_debit', 'debit-1');

    $wallet = $user->wallet->fresh();
    expect($wallet->balance)->toEqual('900.00')
        ->and($wallet->version)->toBe(1)
        ->and($tx->amount)->toEqual('-100.00')
        ->and($tx->balance_after)->toEqual('900.00')
        ->and($tx->type)->toBe('bet_debit');
});

it('is idempotent on the same key (returns the original tx, does not re-debit)', function () {
    $user = User::factory()->withWallet('1000.00')->create();

    $first = $this->service->debit($user, '100.00', 'bet_debit', 'same-debit-key');
    $second = $this->service->debit($user, '100.00', 'bet_debit', 'same-debit-key');

    expect($second->id)->toBe($first->id)
        ->and($user->wallet->fresh()->balance)->toEqual('900.00')
        ->and(WalletTransaction::query()->count())->toBe(1);
});

it('throws InsufficientFundsException when balance < amount', function () {
    $user = User::factory()->withWallet('10.00')->create();

    expect(fn () => $this->service->debit($user, '20.00', 'bet_debit', 'overdraft'))
        ->toThrow(InsufficientFundsException::class);

    expect($user->wallet->fresh()->balance)->toEqual('10.00')
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('allows exact-balance debits to zero out the wallet', function () {
    $user = User::factory()->withWallet('10.00')->create();

    $this->service->debit($user, '10.00', 'bet_debit', 'exact');

    expect($user->wallet->fresh()->balance)->toEqual('0.00');
});

it('accepts a Wallet target directly', function () {
    $wallet = Wallet::factory()->state(['balance' => '50.00'])->create();

    $this->service->debit($wallet, '25.00', 'bet_debit', 'wallet-debit-key');

    expect($wallet->fresh()->balance)->toEqual('25.00');
});

it('rejects negative, zero, or malformed amounts', function (string $bad) {
    $user = User::factory()->withWallet('100.00')->create();

    expect(fn () => $this->service->debit($user, $bad, 'bet_debit', 'k'))
        ->toThrow(InvalidArgumentException::class);
})->with(['0.00', '-1.00', '100', '100.5', 'abc']);

it('throws when the user has no wallet', function () {
    $user = User::factory()->create();

    expect(fn () => $this->service->debit($user, '10.00', 'bet_debit', 'k'))
        ->toThrow(RuntimeException::class);
});

it('persists the actor and note when supplied', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->withWallet('500.00')->create();

    $tx = $this->service->debit(
        $target,
        '120.00',
        'admin_debit',
        'audit-debit-key',
        reference: null,
        actorUserId: $admin->id,
        note: 'reversal of erroneous top-up',
    );

    expect($tx->actor_user_id)->toBe($admin->id)
        ->and($tx->note)->toBe('reversal of erroneous top-up')
        ->and($tx->amount)->toEqual('-120.00')
        ->and($tx->actor)->not->toBeNull()
        ->and($tx->actor->id)->toBe($admin->id);
});
