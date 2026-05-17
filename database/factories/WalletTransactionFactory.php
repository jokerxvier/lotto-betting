<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 1000);

        return [
            'wallet_id' => Wallet::factory(),
            'type' => 'deposit',
            'amount' => number_format($amount, 2, '.', ''),
            'balance_after' => number_format($amount, 2, '.', ''),
            'reference_type' => null,
            'reference_id' => null,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function debit(string $amount = '10.00'): static
    {
        return $this->state([
            'type' => 'bet_debit',
            'amount' => '-'.$amount,
        ]);
    }

    public function credit(string $amount = '6000.00'): static
    {
        return $this->state([
            'type' => 'bet_payout',
            'amount' => $amount,
        ]);
    }
}
