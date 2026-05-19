<?php

declare(strict_types=1);

use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\Game;
use Carbon\Carbon;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);
    Cache::flush();
    config()->set('lotto.scraper.source', 'gma');
});

function backfillGmaFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../../fixtures/gma/lotto-listing-2026-05-17.html');
}

it('default range = last 7 days (Manila) when no --from/--to/--days given', function () {
    Http::fake();
    // Pin "now" so the default range is deterministic.
    Carbon::setTestNow(Carbon::create(2026, 5, 17, 23, 0, 0, 'Asia/Manila'));

    $this->artisan('draws:backfill-results', ['--no-ensure' => true])
        ->expectsOutputToContain('Backfilling draws 2026-05-10 to 2026-05-17 (Manila)')
        ->expectsOutputToContain('No draws found in the requested range')
        ->assertSuccessful();

    Carbon::setTestNow();
});

it('creates a DrawResult when --from/--to bracket a real draw', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(backfillGmaFixture(), 200)]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    $this->artisan('draws:backfill-results', [
        '--from' => '2026-05-17',
        '--to' => '2026-05-17',
        '--no-ensure' => true,
    ])
        ->expectsOutputToContain('Total: 1 created')
        ->assertSuccessful();

    expect(DrawResult::query()->where('draw_id', $draw->id)->first()?->numbers)->toBe([10, 28]);
});

it('accepts --from / --to and validates from <= to', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(backfillGmaFixture(), 200)]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    $this->artisan('draws:backfill-results', [
        '--from' => '2026-05-17',
        '--to' => '2026-05-17',
        '--no-ensure' => true,
    ])->assertSuccessful();

    $this->artisan('draws:backfill-results', [
        '--from' => '2026-05-17',
        '--to' => '2026-05-15',
        '--no-ensure' => true,
    ])
        ->expectsOutputToContain('--from must be on or before --to')
        ->assertFailed();
});

it('rejects --from in the future', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 17, 12, 0, 0, 'Asia/Manila'));

    $this->artisan('draws:backfill-results', [
        '--from' => '2026-06-01',
        '--to' => '2026-06-02',
    ])
        ->expectsOutputToContain('--from cannot be in the future')
        ->assertFailed();

    Carbon::setTestNow();
});

it('rejects malformed --from', function () {
    $this->artisan('draws:backfill-results', [
        '--from' => 'not-a-date',
    ])
        ->expectsOutputToContain('Invalid --from')
        ->assertFailed();
});

it('filters by --games', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(backfillGmaFixture(), 200)]);

    $two = Game::query()->where('code', '2d')->firstOrFail();
    $three = Game::query()->where('code', '3d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');

    $twoDraw = Draw::factory()->for($two)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);
    $threeDraw = Draw::factory()->for($three)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    $this->artisan('draws:backfill-results', [
        '--from' => '2026-05-17',
        '--to' => '2026-05-17',
        '--games' => '2d',
        '--no-ensure' => true,
    ])->assertSuccessful();

    expect(DrawResult::query()->where('draw_id', $twoDraw->id)->exists())->toBeTrue()
        ->and(DrawResult::query()->where('draw_id', $threeDraw->id)->exists())->toBeFalse();
});

it('--dry-run prints the table but writes nothing', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(backfillGmaFixture(), 200)]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    $this->artisan('draws:backfill-results', [
        '--from' => '2026-05-17',
        '--to' => '2026-05-17',
        '--dry-run' => true,
        '--no-ensure' => true,
    ])
        ->expectsOutputToContain('[DRY RUN')
        ->expectsOutputToContain('Total: 1 created')
        ->assertSuccessful();

    expect(DrawResult::query()->count())->toBe(0);
});

it('prints the no-settlement reminder', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(backfillGmaFixture(), 200)]);

    $this->artisan('draws:backfill-results', [
        '--from' => '2026-05-17',
        '--to' => '2026-05-17',
    ])
        ->expectsOutputToContain('does NOT settle bets')
        ->assertSuccessful();
});

it('warns "No draws found" when the range is valid but empty', function () {
    Http::fake();

    $this->artisan('draws:backfill-results', [
        '--from' => '2020-01-01',
        '--to' => '2020-01-02',
        '--no-ensure' => true,
    ])
        ->expectsOutputToContain('No draws found in the requested range')
        ->assertSuccessful();
});

it('seeds missing Draw rows for the range before backfill (default behavior)', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(backfillGmaFixture(), 200)]);

    // No Draw rows exist for 2026-05-17 yet — the ensure step should seed them.
    expect(Draw::query()->whereDate('draw_at', '2026-05-17')->count())->toBe(0);

    $this->artisan('draws:backfill-results', [
        '--from' => '2026-05-17',
        '--to' => '2026-05-17',
    ])
        ->expectsOutputToContain('Seeded')
        ->assertSuccessful();

    // 2 active games × 3 slots = 6 historical Draw rows created.
    expect(Draw::query()->whereDate('draw_at', '2026-05-17')->count())->toBe(6);
});

it('--no-ensure opts out of the seed step (strict reconciliation flow)', function () {
    Http::fake();

    $this->artisan('draws:backfill-results', [
        '--from' => '2026-05-17',
        '--to' => '2026-05-17',
        '--no-ensure' => true,
    ])
        ->doesntExpectOutputToContain('Seeded')
        ->expectsOutputToContain('No draws found in the requested range')
        ->assertSuccessful();

    expect(Draw::query()->whereDate('draw_at', '2026-05-17')->count())->toBe(0);
});
