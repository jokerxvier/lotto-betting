<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Bet;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once per Bet that moved out of `pending` during settlement.
 * The Bet's `status` is already `won` or `lost` and (for wins) the
 * wallet credit ledger row is committed.
 */
final class BetSettled
{
    use Dispatchable;

    public function __construct(public readonly Bet $bet) {}
}
