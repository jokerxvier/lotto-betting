<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameBetType;
use Illuminate\Database\Seeder;
use RuntimeException;

class GameBetTypeSeeder extends Seeder
{
    public function run(): void
    {
        $twoD = Game::query()->where('code', '2d')->first()
            ?? throw new RuntimeException('Game "2d" must be seeded before GameBetTypeSeeder runs.');

        $threeD = Game::query()->where('code', '3d')->first()
            ?? throw new RuntimeException('Game "3d" must be seeded before GameBetTypeSeeder runs.');

        $this->upsertBetType($twoD->id, 'target', 'Target', '5500.00', 'fixed', 1);
        $this->upsertBetType($twoD->id, 'rambol', 'Rambolito', '5500.00', 'split_permutations', 2);
        $this->upsertBetType($threeD->id, 'target', 'Target', '6000.00', 'fixed', 1);
        $this->upsertBetType($threeD->id, 'rambol', 'Rambolito', '6000.00', 'split_permutations', 2);
    }

    private function upsertBetType(
        int $gameId,
        string $code,
        string $label,
        string $basePayout,
        string $strategy,
        int $sortOrder,
    ): void {
        GameBetType::updateOrCreate(
            ['game_id' => $gameId, 'code' => $code],
            [
                'label' => $label,
                'base_bet_amount' => '10.00',
                'base_payout_amount' => $basePayout,
                'payout_strategy' => $strategy,
                'min_bet' => '10.00',
                'max_bet' => '10000.00',
                'active' => true,
                'sort_order' => $sortOrder,
            ],
        );
    }
}
