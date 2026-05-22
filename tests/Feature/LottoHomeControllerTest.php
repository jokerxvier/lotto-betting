<?php

declare(strict_types=1);

use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\Game;
use App\Models\GameBetType;
use App\Models\User;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);
});

it('redirects guests to /login', function () {
    $this->get('/lotto')->assertRedirect('/login');
});

it('lets a telegram-only account reach /lotto without forcing setup-pin', function () {
    $user = User::factory()->telegramOnly()->create();
    $this->actingAs($user);

    $this->get('/lotto')->assertOk();
});

it('self-heals the 7-day upcoming-draws window when the warm-up flag is on', function () {
    // Opt into the home-page warm-up just for this test. Default in
    // phpunit.xml is off so other tests can stage explicit fixtures.
    config(['lotto.home_warm_upcoming_window' => true]);

    $user = User::factory()->withWallet()->create();
    $this->actingAs($user);

    // No draws upfront — the warm-up should populate the window.
    expect(Draw::query()->count())->toBe(0);

    $this->get('/lotto')->assertOk();

    // 2 active games × 7 days × 3 slots = 42.
    expect(Draw::query()->count())->toBe(42);

    // Idempotent: a second hit (within the 15-min cache window) is a
    // no-op even if the cache flushed, because firstOrCreate keys on
    // (game_id, draw_at).
    Cache::flush();
    $this->get('/lotto')->assertOk();
    expect(Draw::query()->count())->toBe(42);
});

it('renders both game cards with empty result/next-draw when no draws exist', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $this->actingAs($user);

    $this->get('/lotto')->assertOk()->assertInertia(fn ($page) => $page
        ->component('lotto/home')
        ->has('games', 2)
        ->where('games.0.code', '2d')
        ->where('games.0.latest_result_numbers', null)
        ->where('games.0.next_draw_at', null)
        ->where('games.1.code', '3d')
        ->has('games.0.bet_types', 2)
        ->where('games.0.bet_types.0.code', 'target')
        ->where('games.0.bet_types.1.code', 'rambol')
        ->where('games.0.number_min', 1)
        ->where('games.0.number_max', 31)
    );
});

it('surfaces the latest settled result and the next open draw per game', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    $settled = Draw::factory()->for($twoD)->settled()->create();
    DrawResult::factory()->for($settled)->create(['numbers' => [7, 22]]);

    $next = Draw::factory()->for($twoD)->open()->create();

    $this->actingAs($user);

    $this->get('/lotto')->assertOk()->assertInertia(fn ($page) => $page
        ->component('lotto/home')
        ->where('games.0.latest_result_numbers', [7, 22])
        ->where('games.0.next_draw_id', $next->id)
        ->where('games.0.payout_label', '₱10 bet wins ₱5,500')
        ->where('games.0.upcoming_draws.0.id', $next->id)
        ->has('games.0.latest_drawn_label')
    );
});

it('labels the latest settled result with a PCSO slot tag (2PM/5PM/9PM)', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    // 09:00 UTC = 17:00 Manila — pin the timestamp explicitly so the
    // factory's default `settled()` draw_at can't override it.
    $drawAt = Carbon::parse('2026-05-17 09:00:00', 'UTC');
    $settled = Draw::factory()->for($twoD)->create([
        'draw_at' => $drawAt,
        'cutoff_at' => $drawAt->copy()->subMinutes(10),
        'status' => 'settled',
    ]);
    DrawResult::factory()->for($settled)->create(['numbers' => [1, 4]]);

    $this->actingAs($user)
        ->get('/lotto')
        ->assertInertia(fn ($page) => $page
            ->where('games.0.latest_drawn_label', '5PM')
        );
});

