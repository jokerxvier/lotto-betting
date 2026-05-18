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
function adminPendingBet(
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

it('forbids non-admins from viewing the draws list', function () {
    $u = User::factory()->withWallet()->create();
    $this->actingAs($u)->get('/admin/draws')->assertForbidden();
});

it('forbids non-admins from posting a result', function () {
    $u = User::factory()->withWallet()->create();
    $draw = Draw::factory()->for(Game::query()->where('code', '2d')->first())->open()->create();

    $this->actingAs($u)
        ->post("/admin/draws/{$draw->id}/result", ['numbers' => [1, 4]])
        ->assertForbidden();
});

it('shows only awaiting draws in the index (past draw_at, no result yet)', function () {
    $admin = User::factory()->admin()->withWallet()->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();

    // Awaiting (past draw_at, no result)
    $awaiting = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->subHour(),
        'cutoff_at' => now()->subMinutes(120),
    ]);
    // Already settled — excluded
    $settled = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->subHours(2),
        'cutoff_at' => now()->subMinutes(180),
        'status' => 'settled',
    ]);
    DrawResult::factory()->for($settled)->create();
    // Future draw — excluded
    $future = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->addHour(),
        'cutoff_at' => now()->addMinutes(50),
    ]);

    $this->actingAs($admin)
        ->get('/admin/draws')
        ->assertInertia(fn ($page) => $page
            ->component('admin/draws/index')
            ->has('draws', 1)
            ->where('draws.0.id', $awaiting->id)
        );

    // suppress unused-variable warnings
    expect([$settled->id, $future->id])->each->toBeInt();
});

it('lets an admin publish a result and settles bets atomically', function () {
    $admin = User::factory()->admin()->withWallet()->create();
    $player = User::factory()->withWallet('100.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->subMinutes(10),
        'cutoff_at' => now()->subMinutes(70),
    ]);
    adminPendingBet($player, $draw, 'target', [1, 4], '5500.00', 'k-win');

    $this->actingAs($admin)
        ->post("/admin/draws/{$draw->id}/result", ['numbers' => [1, 4]])
        ->assertRedirect(route('admin.draws.index'))
        ->assertSessionHas('status');

    expect($draw->fresh()->status)->toBe('settled')
        ->and(Bet::query()->where('idempotency_key', 'k-win')->first()->status)->toBe('won')
        ->and($player->wallet->fresh()->balance)->toEqual('5600.00');
});

it('rejects bad numbers (out of range / wrong count)', function () {
    $admin = User::factory()->admin()->withWallet()->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->subMinutes(10),
        'cutoff_at' => now()->subMinutes(70),
    ]);

    $this->actingAs($admin)
        ->from("/admin/draws/{$draw->id}/result")
        ->post("/admin/draws/{$draw->id}/result", ['numbers' => [1, 99]])
        ->assertSessionHasErrors('numbers.1');

    $this->actingAs($admin)
        ->from("/admin/draws/{$draw->id}/result")
        ->post("/admin/draws/{$draw->id}/result", ['numbers' => [1, 2, 3]])
        ->assertSessionHasErrors('numbers');

    expect($draw->fresh()->status)->not->toBe('settled');
});

it('rejects re-publishing an already-settled draw', function () {
    $admin = User::factory()->admin()->withWallet()->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->subMinutes(10),
        'cutoff_at' => now()->subMinutes(70),
        'status' => 'settled',
    ]);
    DrawResult::factory()->for($draw)->create(['numbers' => [1, 4]]);

    $this->actingAs($admin)
        ->from("/admin/draws/{$draw->id}/result")
        ->post("/admin/draws/{$draw->id}/result", ['numbers' => [1, 4]])
        ->assertSessionHasErrors('numbers');
});

it('pre-fills the form with numbers scraped from the configured source', function () {
    $admin = User::factory()->admin()->withWallet()->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = now()->setTime(17, 0)->subDay(); // 5:00 PM yesterday
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    Http::fake([
        'lottopcso.com/*' => Http::response(
            '<table><tr><td>'.$drawAt->format('Y-m-d').'</td><td>5:00 PM</td><td>EZ2</td><td>17 - 22</td></tr></table>',
            200,
        ),
    ]);

    $this->actingAs($admin)
        ->get("/admin/draws/{$draw->id}/result")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/draws/result')
            ->where('suggested_numbers', [17, 22])
            ->where('suggestion_source', 'lottopcso.com')
        );
});

it('passes null suggestion props when the scraper finds nothing', function () {
    $admin = User::factory()->admin()->withWallet()->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->setTime(17, 0)->subDay(),
        'cutoff_at' => now()->setTime(16, 0)->subDay(),
    ]);

    Http::fake([
        'lottopcso.com/*' => Http::response('<html>no rows here</html>', 200),
    ]);

    $this->actingAs($admin)
        ->get("/admin/draws/{$draw->id}/result")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('suggested_numbers', null)
            ->where('suggestion_source', null)
        );
});

it('forbids non-admins from POSTing /admin/draws/scrape', function () {
    $u = User::factory()->withWallet()->create();
    $this->actingAs($u)->post('/admin/draws/scrape')->assertForbidden();
});

it('admin scrape endpoint runs the action and flashes a summary', function () {
    $admin = User::factory()->admin()->withWallet()->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawAt = now()->setTime(17, 0)->subDay(); // 5:00 PM yesterday
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => $drawAt,
        'cutoff_at' => (clone $drawAt)->subMinutes(60),
    ]);

    Http::fake([
        'lottopcso.com/*' => Http::response(
            '<table><tr><td>'.$drawAt->format('Y-m-d').'</td><td>5:00 PM</td><td>EZ2</td><td>1 - 4</td></tr></table>',
            200,
        ),
    ]);

    $this->actingAs($admin)
        ->from('/admin/draws')
        ->post('/admin/draws/scrape')
        ->assertRedirect('/admin/draws')
        ->assertSessionHas('status');

    expect($draw->fresh()->status)->toBe('settled');
});

it('admin scrape flash reads "No awaiting draws" when there is nothing to do', function () {
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)
        ->from('/admin/draws')
        ->post('/admin/draws/scrape')
        ->assertRedirect('/admin/draws')
        ->assertSessionHas('status', 'No awaiting draws to scrape.');
});

it('does not call the scraper at all when the settings toggle is off', function () {
    (new SettingsService)->set('scraper.suggestions_enabled', false);

    // Sanity: the toggle is visible to a fresh service instance.
    expect((new SettingsService)->get('scraper.suggestions_enabled'))->toBeFalse();

    $admin = User::factory()->admin()->withWallet()->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->setTime(17, 0)->subDay(),
        'cutoff_at' => now()->setTime(16, 0)->subDay(),
    ]);

    Http::fake();

    $this->actingAs($admin)
        ->get("/admin/draws/{$draw->id}/result")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('suggested_numbers', null)
            ->where('suggestion_source', null)
        );

    // Filter out Inertia's own SSR POST — only fail on actual scraper calls.
    Http::assertNotSent(fn ($req): bool => str_contains($req->url(), 'lottopcso.com'));
});
