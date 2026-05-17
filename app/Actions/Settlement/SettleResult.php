<?php

declare(strict_types=1);

namespace App\Actions\Settlement;

/**
 * Summary the SettleDrawAction hands back to the caller after a successful
 * run. Amounts are decimal strings ("1234.00") per Hard Rule 2.
 */
final readonly class SettleResult
{
    public function __construct(
        public int $drawId,
        public int $settledCount,
        public int $wonCount,
        public string $totalPayout,
    ) {}
}
