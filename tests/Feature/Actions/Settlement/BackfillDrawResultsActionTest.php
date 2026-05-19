<?php

declare(strict_types=1);

use App\Actions\Settlement\BackfillDrawResultsAction;
use App\Models\Bet;
use App\Models\BetLeg;
use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\Game;
use App\Models\GameBetType;
use App\Models\User;
use App\Services\PcsoResultScraper;
use App\Services\SettingsService;
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
    $this->action = new BackfillDrawResultsAction(new PcsoResultScraper(new SettingsService));
});

function gmaListing(): string
{
    return (string) file_get_contents(__DIR__.'/../../../fixtures/gma/lotto-listing-2026-05-17.html');
}

function backfillSettledBet(User $user, Draw $draw, string $code, array $numbers): Bet
{
    $type = GameBetType::query()
        ->where('game_id', $draw->game_id)
        ->where('code', $code)
        ->firstOrFail();
    $bet = Bet::query()->create([
        'user_id' => $user->id,
        'draw_id' => $draw->id,
        'amount' => '10.00',
        'potential_payout' => '5500.00',
        'status' => 'won',
        'idempotency_key' => 'k-'.$draw->id.'-'.uniqid(),
    ]);
    BetLeg::query()->create([
        'bet_id' => $bet->id,
        'game_bet_type_id' => $type->id,
        'numbers' => $numbers,
        'amount' => '10.00',
        'potential_payout' => '5500.00',
    ]);

    return $bet;
}

it('creates DrawResult when missing', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(gmaListing(), 200)]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    $from = Carbon::create(2026, 5, 17, 0, 0, 0, 'Asia/Manila');
    $to = Carbon::create(2026, 5, 17, 23, 59, 0, 'Asia/Manila');

    $summary = $this->action->execute($from, $to);

    expect($summary['counts']['created'])->toBe(1)
        ->and(DrawResult::query()->where('draw_id', $draw->id)->first()?->numbers)
        ->toBe([10, 28]);
});

it('updates DrawResult when numbers differ on a non-settled draw', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(gmaListing(), 200)]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
        // NOT settled
    ]);
    DrawResult::factory()->for($draw)->create(['numbers' => [1, 1]]);

    $from = Carbon::create(2026, 5, 17, 0, 0, 0, 'Asia/Manila');
    $to = Carbon::create(2026, 5, 17, 23, 59, 0, 'Asia/Manila');

    $summary = $this->action->execute($from, $to);

    expect($summary['counts']['updated'])->toBe(1)
        ->and(DrawResult::query()->where('draw_id', $draw->id)->first()?->numbers)->toBe([10, 28]);
});

it('marks status=unchanged when scraper returns identical numbers', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(gmaListing(), 200)]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);
    $existing = DrawResult::factory()->for($draw)->create(['numbers' => [10, 28]]);
    $publishedAt = $existing->published_at;

    $from = Carbon::create(2026, 5, 17, 0, 0, 0, 'Asia/Manila');
    $to = Carbon::create(2026, 5, 17, 23, 59, 0, 'Asia/Manila');

    $summary = $this->action->execute($from, $to);

    expect($summary['counts']['unchanged'])->toBe(1)
        // confirm no write — published_at unchanged
        ->and(DrawResult::query()->where('draw_id', $draw->id)->first()?->published_at?->toIso8601String())
        ->toBe($publishedAt?->toIso8601String());
});

it('refuses to overwrite a settled draw and audit-logs as skipped_settled', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(gmaListing(), 200)]);

    $player = User::factory()->withWallet('100.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
        'status' => 'settled',
    ]);
    DrawResult::factory()->for($draw)->create(['numbers' => [1, 1]]);
    backfillSettledBet($player, $draw, 'target', [1, 1]);

    $from = Carbon::create(2026, 5, 17, 0, 0, 0, 'Asia/Manila');
    $to = Carbon::create(2026, 5, 17, 23, 59, 0, 'Asia/Manila');

    $summary = $this->action->execute($from, $to);

    expect($summary['counts']['skipped_settled'])->toBe(1)
        // numbers NOT overwritten
        ->and(DrawResult::query()->where('draw_id', $draw->id)->first()?->numbers)->toBe([1, 1]);
});

