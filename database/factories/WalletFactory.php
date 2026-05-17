<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance' => '0.00',
            'held_balance' => '0.00',
            'version' => 0,
        ];
    }

    public function funded(string $amount = '1000.00'): static
    {
        return $this->state(['balance' => $amount]);
    }
}
