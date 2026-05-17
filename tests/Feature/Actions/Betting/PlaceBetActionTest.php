<?php

declare(strict_types=1);

use App\Actions\Betting\BetLegIntent;
use App\Actions\Betting\PlaceBetAction;
use App\Actions\Betting\PlaceBetIntent;
use App\Events\BetPlaced;
use App\Exceptions\DrawClosedException;
use App\Exceptions\InvalidBetException;
use App\Models\Bet;
use App\Models\Draw;
use App\Models\Game;
use App\Models\GameBetType;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Exceptions\InsufficientFundsException;
use Brick\Money\Money;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);
    $this->action = app(PlaceBetAction::class);
});

function openDraw(string $gameCode = '2d'): Draw
{
    $game = Game::query()->where('code', $gameCode)->firstOrFail();

    return Draw::factory()->for($game)->open()->create();
}

function targetTypeFor(Draw $draw): GameBetType
{
    return GameBetType::query()
        ->where('game_id', $draw->game_id)
        ->where('code', 'target')
        ->firstOrFail();
}

function rambolTypeFor(Draw $draw): GameBetType
{
    return GameBetType::query()
        ->where('game_id', $draw->game_id)
        ->where('code', 'rambol')
        ->firstOrFail();
}

it('places a target bet, debits the wallet, writes legs, dispatches BetPlaced', function () {
    Event::fake([BetPlaced::class]);
    $user = User::factory()->withWallet('500.00')->create();
    $draw = openDraw('2d');
    $type = targetTypeFor($draw);

    $bet = $this->action->execute($user, new PlaceBetIntent(
        drawId: $draw->id,
        idempotencyKey: 'bet-1',
        legs: [
            new BetLegIntent(
                gameBetTypeId: $type->id,
                numbers: [1, 4],
                amount: Money::of('10.00', 'PHP'),
            ),
        ],
    ));

    expect($bet->status)->toBe('pending')
        ->and((string) $bet->amount)->toBe('10.00')
        ->and((string) $bet->potential_payout)->toBe('5500.00')
        ->and($bet->legs)->toHaveCount(1)
        ->and($bet->legs->first()->numbers)->toBe([1, 4])
        ->and($user->wallet->fresh()->balance)->toEqual('490.00');

    $tx = WalletTransaction::query()->latest('id')->firstOrFail();
    expect($tx->type)->toBe('bet_debit')
        ->and($tx->amount)->toEqual('-10.00')
        ->and($tx->reference_id)->toBe($bet->id);

    Event::assertDispatched(BetPlaced::class, fn ($e) => $e->bet->id === $bet->id);
});

it('computes a rambolito payout per the §3 dataset', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $draw = openDraw('3d');
    $rambol = rambolTypeFor($draw);

    $bet = $this->action->execute($user, new PlaceBetIntent(
        drawId: $draw->id,
        idempotencyKey: 'bet-rambol-1',
        legs: [
            new BetLegIntent(
                gameBetTypeId: $rambol->id,
                numbers: [1, 1, 2],
                amount: Money::of('10.00', 'PHP'),
            ),
        ],
    ));

    expect((string) $bet->potential_payout)->toBe('2000.00');
});

it('is idempotent: same idempotency_key returns the original bet, no double debit', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $draw = openDraw('2d');
    $type = targetTypeFor($draw);

    $intent = new PlaceBetIntent(
        drawId: $draw->id,
        idempotencyKey: 'same-key',
        legs: [
            new BetLegIntent($type->id, [1, 4], Money::of('10.00', 'PHP')),
        ],
    );

    $first = $this->action->execute($user, $intent);
    $second = $this->action->execute($user, $intent);

    expect($second->id)->toBe($first->id)
        ->and(Bet::query()->count())->toBe(1)
        ->and(WalletTransaction::query()->count())->toBe(1)
        ->and($user->wallet->fresh()->balance)->toEqual('490.00');
});

it('throws DrawClosedException when the cutoff is in the past', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $draw = openDraw('2d');
    $draw->forceFill(['cutoff_at' => now()->subMinute()])->save();
    $type = targetTypeFor($draw);

    expect(fn () => $this->action->execute($user, new PlaceBetIntent(
        drawId: $draw->id,
        idempotencyKey: 'closed-1',
        legs: [new BetLegIntent($type->id, [1, 4], Money::of('10.00', 'PHP'))],
    )))->toThrow(DrawClosedException::class);

    expect(Bet::query()->count())->toBe(0)
        ->and($user->wallet->fresh()->balance)->toEqual('500.00');
});

it('throws DrawClosedException when status is not scheduled', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $draw = openDraw('2d');
    $draw->forceFill(['status' => 'closed'])->save();
    $type = targetTypeFor($draw);

    expect(fn () => $this->action->execute($user, new PlaceBetIntent(
        drawId: $draw->id,
        idempotencyKey: 'closed-status',
        legs: [new BetLegIntent($type->id, [1, 4], Money::of('10.00', 'PHP'))],
    )))->toThrow(DrawClosedException::class);
});

it('throws InsufficientFundsException and rolls back the bet+legs when wallet is short', function () {
    $user = User::factory()->withWallet('5.00')->create();
    $draw = openDraw('2d');
    $type = targetTypeFor($draw);

    expect(fn () => $this->action->execute($user, new PlaceBetIntent(
        drawId: $draw->id,
        idempotencyKey: 'overdraft-1',
        legs: [new BetLegIntent($type->id, [1, 4], Money::of('10.00', 'PHP'))],
    )))->toThrow(InsufficientFundsException::class);

    expect(Bet::query()->count())->toBe(0)
        ->and(WalletTransaction::query()->count())->toBe(0)
        ->and($user->wallet->fresh()->balance)->toEqual('5.00');
});

it('throws InvalidBetException when the bet type does not belong to the draw game', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $twoDDraw = openDraw('2d');
    $threeDTarget = GameBetType::query()
        ->whereHas('game', fn ($q) => $q->where('code', '3d'))
        ->where('code', 'target')
        ->firstOrFail();

    expect(fn () => $this->action->execute($user, new PlaceBetIntent(
        drawId: $twoDDraw->id,
        idempotencyKey: 'mismatch',
        legs: [new BetLegIntent($threeDTarget->id, [1, 2, 3], Money::of('10.00', 'PHP'))],
    )))->toThrow(InvalidBetException::class);

    expect(Bet::query()->count())->toBe(0);
});

it('throws InvalidBetException when the bet type is inactive', function () {
    $user = User::factory()->withWallet('500.00')->create();
    $draw = openDraw('2d');
    $type = targetTypeFor($draw);
    $type->update(['active' => false]);

    expect(fn () => $this->action->execute($user, new PlaceBetIntent(
        drawId: $draw->id,
        idempotencyKey: 'inactive',
        legs: [new BetLegIntent($type->id, [1, 4], Money::of('10.00', 'PHP'))],
    )))->toThrow(InvalidBetException::class);
});
