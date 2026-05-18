<?php

declare(strict_types=1);

use App\Events\DrawSettled;
use App\Listeners\NotifyUsersOfSettledDraw;
use App\Models\Bet;
use App\Models\BetLeg;
use App\Models\Draw;
use App\Models\Game;
use App\Models\GameBetType;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);
    Cache::flush();
    config()->set('services.telegram.bot_token', 'TEST-BOT-TOKEN');
    config()->set('services.telegram.bot_username', 'ezswerte_bot');
});

/**
 * Build a settled bet with one leg + given payout (₱X.XX as decimal string).
 */
function settledBet(
    User $user,
    Draw $draw,
    string $code,
    string $payoutDecimal,
    string $status = 'won',
): Bet {
    $type = GameBetType::query()
        ->where('game_id', $draw->game_id)
        ->where('code', $code)
        ->firstOrFail();

    $bet = Bet::query()->create([
        'user_id' => $user->id,
        'draw_id' => $draw->id,
        'amount' => '10.00',
        'potential_payout' => $payoutDecimal,
        'status' => $status,
        'settled_at' => now(),
        'idempotency_key' => 'test-'.fake()->uuid(),
    ]);

    BetLeg::query()->create([
        'bet_id' => $bet->id,
        'game_bet_type_id' => $type->id,
        'numbers' => [1, 4],
        'amount' => '10.00',
        'potential_payout' => $payoutDecimal,
        'payout' => $status === 'won' ? $payoutDecimal : '0.00',
    ]);

    return $bet;
}

it('sends one DM per winning user (not per leg) and skips losers', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();

    $alice = User::factory()->telegramLinked(11111111)->withWallet()->create();
    $bob = User::factory()->telegramLinked(22222222)->withWallet()->create();
    $carol = User::factory()->telegramLinked(33333333)->withWallet()->create();

    settledBet($alice, $draw, 'target', '5500.00', 'won');
    settledBet($alice, $draw, 'rambol', '2750.00', 'won');   // second winning bet, same draw
    settledBet($bob, $draw, 'target', '5500.00', 'won');
    settledBet($carol, $draw, 'target', '5500.00', 'lost');  // loser; no DM

    app(NotifyUsersOfSettledDraw::class)->handle(new DrawSettled($draw->fresh()));

    // 2 outbound sendMessage requests: 1 for Alice (8250), 1 for Bob (5500)
    Http::assertSentCount(2);

    Http::assertSent(fn ($req): bool => $req['chat_id'] === 11111111
        && str_contains((string) $req['text'], '8,250')
        && str_contains((string) $req['text'], '2 winning bets'));

    Http::assertSent(fn ($req): bool => $req['chat_id'] === 22222222
        && str_contains((string) $req['text'], '5,500')
        && str_contains((string) $req['text'], '1 winning bet'));
});

it('skips users without telegram_id', function () {
    Http::fake();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $user = User::factory()->withWallet()->create(); // no telegram_id
    settledBet($user, $draw, 'target', '5500.00', 'won');

    app(NotifyUsersOfSettledDraw::class)->handle(new DrawSettled($draw->fresh()));

    Http::assertNotSent(fn ($req): bool => str_contains($req->url(), 'api.telegram.org'));
});

it('skips users who opted out via telegram_notifications_enabled=false', function () {
    Http::fake();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $user = User::factory()->telegramLinked(99999999)->withWallet()->create([
        'telegram_notifications_enabled' => false,
    ]);
    settledBet($user, $draw, 'target', '5500.00', 'won');

    app(NotifyUsersOfSettledDraw::class)->handle(new DrawSettled($draw->fresh()));

    Http::assertNotSent(fn ($req): bool => str_contains($req->url(), 'api.telegram.org'));
});

it('no-ops when the global telegram.push_enabled toggle is off', function () {
    (new SettingsService)->set('telegram.push_enabled', false);
    Http::fake();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $user = User::factory()->telegramLinked(44444444)->withWallet()->create();
    settledBet($user, $draw, 'target', '5500.00', 'won');

    app(NotifyUsersOfSettledDraw::class)->handle(new DrawSettled($draw->fresh()));

    Http::assertNotSent(fn ($req): bool => str_contains($req->url(), 'api.telegram.org'));
});

it('no-ops when the bot token is empty', function () {
    config()->set('services.telegram.bot_token', '');
    Http::fake();
    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $user = User::factory()->telegramLinked(55555555)->withWallet()->create();
    settledBet($user, $draw, 'target', '5500.00', 'won');

    app(NotifyUsersOfSettledDraw::class)->handle(new DrawSettled($draw->fresh()));

    Http::assertNotSent(fn ($req): bool => str_contains($req->url(), 'api.telegram.org'));
});

it('includes an Open Lotto PH button keyed to the configured bot username', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    $game = Game::query()->where('code', '2d')->firstOrFail();
    $draw = Draw::factory()->for($game)->open()->create();
    $user = User::factory()->telegramLinked(66666666)->withWallet()->create();
    settledBet($user, $draw, 'target', '5500.00', 'won');

    app(NotifyUsersOfSettledDraw::class)->handle(new DrawSettled($draw->fresh()));

    Http::assertSent(function ($req): bool {
        $markup = (string) ($req['reply_markup'] ?? '');

        return str_contains($markup, 'Open Lotto PH')
            && str_contains($markup, 'https://t.me/ezswerte_bot');
    });
});

it('declares retry policy on the queued listener', function () {
    $listener = app(NotifyUsersOfSettledDraw::class);

    expect($listener->tries)->toBe(3)
        ->and($listener->backoff)->toBe([30, 90, 300]);
});
