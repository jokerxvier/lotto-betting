<?php

declare(strict_types=1);

namespace App\Actions\Settlement;

use App\Models\Draw;
use App\Models\DrawResult;
use App\Services\PcsoResultScraper;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Backfills historical `DrawResult` rows for past draws in a date range.
 * Sibling to `ScrapeAndSettleAwaitingAction`, but DELIBERATELY NEVER
 * settles bets:
 *  - never invokes SettleDrawAction
 *  - never mutates Draw.status
 *  - never touches Bet.*
 *  - never moves money
 *
 * Used by both `php artisan draws:backfill-results` and the admin
 * "Backfill last N days" button. Re-fetches via PcsoResultScraper
 * (whichever source/fetcher the operator has configured) and upserts
 * the DrawResult per draw:
 *   - missing                                → create
 *   - exists, numbers identical              → unchanged (no write)
 *   - exists, numbers differ, draw NOT settled → update
 *   - exists, numbers differ, draw IS settled  → SKIP (data integrity)
 *
 * Refusing to overwrite settled draws is non-negotiable: settled bets
 * are already paid based on the old numbers; changing them silently
 * would desync wallets from reality. Operator must reverse the ledger
 * manually before re-running.
 */
final class BackfillDrawResultsAction
{
    public function __construct(
        private readonly PcsoResultScraper $scraper,
    ) {}

    /**
     * @param  list<string>|null  $gameCodes  optional whitelist of game codes (e.g. ['2d','3d'])
     * @return array{
     *   from: string,
     *   to: string,
     *   counts: array{
     *     created:int, updated:int, unchanged:int,
     *     skipped_no_match:int, skipped_settled:int, skipped_invalid:int,
     *   },
     *   per_draw: list<array{
     *     draw_id:int, game:string, draw_at:string, status:string,
     *     numbers:list<int>|null, prev_numbers:list<int>|null,
     *   }>,
     * }
     */
    public function execute(
        CarbonInterface $from,
        CarbonInterface $to,
        ?array $gameCodes = null,
        bool $dryRun = false,
    ): array {
        $fromUtc = $from->copy()->setTimezone('Asia/Manila')->startOfDay()->setTimezone('UTC');
        $toUtc = $to->copy()->setTimezone('Asia/Manila')->endOfDay()->setTimezone('UTC');

        $query = Draw::query()
            ->with(['game', 'result'])
            ->whereBetween('draw_at', [$fromUtc, $toUtc])
            ->orderBy('draw_at');

        if ($gameCodes !== null && $gameCodes !== []) {
            $normalized = array_map(strtolower(...), $gameCodes);
            $query->whereHas('game', fn ($q) => $q->whereIn('code', $normalized));
        }

        $draws = $query->get();

        $counts = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped_no_match' => 0,
            'skipped_settled' => 0,
            'skipped_invalid' => 0,
        ];

        /** @var list<array{draw_id:int, game:string, draw_at:string, status:string, numbers:list<int>|null, prev_numbers:list<int>|null}> $perDraw */
        $perDraw = [];

        foreach ($draws as $draw) {
            $numbers = $this->scraper->fetchLatest($draw->game->code, $draw->draw_at);
            $prev = $draw->result?->numbers;

            if ($numbers === null) {
                $this->log('scraper_returned_null', $draw, null, $prev);
                $counts['skipped_no_match']++;
                $perDraw[] = $this->row($draw, 'skipped_no_match', null, $prev);

                continue;
            }

            if (! $this->numbersAreValid($numbers, $draw)) {
                $this->log('numbers_out_of_range_or_wrong_count', $draw, $numbers, $prev);
                $counts['skipped_invalid']++;
                $perDraw[] = $this->row($draw, 'skipped_invalid', $numbers, $prev);

                continue;
            }

            if ($draw->result === null) {
                if (! $dryRun) {
                    DrawResult::query()->create([
                        'draw_id' => $draw->id,
                        'numbers' => $numbers,
                        'published_at' => now(),
                    ]);
                }
                $this->log('created', $draw, $numbers, null);
                $counts['created']++;
                $perDraw[] = $this->row($draw, 'created', $numbers, null);

                continue;
            }

            if ($prev === $numbers) {
                $this->log('unchanged', $draw, $numbers, $prev);
                $counts['unchanged']++;
                $perDraw[] = $this->row($draw, 'unchanged', $numbers, $prev);

                continue;
            }

            if ($draw->status === 'settled') {
                $this->log('skipped_settled', $draw, $numbers, $prev, level: 'warning');
                $counts['skipped_settled']++;
                $perDraw[] = $this->row($draw, 'skipped_settled', $numbers, $prev);

                continue;
            }

            if (! $dryRun) {
                $draw->result->update([
                    'numbers' => $numbers,
                    'published_at' => now(),
                ]);
            }
            $this->log('updated', $draw, $numbers, $prev);
            $counts['updated']++;
            $perDraw[] = $this->row($draw, 'updated', $numbers, $prev);
        }

        return [
            'from' => $from->copy()->setTimezone('Asia/Manila')->toDateString(),
            'to' => $to->copy()->setTimezone('Asia/Manila')->toDateString(),
            'counts' => $counts,
            'per_draw' => $perDraw,
        ];
    }

    /**
     * @param  list<int>  $numbers
     */
    private function numbersAreValid(array $numbers, Draw $draw): bool
    {
        $game = $draw->game;
        if (count($numbers) !== $game->picks_count) {
            return false;
        }
        foreach ($numbers as $n) {
            if ($n < $game->number_min || $n > $game->number_max) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<int>|null  $numbers
     * @param  list<int>|null  $prev
     * @return array{draw_id:int, game:string, draw_at:string, status:string, numbers:list<int>|null, prev_numbers:list<int>|null}
     */
    private function row(Draw $draw, string $status, ?array $numbers, ?array $prev): array
    {
        return [
            'draw_id' => $draw->id,
            'game' => $draw->game->code,
            // Always serialize as UTC so the CLI / API consumer can re-convert
            // unambiguously. Carbon's default parse-tz can shift across test
            // setups (e.g. Carbon::setTestNow with a non-UTC time) — pinning
            // the offset removes the ambiguity.
            'draw_at' => $draw->draw_at->copy()->setTimezone('UTC')->toIso8601String(),
            'status' => $status,
            'numbers' => $numbers,
            'prev_numbers' => $prev,
        ];
    }

    /**
     * @param  list<int>|null  $numbers
     * @param  list<int>|null  $prev
     */
    private function log(string $event, Draw $draw, ?array $numbers, ?array $prev, string $level = 'info'): void
    {
        Log::channel('audit')->{$level}("draw.backfill.{$event}", [
            'draw_id' => $draw->id,
            'game_id' => $draw->game_id,
            'numbers' => $numbers,
            'prev_numbers' => $prev,
            'draw_status' => $draw->status,
        ]);
    }
}
