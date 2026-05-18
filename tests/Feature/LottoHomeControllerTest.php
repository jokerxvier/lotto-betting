<?php

declare(strict_types=1);

use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\Game;
use App\Models\GameBetType;
use App\Models\User;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;

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

it('ignores draws past their cutoff when picking the next open draw', function () {
    $user = User::factory()->withWallet()->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    // Past cutoff, still status=scheduled → not "next"
    Draw::factory()->for($twoD)->create([
        'draw_at' => now()->subHour(),
        'cutoff_at' => now()->subHours(2),
        'status' => 'scheduled',
    ]);

    $this->actingAs($user);

    $this->get('/lotto')->assertOk()->assertInertia(fn ($page) => $page
        ->where('games.0.next_draw_id', null)
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
