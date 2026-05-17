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

    public function admin(): static
    {
        return $this->state(['is_admin' => true]);
    }

    public function withWallet(string $balance = '0.00'): static
    {
        return $this->afterCreating(function (User $user) use ($balance): void {
            Wallet::factory()->for($user)->state(['balance' => $balance])->create();
        });
    }
}
