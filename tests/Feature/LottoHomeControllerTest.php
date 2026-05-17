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

it('bounces an incomplete telegram-only account to /auth/setup-pin', function () {
    $user = User::factory()->telegramOnly()->create();
    $this->actingAs($user);

    $this->get('/lotto')->assertRedirect(route('auth.setup-pin'));
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
