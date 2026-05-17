<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Bet;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a Bet is created and the wallet debit is committed. The
 * future `SettleDrawJob` and a downstream "ticket placed" Telegram bot
 * notification will subscribe here.
 */
final class BetPlaced
{
    use Dispatchable;

    public function __construct(public readonly Bet $bet) {}
}
