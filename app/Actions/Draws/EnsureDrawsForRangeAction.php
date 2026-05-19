<?php

declare(strict_types=1);

namespace App\Actions\Draws;

use App\Models\Draw;
use App\Models\Game;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Idempotently creates `Draw` rows for every active game's slot in the
 * given Manila date range [from, to] (inclusive). Past, present, and
 * future ranges are all valid — for historical ranges this seeds the
 * rows that `BackfillDrawResultsAction` then attaches `DrawResult` to.
 *
 * Schedule + cutoff are read from config('lotto.draw_schedule') and
 * config('lotto.cutoff_minutes') so this stays in lockstep with the
 * existing `lotto:generate-upcoming-draws` cron path.
 *
 * Idempotency: `firstOrCreate` keyed on the `draws_game_id_draw_at_unique`
 * index — re-runs across the same range are no-ops. Never mutates existing
 * rows: if a Draw already exists, its status / cutoff_at stay as-is.
 */
final class EnsureDrawsForRangeAction
{
    /**
     * @return array{created:int, days:int, games:int, slots:int}
     */
    public function execute(CarbonInterface $from, CarbonInterface $to): array
    {
        $fromDay = CarbonImmutable::instance($from)
            ->setTimezone('Asia/Manila')
            ->startOfDay();
        $toDay = CarbonImmutable::instance($to)
            ->setTimezone('Asia/Manila')
            ->startOfDay();

        if ($toDay->lessThan($fromDay)) {
            return ['created' => 0, 'days' => 0, 'games' => 0, 'slots' => 0];
        }

        $cutoffMinutes = (int) config('lotto.cutoff_minutes', 60);
        /** @var array<string, list<string>> $scheduleConfig */
        $scheduleConfig = (array) config('lotto.draw_schedule', []);
        /** @var list<string> $defaultSlots */
        $defaultSlots = (array) ($scheduleConfig['default'] ?? []);

        if ($defaultSlots === []) {
            return ['created' => 0, 'days' => 0, 'games' => 0, 'slots' => 0];
        }

        $appTz = (string) config('app.timezone', 'UTC');

        /** @var Collection<int, Game> $games */
        $games = Game::query()->where('active', true)->orderBy('id')->get();

        $created = 0;
        $slotsConsidered = 0;
        $days = (int) $fromDay->diffInDays($toDay) + 1;

        foreach ($games as $game) {
            /** @var list<string> $slots */
            $slots = (array) ($scheduleConfig[$game->code] ?? $defaultSlots);

            for ($d = 0; $d < $days; $d++) {
                $day = $fromDay->addDays($d);

                foreach ($slots as $slot) {
                    [$h, $m] = array_pad(
                        array_map('intval', explode(':', $slot)),
                        2,
                        0,
                    );

                    $drawAt = $day->setTime($h, $m)->setTimezone($appTz);
                    $cutoffAt = $drawAt->subMinutes($cutoffMinutes);

                    $row = Draw::query()->firstOrCreate(
                        [
                            'game_id' => $game->id,
                            'draw_at' => $drawAt,
                        ],
                        [
                            'cutoff_at' => $cutoffAt,
                            'status' => 'scheduled',
                        ],
                    );

                    $slotsConsidered++;
                    if ($row->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }
        }

        return [
            'created' => $created,
            'days' => $days,
            'games' => $games->count(),
            'slots' => $slotsConsidered,
        ];
    }
}
