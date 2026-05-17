<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bet;
use App\Models\Draw;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Bet>
 */
class BetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'draw_id' => Draw::factory(),
            'amount' => '10.00',
            'potential_payout' => '6000.00',
            'status' => 'pending',
            'settled_at' => null,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'settled_at' => null]);
    }

    public function won(): static
    {
        return $this->state(['status' => 'won', 'settled_at' => now()]);
    }

    public function lost(): static
    {
        return $this->state(['status' => 'lost', 'settled_at' => now()]);
    }

    public function voided(): static
    {
        return $this->state(['status' => 'void', 'settled_at' => now()]);
    }
}
