<?php

declare(strict_types=1);

use App\Models\Bet;
use App\Models\Draw;
use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\DevFixturesSeeder;
use Database\Seeders\GameBetTypeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    // Games + bet types are prerequisites in production DatabaseSeeder
    // ordering; mirror that here so the dev fixtures run against the
    // same shape they'd see in real usage.
    $this->seed(GameSeeder::class);
    $this->seed(GameBetTypeSeeder::class);
    $this->seed(DevFixturesSeeder::class);
});

it('seeds the admin user with the right credentials and flag', function () {
    $admin = User::query()->where('username', 'admin')->firstOrFail();

    expect($admin->is_admin)->toBeTrue()
        ->and(Hash::check('472901', $admin->pin_hash))->toBeTrue()
        ->and($admin->wallet?->balance)->toEqual('0.00');
});

it('seeds the three player accounts with funded wallets', function () {
    foreach (['jane', 'pedro', 'maria'] as $username) {
        $user = User::query()->where('username', $username)->firstOrFail();

        expect($user->is_admin)->toBeFalse()
            ->and(Hash::check('472901', $user->pin_hash))->toBeTrue()
            ->and($user->wallet?->balance)->toEqual('500.00');
    }

    // Each funded player also gets the audit-trail topup ledger row.
    expect(WalletTransaction::query()->where('type', 'admin_topup')->count())
        ->toBeGreaterThanOrEqual(3);
});

it('does NOT create any Draw rows (draws come from the cron)', function () {
    expect(Draw::query()->count())->toBe(0);
});

it('does NOT create any Bet rows', function () {
    expect(Bet::query()->count())->toBe(0);
});

it('is idempotent — re-running does not duplicate users', function () {
    $countBefore = User::query()->count();

    $this->seed(DevFixturesSeeder::class);

    expect(User::query()->count())->toBe($countBefore);
});
