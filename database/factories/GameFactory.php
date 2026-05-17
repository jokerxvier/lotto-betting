<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('??'),
            'name' => fake()->unique()->words(2, true),
            'picks_count' => 3,
            'number_min' => 0,
            'number_max' => 9,
            'active' => true,
            'sort_order' => 0,
        ];
    }

    public function twoDigit(): static
    {
        return $this->state([
            'code' => '2d',
            'name' => 'EZ2',
            'picks_count' => 2,
            'number_min' => 1,
            'number_max' => 31,
            'sort_order' => 1,
        ]);
    }

    public function threeDigit(): static
    {
        return $this->state([
            'code' => '3d',
            'name' => 'Swertres',
            'picks_count' => 3,
            'number_min' => 0,
            'number_max' => 9,
            'sort_order' => 2,
        ]);
    }
}
