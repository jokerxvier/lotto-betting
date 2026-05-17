<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'draw_id',
    'amount',
    'potential_payout',
    'status',
    'settled_at',
    'idempotency_key',
])]
#[Hidden(['idempotency_key'])]
class Bet extends Model
{
    /** @use HasFactory<BetFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'potential_payout' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Draw, $this> */
    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }

    /** @return HasMany<BetLeg, $this> */
    public function legs(): HasMany
    {
        return $this->hasMany(BetLeg::class);
    }
}
