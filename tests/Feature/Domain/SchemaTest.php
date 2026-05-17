<?php

declare(strict_types=1);

use App\Models\Bet;
use App\Models\BetLeg;
use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\Game;
use App\Models\GameBetType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('migrates the full domain schema', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'users',
    'wallets',
    'wallet_transactions',
    'games',
    'game_bet_types',
    'draws',
    'draw_results',
    'bets',
    'bet_legs',
]);

it('extends the users table with lotto columns', function (string $column) {
    expect(Schema::hasColumn('users', $column))->toBeTrue();
})->with([
    'telegram_id',
    'username',
    'pin_hash',
    'status',
    'wallet_code',
    'locked_until',
]);

it('runs every domain factory cleanly', function (string $factoryClass) {
    $model = $factoryClass::new()->create();

    expect($model->exists)->toBeTrue();
})->with([
    Wallet::factory()::class,
    WalletTransaction::factory()::class,
    Game::factory()::class,
    GameBetType::factory()::class,
    Draw::factory()::class,
    DrawResult::factory()::class,
    Bet::factory()::class,
    BetLeg::factory()::class,
]);

it('auto-generates an 8-char wallet_code on user creation', function () {
    $user = User::factory()->create();

    expect($user->wallet_code)->toHaveLength(8)
        ->and($user->wallet_code)->toMatch('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{8}$/');
});

it('enforces unique wallet_code', function () {
    $first = User::factory()->create();

    expect(fn () => User::factory()->create(['wallet_code' => $first->wallet_code]))
        ->toThrow(QueryException::class);
});

it('enforces unique idempotency_key per user on bets', function () {
    $user = User::factory()->create();
    $draw = Draw::factory()->create();

    Bet::factory()->for($user)->for($draw)->create(['idempotency_key' => 'same-key']);

    expect(fn () => Bet::factory()->for($user)->for($draw)->create(['idempotency_key' => 'same-key']))
        ->toThrow(QueryException::class);
});

it('allows the same idempotency_key across different users on bets', function () {
    [$alice, $bob] = User::factory()->count(2)->create();
    $draw = Draw::factory()->create();

    Bet::factory()->for($alice)->for($draw)->create(['idempotency_key' => 'shared-key']);
    $bobBet = Bet::factory()->for($bob)->for($draw)->create(['idempotency_key' => 'shared-key']);

    expect($bobBet->exists)->toBeTrue();
});

it('enforces unique idempotency_key per wallet on transactions', function () {
    $wallet = Wallet::factory()->create();

    WalletTransaction::factory()->for($wallet)->create(['idempotency_key' => 'wt-key']);

    expect(fn () => WalletTransaction::factory()->for($wallet)->create(['idempotency_key' => 'wt-key']))
        ->toThrow(QueryException::class);
});

it('seeds the two MVP games', function () {
    $this->seed(GameSeeder::class);

    expect(Game::query()->pluck('code')->all())
        ->toEqualCanonicalizing(['2d', '3d']);
});

it('seeds four MVP game bet types with the expected payouts', function () {
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);

    $twoD = Game::query()->where('code', '2d')->firstOrFail();
    $threeD = Game::query()->where('code', '3d')->firstOrFail();

    expect(GameBetType::query()->count())->toBe(4)
        ->and(GameBetType::query()->where('game_id', $twoD->id)->where('code', 'target')->value('base_payout_amount'))->toEqual('5500.00')
        ->and(GameBetType::query()->where('game_id', $twoD->id)->where('code', 'rambol')->value('payout_strategy'))->toBe('split_permutations')
        ->and(GameBetType::query()->where('game_id', $threeD->id)->where('code', 'target')->value('base_payout_amount'))->toEqual('6000.00')
        ->and(GameBetType::query()->where('game_id', $threeD->id)->where('code', 'rambol')->value('payout_strategy'))->toBe('split_permutations');
});

it('resolves the wallet relation on user', function () {
    $user = User::factory()->withWallet('500.00')->create();

    expect($user->wallet)->not->toBeNull()
        ->and($user->wallet->balance)->toEqual('500.00');
});

it('resolves nested bet -> legs -> betType -> game relations', function () {
    $game = Game::factory()->threeDigit()->create();
    $betType = GameBetType::factory()->for($game)->target()->create();
    $draw = Draw::factory()->for($game)->open()->create();
    $bet = Bet::factory()->for($draw)->create();
    BetLeg::factory()->for($bet)->for($betType, 'betType')->create();

    $loaded = Bet::query()->with('legs.betType.game')->find($bet->id);

    expect($loaded?->legs)->toHaveCount(1)
        ->and($loaded?->legs->first()?->betType->code)->toBe('target')
        ->and($loaded?->legs->first()?->betType->game->code)->toBe('3d');
});

it('resolves the result relation on a settled draw', function () {
    $draw = Draw::factory()->settled()->create();
    DrawResult::factory()->for($draw)->create(['numbers' => [1, 2, 3]]);

    expect($draw->fresh()?->result?->numbers)->toBe([1, 2, 3]);
});
