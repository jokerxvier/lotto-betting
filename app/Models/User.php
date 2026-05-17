<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'password',
    'telegram_id',
    'username',
    'pin_hash',
    'status',
    'wallet_code',
    'locked_until',
])]
#[Hidden([
    'password',
    'pin_hash',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    private const WALLET_CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const WALLET_CODE_LENGTH = 8;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin_hash' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
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
        do {
            $code = '';
            for ($i = 0; $i < self::WALLET_CODE_LENGTH; $i++) {
                $code .= self::WALLET_CODE_ALPHABET[random_int(0, strlen(self::WALLET_CODE_ALPHABET) - 1)];
            }
        } while (self::query()->where('wallet_code', $code)->exists());

        return $code;
    }
}
