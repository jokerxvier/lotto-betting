<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Draws\EnsureDrawsForRangeAction;
use App\Actions\Settlement\BackfillDrawResultsAction;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Backfill historical PCSO results into the `draw_results` table without
 * settling anything. Sibling to `draws:auto-settle` (which scrapes +
 * settles awaiting draws). Useful for:
 *
 *  - Initial seed when adopting a new scraper source.
 *  - Catch-up after the cron was down.
 *  - Correcting wrong numbers on a draw that was never settled.
 *  - Reconciliation against PCSO after a manual investigation.
 *
 * Default range: last 7 days (Manila), today inclusive. Override with
 * --from/--to (mutually exclusive with --days). Pass --dry-run to print
 * the table without writing.
 *
 * DOES NOT settle bets — credits no wallets, mutates no Bet.status,
 * mutates no Draw.status. For settlement, use `draws:auto-settle` or
 * the admin "Scrape PCSO results" button.
 *
 * Refuses to overwrite numbers on draws that are already `status=settled`
 * (would silently desync paid bets from the new truth — operator must
 * reverse the ledger manually before re-running).
 */
#[Signature('draws:backfill-results
                            {--from= : Start of the range (YYYY-MM-DD, Manila). Defaults to 7 days ago.}
                            {--to= : End of the range (YYYY-MM-DD, Manila). Defaults to today.}
                            {--days=7 : Backfill the last N days (mutually exclusive with --from/--to).}
                            {--games= : Comma-separated list of game codes to backfill (e.g. 2d,3d). Defaults to all.}
                            {--no-ensure : Skip the EnsureDrawsForRangeAction pre-step (reconciliation flows only).}
                            {--dry-run : Parse + report, do not write to the database.}')]
#[Description('Backfill historical PCSO results into draw_results — does NOT settle bets.')]
final class BackfillDrawResultsCommand extends Command
{
    public function handle(
        EnsureDrawsForRangeAction $ensure,
        BackfillDrawResultsAction $action,
    ): int {
        try {
            [$from, $to] = $this->resolveRange();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $gameCodes = $this->resolveGames();
        $dryRun = (bool) $this->option('dry-run');
        $skipEnsure = (bool) $this->option('no-ensure');

        $this->info(sprintf(
            'Backfilling draws %s to %s (Manila)%s%s',
            $from->toDateString(),
            $to->toDateString(),
            $gameCodes !== null ? ' for games '.implode(',', $gameCodes) : '',
            $dryRun ? ' [DRY RUN — no DB writes]' : '',
        ));

        if (! $skipEnsure && ! $dryRun) {
            $ensureSummary = $ensure->execute($from, $to);
            if ($ensureSummary['created'] > 0) {
                $this->info(sprintf(
                    'Seeded %d missing draw slot(s) before backfill.',
                    $ensureSummary['created'],
                ));
            }
        }

        $summary = $action->execute($from, $to, $gameCodes, $dryRun);

        $rows = array_map(static function (array $row): array {
            $manila = Carbon::parse($row['draw_at'])->setTimezone('Asia/Manila');

            return [
                $manila->format('Y-m-d'),
                $row['game'],
                $manila->format('gA'),
                $row['status'],
                $row['numbers'] !== null ? implode('-', $row['numbers']) : '-',
                $row['prev_numbers'] !== null ? implode('-', $row['prev_numbers']) : '-',
            ];
        }, $summary['per_draw']);

        if ($rows !== []) {
            $this->table(['Date', 'Game', 'Slot', 'Status', 'Numbers', 'Was'], $rows);
        } else {
            $this->warn('No draws found in the requested range.');
        }

        $c = $summary['counts'];
        $this->info(sprintf(
            'Total: %d created, %d updated, %d unchanged, %d skipped_no_match, %d skipped_settled, %d skipped_invalid.',
            $c['created'], $c['updated'], $c['unchanged'],
            $c['skipped_no_match'], $c['skipped_settled'], $c['skipped_invalid'],
        ));

        $this->line('Reminder: this command does NOT settle bets. Use `draws:auto-settle` for settlement.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(): array
    {
        $rawFrom = $this->option('from');
        $rawTo = $this->option('to');
        $rawDays = $this->option('days');

        // --from / --to take precedence over --days when explicitly set.
        if ($rawFrom !== null || $rawTo !== null) {
            $to = $rawTo !== null
                ? $this->parseManilaDate($rawTo, '--to')->endOfDay()
                : Carbon::now('Asia/Manila')->endOfDay();
            $from = $rawFrom !== null
                ? $this->parseManilaDate($rawFrom, '--from')->startOfDay()
                : $to->copy()->subDays(7)->startOfDay();
        } else {
            $days = max(1, (int) ($rawDays ?? 7));
            $to = Carbon::now('Asia/Manila')->endOfDay();
            $from = $to->copy()->subDays($days)->startOfDay();
        }

        if ($from->greaterThan($to)) {
            throw new \InvalidArgumentException('--from must be on or before --to.');
        }
        if ($from->greaterThan(Carbon::now('Asia/Manila')->endOfDay())) {
            throw new \InvalidArgumentException('--from cannot be in the future.');
        }

        return [$from, $to];
    }

    private function parseManilaDate(string $raw, string $option): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $raw, 'Asia/Manila')
                ?: throw new InvalidFormatException("Invalid {$option}: {$raw}");
        } catch (InvalidFormatException $e) {
            throw new \InvalidArgumentException("Invalid {$option}: expected YYYY-MM-DD, got '{$raw}'.");
        }
    }

    /**
     * @return list<string>|null
     */
    private function resolveGames(): ?array
    {
        $raw = $this->option('games');
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $codes = array_filter(array_map(
            fn ($s) => trim(strtolower($s)),
            explode(',', $raw),
        ));

        return $codes !== [] ? array_values($codes) : null;
    }
}
