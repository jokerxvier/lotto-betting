<?php

declare(strict_types=1);

use App\Models\Bet;
use App\Models\Draw;
use App\Models\Game;
use App\Models\GameBetType;
use App\Models\User;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);
});

it('POST happy path: debits wallet, creates bet, redirects to /lotto with flash', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $target = GameBetType::query()
        ->where('game_id', $game->id)
        ->where('code', 'target')
        ->firstOrFail();

    $response = $this->actingAs($user)->post('/games/2d/bet', [
        'draw_id' => $draw->id,
        'idempotency_key' => '00000000-0000-4000-8000-000000000001',
        'legs' => [[
            'game_bet_type_id' => $target->id,
            'numbers' => [1, 4],
            'amount' => '10.00',
        ]],
    ]);

    $response->assertRedirect('/lotto')
        ->assertSessionHas('status');

    expect(Bet::query()->count())->toBe(1)
        ->and($user->wallet->fresh()->balance)->toEqual('490.00');
});

it('POST surfaces insufficient funds as a form error', function () {
    $user = User::factory()->withWallet('5.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $target = GameBetType::query()
        ->where('game_id', $game->id)
        ->where('code', 'target')
        ->firstOrFail();

    $this->actingAs($user)
        ->from('/lotto')
        ->post('/games/2d/bet', [
            'draw_id' => $draw->id,
            'idempotency_key' => '00000000-0000-4000-8000-000000000002',
            'legs' => [[
                'game_bet_type_id' => $target->id,
                'numbers' => [1, 4],
                'amount' => '10.00',
            ]],
        ])
        ->assertSessionHasErrors('amount');

    expect(Bet::query()->count())->toBe(0)
        ->and($user->wallet->fresh()->balance)->toEqual('5.00');
});

it('POST rejects out-of-range numbers via the FormRequest', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $target = GameBetType::query()
        ->where('game_id', $game->id)
        ->where('code', 'target')
        ->firstOrFail();

    $this->actingAs($user)
        ->from('/lotto')
        ->post('/games/2d/bet', [
            'draw_id' => $draw->id,
            'idempotency_key' => '00000000-0000-4000-8000-000000000003',
            'legs' => [[
                'game_bet_type_id' => $target->id,
                'numbers' => [1, 99],
                'amount' => '10.00',
            ]],
        ])
        ->assertSessionHasErrors('legs.0.numbers');

    expect(Bet::query()->count())->toBe(0);
});

it('POST rejects a closed draw via the FormRequest', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $draw->forceFill(['cutoff_at' => now()->subMinute()])->save();
    $target = GameBetType::query()
        ->where('game_id', $game->id)
        ->where('code', 'target')
        ->firstOrFail();

    $this->actingAs($user)
        ->from('/lotto')
        ->post('/games/2d/bet', [
            'draw_id' => $draw->id,
            'idempotency_key' => '00000000-0000-4000-8000-000000000004',
            'legs' => [[
                'game_bet_type_id' => $target->id,
                'numbers' => [1, 4],
                'amount' => '10.00',
            ]],
        ])
        ->assertSessionHasErrors('draw_id');
});

it('POST rejects an amount outside the bet type bounds', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $target = GameBetType::query()
        ->where('game_id', $game->id)
        ->where('code', 'target')
        ->firstOrFail();

    $this->actingAs($user)
        ->from('/lotto')
        ->post('/games/2d/bet', [
            'draw_id' => $draw->id,
            'idempotency_key' => '00000000-0000-4000-8000-000000000005',
            'legs' => [[
                'game_bet_type_id' => $target->id,
                'numbers' => [1, 4],
                'amount' => '5.00', // below min_bet=10.00 from seeder
            ]],
        ])
        ->assertSessionHasErrors('legs.0.amount');
});
