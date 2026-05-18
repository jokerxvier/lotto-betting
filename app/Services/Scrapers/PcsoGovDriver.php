<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use Carbon\CarbonInterface;

/**
 * Driver for `pcso.gov.ph/searchlottoresult.aspx` — the official PCSO
 * search page. Renders an ASP.NET WebForms GridView with five columns:
 * LOTTO GAME | COMBINATIONS | DRAW DATE | JACKPOT | WINNERS.
 *
 * We match by label ("2D Lotto 5PM" / "3D Lotto 9PM") AND date
 * ("M/D/YYYY", no zero-padding). The 2D combinations cell is
 * zero-padded ("10-09"); the 3D cell is not ("5-1-9"); both parse to
 * the same list<int>.
 *
 * ⚠ pcso.gov.ph sits behind Akamai bot-WAF. Plain `Http::get()` from a
 * server returns 403 at the edge — this driver's `parse()` works on
 * any HTML you can hand it, but production fetches need a real-browser
 * renderer (e.g. spatie/browsershot) ahead of the parser, or a
 * residential / non-flagged egress.
 */
final class PcsoGovDriver implements ScraperDriver
{
    public function urlFor(string $gameCode, CarbonInterface $drawAt): string
    {
        unset($gameCode, $drawAt);

        // One URL covers every draw — the page lists ~25 recent rows
        // across every game in one response, so per-slot caching by URL
        // would defeat itself. Caller's cache key still works (URL is
        // identical) and a single fetch services every awaiting draw.
        return 'https://www.pcso.gov.ph/searchlottoresult.aspx';
    }

    public function parse(string $body, string $gameCode, CarbonInterface $drawAt): ?array
    {
        $matchKey = $this->matchKeyFor($gameCode, $drawAt);
        if ($matchKey === null) {
            return null;
        }
        [$expected, $label, $date] = $matchKey;

        $rows = preg_split('/<\/tr>/i', $body) ?: [];

        foreach ($rows as $row) {
            // Insert spaces at tag boundaries before strip — otherwise
            // adjacent <td>s concatenate ("<td>5PM</td><td>10-28</td>"
            // -> "5PM10-28") and the regex picks up garbage.
            $text = (string) preg_replace('/<[^>]*>/', ' ', $row);
            $text = (string) preg_replace('/\s+/u', ' ', $text);

            if (! str_contains(strtoupper($text), $label)) {
                continue;
            }
            if (! str_contains($text, $date)) {
                continue;
            }

            // COMBINATIONS is the only hyphen-separated run of digits on
            // the row — date uses "/", jackpot uses "," + "." only.
            if (preg_match('/\b\d{1,2}(?:-\d{1,2}){1,5}\b/', $text, $m) !== 1) {
                continue;
            }

            /** @var list<int> $numbers */
            $numbers = array_values(array_map('intval', explode('-', $m[0])));

            if (count($numbers) === $expected) {
                return $numbers;
            }
        }

        return null;
    }

    public function label(): string
    {
        return 'pcso.gov.ph';
    }

    /**
     * Pick the matching draw row from a pre-parsed JSON payload (the
     * Playwright sidecar's `/scrape` output). Same {game, slot, date}
     * match logic as parse(), but consumes structured rows so we don't
     * re-parse HTML for every awaiting draw.
     *
     * @param  list<array{game:string, date:string, numbers:list<int>}>  $rows
     * @return list<int>|null
     */
    public function pickFromRows(array $rows, string $gameCode, CarbonInterface $drawAt): ?array
    {
        $matchKey = $this->matchKeyFor($gameCode, $drawAt);
        if ($matchKey === null) {
            return null;
        }
        [$expected, $label, $date] = $matchKey;

        foreach ($rows as $row) {
            if (strtoupper($row['game']) !== $label) {
                continue;
            }
            if ($row['date'] !== $date) {
                continue;
            }
            if (count($row['numbers']) !== $expected) {
                return null;
            }

            return array_values($row['numbers']);
        }

        return null;
    }

    /**
     * Derive the {expected_count, "2D LOTTO 5PM", "5/17/2026"} tuple used
     * by both parse() and pickFromRows(). Returns null when the game
     * code is unsupported or the draw_at hour is not a real PCSO slot.
     *
     * @return array{int, string, string}|null
     */
    private function matchKeyFor(string $gameCode, CarbonInterface $drawAt): ?array
    {
        $game = strtolower($gameCode);
        $expected = match ($game) {
            '2d' => 2,
            '3d' => 3,
            default => null,
        };
        if ($expected === null) {
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

        return [$expected, strtoupper($game).' LOTTO '.$slot, $manila->format('n/j/Y')];
    }
}
