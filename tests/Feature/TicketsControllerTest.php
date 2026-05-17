<?php

declare(strict_types=1);

use App\Models\Bet;
use App\Models\BetLeg;
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

it('redirects guests to /login from the index', function () {
    $this->get('/tickets')->assertRedirect('/login');
});

it('lists only the auth user\'s bets, newest first', function () {
    $jane = User::factory()->withWallet()->create();
    $pedro = User::factory()->withWallet()->create();

    $twoD = Game::query()->where('code', '2d')->firstOrFail();
    $target = GameBetType::query()
        ->where('game_id', $twoD->id)
        ->where('code', 'target')
        ->firstOrFail();
    $draw = Draw::factory()->for($twoD)->open()->create();

    $janeBet = Bet::factory()->for($jane)->for($draw)->create();
    BetLeg::factory()->for($janeBet)->for($target, 'betType')->create();

    $pedroBet = Bet::factory()->for($pedro)->for($draw)->create();
    BetLeg::factory()->for($pedroBet)->for($target, 'betType')->create();

    $this->actingAs($jane)->get('/tickets')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tickets/index')
            ->has('tickets', 1)
            ->where('tickets.0.id', $janeBet->id)
        );
});

it('renders the empty state when the user has no bets', function () {
    $user = User::factory()->withWallet()->create();

    $this->actingAs($user)->get('/tickets')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tickets/index')
            ->has('tickets', 0)
        );
});

it('shows a single ticket detail with legs + draw result', function () {
    $jane = User::factory()->withWallet()->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();
    $target = GameBetType::query()
        ->where('game_id', $twoD->id)
        ->where('code', 'target')
        ->firstOrFail();

    $draw = Draw::factory()->for($twoD)->settled()->create();
    DrawResult::factory()->for($draw)->create(['numbers' => [7, 22]]);

    $bet = Bet::factory()->for($jane)->for($draw)->create([
        'status' => 'won',
        'settled_at' => now(),
    ]);
    BetLeg::factory()->for($bet)->for($target, 'betType')->create([
        'numbers' => [7, 22],
        'amount' => '10.00',
        'potential_payout' => '5500.00',
        'payout' => '5500.00',
    ]);

    $this->actingAs($jane)->get("/tickets/{$bet->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tickets/show')
            ->where('ticket.id', $bet->id)
            ->where('ticket.status', 'won')
            ->where('ticket.draw.result_numbers', [7, 22])
            ->has('ticket.legs', 1)
            ->where('ticket.legs.0.numbers', [7, 22])
            ->where('ticket.legs.0.payout', '5500.00')
        );
});

it('404s when viewing another user\'s ticket', function () {
    $jane = User::factory()->withWallet()->create();
    $pedro = User::factory()->withWallet()->create();

    $twoD = Game::query()->where('code', '2d')->firstOrFail();
    $target = GameBetType::query()
        ->where('game_id', $twoD->id)
        ->where('code', 'target')
        ->firstOrFail();
    $draw = Draw::factory()->for($twoD)->open()->create();

    $pedroBet = Bet::factory()->for($pedro)->for($draw)->create();
    BetLeg::factory()->for($pedroBet)->for($target, 'betType')->create();

    $this->actingAs($jane)->get("/tickets/{$pedroBet->id}")->assertNotFound();
});
