<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the standalone `pcso-parser` Node REST API. The API
 * runs as its own Forge daemon (typically bound to 127.0.0.1:3001),
 * scrapes lottopcso.com via headless Chromium, and returns parsed JSON.
 *
 * Contract: docs/PCSO_PARSER_API.md (or the upstream repo's README).
 * Stable row `_id` format: `YYYY-MM-DD-<game>[-<HHMMAM/PM>]` —
 * downstream lookup is by exact id, not fuzzy label match.
 *
 * Failure path: throws RuntimeException with a short reason code so
 * the caller (PcsoResultScraper) can audit-log + null out without
 * leaking detail. Never throws on missing/extra rows — an empty
 * `results` array is a valid response.
 */
final class PcsoParserApiClient
{
    private const RETRY_AFTER_SECONDS = 5;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds = 60,
    ) {}

    /**
     * Fetch every parsed row for the given Manila date.
     *
     * @return list<array{
     *   _id:string,
     *   game:string,
     *   date:string,
     *   winning:string,
     *   prize?:string,
     *   winners?:string,
     *   time?:string
     * }>
     */
    public function fetchFor(string $isoDate): array
    {
        $response = $this->postFetchWithRetry(['date' => $isoDate]);

        $rows = $response->json('results');
        if (! is_array($rows)) {
            throw new RuntimeException('pcso_api_malformed_response');
        }

        return $this->normalizeRows($rows);
    }

    /**
     * Fetch every parsed row for an inclusive Manila date range in a single
     * upstream call. Returns rows grouped by ISO date so callers can prime
     * per-date caches without re-grouping.
     *
     * Response shape (upstream): { from, to, totals, days: [{ date, results, ... }] }.
     * Unrecognized days are skipped. An empty `days` array is a valid
     * response (e.g. far-future range) and returns `[]`.
     *
     * @return array<string, list<array{
     *   _id:string,
     *   game:string,
     *   date:string,
     *   winning:string,
     *   prize?:string,
     *   winners?:string,
     *   time?:string,
     * }>>
     */
    public function fetchForRange(string $fromIsoDate, string $toIsoDate): array
    {
        $response = $this->postFetchWithRetry([
            'from' => $fromIsoDate,
            'to' => $toIsoDate,
        ]);

        $days = $response->json('days');
        if (! is_array($days)) {
            throw new RuntimeException('pcso_api_malformed_range_response');
        }

        /** @var array<string, list<array{_id:string, game:string, date:string, winning:string, prize?:string, winners?:string, time?:string}>> $byDate */
        $byDate = [];
        foreach ($days as $day) {
            if (! is_array($day)) {
                continue;
            }
            $date = $day['date'] ?? null;
            $rows = $day['results'] ?? null;
            if (! is_string($date) || ! is_array($rows)) {
                continue;
            }
            $byDate[$date] = $this->normalizeRows($rows);
        }

        return $byDate;
    }

    /**
     * POST /fetch with the API's one-retry-on-429 policy. The upstream
     * serializes Chromium calls; a 429 usually means another caller (cron
     * + manual button overlap) beat us by ~1s. Sleep, retry once, then
     * throw if still busy.
     *
     * @param  array<string, mixed>  $payload
     */
    private function postFetchWithRetry(array $payload): Response
    {
        $url = rtrim($this->baseUrl, '/').'/fetch';

        $response = Http::timeout($this->timeoutSeconds)->acceptJson()->asJson()->post($url, $payload);

        if ($response->status() === 429) {
            sleep(self::RETRY_AFTER_SECONDS);
            $response = Http::timeout($this->timeoutSeconds)->acceptJson()->asJson()->post($url, $payload);
        }

        if ($response->failed()) {
            throw new RuntimeException("pcso_api_upstream_{$response->status()}");
        }

        return $response;
    }

    /**
     * Validate + project an upstream `results[]` array into the strict
     * shape used by downstream lookups. Rows missing any of the four
     * required string fields are dropped silently.
     *
     * @param  array<int|string, mixed>  $rows
     * @return list<array{_id:string, game:string, date:string, winning:string, prize?:string, winners?:string, time?:string}>
     */
    private function normalizeRows(array $rows): array
    {
        /** @var list<array{_id:string, game:string, date:string, winning:string, prize?:string, winners?:string, time?:string}> $normalized */
        $normalized = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = $row['_id'] ?? null;
            $game = $row['game'] ?? null;
            $date = $row['date'] ?? null;
            $winning = $row['winning'] ?? null;
            if (! is_string($id) || ! is_string($game) || ! is_string($date) || ! is_string($winning)) {
                continue;
            }
            $entry = [
                '_id' => $id,
                'game' => $game,
                'date' => $date,
                'winning' => $winning,
            ];
            if (isset($row['prize']) && is_string($row['prize'])) {
                $entry['prize'] = $row['prize'];
            }
            if (isset($row['winners']) && is_string($row['winners'])) {
                $entry['winners'] = $row['winners'];
            }
            if (isset($row['time']) && is_string($row['time'])) {
                $entry['time'] = $row['time'];
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }
}
