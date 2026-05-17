<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Draw;
use App\Models\Game;
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
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoffMinutes = (int) config('lotto.cutoff_minutes', 60);
        /** @var array<string, list<string>> $scheduleConfig */
        $scheduleConfig = (array) config('lotto.draw_schedule', []);
        /** @var list<string> $defaultSlots */
        $defaultSlots = (array) ($scheduleConfig['default'] ?? []);

        if ($defaultSlots === []) {
            $this->error('config(lotto.draw_schedule.default) is empty — nothing to seed.');

            return self::FAILURE;
        }

        $createdCount = 0;
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();

        /** @var iterable<Game> $games */
        $games = Game::query()->where('active', true)->orderBy('id')->get();

        foreach ($games as $game) {
            /** @var list<string> $slots */
            $slots = (array) ($scheduleConfig[$game->code] ?? $defaultSlots);

            for ($d = 0; $d < $days; $d++) {
                $day = $today->addDays($d);

                foreach ($slots as $slot) {
                    [$h, $m] = array_pad(
                        array_map('intval', explode(':', $slot)),
                        2,
                        0,
                    );

                    $drawAt = $day
                        ->setTime($h, $m)
                        ->setTimezone((string) config('app.timezone', 'UTC'));
                    $cutoffAt = $drawAt->subMinutes($cutoffMinutes);

                    $created = Draw::query()->firstOrCreate(
                        [
                            'game_id' => $game->id,
                            'draw_at' => $drawAt,
                        ],
                        [
                            'cutoff_at' => $cutoffAt,
                            'status' => 'scheduled',
                        ],
                    );

                    if ($created->wasRecentlyCreated) {
                        $createdCount++;
                    }
                }
            }
        }

        $this->info("Created {$createdCount} new draw(s) across {$games->count()} active game(s) for the next {$days} day(s).");

        return self::SUCCESS;
    }
}
