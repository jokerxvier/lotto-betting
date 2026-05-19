<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Scrapers\GmaNetworkDriver;
use App\Services\Scrapers\LottopcsoDriver;
use App\Services\Scrapers\PcsoGovDriver;
use App\Services\Scrapers\PcsoParserApiClient;
use App\Services\Scrapers\PcsoParserApiDriver;
use App\Services\Scrapers\PlaywrightSidecarClient;
use App\Services\Scrapers\ScraperDriver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Best-effort PCSO result scraper. Pre-fills the admin publish form so an
 * admin's job goes from "read the site, type 2 numbers, tap, tap" to
 * "eyeball, tap, tap". A broken or missing scrape is always safe: returns
 * null, admin sees the same empty form as Option A.
 *
 * Guarantees:
 *  - Never throws. Every failure path → null + an `audit` log line.
 *  - Caches the upstream response by URL for config('lotto.scraper.cache_ttl_seconds').
 *  - Honors the SettingsService runtime toggle (`scraper.suggestions_enabled`).
 *  - HTTP timeout capped via config('lotto.scraper.http_timeout_seconds').
 *
 * Driver-pattern: source-specific HTML parsing lives in App\Services\Scrapers\*.
 * Add a new driver to swap sources without touching this class.
 *
 * Three fetcher modes (set via LOTTO_SCRAPER_FETCHER):
 *  - 'http' (default)       — plain Http::get; works for any non-WAF source.
 *  - 'playwright'           — pcso.gov.ph via the standalone scraper/ sidecar
 *                             (Node + Playwright daemon on 127.0.0.1). Only
 *                             compatible with the `pcso_gov` source driver.
 *  - 'pcso_api'             — the standalone pcso-parser REST API (Node + Chromium
 *                             daemon, typically 127.0.0.1:3001). Only compatible
 *                             with the `pcso_api` source driver. Lookup is by
 *                             stable `_id` (YYYY-MM-DD-<game>-<HHMMAM/PM>).
 */
