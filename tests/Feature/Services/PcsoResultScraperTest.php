<?php

declare(strict_types=1);

use App\Services\PcsoResultScraper;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    // SettingsService default for the toggle is `true`, but the helper sits
    // on top of the cache so flush() above resets it implicitly.
    $this->scraper = new PcsoResultScraper(new SettingsService);
    config()->set('lotto.scraper.source', 'lottopcso');
});

function lottopcsoFixtureHtml(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../../fixtures/lottopcso/'.$name);
}

it('parses 2D EZ2 numbers from the upstream HTML', function () {
    Http::fake([
        'lottopcso.com/*' => Http::response(lottopcsoFixtureHtml('ez2-2026-05-18.html'), 200),
    ]);

    $drawAt = Carbon::create(2026, 5, 18, 17, 0); // 5:00 PM

    expect($this->scraper->fetchLatest('2d', $drawAt))->toBe([17, 22]);
});

it('parses 3D Swertres numbers from the upstream HTML', function () {
    Http::fake([
        'lottopcso.com/*' => Http::response(lottopcsoFixtureHtml('swertres-2026-05-18.html'), 200),
    ]);

    $drawAt = Carbon::create(2026, 5, 18, 17, 0); // 5:00 PM

    expect($this->scraper->fetchLatest('3d', $drawAt))->toBe([4, 1, 9]);
});

it('returns null when no row matches the requested slot', function () {
    Http::fake([
        'lottopcso.com/*' => Http::response(lottopcsoFixtureHtml('ez2-2026-05-18.html'), 200),
    ]);

    $drawAt = Carbon::create(2026, 5, 18, 18, 0); // 6 PM — no such slot

    expect($this->scraper->fetchLatest('2d', $drawAt))->toBeNull();
});

it('returns null on upstream 5xx', function () {
    Http::fake([
        'lottopcso.com/*' => Http::response('Server Error', 503),
    ]);

    expect($this->scraper->fetchLatest('2d', Carbon::create(2026, 5, 18, 17)))->toBeNull();
});

it('returns null on malformed HTML (no recognizable rows)', function () {
    Http::fake([
        'lottopcso.com/*' => Http::response('<html><body>nothing here</body></html>', 200),
    ]);

    expect($this->scraper->fetchLatest('2d', Carbon::create(2026, 5, 18, 17)))->toBeNull();
});

it('caches the upstream response — second call hits cache, not HTTP', function () {
    Http::fake([
        'lottopcso.com/*' => Http::response(lottopcsoFixtureHtml('ez2-2026-05-18.html'), 200),
    ]);

    $drawAt = Carbon::create(2026, 5, 18, 17);
    $this->scraper->fetchLatest('2d', $drawAt);
    $this->scraper->fetchLatest('2d', $drawAt);

    Http::assertSentCount(1);
});

it('makes zero HTTP requests when the settings toggle is off', function () {
    Http::fake();
    $settings = new SettingsService;
    $settings->set('scraper.suggestions_enabled', false);

    $scraper = new PcsoResultScraper($settings);

    expect($scraper->fetchLatest('2d', Carbon::create(2026, 5, 18, 17)))->toBeNull();

    Http::assertNothingSent();
});

it('returns null when the configured source has no driver', function () {
    config()->set('lotto.scraper.source', 'does-not-exist');
    Http::fake();

    expect($this->scraper->fetchLatest('2d', Carbon::create(2026, 5, 18, 17)))->toBeNull();
    Http::assertNothingSent();
});

// ── Playwright sidecar fetcher branch ────────────────────────────────────────

function fakePlaywrightSidecar(array $rows = []): void
{
    config()->set('lotto.scraper.source', 'pcso_gov');
    config()->set('lotto.scraper.fetcher', 'playwright');
    config()->set('lotto.scraper.sidecar_url', 'http://127.0.0.1:8787');
    config()->set('lotto.scraper.sidecar_token', 'test-token');

    Http::fake([
        '127.0.0.1:8787/scrape*' => Http::response([
            'source' => 'pcso.gov.ph',
            'fetchedAt' => '2026-05-17T13:00:00.000Z',
            'rows' => $rows,
        ], 200),
    ]);
}