it('respects the games filter — only touches draws for the given game codes', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(gmaListing(), 200)]);

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

    $from = Carbon::create(2026, 5, 17, 0, 0, 0, 'Asia/Manila');
    $to = Carbon::create(2026, 5, 17, 23, 59, 0, 'Asia/Manila');

    $summary = $this->action->execute($from, $to, gameCodes: ['2d']);

    expect($summary['counts']['created'])->toBe(1)
        ->and(DrawResult::query()->where('draw_id', $twoDraw->id)->exists())->toBeTrue()
        ->and(DrawResult::query()->where('draw_id', $threeDraw->id)->exists())->toBeFalse();
});

it('respects the date range — ignores draws outside [from, to]', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(gmaListing(), 200)]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $insideAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    $outsideAt = Carbon::create(2026, 5, 14, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');

    $inside = Draw::factory()->for($game)->open()->create([
        'draw_at' => $insideAt,
        'cutoff_at' => (clone $insideAt)->subMinutes(60),
    ]);
    $outside = Draw::factory()->for($game)->open()->create([
        'draw_at' => $outsideAt,
        'cutoff_at' => (clone $outsideAt)->subMinutes(60),
    ]);

    $from = Carbon::create(2026, 5, 16, 0, 0, 0, 'Asia/Manila');
    $to = Carbon::create(2026, 5, 17, 23, 59, 0, 'Asia/Manila');

    $summary = $this->action->execute($from, $to);

    expect(DrawResult::query()->where('draw_id', $inside->id)->exists())->toBeTrue()
        ->and(DrawResult::query()->where('draw_id', $outside->id)->exists())->toBeFalse()
        ->and(count($summary['per_draw']))->toBe(1);
});

it('NEVER mutates Draw.status or Bet.status (the non-settle invariant)', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(gmaListing(), 200)]);

    $player = User::factory()->withWallet('100.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
        'status' => 'scheduled',
    ]);
    $bet = Bet::query()->create([
        'user_id' => $player->id,
        'draw_id' => $draw->id,
        'amount' => '10.00',
        'potential_payout' => '5500.00',
        'status' => 'pending',
        'idempotency_key' => 'k-backfill-invariant',
    ]);
    $type = GameBetType::query()
        ->where('game_id', $draw->game_id)
        ->where('code', 'target')
        ->firstOrFail();
    BetLeg::query()->create([
        'bet_id' => $bet->id,
        'game_bet_type_id' => $type->id,
        'numbers' => [10, 28],
        'amount' => '10.00',
        'potential_payout' => '5500.00',
    ]);

    $startingBalance = $player->wallet->fresh()->balance;

    $from = Carbon::create(2026, 5, 17, 0, 0, 0, 'Asia/Manila');
    $to = Carbon::create(2026, 5, 17, 23, 59, 0, 'Asia/Manila');
    $this->action->execute($from, $to);

    expect($draw->fresh()->status)->toBe('scheduled')
        ->and($bet->fresh()->status)->toBe('pending')
        ->and($player->wallet->fresh()->balance)->toEqual($startingBalance)
        ->and(DrawResult::query()->where('draw_id', $draw->id)->first()?->numbers)
        ->toBe([10, 28]);
});

it('--dry-run does not write to the database', function () {
    Http::fake(['gmanetwork.com/*' => Http::response(gmaListing(), 200)]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    $from = Carbon::create(2026, 5, 17, 0, 0, 0, 'Asia/Manila');
    $to = Carbon::create(2026, 5, 17, 23, 59, 0, 'Asia/Manila');

    $summary = $this->action->execute($from, $to, dryRun: true);

    expect($summary['counts']['created'])->toBe(1)
        ->and(DrawResult::query()->count())->toBe(0);
});

it('reports skipped_no_match when the scraper has no row for the draw', function () {
    Http::fake(['gmanetwork.com/*' => Http::response('<div>no rows here</div>', 200)]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    $from = Carbon::create(2026, 5, 17, 0, 0, 0, 'Asia/Manila');
    $to = Carbon::create(2026, 5, 17, 23, 59, 0, 'Asia/Manila');

    $summary = $this->action->execute($from, $to);

    expect($summary['counts']['skipped_no_match'])->toBe(1)
        ->and(DrawResult::query()->count())->toBe(0);
});
