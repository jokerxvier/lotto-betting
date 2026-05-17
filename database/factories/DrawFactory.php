<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Draw;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Draw>
 */
class DrawFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $drawAt = now()->addDay()->setTime(21, 0);

        return [
            'game_id' => Game::factory(),
            'draw_at' => $drawAt,
            'cutoff_at' => $drawAt->copy()->subMinutes(10),
            'status' => 'scheduled',
        ];
    }

    public function open(): static
    {
        return $this->state(function (): array {
            $drawAt = now()->addHours(2);

            return [
                'draw_at' => $drawAt,
                'cutoff_at' => $drawAt->copy()->subMinutes(10),
                'status' => 'scheduled',
            ];
        });
    }

    public function closed(): static
    {
        return $this->state(function (): array {
            $drawAt = now()->subMinutes(5);

            return [
                'draw_at' => $drawAt,
                'cutoff_at' => $drawAt->copy()->subMinutes(10),
                'status' => 'closed',
            ];
        });
    }

    public function settled(): static
    {
        return $this->state(function (): array {
            $drawAt = now()->subHour();

            return [
                'draw_at' => $drawAt,
                'cutoff_at' => $drawAt->copy()->subMinutes(10),
                'status' => 'settled',
            ];
        });
    }
}
