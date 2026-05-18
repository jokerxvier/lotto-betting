<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use Carbon\CarbonInterface;

/**
 * Driver that picks PCSO results from the `pcso-parser` API's JSON
 * response. Doesn't HTTP-fetch HTML — `PcsoResultScraper` fetches via
 * `PcsoParserApiClient` (cached per Manila date) and hands the row
 * list to `pickFromRows()` here.
 *
 * Lookup is by **stable `_id` reconstruction**, not fuzzy label match.
 * Format from the upstream API:
 *
 *   2D 14:00 Manila 2026-05-18 → _id = "2026-05-18-2D-200PM"
 *   2D 17:00 Manila 2026-05-18 → _id = "2026-05-18-2D-500PM"
 *   2D 21:00 Manila 2026-05-18 → _id = "2026-05-18-2D-900PM"
 *   3D 14:00 Manila 2026-05-18 → _id = "2026-05-18-3D-200PM"
 *
 * `parse()` is a no-op (returns null) since this driver doesn't see
 * raw HTML. The scraper routes through `fetchViaPcsoApi()` instead.
 */
final class PcsoParserApiDriver implements ScraperDriver
{
    public function urlFor(string $gameCode, CarbonInterface $drawAt): string
    {
        unset($gameCode, $drawAt);

        // Diagnostic only — `PcsoResultScraper` builds the real request via
        // `PcsoParserApiClient::fetchFor($date)`, with the host pulled from
        // `lotto.scraper.api_url`. This stub satisfies the interface and
        // gives admin UI / audit logs a stable path label.
        return '/fetch';
    }

    public function parse(string $body, string $gameCode, CarbonInterface $drawAt): ?array
    {
        unset($body, $gameCode, $drawAt);

        // No HTML path — see pickFromRows() instead.
        return null;
    }

    public function label(): string
    {
        return 'pcso-parser-api';
    }

    /**
     * Find the row matching the requested game + slot inside a sidecar
     * JSON payload. Returns the winning numbers as list<int>.
     *
     * @param  list<array{_id:string, game:string, date:string, winning:string, prize?:string, winners?:string, time?:string}>  $rows
     * @return list<int>|null
     */
    public function pickFromRows(array $rows, string $gameCode, CarbonInterface $drawAt): ?array
    {
        $expectedId = $this->expectedIdFor($gameCode, $drawAt);
        if ($expectedId === null) {
            return null;
        }
        [$expectedCount, $id] = $expectedId;

        foreach ($rows as $row) {
            if ($row['_id'] !== $id) {
                continue;
            }

            return $this->parseWinning($row['winning'], $expectedCount);
        }

        return null;
    }

    /**
     * Build the expected upstream `_id` for the given game + draw_at.
     * Returns null on unsupported game or off-schedule hour.
     *
     * @return array{int, string}|null [expected_count, _id]
     */
    private function expectedIdFor(string $gameCode, CarbonInterface $drawAt): ?array
    {
        $game = strtolower($gameCode);
        [$expected, $apiCode] = match ($game) {
            '2d' => [2, '2D'],
            '3d' => [3, '3D'],
            default => [null, null],
        };
        if ($expected === null || $apiCode === null) {
            return null;
        }

        $manila = $drawAt->copy()->setTimezone('Asia/Manila');
        $slot = match ($manila->hour) {
            14 => '200PM',
            17 => '500PM',
            21 => '900PM',
            default => null,
        };
        if ($slot === null) {
            return null;
        }

        return [$expected, sprintf('%s-%s-%s', $manila->format('Y-m-d'), $apiCode, $slot)];
    }

    /**
     * Split the API's `winning` string (e.g. "10-28" or "5-1-9") into
     * a list of ints, validating the count matches the expected game
     * pick count.
     *
     * @return list<int>|null
     */
    private function parseWinning(string $winning, int $expectedCount): ?array
    {
        $parts = preg_split('/\s*-\s*/', trim($winning)) ?: [];
        if (count($parts) !== $expectedCount) {
            return null;
        }

        /** @var list<int> $numbers */
        $numbers = [];
        foreach ($parts as $p) {
            if (! is_numeric($p)) {
                return null;
            }
            $numbers[] = (int) $p;
        }

        return $numbers;
    }
}
