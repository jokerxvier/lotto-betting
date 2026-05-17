<?php

declare(strict_types=1);

namespace App\Actions\Betting;

use Brick\Money\Money;

/**
 * One leg of a bet. The amount is a `Brick\Money\Money` so arithmetic in
 * the action and calculator can't accidentally coerce to float.
 */
final readonly class BetLegIntent
{
    /**
     * @param  list<int>  $numbers
     */
    public function __construct(
        public int $gameBetTypeId,
        public array $numbers,
        public Money $amount,
    ) {}
}
