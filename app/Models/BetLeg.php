<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BetLegFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bet_id',
    'game_bet_type_id',
    'numbers',
    'amount',
    'potential_payout',
    'payout',
])]
class BetLeg extends Model
{
    /** @use HasFactory<BetLegFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'numbers' => 'array',
        ];
    }

    /** @return BelongsTo<Bet, $this> */
    public function bet(): BelongsTo
    {
        return $this->belongsTo(Bet::class);
    }

    /** @return BelongsTo<GameBetType, $this> */
    public function betType(): BelongsTo
    {
        return $this->belongsTo(GameBetType::class, 'game_bet_type_id');
    }
}
