<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use Carbon\CarbonInterface;

/**
 * Contract for a per-source PCSO result scraper. The PcsoResultScraper
 * orchestrates the HTTP / cache / toggle plumbing; this interface is
 * just the "given a game and a draw timestamp, find the row in the
 * upstream HTML" part. Swappable: when one source rots, write a new
 * driver and flip `config('lotto.scraper.source')`.
 */
interface ScraperDriver
{
    /**
     * The URL to fetch for the given game + slot. Used as the cache key
     * so different slots don't share a cached HTML blob.
     */
    public function urlFor(string $gameCode, CarbonInterface $drawAt): string;

    /**
     * Parse the raw upstream response body and return the winning numbers
     * matching the requested game + slot exactly. Return null for any
     * failure (no match for the slot, malformed HTML, unexpected shape).
     *
     * @return list<int>|null
     */
    public function parse(string $body, string $gameCode, CarbonInterface $drawAt): ?array;

    /**
     * Human-readable source label, surfaced to the admin in the publish
     * form ("Suggested by lottopcso.com").
     */
    public function label(): string;
}
