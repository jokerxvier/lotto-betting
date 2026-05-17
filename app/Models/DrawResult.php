<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DrawResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['draw_id', 'numbers', 'published_at'])]
class DrawResult extends Model
{
    /** @use HasFactory<DrawResultFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'numbers' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Draw, $this> */
    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }
}
