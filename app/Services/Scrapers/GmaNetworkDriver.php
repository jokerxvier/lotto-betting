<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use Carbon\CarbonInterface;

/**
 * Driver for `gmanetwork.com/news/lotto/` — Philippine news mirror that
 * republishes PCSO results within minutes. Reachable from PH ISPs and
 * not behind Akamai (which 403s `pcso.gov.ph`) nor the CICC DNS block
 * that takes out `lottopcso.com`.
 *
 * Markup shape (one per draw):
 *   <div class="lotto-result --jackpot">
 *     <a data-date="May 17, 2026" data-type="2D 5PM" href="...">
 *       <h3>2D 5PM</h3>
 *     </a>
 *     <p>10 28</p>
 *     <p class="--jackpot">P 4,000</p>
 *   </div>
 *
 * Note: GMA labels 3D draws as "Swertres" (e.g. "Swertres 5PM"), not
 * "3D Lotto" like the PCSO official site. Handled in matchKeyFor().
 *
 * Numbers are space-separated and zero-padded for 2D ("10 28"),
 * single-digit for 3D ("5 1 9") — both parse to list<int> the same
 * way once we split on whitespace.
 */
final class GmaNetworkDriver implements ScraperDriver
{
    public function urlFor(string $gameCode, CarbonInterface $drawAt): string
    {
        unset($gameCode, $drawAt);

        // Single URL services every awaiting draw — the listing page
        // contains ~6 days of results across every game in one fetch.
        return 'https://www.gmanetwork.com/news/lotto/';
    }

    public function parse(string $body, string $gameCode, CarbonInterface $drawAt): ?array
    {
        $matchKey = $this->matchKeyFor($gameCode, $drawAt);
        if ($matchKey === null) {
            return null;
        }
        [$expected, $label, $date] = $matchKey;

        // Match the anchor — `data-date` and `data-type` may appear in
        // either order, so we look up the label first then re-check
        // date on the same anchor. Whitespace-tolerant inside attrs.
        $pattern = '/<a\b[^>]*data-(?:date|type)="(?P<v1>[^"]*)"[^>]*data-(?:date|type)="(?P<v2>[^"]*)"[^>]*>'
            .'\s*<h3\b[^>]*>[^<]*<\/h3>\s*<\/a>\s*<p\b[^>]*>(?P<numbers>[^<]+)<\/p>/i';

        if (preg_match_all($pattern, $body, $matches, PREG_SET_ORDER) === false) {
            return null;
        }

        foreach ($matches as $m) {
            $attrs = [$m['v1'], $m['v2']];
            if (! in_array($date, $attrs, true) || ! in_array($label, $attrs, true)) {
                continue;
            }

            /** @var list<int> $numbers */
            $numbers = array_values(array_map(
                'intval',
                preg_split('/\s+/', trim($m['numbers'])) ?: [],
            ));

            if (count($numbers) === $expected) {
                return $numbers;
            }
        }

        return null;
    }

    public function label(): string
    {
        return 'gmanetwork.com';
    }

    /**
     * Derive the {expected_count, "2D 5PM"/"Swertres 5PM", "May 17, 2026"}
     * tuple. Returns null when the game code is unsupported or the
     * draw_at hour is not a real PCSO slot (14/17/21 Manila).
     *
     * @return array{int, string, string}|null
     */
    private function matchKeyFor(string $gameCode, CarbonInterface $drawAt): ?array
    {
        $game = strtolower($gameCode);
        [$expected, $gameLabel] = match ($game) {
            '2d' => [2, '2D'],
            '3d' => [3, 'Swertres'],
            default => [null, null],
        };
        if ($expected === null || $gameLabel === null) {
            return null;
        }

        $manila = $drawAt->copy()->setTimezone('Asia/Manila');
        $slot = match ($manila->hour) {
            14 => '2PM',
            17 => '5PM',
            21 => '9PM',
            default => null,
        };
        if ($slot === null) {
            return null;
        }

        return [$expected, "{$gameLabel} {$slot}", $manila->format('F j, Y')];
    }
}
