<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Draws\EnsureDrawsForRangeAction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Seeds the next N days of scheduled draws for every active game using the
 * PCSO time slots in config('lotto.draw_schedule'). Idempotent thanks to
 * the `draws_game_id_draw_at_unique` index — re-runs no-op on existing rows.
 *
 * Schedule: dailyAt('00:05') in routes/console.php so it runs at 12:05 AM
 * Manila and keeps the window topped up every night.
 */
#[Signature('draws:generate-upcoming
                            {--days=7 : How many days ahead to seed}')]
#[Description('Idempotently create scheduled draws for active games per the PCSO schedule.')]
final class GenerateUpcomingDrawsCommand extends Command
{
    public function handle(EnsureDrawsForRangeAction $action): int
    {
        $days = max(1, (int) $this->option('days'));
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $end = $today->addDays($days - 1);

        $summary = $action->execute($today, $end);

        if ($summary['days'] === 0 || $summary['slots'] === 0) {
            $this->error('config(lotto.draw_schedule.default) is empty or no active games — nothing to seed.');

            return self::FAILURE;
        }

        $this->info("Created {$summary['created']} new draw(s) across {$summary['games']} active game(s) for the next {$days} day(s).");

        return self::SUCCESS;
    }
}
