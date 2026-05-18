<?php

declare(strict_types=1);

use App\Models\Bet;
use App\Models\BetLeg;
use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\Game;
use App\Models\GameBetType;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);
    Cache::flush();
});

/**
 * @param  list<int>  $numbers
 */
function autoBet(
    User $user,
    Draw $draw,
    string $code,
    array $numbers,
    string $payout,
    string $key,
): Bet {
    $type = GameBetType::query()
        ->where('game_id', $draw->game_id)
        ->where('code', $code)
        ->firstOrFail();
    $bet = Bet::query()->create([
        'user_id' => $user->id,
        'draw_id' => $draw->id,
        'amount' => '10.00',
        'potential_payout' => $payout,
        'status' => 'pending',
        'idempotency_key' => $key,
    ]);
    BetLeg::query()->create([
        'bet_id' => $bet->id,
        'game_bet_type_id' => $type->id,
        'numbers' => $numbers,
        'amount' => '10.00',
        'potential_payout' => $payout,
    ]);

    return $bet;
}

it('is a no-op when the auto_publish_enabled toggle is off', function () {
    Http::fake();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->setTime(17, 0)->subDay(),
        'cutoff_at' => now()->setTime(16, 0)->subDay(),
    ]);

    $this->artisan('draws:auto-settle')->assertSuccessful();

    expect($draw->fresh()->status)->not->toBe('settled');
    Http::assertNotSent(fn ($req): bool => str_contains($req->url(), 'lottopcso.com'));
});

it('--force bypasses the toggle and processes awaiting draws', function () {
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = now()->setTime(17, 0)->subDay();
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);
    $player = User::factory()->withWallet('100.00')->create();
    autoBet($player, $draw, 'target', [1, 4], '5500.00', 'k-win');

    Http::fake([
        'lottopcso.com/*' => Http::response(
            '<table><tr><td>'.$drawAt->format('Y-m-d').'</td><td>5:00 PM</td><td>EZ2</td><td>1 - 4</td></tr></table>',
            200,
        ),
    ]);

    $this->artisan('draws:auto-settle --force')->assertSuccessful();

    expect($draw->fresh()->status)->toBe('settled')
        ->and(Bet::query()->where('idempotency_key', 'k-win')->first()->status)->toBe('won')
        ->and($player->wallet->fresh()->balance)->toEqual('5600.00');
});

it('settles awaiting draws when the toggle is on', function () {
    (new SettingsService)->set('scraper.auto_publish_enabled', true);
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = now()->setTime(17, 0)->subDay();
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    Http::fake([
        'lottopcso.com/*' => Http::response(
            '<table><tr><td>'.$drawAt->format('Y-m-d').'</td><td>5:00 PM</td><td>EZ2</td><td>7 - 8</td></tr></table>',
            200,
        ),
    ]);

    $this->artisan('draws:auto-settle')->assertSuccessful();

    expect($draw->fresh()->status)->toBe('settled')
        ->and(DrawResult::query()->where('draw_id', $draw->id)->first()->numbers)->toBe([7, 8]);
});

it('skips a draw when the scraper returns null (no double-effect)', function () {
    (new SettingsService)->set('scraper.auto_publish_enabled', true);
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->setTime(17, 0)->subDay(),
        'cutoff_at' => now()->setTime(16, 0)->subDay(),
    ]);

    Http::fake([
        'lottopcso.com/*' => Http::response('<html>no rows here</html>', 200),
    ]);

    $this->artisan('draws:auto-settle')->assertSuccessful();

    expect($draw->fresh()->status)->not->toBe('settled')
        ->and(DrawResult::query()->where('draw_id', $draw->id)->exists())->toBeFalse();
});

it('refuses to publish out-of-range numbers (defense in depth)', function () {
    (new SettingsService)->set('scraper.auto_publish_enabled', true);
    $game = Game::query()->where('code', '2d')->firstOrFail(); // range 1-31
    $drawAt = now()->setTime(17, 0)->subDay();
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    // Scraper returns 99 — out of 1-31 range
    Http::fake([
        'lottopcso.com/*' => Http::response(
            '<table><tr><td>'.$drawAt->format('Y-m-d').'</td><td>5:00 PM</td><td>EZ2</td><td>99 - 04</td></tr></table>',
            200,
        ),
    ]);

    $this->artisan('draws:auto-settle')->assertSuccessful();

    expect($draw->fresh()->status)->not->toBe('settled')
        ->and(DrawResult::query()->where('draw_id', $draw->id)->exists())->toBeFalse();
});

it('is idempotent: re-running after a successful settle is a no-op', function () {
    (new SettingsService)->set('scraper.auto_publish_enabled', true);
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = now()->setTime(17, 0)->subDay();
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    Http::fake([
        'lottopcso.com/*' => Http::response(
            '<table><tr><td>'.$drawAt->format('Y-m-d').'</td><td>5:00 PM</td><td>EZ2</td><td>7 - 8</td></tr></table>',
            200,
        ),
    ]);

    $this->artisan('draws:auto-settle')->assertSuccessful();
    $this->artisan('draws:auto-settle')->assertSuccessful();

    // Still one DrawResult; once a draw is settled it's excluded from the
    // awaiting query.
    expect(DrawResult::query()->where('draw_id', $draw->id)->count())->toBe(1);
});

it('--draw=N processes only that draw', function () {
    (new SettingsService)->set('scraper.auto_publish_enabled', true);
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = now()->setTime(17, 0)->subDay();
    $a = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);
    $b = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->setTime(14, 0)->subDay(),
        'cutoff_at' => now()->setTime(13, 0)->subDay(),
    ]);

    Http::fake([
        'lottopcso.com/*' => Http::response(
            '<table><tr><td>'.$drawAt->format('Y-m-d').'</td><td>5:00 PM</td><td>EZ2</td><td>7 - 8</td></tr></table>',
            200,
        ),
    ]);

    $this->artisan("draws:auto-settle --draw={$a->id}")->assertSuccessful();

    expect($a->fresh()->status)->toBe('settled')
        ->and($b->fresh()->status)->not->toBe('settled');
});
