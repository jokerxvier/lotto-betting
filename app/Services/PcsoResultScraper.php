<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Scrapers\LottopcsoDriver;
use App\Services\Scrapers\ScraperDriver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
                        throw new \RuntimeException(
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

    public function sourceLabel(): string
    {
        return (string) config(
            'lotto.scraper.source_label',
            $this->resolveDriver()?->label() ?? 'unknown',
        );
    }

    private function resolveDriver(): ?ScraperDriver
    {
        return match ((string) config('lotto.scraper.source', 'lottopcso')) {
            'lottopcso' => new LottopcsoDriver,
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
        ]);
    }
}
