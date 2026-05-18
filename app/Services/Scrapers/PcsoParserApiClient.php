<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

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
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->post(rtrim($this->baseUrl, '/').'/fetch', ['date' => $isoDate]);

        if ($response->status() === 429) {
            // One retry — the API serializes Chromium calls; a 429 here
            // usually means another caller (cron + manual button overlap)
            // beat us by ~1s. Sleep then try again, fall through to throw
            // if still busy.
            sleep(self::RETRY_AFTER_SECONDS);
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->asJson()
                ->post(rtrim($this->baseUrl, '/').'/fetch', ['date' => $isoDate]);
        }

        if ($response->failed()) {
            throw new RuntimeException("pcso_api_upstream_{$response->status()}");
        }

        $rows = $response->json('results');
        if (! is_array($rows)) {
            throw new RuntimeException('pcso_api_malformed_response');
        }

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
