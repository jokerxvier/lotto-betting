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
