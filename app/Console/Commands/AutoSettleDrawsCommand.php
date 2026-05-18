<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Settlement\SettleDrawAction;
use App\Exceptions\DrawAlreadySettledException;
use App\Exceptions\DrawNotReadyException;
use App\Models\Draw;
use App\Models\DrawResult;
use App\Services\PcsoResultScraper;
use App\Services\SettingsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Auto-publish + settle awaiting draws using the PCSO scraper. Runs every
 * 5 minutes (see routes/console.php). No admin in the loop.
 *
 * Gated by `SettingsService::get('scraper.auto_publish_enabled')`:
 *   - false (default): command no-ops immediately
 *   - true: for each awaiting draw, ask the scraper for numbers, validate
 *     against the game's range, then write DrawResult + run SettleDrawAction
 *     inside one transaction
 *
 * `--force` bypasses the toggle (ops debugging only — admin can invoke a
 * one-shot manual run when verifying the wiring without flipping production
 * state). `--draw=N` settles a single specific awaiting draw.
 *
 * Safety:
 *  - Numbers from the scraper are range-validated against the game's
 *    `number_min`/`number_max` + `picks_count` (same constraints the manual
 *    admin form enforces). A scraper returning bad numbers can't write a
 *    bogus DrawResult.
 *  - SettleDrawAction's idempotency means a duplicate cron tick can't
 *    double-credit.
 *  - Every auto-settle and every refusal writes an `audit` log line.
 */
#[Signature('draws:auto-settle
                            {--force : Bypass the scraper.auto_publish_enabled toggle (ops only)}
                            {--draw= : Settle a single draw by ID}')]
#[Description('Auto-publish PCSO results + settle awaiting draws via the scraper.')]
final class AutoSettleDrawsCommand extends Command
{
    public function handle(
        SettingsService $settings,
        PcsoResultScraper $scraper,
        SettleDrawAction $settle,
    ): int {
        $force = (bool) $this->option('force');

        if (! $force && $settings->get('scraper.auto_publish_enabled', false) !== true) {
            $this->info('Auto-publish is disabled — skipping (no-op).');

            return self::SUCCESS;
        }

        $query = Draw::query()
            ->where('status', '!=', 'settled')
            ->where('draw_at', '<=', now())
            ->whereDoesntHave('result')
            ->with('game');

        if ($drawId = $this->option('draw')) {
            $query->where('id', (int) $drawId);
        }

        /** @var iterable<Draw> $awaiting */
        $awaiting = $query->orderBy('draw_at')->get();

        if ($awaiting->isEmpty()) {
            $this->info('No awaiting draws.');

            return self::SUCCESS;
        }

        $settledCount = 0;
        $skippedCount = 0;

        foreach ($awaiting as $draw) {
            $numbers = $scraper->fetchLatest($draw->game->code, $draw->draw_at);

            if ($numbers === null) {
                $this->logSkip($draw, 'scraper_returned_null');
                $skippedCount++;

                continue;
            }

            if (! $this->numbersAreValid($numbers, $draw)) {
                $this->logSkip($draw, 'numbers_out_of_range_or_wrong_count');
                $skippedCount++;

                continue;
            }

            try {
                $result = DB::transaction(
                    function () use ($draw, $numbers, $settle) {
                        DrawResult::query()->create([
                            'draw_id' => $draw->id,
                            'numbers' => $numbers,
                            'published_at' => now(),
                        ]);

                        return $settle->execute($draw->fresh());
                    },
                    attempts: 3,
                );

                Log::channel('audit')->info('draw.auto_settled', [
                    'draw_id' => $result->drawId,
                    'game_id' => $draw->game_id,
                    'numbers' => $numbers,
                    'settled_count' => $result->settledCount,
                    'won_count' => $result->wonCount,
                    'total_payout' => $result->totalPayout,
                ]);

                $this->info(sprintf(
                    'Draw #%d (%s) settled — %d bet(s), %d winner(s), ₱%s paid out.',
                    $result->drawId,
                    $draw->game->code,
                    $result->settledCount,
                    $result->wonCount,
                    $result->totalPayout,
                ));

                $settledCount++;
            } catch (DrawAlreadySettledException) {
                // Race: another worker beat us to it. Safe; just skip.
                $this->logSkip($draw, 'already_settled');
                $skippedCount++;
            } catch (DrawNotReadyException $e) {
                // Impossible — we just wrote the result. Log + skip.
                $this->logSkip($draw, 'unexpected_not_ready:'.$e->getMessage());
                $skippedCount++;
            } catch (Throwable $e) {
                Log::channel('audit')->warning('draw.auto_settle.failure', [
                    'draw_id' => $draw->id,
                    'reason' => $e->getMessage(),
                ]);
                $this->warn("Draw #{$draw->id} auto-settle failed: {$e->getMessage()}");
                $skippedCount++;
            }
        }

        $this->info("Done — {$settledCount} settled, {$skippedCount} skipped.");

        return self::SUCCESS;
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
