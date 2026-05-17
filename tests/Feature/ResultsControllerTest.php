<?php

declare(strict_types=1);

use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);
});

it('redirects guests to /login', function () {
    $this->get('/results')->assertRedirect('/login');
});

it('lists draws from the last 7 days with state derived correctly', function () {
    $user = User::factory()->withWallet()->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    // Settled past draw (result published)
    $settled = Draw::factory()->for($twoD)->settled()->create();
    DrawResult::factory()->for($settled)->create(['numbers' => [7, 22]]);

    // Past draw, no result yet (admin still entering) → awaiting
    $awaiting = Draw::factory()->for($twoD)->create([
        'draw_at' => now()->subMinutes(5),
        'cutoff_at' => now()->subMinutes(15),
        'status' => 'closed',
    ]);

    // Upcoming draw, cutoff in future → open
    $open = Draw::factory()->for($twoD)->open()->create();

    $this->actingAs($user)->get('/results')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('results/index')
            ->has('results', 3)
            ->where('results.0.state', 'open')         // future draw first (desc by draw_at)
            ->where('results.1.state', 'awaiting')
            ->where('results.2.state', 'settled')
            ->where('results.2.numbers', [7, 22])
        );
});

it('excludes draws older than 7 days', function () {
    $user = User::factory()->withWallet()->create();
    $twoD = Game::query()->where('code', '2d')->firstOrFail();

    Draw::factory()->for($twoD)->create([
        'draw_at' => now()->subDays(10),
        'cutoff_at' => now()->subDays(10)->subMinutes(10),
        'status' => 'settled',
    ]);

    $this->actingAs($user)->get('/results')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('results', 0));
});

it('renders the empty state when no draws are scheduled within the window', function () {
    $user = User::factory()->withWallet()->create();

    $this->actingAs($user)->get('/results')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('results/index')
            ->has('results', 0)
        );
});
