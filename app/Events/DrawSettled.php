<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Draw;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once per draw after SettleDrawAction commits, before any per-bet
 * BetSettled events. Downstream listeners (notifications, analytics) can
 * use it to react to "this draw is now final".
 */
final class DrawSettled
{
    use Dispatchable;

    public function __construct(public readonly Draw $draw) {}
}