it('exposes a 7-day upcoming_draws list per game (for advance betting)', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    // Three future draws in the 7-day window
    $a = Draw::factory()->for($twoD)->open()->create([
        'draw_at' => now()->addHour(),
        'cutoff_at' => now()->addMinutes(50),
    ]);
    $b = Draw::factory()->for($twoD)->open()->create([
        'draw_at' => now()->addDay(),
        'cutoff_at' => now()->addDay()->subMinutes(10),
    ]);
    $c = Draw::factory()->for($twoD)->open()->create([
        'draw_at' => now()->addDays(3),
        'cutoff_at' => now()->addDays(3)->subMinutes(10),
    ]);
    // Outside the window — should NOT appear
    Draw::factory()->for($twoD)->open()->create([
        'draw_at' => now()->addDays(14),
        'cutoff_at' => now()->addDays(14)->subMinutes(10),
    ]);
    // Already past cutoff — should NOT appear
    Draw::factory()->for($twoD)->open()->create([
        'draw_at' => now()->addMinutes(5),
        'cutoff_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->get('/lotto')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // first card is 2D; assert exactly 3 upcoming draws in order
            ->has('games.0.upcoming_draws', 3)
            ->where('games.0.upcoming_draws.0.id', $a->id)
            ->where('games.0.upcoming_draws.1.id', $b->id)
            ->where('games.0.upcoming_draws.2.id', $c->id)
        );
});

it('includes the wallet balance via shared inertia props', function () {
    $user = User::factory()->withWallet('1234.56')->create();
    $this->actingAs($user);

    $this->get('/lotto')->assertOk()->assertInertia(fn ($page) => $page
        ->where('auth.wallet.balance', '1234.56')
        ->where('auth.wallet.wallet_code', $user->wallet_code)
    );
});

it('hides inactive games from the home page', function () {
    Game::query()->where('code', '3d')->update(['active' => false]);
    $user = User::factory()->withWallet()->create();
    $this->actingAs($user);

    $this->get('/lotto')->assertOk()->assertInertia(fn ($page) => $page
        ->has('games', 1)
        ->where('games.0.code', '2d')
    );
});

it('surfaces a drawn-but-not-yet-settled result so display is decoupled from settlement', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    // Older, fully-settled draw with numbers — would have been shown
    // under the old "status=settled" filter.
    $older = Draw::factory()->for($twoD)->create([
        'draw_at' => now()->subDay()->setTime(17, 0),
        'cutoff_at' => now()->subDay()->setTime(16, 50),
        'status' => 'settled',
    ]);
    DrawResult::factory()->for($older)->create(['numbers' => [8, 16]]);

    // Today's 5PM slot: scraper has attached the result but admin hasn't
    // published it yet, so the draw is still `scheduled`. Should be the
    // one we surface — display follows the result, not settlement state.
    $todays = Draw::factory()->for($twoD)->create([
        'draw_at' => now()->subHours(3),
        'cutoff_at' => now()->subHours(3)->subMinutes(10),
        'status' => 'scheduled',
    ]);
    DrawResult::factory()->for($todays)->create(['numbers' => [17, 23]]);

    $this->actingAs($user)
        ->get('/lotto')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('games.0.latest_result_numbers', [17, 23])
        );
});

it('skips drawn slots that have no result yet and shows the previous one with numbers', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    // Older settled with numbers.
    $older = Draw::factory()->for($twoD)->create([
        'draw_at' => now()->subHours(6),
        'cutoff_at' => now()->subHours(6)->subMinutes(10),
        'status' => 'settled',
    ]);
    DrawResult::factory()->for($older)->create(['numbers' => [3, 11]]);

    // Most recent slot — drawn but the scraper hasn't returned numbers
    // yet (no DrawResult). Don't let it mask the previous slot.
    Draw::factory()->for($twoD)->create([
        'draw_at' => now()->subMinutes(20),
        'cutoff_at' => now()->subMinutes(30),
        'status' => 'scheduled',
    ]);

    $this->actingAs($user)
        ->get('/lotto')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('games.0.latest_result_numbers', [3, 11])
        );
});

it('ignores stale orphan draws (no result, beyond the 6-hour lookback)', function () {
    $user = User::factory()->withWallet()->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    // Pre-settlement-pipeline leftover: scheduled, no result, days old.
    Draw::factory()->for($twoD)->create([
        'draw_at' => now()->subDays(2),
        'cutoff_at' => now()->subDays(2)->subMinutes(60),
        'status' => 'scheduled',
    ]);

    $this->actingAs($user);

    $this->get('/lotto')->assertOk()->assertInertia(fn ($page) => $page
        ->where('games.0.next_draw_id', null)
    );
});

