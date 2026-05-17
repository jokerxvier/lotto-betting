<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GameBetTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'game_id',
    'code',
    'label',
    'base_bet_amount',
    'base_payout_amount',
    'payout_strategy',
    'min_bet',
    'max_bet',
    'active',
    'sort_order',
])]
class GameBetType extends Model
{
    /** @use HasFactory<GameBetTypeFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'base_bet_amount' => 'decimal:2',
            'base_payout_amount' => 'decimal:2',
            'min_bet' => 'decimal:2',
            'max_bet' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
