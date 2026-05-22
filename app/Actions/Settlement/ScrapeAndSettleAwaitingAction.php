<?php

declare(strict_types=1);

namespace App\Actions\Settlement;

use App\Exceptions\DrawAlreadySettledException;
use App\Exceptions\DrawNotReadyException;
use App\Models\Draw;
use App\Models\DrawResult;
use App\Services\PcsoResultScraper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drives the scrape-and-settle loop for awaiting draws. Used by the cron
 * (`draws:auto-settle`) and the admin "Scrape PCSO results" button on
 * `/admin/draws`. Both paths share the same logging + idempotency +
 * range-validation guarantees.
 *
 *  - Numbers from the scraper are range-validated against the game's
 *    `number_min`/`number_max` + `picks_count` (same constraints the manual
 *    admin form enforces). A scraper returning bad numbers can't write a
 *    bogus DrawResult.
 *  - SettleDrawAction's idempotency means a duplicate call can't
 *    double-credit.
 *  - Every settle and every skip writes an `audit` log line.
 *
 * Two execution paths inside the loop:
 *  - Path A — no result attached: scrape PCSO, range-validate numbers,
 *    write DrawResult, run SettleDrawAction.
 *  - Path B — result already attached (the slot-aligned
 *    `draws:backfill-results` cron writes results without settling):
 *    re-validate the persisted numbers and run SettleDrawAction only.
 *
 * Returns a summary the caller can render to the user (CLI prints lines;
 * controller flashes a count). Skipped draws keep their original
 * unsettled state so the next tick / click can retry.
 */
final class ScrapeAndSettleAwaitingAction
{
    public function __construct(
        private readonly PcsoResultScraper $scraper,
        private readonly SettleDrawAction $settle,
    ) {}

    /**
     * Scrape + settle every awaiting draw matching the optional filter.
     *
     * @param  int|null  $onlyDrawId  if set, scope to a single draw id
     * @return array{settled_count: int, skipped_count: int, total_payout: string, lines: list<string>}
     */
    public function execute(?int $onlyDrawId = null): array
    {
        $query = Draw::query()
            ->where('status', '!=', 'settled')
            ->where('draw_at', '<=', now())
            ->with(['game', 'result']);

        if ($onlyDrawId !== null) {
            $query->where('id', $onlyDrawId);
        }

        $awaiting = $query->orderBy('draw_at')->get();

        $settledCount = 0;
        $skippedCount = 0;
        $totalPayoutCents = 0;
        /** @var list<string> $lines */
        $lines = [];

        foreach ($awaiting as $draw) {
            if ($draw->result !== null) {
                $this->settleAttachedResult(
                    $draw,
                    $lines,
                    $settledCount,
                    $skippedCount,
                    $totalPayoutCents,
                );

                continue;
            }

            $this->scrapeAndSettle(
                $draw,
                $lines,
                $settledCount,
                $skippedCount,
                $totalPayoutCents,
            );
        }

        return [
            'settled_count' => $settledCount,
            'skipped_count' => $skippedCount,
            'total_payout' => number_format($totalPayoutCents / 100, 2, '.', ''),
            'lines' => $lines,
        ];
    }

