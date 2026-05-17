<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Exceptions\InsufficientFundsException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Single entry point for all wallet mutations. Per CLAUDE.md Hard Rule 3:
 * every change to a wallet balance flows through here inside a transaction
 * with `lockForUpdate` + an idempotency key + a `wallet_transactions`
 * ledger row.
 *
 * Money is handled as decimal strings ("1234.50") end-to-end; arithmetic
 * uses bcmath so no float coercion can ever happen.
 */
final class WalletService
{
    /**
     * Credit a wallet idempotently. Re-running with the same idempotency_key
     * returns the original transaction unchanged.
     *
     * @param  User|Wallet  $target  wallet owner or wallet itself
     * @param  string  $amount  positive decimal string, e.g. "100.00"
     * @param  string  $type  ledger type (e.g. "admin_topup", "deposit", "bet_payout", "refund")
     * @param  string  $idempotencyKey  unique-per-wallet caller-supplied key
     * @param  Model|null  $reference  optional source row (Deposit, Bet, …)
     */
    public function credit(
        User|Wallet $target,
        string $amount,
        string $type,
        string $idempotencyKey,
        ?Model $reference = null,
    ): WalletTransaction {
        if (! preg_match('/^\d{1,12}\.\d{2}$/', $amount) || bccomp($amount, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('Credit amount must be a positive decimal string like "100.00".');
        }

        $walletId = $target instanceof Wallet
            ? $target->id
            : ($target->wallet?->id ?? throw new RuntimeException('User has no wallet to credit.'));

        return DB::transaction(function () use ($walletId, $amount, $type, $idempotencyKey, $reference): WalletTransaction {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($walletId);

            $existing = WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $newBalance = bcadd($wallet->balance, $amount, 2);

            $tx = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $wallet->forceFill([
                'balance' => $newBalance,
                'version' => $wallet->version + 1,
            ])->save();

            return $tx;
        }, attempts: 3);
    }

    /**
     * Debit a wallet idempotently. Caller supplies the amount as a positive
     * decimal string; the ledger row is stored with a leading "-" per the
     * project convention (see WalletTransactionFactory::debit). Re-running
     * with the same idempotency_key returns the original transaction.
     *
     * @param  User|Wallet  $target  wallet owner or wallet itself
     * @param  string  $amount  positive decimal string, e.g. "100.00"
     * @param  string  $type  ledger type (e.g. "bet_debit", "withdrawal")
     * @param  string  $idempotencyKey  unique-per-wallet caller-supplied key
     * @param  Model|null  $reference  optional source row (Bet, Withdrawal, …)
     *
     * @throws InsufficientFundsException when balance < amount
     */
    public function debit(
        User|Wallet $target,
        string $amount,
        string $type,
        string $idempotencyKey,
        ?Model $reference = null,
    ): WalletTransaction {
        if (! preg_match('/^\d{1,12}\.\d{2}$/', $amount) || bccomp($amount, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('Debit amount must be a positive decimal string like "100.00".');
        }

        $walletId = $target instanceof Wallet
            ? $target->id
            : ($target->wallet?->id ?? throw new RuntimeException('User has no wallet to debit.'));

        return DB::transaction(function () use ($walletId, $amount, $type, $idempotencyKey, $reference): WalletTransaction {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($walletId);

            $existing = WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            if (bccomp($wallet->balance, $amount, 2) < 0) {
                throw new InsufficientFundsException(
                    'Wallet balance is less than the requested debit.',
                );
            }

            $newBalance = bcsub($wallet->balance, $amount, 2);

            $tx = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => $type,
                'amount' => '-'.$amount,
                'balance_after' => $newBalance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $wallet->forceFill([
                'balance' => $newBalance,
                'version' => $wallet->version + 1,
            ])->save();

            return $tx;
        }, attempts: 3);
    }
}
