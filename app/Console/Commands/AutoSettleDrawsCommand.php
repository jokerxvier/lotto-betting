<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Settlement\ScrapeAndSettleAwaitingAction;
use App\Services\SettingsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Auto-publish + settle awaiting draws using the PCSO scraper. Runs every
 * 5 minutes (see routes/console.php). No admin in the loop.
 *
 * Gated by `SettingsService::get('scraper.auto_publish_enabled')`:
 *   - false (default): command no-ops immediately
 *   - true: delegates to ScrapeAndSettleAwaitingAction, which does the
 *     scrape, range-validate, write DrawResult, and run SettleDrawAction
 *     inside one transaction per draw.
 *
 * `--force` bypasses the toggle (ops debugging only). `--draw=N` scopes
 * the run to a single draw id. The same Action also backs the admin
 * "Scrape PCSO results" button on /admin/draws (force=true by default —
 * admin click = explicit consent).
 */
#[Signature('draws:auto-settle
                            {--force : Bypass the scraper.auto_publish_enabled toggle (ops only)}
                            {--draw= : Settle a single draw by ID}')]
#[Description('Auto-publish PCSO results + settle awaiting draws via the scraper.')]
final class AutoSettleDrawsCommand extends Command
{
    public function handle(
        SettingsService $settings,
        ScrapeAndSettleAwaitingAction $action,
    ): int {
        $force = (bool) $this->option('force');

        if (! $force && $settings->get('scraper.auto_publish_enabled', false) !== true) {
            $this->info('Auto-publish is disabled — skipping (no-op).');

            return self::SUCCESS;
        }

        $drawId = $this->option('draw') !== null
            ? (int) $this->option('draw')
            : null;

        $summary = $action->execute(onlyDrawId: $drawId);

        if ($summary['settled_count'] === 0 && $summary['skipped_count'] === 0) {
            $this->info('No awaiting draws.');

            return self::SUCCESS;
        }

        foreach ($summary['lines'] as $line) {
            $this->info($line);
        }

        $this->info(sprintf(
            'Done — %d settled, %d skipped.',
            $summary['settled_count'],
            $summary['skipped_count'],
        ));

        return self::SUCCESS;
    }
}
