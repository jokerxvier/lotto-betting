<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Realistic data for local development and manual browser testing.
 * Idempotent: re-running upserts the same users and wallets. Skipped
 * in production.
 *
 * SCOPE: users + wallets only. Draws and results come from the crons:
 *   php artisan draws:generate-upcoming --days=7   (real 14:00/17:00/21:00
 *                                                   PCSO slots per game)
 *   php artisan draws:auto-settle [--force]        (scraper-fetched real
 *                                                   PCSO numbers + settle)
 * Run those once after seeding to populate draws and results. We don't
 * call them from here so the seed stays fast and the production cron
 * remains the single source of truth for both.
 *
 *  - 1 admin (`admin / 472901`) + matching empty wallet
 *  - 3 players (`jane`, `pedro`, `maria` / 472901) each with ₱500 wallet
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
}