final class PcsoResultScraper
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return list<int>|null
     */
    public function fetchLatest(string $gameCode, CarbonInterface $drawAt): ?array
    {
        if ($this->settings->get('scraper.suggestions_enabled', true) !== true) {
            return null;
        }

        $driver = $this->resolveDriver();
        if ($driver === null) {
            $this->logFailure('unknown_source', $gameCode, $drawAt);

            return null;
        }

        return match ($this->fetcherKind()) {
            'playwright' => $this->fetchViaPlaywright($driver, $gameCode, $drawAt),
            'pcso_api' => $this->fetchViaPcsoApi($driver, $gameCode, $drawAt),
            default => $this->fetchViaHttp($driver, $gameCode, $drawAt),
        };
    }

    public function sourceLabel(): string
    {
        return (string) config(
            'lotto.scraper.source_label',
            $this->resolveDriver()?->label() ?? 'unknown',
        );
    }

    /**
     * Pre-warm the per-date cache for every Manila day in [from, to] using
     * the pcso-parser API's range endpoint. Safe to call regardless of
     * fetcher mode — no-op outside `pcso_api`. Safe on any failure —
     * audit-logs and returns, falling back to per-day fetches inside the
     * caller's iteration.
     *
     * Purpose: lets `BackfillDrawResultsAction` collapse N×daily roundtrips
     * into a single upstream request, without changing the per-draw lookup
     * path (which still reads `lotto.scraper:pcso_api_rows:{$isoDate}`).
     */
    public function primeCacheForRange(CarbonInterface $from, CarbonInterface $to): void
    {
        if ($this->settings->get('scraper.suggestions_enabled', true) !== true) {
            return;
        }
        if ($this->fetcherKind() !== 'pcso_api') {
            return;
        }

        $fromIso = $from->copy()->setTimezone('Asia/Manila')->format('Y-m-d');
        $toIso = $to->copy()->setTimezone('Asia/Manila')->format('Y-m-d');
        $ttl = (int) config('lotto.scraper.cache_ttl_seconds', 60);

        try {
            $byDate = $this->pcsoApiClient()->fetchForRange($fromIso, $toIso);
        } catch (Throwable $e) {
            $this->logFailure($e->getMessage(), 'range', $from);

            return;
        }

        foreach ($byDate as $isoDate => $rows) {
            Cache::put("lotto.scraper:pcso_api_rows:{$isoDate}", $rows, $ttl);
        }
    }

    /**
     * @return list<int>|null
     */
    private function fetchViaHttp(ScraperDriver $driver, string $gameCode, CarbonInterface $drawAt): ?array
    {
        $url = $driver->urlFor($gameCode, $drawAt);
        $ttl = (int) config('lotto.scraper.cache_ttl_seconds', 60);
        $timeout = (int) config('lotto.scraper.http_timeout_seconds', 8);

        try {
            /** @var string $body */
            $body = Cache::remember(
                'lotto.scraper:'.md5($url),
                $ttl,
                function () use ($url, $timeout): string {
                    $response = Http::timeout($timeout)
                        ->withHeaders(['User-Agent' => 'LottoPH/1.0 (+admin scraper)'])
                        ->get($url);
                    if ($response->failed()) {
                        // Bail out of the cache write — throw so Cache::remember
                        // doesn't memoize an empty body and we surface the
                        // failure to the catch below.
                        throw new RuntimeException(
                            "upstream_status_{$response->status()}",
                        );
                    }

                    return (string) $response->body();
                },
            );
        } catch (Throwable $e) {
            $this->logFailure($e->getMessage(), $gameCode, $drawAt, $url);

            return null;
        }

        $numbers = $driver->parse($body, $gameCode, $drawAt);
        if ($numbers === null) {
            $this->logFailure('no_match_or_malformed', $gameCode, $drawAt, $url);

            return null;
        }

        return $numbers;
    }

    /**
     * @return list<int>|null
     */
    private function fetchViaPlaywright(ScraperDriver $driver, string $gameCode, CarbonInterface $drawAt): ?array
    {
        if (! $driver instanceof PcsoGovDriver) {
            // The sidecar's JSON shape is specific to pcso.gov.ph. Any other
            // driver would have to gain its own pickFromRows() before we'd
            // know how to route. Loud failure is better than silent confusion.
            $this->logFailure('playwright_requires_pcso_gov', $gameCode, $drawAt);

            return null;
        }

        try {
            $rows = $this->cachedSidecarRows();
        } catch (Throwable $e) {
            $this->logFailure($e->getMessage(), $gameCode, $drawAt);

            return null;
        }

        $numbers = $driver->pickFromRows($rows, $gameCode, $drawAt);
        if ($numbers === null) {
            $this->logFailure('no_match_in_sidecar_rows', $gameCode, $drawAt);

            return null;
        }

        return $numbers;
    }

    /**
     * @return list<array{game:string, date:string, numbers:list<int>}>
     */
    private function cachedSidecarRows(): array
    {
        $ttl = (int) config('lotto.scraper.cache_ttl_seconds', 60);

        /** @var list<array{game:string, date:string, numbers:list<int>}> $rows */
        $rows = Cache::remember(
            'lotto.scraper:sidecar_rows',
            $ttl,
            fn (): array => $this->sidecarClient()->fetchAll(),
        );

        return $rows;
    }

    private function sidecarClient(): PlaywrightSidecarClient
    {
        return new PlaywrightSidecarClient(
            baseUrl: (string) config('lotto.scraper.sidecar_url', 'http://127.0.0.1:8787'),
            token: (string) config('lotto.scraper.sidecar_token', ''),
            timeoutSeconds: (int) config('lotto.scraper.sidecar_timeout_seconds', 30),
        );
    }

    /**
     * @return list<int>|null
     */
    private function fetchViaPcsoApi(ScraperDriver $driver, string $gameCode, CarbonInterface $drawAt): ?array
    {
        if (! $driver instanceof PcsoParserApiDriver) {
            // The pcso_api fetcher consumes the API's JSON shape via the
            // driver's pickFromRows(). Any other driver wouldn't know how
            // to route. Loud failure beats silent confusion.
            $this->logFailure('pcso_api_requires_pcso_api_driver', $gameCode, $drawAt);

            return null;
        }

        $manila = $drawAt->copy()->setTimezone('Asia/Manila');
        $isoDate = $manila->format('Y-m-d');

        try {
            $rows = $this->cachedPcsoApiRows($isoDate);
        } catch (Throwable $e) {
            $this->logFailure($e->getMessage(), $gameCode, $drawAt);

            return null;
        }

        $numbers = $driver->pickFromRows($rows, $gameCode, $drawAt);
        if ($numbers === null) {
            $this->logFailure('no_match_in_pcso_api_rows', $gameCode, $drawAt);

            return null;
        }

        return $numbers;
    }

    /**
     * @return list<array{_id:string, game:string, date:string, winning:string, prize?:string, winners?:string, time?:string}>
     */
    private function cachedPcsoApiRows(string $isoDate): array
    {
        $ttl = (int) config('lotto.scraper.cache_ttl_seconds', 60);

        /** @var list<array{_id:string, game:string, date:string, winning:string, prize?:string, winners?:string, time?:string}> $rows */
        $rows = Cache::remember(
            "lotto.scraper:pcso_api_rows:{$isoDate}",
            $ttl,
            fn (): array => $this->pcsoApiClient()->fetchFor($isoDate),
        );

        return $rows;
    }

    private function pcsoApiClient(): PcsoParserApiClient
    {
        return new PcsoParserApiClient(
            baseUrl: (string) config('lotto.scraper.api_url', 'http://127.0.0.1:3001'),
            timeoutSeconds: (int) config('lotto.scraper.api_timeout_seconds', 60),
        );
    }

    private function fetcherKind(): string
    {
        $kind = (string) config('lotto.scraper.fetcher', 'http');

        return in_array($kind, ['http', 'playwright', 'pcso_api'], true) ? $kind : 'http';
    }

    private function resolveDriver(): ?ScraperDriver
    {
        return match ((string) config('lotto.scraper.source', 'lottopcso')) {
            'lottopcso' => new LottopcsoDriver,
            'pcso_gov' => new PcsoGovDriver,
            'gma' => new GmaNetworkDriver,
            'pcso_api' => new PcsoParserApiDriver,
            default => null,
        };
    }

    private function logFailure(
        string $reason,
        string $gameCode,
        CarbonInterface $drawAt,
        ?string $url = null,
    ): void {
        Log::channel('audit')->info('scraper.failure', [
            'reason' => $reason,
            'game' => $gameCode,
            'slot' => $drawAt->toIso8601String(),
            'url' => $url,
            'fetcher' => $this->fetcherKind(),
        ]);
    }
}
