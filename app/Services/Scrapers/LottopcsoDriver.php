<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use Carbon\CarbonInterface;

/**
 * Driver for `lottopcso.com`.
 *
 * The site lists results in a flat table where each row has a recognizable
 * shape: `Date | Time | GameLabel | Numbers`. We parse with a deliberately
 * lenient regex per row so a markup change (CSS classes, wrapping divs)
 * doesn't immediately break us — only the column ORDER and DELIMITER style
 * are load-bearing. If the upstream changes drastically, parse() returns
 * null and admin sees the manual form.
 *
 * Game label expectations:
 *   2D → "EZ2"   (case-insensitive)
 *   3D → "SWERTRES" / "3D" / "SWERTRES LOTTO"
 *
 * Numbers expected as "NN - NN" (2D) or "NN - NN - NN" (3D), zero-padded.
 */
final class LottopcsoDriver implements ScraperDriver
{
    public function urlFor(string $gameCode, CarbonInterface $drawAt): string
    {
        // The site has per-game pages with a date query param; using YYYY-MM-DD
        // keeps the URL canonical (and the cache key meaningful).
        $date = $drawAt->format('Y-m-d');
        $game = match (strtolower($gameCode)) {
            '2d' => 'ez2',
            '3d' => 'swertres',
            default => strtolower($gameCode),
        };

        return "https://lottopcso.com/{$game}-result/?date={$date}";
    }

    public function parse(string $body, string $gameCode, CarbonInterface $drawAt): ?array
    {
        $labels = match (strtolower($gameCode)) {
            '2d' => ['EZ2', '2D'],
            '3d' => ['SWERTRES', '3D'],
            default => [strtoupper($gameCode)],
        };
        $date = $drawAt->format('Y-m-d');
        $slot = $drawAt->format('g:i A'); // e.g. "5:00 PM"
        $expected = strtolower($gameCode) === '2d' ? 2 : 3;

        // Strip tags, then collapse whitespace. Splitting on </tr> first gives
        // us per-row buckets we can substring-check without worrying about
        // catastrophic regex backtracking across the whole document.
        $rows = preg_split('/<\/tr>/i', $body) ?: [];

        foreach ($rows as $row) {
            // Insert a space between every tag boundary first — otherwise
            // strip_tags() concatenates adjacent cells (`<td>EZ2</td><td>7`
            // becomes `EZ27`) and the regex picks up bogus numbers.
            $text = (string) preg_replace('/<[^>]*>/', ' ', $row);
            $text = (string) preg_replace('/\s+/u', ' ', $text);
            $textUpper = strtoupper($text);

            if (! str_contains($text, $date)) {
                continue;
            }
            if (! str_contains($textUpper, strtoupper($slot))) {
                continue;
            }

            $hasLabel = false;
            foreach ($labels as $label) {
                if (str_contains($textUpper, strtoupper($label))) {
                    $hasLabel = true;
                    break;
                }
            }
            if (! $hasLabel) {
                continue;
            }

            // Grab the last "N - N (- N)" cluster on the row — typically the
            // numbers column. Bounded quantifier to keep the regex safe.
            if (preg_match_all('/\d{1,2}(?:\s*[-–—]\s*\d{1,2}){1,5}/u', $text, $m) === 0) {
                continue;
            }
            $rawNumbers = (string) end($m[0]);

            /** @var list<int> $numbers */
            $numbers = array_values(array_map(
                'intval',
                preg_split('/\s*[-–—]\s*/u', $rawNumbers) ?: [],
            ));

            if (count($numbers) === $expected) {
                return $numbers;
            }
        }

        return null;
    }

    public function label(): string
    {
        return 'lottopcso.com';
    }
}