it('routes through the Playwright sidecar when fetcher=playwright', function () {
    fakePlaywrightSidecar([
        ['game' => '2D Lotto 5PM', 'date' => '5/17/2026', 'numbers' => [10, 28]],
        ['game' => '3D Lotto 9PM', 'date' => '5/17/2026', 'numbers' => [4, 3, 1]],
    ]);

    // 5/17/2026 5PM Manila = 9:00 UTC
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');

    expect($this->scraper->fetchLatest('2d', $drawAt))->toBe([10, 28]);

    Http::assertSent(fn ($req): bool => $req->hasHeader('X-Scraper-Token', 'test-token')
        && str_contains($req->url(), '127.0.0.1:8787/scrape'));
});

it('only hits the sidecar once per cache window for the whole loop', function () {
    fakePlaywrightSidecar([
        ['game' => '2D Lotto 5PM', 'date' => '5/17/2026', 'numbers' => [10, 28]],
        ['game' => '3D Lotto 9PM', 'date' => '5/17/2026', 'numbers' => [4, 3, 1]],
    ]);

    $a = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    $b = Carbon::create(2026, 5, 17, 21, 0, 0, 'Asia/Manila')->setTimezone('UTC');

    expect($this->scraper->fetchLatest('2d', $a))->toBe([10, 28])
        ->and($this->scraper->fetchLatest('3d', $b))->toBe([4, 3, 1]);

    Http::assertSentCount(1);
});

it('returns null when the sidecar has no matching row', function () {
    fakePlaywrightSidecar([
        ['game' => '2D Lotto 2PM', 'date' => '5/17/2026', 'numbers' => [1, 2]],
    ]);

    // ask for the 5PM row — sidecar only returned 2PM
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    expect($this->scraper->fetchLatest('2d', $drawAt))->toBeNull();
});

it('returns null when the sidecar fails', function () {
    config()->set('lotto.scraper.source', 'pcso_gov');
    config()->set('lotto.scraper.fetcher', 'playwright');
    config()->set('lotto.scraper.sidecar_url', 'http://127.0.0.1:8787');
    config()->set('lotto.scraper.sidecar_token', 'test-token');

    Http::fake([
        '127.0.0.1:8787/*' => Http::response(['error' => 'upstream_failure'], 502),
    ]);

    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    expect($this->scraper->fetchLatest('2d', $drawAt))->toBeNull();
});

it('refuses to use the playwright fetcher with a non-pcso_gov source', function () {
    // misconfig: lottopcso source + playwright fetcher — JSON shape mismatch
    config()->set('lotto.scraper.source', 'lottopcso');
    config()->set('lotto.scraper.fetcher', 'playwright');
    Http::fake(); // any call would explode the test

    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');
    expect($this->scraper->fetchLatest('2d', $drawAt))->toBeNull();

    Http::assertNothingSent();
});

// ── GMA Network source (default production source) ───────────────────────────

it('parses numbers via the gma source end-to-end', function () {
    config()->set('lotto.scraper.source', 'gma');
    Http::fake([
        'gmanetwork.com/*' => Http::response(
            (string) file_get_contents(__DIR__.'/../../fixtures/gma/lotto-listing-2026-05-17.html'),
            200,
        ),
    ]);

    // 5/17/2026 5PM Manila → 9:00 UTC. GMA listing has it as "10 28".
    $drawAt = Carbon::create(2026, 5, 17, 17, 0, 0, 'Asia/Manila')->setTimezone('UTC');

    expect($this->scraper->fetchLatest('2d', $drawAt))->toBe([10, 28]);
});
