<?php

declare(strict_types=1);

use App\Actions\Settlement\SettleDrawAction;
use App\Events\BetSettled;
use App\Events\DrawSettled;
use App\Exceptions\DrawAlreadySettledException;
use App\Exceptions\DrawNotReadyException;
use App\Models\Bet;
use App\Models\BetLeg;
use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\Game;
use App\Models\GameBetType;
use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);
    $this->action = app(SettleDrawAction::class);
});

/**
 * Build a pending Bet with one leg using the given bet-type code and picks.
 *
 * @param  list<int>  $numbers
 */
function pendingBet(
    User $user,
    Draw $draw,
    string $betTypeCode,
    array $numbers,
    string $potentialPayout,
    string $idempotencyKey,
): Bet {
    $type = GameBetType::query()
        ->where('game_id', $draw->game_id)
        ->where('code', $betTypeCode)
        ->firstOrFail();

    $bet = Bet::query()->create([
        'user_id' => $user->id,
        'draw_id' => $draw->id,
        'amount' => '10.00',
        'potential_payout' => $potentialPayout,
        'status' => 'pending',
        'idempotency_key' => $idempotencyKey,
    ]);

    BetLeg::query()->create([
        'bet_id' => $bet->id,
        'game_bet_type_id' => $type->id,
        'numbers' => $numbers,
        'amount' => '10.00',
        'potential_payout' => $potentialPayout,
    ]);

    return $bet->load('legs.betType', 'user');
}

it('settles a draw end-to-end: winners credited, losers marked, draw closed', function () {
    Event::fake([DrawSettled::class, BetSettled::class]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    DrawResult::factory()->for($draw)->create(['numbers' => [1, 4]]);

    $alice = User::factory()->withWallet('100.00')->create();
    $bob = User::factory()->withWallet('100.00')->create();
    $carol = User::factory()->withWallet('100.00')->create();

    // Alice: target [1,4] → winner ₱5500
    pendingBet($alice, $draw, 'target', [1, 4], '5500.00', 'bet-alice');
    // Bob: rambol [4,1] → winner ₱2750 (sorted equality)
    pendingBet($bob, $draw, 'rambol', [4, 1], '2750.00', 'bet-bob');
    // Carol: target [9,9] → loser
    pendingBet($carol, $draw, 'target', [9, 9], '5500.00', 'bet-carol');

    $result = $this->action->execute($draw->fresh());

    expect($result->settledCount)->toBe(3)
        ->and($result->wonCount)->toBe(2)
        ->and($result->totalPayout)->toBe('8250.00');

    expect($draw->fresh()->status)->toBe('settled');

    expect(Bet::query()->where('idempotency_key', 'bet-alice')->first()->status)->toBe('won')
        ->and(Bet::query()->where('idempotency_key', 'bet-bob')->first()->status)->toBe('won')
        ->and(Bet::query()->where('idempotency_key', 'bet-carol')->first()->status)->toBe('lost');

    expect($alice->wallet->fresh()->balance)->toEqual('5600.00')
        ->and($bob->wallet->fresh()->balance)->toEqual('2850.00')
        ->and($carol->wallet->fresh()->balance)->toEqual('100.00');

    Event::assertDispatched(DrawSettled::class, 1);
    Event::assertDispatched(BetSettled::class, 3);
});

it('is idempotent: re-running on a settled draw throws and does not double-credit', function () {
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    DrawResult::factory()->for($draw)->create(['numbers' => [1, 4]]);

    $alice = User::factory()->withWallet('100.00')->create();
    pendingBet($alice, $draw, 'target', [1, 4], '5500.00', 'bet-alice');

    $this->action->execute($draw->fresh());

    expect(fn () => $this->action->execute($draw->fresh()))
        ->toThrow(DrawAlreadySettledException::class);

    // Only one bet_payout row, single credit applied
    expect(WalletTransaction::query()->where('type', 'bet_payout')->count())->toBe(1)
        ->and($alice->wallet->fresh()->balance)->toEqual('5600.00');
});

it('throws DrawNotReadyException when no result is published', function () {
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();

    expect(fn () => $this->action->execute($draw->fresh()))
        ->toThrow(DrawNotReadyException::class);

    expect($draw->fresh()->status)->not->toBe('settled');
});

it('marks every leg with a per-leg payout (0.00 for losers)', function () {
    $game = Game::query()->where('code', '3d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    DrawResult::factory()->for($draw)->create(['numbers' => [1, 2, 3]]);

    $user = User::factory()->withWallet('100.00')->create();
    $bet = pendingBet($user, $draw, 'target', [9, 9, 9], '6000.00', 'bet-lose');

    $this->action->execute($draw->fresh());

    $leg = $bet->fresh()->legs->first();
    expect($leg->payout)->toEqual('0.00');
});

it('only settles bets for the target draw — leaves other pending bets alone', function () {
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $drawA = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->addHour(),
        'cutoff_at' => now()->addMinutes(50),
    ]);
    $drawB = Draw::factory()->for($game)->open()->create([
        'draw_at' => now()->addHours(2),
        'cutoff_at' => now()->addMinutes(110),
    ]);
    DrawResult::factory()->for($drawA)->create(['numbers' => [1, 4]]);

    $user = User::factory()->withWallet('100.00')->create();
    pendingBet($user, $drawA, 'target', [1, 4], '5500.00', 'a');
    pendingBet($user, $drawB, 'target', [1, 4], '5500.00', 'b');

    $this->action->execute($drawA->fresh());

    expect(Bet::query()->where('idempotency_key', 'a')->first()->status)->toBe('won')
        ->and(Bet::query()->where('idempotency_key', 'b')->first()->status)->toBe('pending');
});
