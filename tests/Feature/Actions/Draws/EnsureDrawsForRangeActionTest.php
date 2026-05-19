<?php

declare(strict_types=1);

use App\Actions\Draws\EnsureDrawsForRangeAction;
use App\Models\Draw;
use App\Models\Game;
use Carbon\Carbon;
use Database\Seeders\GameSeeder;

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
    config()->set('lotto.draw_schedule', ['default' => ['14:00', '17:00', '21:00']]);
    config()->set('lotto.cutoff_minutes', 60);
});

it('creates one row per active game × slot × day in the range', function () {
    $from = Carbon::create(2026, 5, 13, 0, 0, 0, 'Asia/Manila');
    $to = Carbon::create(2026, 5, 15, 23, 59, 0, 'Asia/Manila');

    $summary = (new EnsureDrawsForRangeAction)->execute($from, $to);

    $activeGames = Game::query()->where('active', true)->count();
    $expected = $activeGames * 3 /* slots */ * 3 /* days: 13,14,15 */;

    expect($summary['created'])->toBe($expected)
        ->and($summary['days'])->toBe(3)
        ->and($summary['games'])->toBe($activeGames)
        ->and(Draw::query()->count())->toBe($expected);
});

it('is idempotent — re-running produces zero additional rows', function () {
    $from = Carbon::create(2026, 5, 13, 0, 0, 0, 'Asia/Manila');
    $to = Carbon::create(2026, 5, 14, 23, 59, 0, 'Asia/Manila');

    $first = (new EnsureDrawsForRangeAction)->execute($from, $to);
    $second = (new EnsureDrawsForRangeAction)->execute($from, $to);

    expect($first['created'])->toBeGreaterThan(0)
        ->and($second['created'])->toBe(0)
        ->and(Draw::query()->count())->toBe($first['created']);
});

it('never mutates the status / cutoff_at of an existing row', function () {
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 13, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');

    $existing = Draw::factory()->for($game)->create([
        'draw_at' => $drawAt,
        // intentionally different cutoff to prove it isn't overwritten
        'cutoff_at' => (clone $drawAt)->subMinutes(999),
        'status' => 'settled',
    ]);
    $beforeCutoff = $existing->cutoff_at->toIso8601String();

    (new EnsureDrawsForRangeAction)->execute(
        Carbon::create(2026, 5, 13, 0, 0, 0, 'Asia/Manila'),
        Carbon::create(2026, 5, 13, 23, 59, 0, 'Asia/Manila'),
    );

    expect($existing->fresh()->status)->toBe('settled')
        ->and($existing->fresh()->cutoff_at->toIso8601String())->toBe($beforeCutoff);
});

it('honors per-game schedule overrides from config', function () {
    config()->set('lotto.draw_schedule', [
        'default' => ['14:00', '17:00', '21:00'],
        '2d' => ['11:00'], // 2d only runs at 11AM in this test
    ]);

    (new EnsureDrawsForRangeAction)->execute(
        Carbon::create(2026, 5, 13, 0, 0, 0, 'Asia/Manila'),
        Carbon::create(2026, 5, 13, 23, 59, 0, 'Asia/Manila'),
    );

    $twoD = Game::query()->where('code', '2d')->firstOrFail();
    expect(Draw::query()->where('game_id', $twoD->id)->count())->toBe(1);
});

it('returns zero when from > to', function () {
    $summary = (new EnsureDrawsForRangeAction)->execute(
        Carbon::create(2026, 5, 15, 0, 0, 0, 'Asia/Manila'),
        Carbon::create(2026, 5, 13, 0, 0, 0, 'Asia/Manila'),
    );

    expect($summary)->toBe(['created' => 0, 'days' => 0, 'games' => 0, 'slots' => 0])
        ->and(Draw::query()->count())->toBe(0);
});

it('returns zero when no slots are configured', function () {
    config()->set('lotto.draw_schedule', ['default' => []]);

    $summary = (new EnsureDrawsForRangeAction)->execute(
        Carbon::create(2026, 5, 13, 0, 0, 0, 'Asia/Manila'),
        Carbon::create(2026, 5, 13, 23, 59, 0, 'Asia/Manila'),
    );

    expect($summary['created'])->toBe(0)
        ->and(Draw::query()->count())->toBe(0);
});
