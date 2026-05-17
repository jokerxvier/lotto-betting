<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bet;
use App\Models\BetLeg;
use App\Models\GameBetType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BetLeg>
 */
class BetLegFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bet_id' => Bet::factory(),
            'game_bet_type_id' => GameBetType::factory(),
            'numbers' => [1, 2, 3],
            'amount' => '10.00',
            'potential_payout' => '6000.00',
            'payout' => null,
        ];
    }
}
