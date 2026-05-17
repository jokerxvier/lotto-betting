<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Draw;
use App\Models\DrawResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DrawResult>
 */
class DrawResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'draw_id' => Draw::factory(),
            'numbers' => [
                fake()->numberBetween(0, 9),
                fake()->numberBetween(0, 9),
                fake()->numberBetween(0, 9),
            ],
            'published_at' => now(),
        ];
    }
}
