<?php

declare(strict_types=1);

use App\Models\Bet;
use App\Models\Draw;
use App\Models\Game;
use App\Models\GameBetType;
use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);
});

/**
 * Build one valid cart-leg payload with a fresh UUID `leg_token`.
 *
 * @param  array<int, int>  $numbers
 */
function leg(Draw $draw, GameBetType $type, array $numbers, string $amount, ?string $token = null): array
{
    return [
        'leg_token' => $token ?? (string) Str::uuid(),
        'draw_id' => $draw->id,
        'game_bet_type_id' => $type->id,
        'numbers' => $numbers,
        'amount' => $amount,
    ];
}

it('redirects guests to /login', function () {
    $this->post('/bets/cart', ['legs' => []])->assertRedirect('/login');
});

it('places multiple bets atomically: one Bet per leg', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $target = GameBetType::query()->where('game_id', $game->id)->where('code', 'target')->firstOrFail();
    $rambol = GameBetType::query()->where('game_id', $game->id)->where('code', 'rambol')->firstOrFail();

    $this->actingAs($user)
        ->post('/bets/cart', [
            'legs' => [
                leg($draw, $target, [1, 4], '10.00'),
                leg($draw, $rambol, [2, 5], '15.00'),
            ],
        ])
        ->assertRedirect('/tickets')
        ->assertSessionHas('status', '2 tickets placed.');

    expect(Bet::query()->count())->toBe(2)
        ->and($user->wallet->fresh()->balance)->toEqual('475.00');
});

it('is idempotent: re-posting the same cart leaves wallet and bet count unchanged', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $target = GameBetType::query()->where('game_id', $game->id)->where('code', 'target')->firstOrFail();
    $rambol = GameBetType::query()->where('game_id', $game->id)->where('code', 'rambol')->firstOrFail();

    $payload = [
        'legs' => [
            leg($draw, $target, [1, 4], '10.00', token: '00000000-0000-4000-8000-000000000aaa'),
            leg($draw, $rambol, [2, 5], '15.00', token: '00000000-0000-4000-8000-000000000bbb'),
        ],
    ];

    $this->actingAs($user)->post('/bets/cart', $payload)->assertRedirect('/tickets');
    $this->actingAs($user)->post('/bets/cart', $payload)->assertRedirect('/tickets');

    expect(Bet::query()->count())->toBe(2)
        ->and(WalletTransaction::query()->where('type', 'bet_debit')->count())->toBe(2)
        ->and($user->wallet->fresh()->balance)->toEqual('475.00');
});

it('rejects duplicate leg_token values in a single cart', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $target = GameBetType::query()->where('game_id', $game->id)->where('code', 'target')->firstOrFail();
    $dup = '00000000-0000-4000-8000-000000000ccc';

    $this->actingAs($user)
        ->from('/lotto')
        ->post('/bets/cart', [
            'legs' => [
                leg($draw, $target, [1, 4], '10.00', token: $dup),
                leg($draw, $target, [2, 5], '10.00', token: $dup),
            ],
        ])
        ->assertSessionHasErrors('legs.1.leg_token');

    expect(Bet::query()->count())->toBe(0);
});

it('requires a UUID leg_token on every leg', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $target = GameBetType::query()->where('game_id', $game->id)->where('code', 'target')->firstOrFail();

    $this->actingAs($user)
        ->from('/lotto')
        ->post('/bets/cart', [
            'legs' => [
                [
                    'draw_id' => $draw->id,
                    'game_bet_type_id' => $target->id,
                    'numbers' => [1, 4],
                    'amount' => '10.00',
                ],
            ],
        ])
        ->assertSessionHasErrors('legs.0.leg_token');
});

it('rolls back the whole cart when the wallet cannot cover the total', function () {
    $user = User::factory()->withWallet('12.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $target = GameBetType::query()->where('game_id', $game->id)->where('code', 'target')->firstOrFail();

    $this->actingAs($user)
        ->from('/lotto')
        ->post('/bets/cart', [
            'legs' => [
                leg($draw, $target, [1, 4], '10.00'),
                leg($draw, $target, [2, 5], '10.00'),
            ],
        ])
        ->assertRedirect('/lotto')
        ->assertSessionHasErrors('legs');

    expect(Bet::query()->count())->toBe(0)
        ->and($user->wallet->fresh()->balance)->toEqual('12.00');
});

it('rejects a leg with an out-of-range number', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $target = GameBetType::query()->where('game_id', $game->id)->where('code', 'target')->firstOrFail();

    $this->actingAs($user)
        ->from('/lotto')
        ->post('/bets/cart', [
            'legs' => [leg($draw, $target, [1, 99], '10.00')],
        ])
        ->assertSessionHasErrors('legs.0.numbers');

    expect(Bet::query()->count())->toBe(0);
});

it('rejects a closed draw via the FormRequest', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $draw->forceFill(['cutoff_at' => now()->subMinute()])->save();
    $target = GameBetType::query()->where('game_id', $game->id)->where('code', 'target')->firstOrFail();

    $this->actingAs($user)
        ->from('/lotto')
        ->post('/bets/cart', [
            'legs' => [leg($draw, $target, [1, 4], '10.00')],
        ])
        ->assertSessionHasErrors('legs.0.draw_id');
});

it('rejects an amount outside the bet type bounds', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $target = GameBetType::query()->where('game_id', $game->id)->where('code', 'target')->firstOrFail();

    $this->actingAs($user)
        ->from('/lotto')
        ->post('/bets/cart', [
            'legs' => [leg($draw, $target, [1, 4], '5.00')],
        ])
        ->assertSessionHasErrors('legs.0.amount');
});