    /**
     * Path A: no `DrawResult` yet. Scrape PCSO, validate, write the
     * result row, settle bets — all inside one transaction so a partial
     * failure doesn't leave a result without settled bets.
     *
     * @param  list<string>  $lines
     */
    private function scrapeAndSettle(
        Draw $draw,
        array &$lines,
        int &$settledCount,
        int &$skippedCount,
        int &$totalPayoutCents,
    ): void {
        $numbers = $this->scraper->fetchLatest($draw->game->code, $draw->draw_at);

        if ($numbers === null) {
            $this->logSkip($draw, 'scraper_returned_null');
            $skippedCount++;

            return;
        }

        if (! $this->numbersAreValid($numbers, $draw)) {
            $this->logSkip($draw, 'numbers_out_of_range_or_wrong_count');
            $skippedCount++;

            return;
        }

        try {
            $result = DB::transaction(
                function () use ($draw, $numbers) {
                    DrawResult::query()->create([
                        'draw_id' => $draw->id,
                        'numbers' => $numbers,
                        'published_at' => now(),
                    ]);

                    return $this->settle->execute($draw->fresh());
                },
                attempts: 3,
            );

            $this->recordSettled(
                $result,
                $draw,
                $numbers,
                $lines,
                $settledCount,
                $totalPayoutCents,
            );
        } catch (DrawAlreadySettledException) {
            $this->logSkip($draw, 'already_settled');
            $skippedCount++;
        } catch (DrawNotReadyException $e) {
            $this->logSkip($draw, 'unexpected_not_ready:'.$e->getMessage());
            $skippedCount++;
        } catch (Throwable $e) {
            $this->logFailure($draw, $e, $lines);
            $skippedCount++;
        }
    }

    /**
     * Path B: a `DrawResult` is already attached (typically the
     * slot-aligned `draws:backfill-results` cron wrote it earlier).
     * Re-validate the persisted numbers — a corrupt or out-of-range
     * result must not slip through to bet settlement — then run
     * `SettleDrawAction` only. No scraper call.
     *
     * @param  list<string>  $lines
     */
    private function settleAttachedResult(
        Draw $draw,
        array &$lines,
        int &$settledCount,
        int &$skippedCount,
        int &$totalPayoutCents,
    ): void {
        /** @var list<int> $numbers */
        $numbers = $draw->result->numbers;

        if (! $this->numbersAreValid($numbers, $draw)) {
            $this->logSkip($draw, 'attached_numbers_out_of_range_or_wrong_count');
            $skippedCount++;

            return;
        }

        try {
            $result = DB::transaction(
                fn () => $this->settle->execute($draw->fresh()),
                attempts: 3,
            );

            $this->recordSettled(
                $result,
                $draw,
                $numbers,
                $lines,
                $settledCount,
                $totalPayoutCents,
            );
        } catch (DrawAlreadySettledException) {
            $this->logSkip($draw, 'already_settled');
            $skippedCount++;
        } catch (DrawNotReadyException $e) {
            $this->logSkip($draw, 'unexpected_not_ready:'.$e->getMessage());
            $skippedCount++;
        } catch (Throwable $e) {
            $this->logFailure($draw, $e, $lines);
            $skippedCount++;
        }
    }

    /**
     * @param  list<int>  $numbers
     * @param  list<string>  $lines
     */
    private function recordSettled(
        SettleResult $result,
        Draw $draw,
        array $numbers,
        array &$lines,
        int &$settledCount,
        int &$totalPayoutCents,
    ): void {
        Log::channel('audit')->info('draw.auto_settled', [
            'draw_id' => $result->drawId,
            'game_id' => $draw->game_id,
            'numbers' => $numbers,
            'settled_count' => $result->settledCount,
            'won_count' => $result->wonCount,
            'total_payout' => $result->totalPayout,
        ]);

        $lines[] = sprintf(
            'Draw #%d (%s) settled — %d bet(s), %d winner(s), ₱%s paid out.',
            $result->drawId,
            $draw->game->code,
            $result->settledCount,
            $result->wonCount,
            $result->totalPayout,
        );

        $settledCount++;
        $totalPayoutCents += (int) round(((float) $result->totalPayout) * 100);
    }

    /**
     * @param  list<string>  $lines
     */
    private function logFailure(Draw $draw, Throwable $e, array &$lines): void
    {
        Log::channel('audit')->warning('draw.auto_settle.failure', [
            'draw_id' => $draw->id,
            'reason' => $e->getMessage(),
        ]);
        $lines[] = "Draw #{$draw->id} failed: {$e->getMessage()}";
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

    private function logSkip(Draw $draw, string $reason): void
    {
        Log::channel('audit')->info('draw.auto_settle.skipped', [
            'draw_id' => $draw->id,
            'game_id' => $draw->game_id,
            'reason' => $reason,
        ]);
    }
}
