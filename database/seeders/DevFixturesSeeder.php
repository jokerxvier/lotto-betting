<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Bet;
use App\Models\BetLeg;
use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\Game;
use App\Models\GameBetType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Realistic data for local development and manual browser testing.
 * Idempotent: re-running clears and reseeds. Skipped in production.
 *
 *  - 1 admin (`admin / 472901`) + 3 player accounts each with a funded wallet
 *  - 2 settled past draws (one 2d, one 3d) with results
 *  - 2 upcoming draws (one 2d, one 3d) accepting bets
 *  - A pending and a settled bet per player
 */
final class DevFixturesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin',
                'pin_hash' => Hash::make('472901'),
                'is_admin' => true,
                'status' => 'active',
            ],
        );
        $this->ensureWallet($admin, '0.00');

        $players = collect(['jane', 'pedro', 'maria'])->map(
            fn (string $username) => User::query()->updateOrCreate(
                ['username' => $username],
                [
                    'name' => Str::ucfirst($username),
                    'pin_hash' => Hash::make('472901'),
                    'status' => 'active',
                ],
            ),
        );

        $players->each(fn (User $user) => $this->ensureWallet($user, '500.00'));

        $twoD = Game::query()->where('code', '2d')->firstOrFail();
        $threeD = Game::query()->where('code', '3d')->firstOrFail();
        $twoDTarget = GameBetType::query()
            ->where('game_id', $twoD->id)->where('code', 'target')->firstOrFail();
        $threeDTarget = GameBetType::query()
            ->where('game_id', $threeD->id)->where('code', 'target')->firstOrFail();

        $settled2d = $this->upsertDraw($twoD, now()->subHours(3), 'settled');
        $settled3d = $this->upsertDraw($threeD, now()->subHours(2), 'settled');
        $upcoming2d = $this->upsertDraw($twoD, now()->addHours(2), 'scheduled');
        $upcoming3d = $this->upsertDraw($threeD, now()->addHours(4), 'scheduled');

        DrawResult::query()->updateOrCreate(
            ['draw_id' => $settled2d->id],
            ['numbers' => [7, 22], 'published_at' => $settled2d->draw_at],
        );
        DrawResult::query()->updateOrCreate(
            ['draw_id' => $settled3d->id],
            ['numbers' => [4, 1, 9], 'published_at' => $settled3d->draw_at],
        );

        $players->each(function (User $user) use ($upcoming2d, $settled3d, $twoDTarget, $threeDTarget): void {
            $pending = Bet::query()->updateOrCreate(
                ['user_id' => $user->id, 'idempotency_key' => "dev-pending-{$user->id}"],
                [
                    'draw_id' => $upcoming2d->id,
                    'amount' => '10.00',
                    'potential_payout' => '5500.00',
                    'status' => 'pending',
                ],
            );
            BetLeg::query()->firstOrCreate(
                ['bet_id' => $pending->id, 'game_bet_type_id' => $twoDTarget->id],
                ['numbers' => [13, 27], 'amount' => '10.00', 'potential_payout' => '5500.00'],
            );

            $lost = Bet::query()->updateOrCreate(
                ['user_id' => $user->id, 'idempotency_key' => "dev-lost-{$user->id}"],
                [
                    'draw_id' => $settled3d->id,
                    'amount' => '10.00',
                    'potential_payout' => '6000.00',
                    'status' => 'lost',
                    'settled_at' => $settled3d->draw_at,
                ],
            );
            BetLeg::query()->firstOrCreate(
                ['bet_id' => $lost->id, 'game_bet_type_id' => $threeDTarget->id],
                ['numbers' => [1, 2, 3], 'amount' => '10.00', 'potential_payout' => '6000.00', 'payout' => '0.00'],
            );
        });
    }

    private function ensureWallet(User $user, string $balance): Wallet
    {
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => $balance, 'held_balance' => '0.00', 'version' => 0],
        );

        if ($wallet->wasRecentlyCreated && $balance !== '0.00') {
            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'admin_topup',
                'amount' => $balance,
                'balance_after' => $balance,
                'idempotency_key' => "dev-seed-topup-{$wallet->id}",
            ]);
        }

        return $wallet;
    }

    private function upsertDraw(Game $game, \DateTimeInterface $drawAt, string $status): Draw
    {
        return Draw::query()->updateOrCreate(
            ['game_id' => $game->id, 'draw_at' => $drawAt],
            [
                'cutoff_at' => Carbon::instance($drawAt)->subMinutes(10),
                'status' => $status,
            ],
        );
    }
}
