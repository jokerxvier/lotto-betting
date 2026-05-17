<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameBetType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameBetType>
 */
class GameBetTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'code' => 'target',
            'label' => 'Target',
            'base_bet_amount' => '10.00',
            'base_payout_amount' => '6000.00',
            'payout_strategy' => 'fixed',
            'min_bet' => '10.00',
            'max_bet' => '10000.00',
            'active' => true,
            'sort_order' => 1,
        ];
    }

    public function target(): static
    {
        return $this->state([
            'code' => 'target',
            'label' => 'Target',
            'payout_strategy' => 'fixed',
            'sort_order' => 1,
        ]);
    }

    public function rambol(): static
    {
        return $this->state([
            'code' => 'rambol',
            'label' => 'Rambolito',
            'payout_strategy' => 'split_permutations',
            'sort_order' => 2,
        ]);
    }
}