it('surfaces a recently-drawn slot awaiting its scraped result as next_draw', function () {
    $user = User::factory()->withWallet()->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    // 9PM happened 3 minutes ago, scraper hasn't returned numbers yet.
    // Card should stay on this slot until the result attaches.
    $awaiting = Draw::factory()->for($twoD)->create([
        'draw_at' => now()->subMinutes(3),
        'cutoff_at' => now()->subHour()->subMinutes(3),
        'status' => 'scheduled',
    ]);
    // Tomorrow's 2PM exists and is bettable — shouldn't override the
    // still-awaiting today slot.
    Draw::factory()->for($twoD)->open()->create([
        'draw_at' => now()->addDay(),
        'cutoff_at' => now()->addDay()->subMinutes(10),
    ]);

    $this->actingAs($user)
        ->get('/lotto')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('games.0.next_draw_id', $awaiting->id)
        );
});

it('moves on to the next chronological slot once the current draw has its result attached', function () {
    $user = User::factory()->withWallet()->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    // Today's 9PM is drawn and the scraper has attached numbers → done.
    $drawn = Draw::factory()->for($twoD)->create([
        'draw_at' => now()->subMinutes(5),
        'cutoff_at' => now()->subHour()->subMinutes(5),
        'status' => 'scheduled',
    ]);
    DrawResult::factory()->for($drawn)->create(['numbers' => [12, 7]]);

    // Tomorrow's 2PM is the new "next".
    $next = Draw::factory()->for($twoD)->open()->create([
        'draw_at' => now()->addDay(),
        'cutoff_at' => now()->addDay()->subMinutes(10),
    ]);

    $this->actingAs($user)
        ->get('/lotto')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('games.0.next_draw_id', $next->id)
        );
});

it('surfaces a closed-window draw (cutoff passed, draw_at still future) as next_draw', function () {
    $user = User::factory()->withWallet()->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    // Tonight's 9PM: betting closed at 8PM but the draw is in ~1h.
    // The card should still surface this as "next" so the UI can render
    // a disabled NEW BET + CLOSED badge per UI_FLOWS.md.
    $closing = Draw::factory()->for($twoD)->create([
        'draw_at' => now()->addMinutes(30),
        'cutoff_at' => now()->subMinutes(30),
        'status' => 'scheduled',
    ]);
    // A clean future draw with cutoff still ahead — shouldn't override
    // the closer-in-time draw.
    Draw::factory()->for($twoD)->open()->create([
        'draw_at' => now()->addDay(),
        'cutoff_at' => now()->addDay()->subMinutes(10),
    ]);

    $this->actingAs($user)
        ->get('/lotto')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('games.0.next_draw_id', $closing->id)
        );
});

it('excludes closed-window draws from upcoming_draws (advance bet sheet)', function () {
    $user = User::factory()->withWallet()->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    // Closed-window draw — should be `next` but NOT in upcoming_draws.
    Draw::factory()->for($twoD)->create([
        'draw_at' => now()->addMinutes(30),
        'cutoff_at' => now()->subMinutes(30),
        'status' => 'scheduled',
    ]);
    $bettable = Draw::factory()->for($twoD)->open()->create([
        'draw_at' => now()->addDay(),
        'cutoff_at' => now()->addDay()->subMinutes(10),
    ]);

    $this->actingAs($user)
        ->get('/lotto')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('games.0.upcoming_draws', 1)
            ->where('games.0.upcoming_draws.0.id', $bettable->id)
        );
});

it('skips the payout label when the target bet type is inactive', function () {
    GameBetType::query()
        ->where('code', 'target')
        ->update(['active' => false]);

    $user = User::factory()->withWallet()->create();
    $this->actingAs($user);

    $this->get('/lotto')->assertOk()->assertInertia(fn ($page) => $page
        ->where('games.0.payout_label', null)
        ->where('games.0.target_bet_type_id', null)
    );
});
