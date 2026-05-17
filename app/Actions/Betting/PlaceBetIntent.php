<?php

declare(strict_types=1);

namespace App\Actions\Betting;

/**
 * Caller-supplied input to PlaceBetAction. Built by the controller from a
 * validated FormRequest so the action itself can stay typed and unaware of
 * HTTP shape.
 */
final readonly class PlaceBetIntent
{
    /**
     * @param  list<BetLegIntent>  $legs
     */
    public function __construct(
        public int $drawId,
        public string $idempotencyKey,
        public array $legs,
    ) {}
}
