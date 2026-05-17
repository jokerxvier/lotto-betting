<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        Game::updateOrCreate(
            ['code' => '2d'],
            [
                'name' => 'EZ2',
                'picks_count' => 2,
                'number_min' => 1,
                'number_max' => 31,
                'active' => true,
                'sort_order' => 1,
            ],
        );

        Game::updateOrCreate(
            ['code' => '3d'],
            [
                'name' => 'Swertres',
                'picks_count' => 3,
                'number_min' => 0,
                'number_max' => 9,
                'active' => true,
                'sort_order' => 2,
            ],
        );
    }
}
