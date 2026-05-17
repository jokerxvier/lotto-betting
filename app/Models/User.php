<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable([
    'name',
    'telegram_id',
    'username',
    'pin_hash',
    'status',
    'is_admin',
    'wallet_code',
    'locked_until',
])]
#[Hidden([
    'pin_hash',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    private const WALLET_CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const WALLET_CODE_LENGTH = 8;

    private const WALLET_CODE_MAX_ATTEMPTS = 10;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pin_hash' => 'hashed',
            'is_admin' => 'boolean',
            'locked_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (empty($user->wallet_code)) {
                $user->wallet_code = self::generateWalletCode();
            }
        });
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLocked(Builder $query): Builder
    {
        return $query->whereNotNull('locked_until')->where('locked_until', '>', now());
    }

    /** @return HasOne<Wallet, $this> */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /** @return HasMany<Bet, $this> */
    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    private static function generateWalletCode(): string
    {
        for ($attempt = 0; $attempt < self::WALLET_CODE_MAX_ATTEMPTS; $attempt++) {
            $code = '';
            for ($i = 0; $i < self::WALLET_CODE_LENGTH; $i++) {
                $code .= self::WALLET_CODE_ALPHABET[random_int(0, strlen(self::WALLET_CODE_ALPHABET) - 1)];
            }
            if (! self::query()->where('wallet_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not generate a unique wallet_code after '.self::WALLET_CODE_MAX_ATTEMPTS.' attempts.');
    }
}
