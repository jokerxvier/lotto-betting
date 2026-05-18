<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $cachedPinHash = null;

    protected static ?string $cachedAdminPasswordHash = null;

    /**
     * Default state — a username + PIN account, the most common case in tests.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'telegram_id' => null,
            'username' => fake()->unique()->userName(),
            'pin_hash' => static::$cachedPinHash ??= Hash::make('4729'),
            'status' => 'active',
            'locked_until' => null,
        ];
    }

    /**
     * Telegram-only account: signed up via widget, hasn't completed PIN setup yet.
     */
    public function telegramOnly(?int $telegramId = null): static
    {
        return $this->state(fn (): array => [
            'telegram_id' => $telegramId ?? fake()->unique()->randomNumber(8, true),
            'username' => null,
            'pin_hash' => null,
        ]);
    }

    /**
     * Telegram account that has completed PIN setup (linked + active).
     */
    public function telegramLinked(?int $telegramId = null): static
    {
        return $this->state(fn (): array => [
            'telegram_id' => $telegramId ?? fake()->unique()->randomNumber(8, true),
        ]);
    }

    public function locked(): static
    {
        return $this->state(['locked_until' => now()->addMinutes(30)]);
    }

    /**
     * Admin account — flips is_admin and seeds a hashed password so the
     * /admin/login flow finds something to authenticate against. The
     * pin_hash is kept (tests can rely on the default `4729` PIN) for any
     * legacy code path that hasn't been migrated off PINs yet.
     */
    public function admin(string $password = 'admin-pass-2026'): static
    {
        return $this->state(function () use ($password): array {
            // Hash the default password once per process; allow callers to
            // pass a different password (which won't be cached).
            $hashed = $password === 'admin-pass-2026'
                ? (static::$cachedAdminPasswordHash ??= Hash::make($password))
                : Hash::make($password);

            return [
                'is_admin' => true,
                'password' => $hashed,
            ];
        });
    }

    public function withWallet(string $balance = '0.00'): static
    {
        return $this->afterCreating(function (User $user) use ($balance): void {
            Wallet::factory()->for($user)->state(['balance' => $balance])->create();
        });
    }
}
