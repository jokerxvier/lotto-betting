<?php

declare(strict_types=1);

return [
    /*
    |---------------------------------------------------------------------------
    | Draw schedule
    |---------------------------------------------------------------------------
    | PCSO draw times in Asia/Manila as `HH:MM` 24-hour strings. The cron
    | (App\Console\Commands\GenerateUpcomingDrawsCommand) uses this list to
    | seed upcoming draws for every active game N days ahead. Override per
    | game by adding a `<game_code>` key (e.g. `'2d' => [...]`) — falls back
    | to `default` when no game-specific entry exists.
    */
    'draw_schedule' => [
        'default' => ['14:00', '17:00', '21:00'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Bet cutoff window
    |---------------------------------------------------------------------------
    | How many minutes before each draw bets stop being accepted. Drives the
    | cron's `cutoff_at = draw_at - cutoff_minutes`. Cutoff is also enforced
    | server-side in PlaceBetAction (Hard Rule 4), so changing this here
    | only affects newly-created draws — historical draws keep their values.
    */
    'cutoff_minutes' => 60,

    /*
    |---------------------------------------------------------------------------
    | PCSO result scraper
    |---------------------------------------------------------------------------
    | Source selection + caching for the App\Services\PcsoResultScraper.
    | The runtime on/off toggle ISN'T here — it's at /admin/settings (admin
    | flips it without a deploy). These values describe HOW the scraper
    | behaves when enabled, not whether it's enabled at all.
    */
    'scraper' => [
        // Source driver: 'gma' (recommended — gmanetwork.com, plain HTTP, no
        // WAF/blocks), 'lottopcso' (legacy — blocked from PH ISPs by CICC
        // DNS), 'pcso_gov' (official — behind Akamai bot-WAF, requires the
        // Playwright sidecar fetcher), or 'pcso_api' (standalone pcso-parser
        // REST API daemon — requires the pcso_api fetcher).
        'source' => env('LOTTO_SCRAPER_SOURCE', 'lottopcso'),
        'source_label' => env('LOTTO_SCRAPER_SOURCE_LABEL', 'lottopcso.com'),
        'cache_ttl_seconds' => (int) env('LOTTO_SCRAPER_CACHE_TTL', 60),
        'http_timeout_seconds' => (int) env('LOTTO_SCRAPER_HTTP_TIMEOUT', 8),

        // Fetcher: 'http' (default — plain Guzzle, for any non-WAF source),
        // 'playwright' (calls the standalone scraper/ sidecar at sidecar_url,
        // for pcso.gov.ph behind Akamai), or 'pcso_api' (calls the
        // standalone pcso-parser REST API at api_url; pairs with the
        // 'pcso_api' source driver).
        'fetcher' => env('LOTTO_SCRAPER_FETCHER', 'http'),
        'sidecar_url' => env('LOTTO_SCRAPER_SIDECAR_URL', 'http://127.0.0.1:8787'),
        'sidecar_token' => env('LOTTO_SCRAPER_SIDECAR_TOKEN', ''),
        'sidecar_timeout_seconds' => (int) env('LOTTO_SCRAPER_SIDECAR_TIMEOUT', 30),

        // pcso-parser API (Node REST daemon). Used when fetcher=pcso_api.
        // One POST /fetch?date=YYYY-MM-DD returns every game's parsed row
        // for the day; we look up by stable _id (YYYY-MM-DD-<game>-<HHMMAM/PM>).
        'api_url' => env('LOTTO_SCRAPER_API_URL', 'http://127.0.0.1:3001'),
        'api_timeout_seconds' => (int) env('LOTTO_SCRAPER_API_TIMEOUT', 60),
    ],
];
