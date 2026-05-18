<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP client for the standalone Node + Playwright sidecar
 * (`scraper/server.mjs`). The sidecar runs as a Forge daemon bound to
 * 127.0.0.1, handles Chromium lifecycle, and returns parsed PCSO
 * results as JSON. We just GET + bearer-token + unwrap `rows`.
 *
 * Failure path: `RuntimeException` with a short reason code so the
 * caller can log it to the `audit` channel without leaking detail.
 * Never throws on missing/extra rows — empty list is a valid response.
 */
final class PlaywrightSidecarClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeoutSeconds = 30,
    ) {}

    /**
     * Fetch every recent draw row from the sidecar.
     *
     * @return list<array{game:string, date:string, numbers:list<int>}>
     */
    public function fetchAll(bool $refresh = false): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders(['X-Scraper-Token' => $this->token])
            ->get(rtrim($this->baseUrl, '/').'/scrape', $refresh ? ['refresh' => 1] : []);

        if ($response->failed()) {
            throw new RuntimeException("sidecar_upstream_{$response->status()}");
        }

        $rows = $response->json('rows');
        if (! is_array($rows)) {
            throw new RuntimeException('sidecar_malformed_response');
        }

        /** @var list<array{game:string, date:string, numbers:list<int>}> $normalized */
        $normalized = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $game = $row['game'] ?? null;
            $date = $row['date'] ?? null;
            $numbers = $row['numbers'] ?? null;
            if (! is_string($game) || ! is_string($date) || ! is_array($numbers)) {
                continue;
            }
            $normalized[] = [
                'game' => $game,
                'date' => $date,
                'numbers' => array_values(array_map('intval', $numbers)),
            ];
        }

        return $normalized;
    }
}
