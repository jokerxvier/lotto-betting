<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'picks_count',
    'number_min',
    'number_max',
    'active',
    'sort_order',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<GameBetType, $this> */
    public function betTypes(): HasMany
    {
        return $this->hasMany(GameBetType::class);
    }

    /** @return HasMany<Draw, $this> */
    public function draws(): HasMany
    {
        return $this->hasMany(Draw::class);
    }
}
