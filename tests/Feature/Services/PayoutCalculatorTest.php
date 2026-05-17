<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameBetType;
use App\Services\PayoutCalculator;
use Brick\Money\Money;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);
    $this->calc = app(PayoutCalculator::class);
});

dataset('payouts', [
    // [game_code, bet_type_code, picks, bet, expected_payout]
    '3D target 123 ₱10' => ['3d', 'target', [1, 2, 3], '10.00', '6000.00'],
    '3D target 112 ₱10' => ['3d', 'target', [1, 1, 2], '10.00', '6000.00'],
    '3D rambol 123 ₱10' => ['3d', 'rambol', [1, 2, 3], '10.00', '1000.00'],
    '3D rambol 112 ₱10' => ['3d', 'rambol', [1, 1, 2], '10.00', '2000.00'],
    '3D rambol 111 ₱10' => ['3d', 'rambol', [1, 1, 1], '10.00', '6000.00'],
    '2D target 1-4 ₱10' => ['2d', 'target', [1, 4], '10.00', '5500.00'],
    '2D rambol 1-4 ₱10' => ['2d', 'rambol', [1, 4], '10.00', '2750.00'],
    '2D rambol 1-1 ₱10' => ['2d', 'rambol', [1, 1], '10.00', '5500.00'],
    '3D rambol 123 ₱30' => ['3d', 'rambol', [1, 2, 3], '30.00', '3000.00'],
    '3D target 222 ₱20' => ['3d', 'target', [2, 2, 2], '20.00', '12000.00'],
]);

it('computes payouts correctly across the BETTING_RULES.md §9 dataset', function (
    string $gameCode,
    string $typeCode,
    array $picks,
    string $bet,
    string $expected,
) {
    $game = Game::query()->where('code', $gameCode)->firstOrFail();
    $type = GameBetType::query()
        ->where('game_id', $game->id)
        ->where('code', $typeCode)
        ->firstOrFail();

    $payout = $this->calc->potentialPayout(
        $type,
        $picks,
        Money::of($bet, 'PHP'),
    );

    expect((string) $payout->getAmount())->toBe($expected);
})->with('payouts');

it('reports the unique permutation count for common patterns', function (array $picks, int $expected) {
    expect($this->calc->uniquePermutations($picks))->toBe($expected);
})->with([
    'all-different ABC' => [[1, 2, 3], 6],
    'one pair AAB' => [[1, 1, 2], 3],
    'one pair ABB' => [[1, 2, 2], 3],
    'all same AAA' => [[1, 1, 1], 1],
    '2D different AB' => [[1, 4], 2],
    '2D same AA' => [[1, 1], 1],
]);

it('rejects picks that do not match picks_count', function () {
    $twoD = Game::query()->where('code', '2d')->firstOrFail();
    $target = GameBetType::query()
        ->where('game_id', $twoD->id)
        ->where('code', 'target')
        ->firstOrFail();

    expect(fn () => $this->calc->potentialPayout($target, [1, 2, 3], Money::of('10.00', 'PHP')))
        ->toThrow(InvalidArgumentException::class, 'Expected 2 picks');
});

it('rejects picks outside the game number range', function () {
    $twoD = Game::query()->where('code', '2d')->firstOrFail();
    $target = GameBetType::query()
        ->where('game_id', $twoD->id)
        ->where('code', 'target')
        ->firstOrFail();

    expect(fn () => $this->calc->potentialPayout($target, [1, 99], Money::of('10.00', 'PHP')))
        ->toThrow(InvalidArgumentException::class, 'outside range');
});

it('rejects an unknown payout_strategy', function () {
    $twoD = Game::query()->where('code', '2d')->firstOrFail();
    $bad = GameBetType::query()
        ->where('game_id', $twoD->id)
        ->where('code', 'target')
        ->firstOrFail();
    $bad->payout_strategy = 'nonsense';

    expect(fn () => $this->calc->potentialPayout($bad, [1, 4], Money::of('10.00', 'PHP')))
        ->toThrow(InvalidArgumentException::class, 'Unknown payout_strategy');
});
