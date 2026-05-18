<?php

declare(strict_types=1);

use App\Services\Scrapers\PlaywrightSidecarClient;
use Illuminate\Support\Facades\Http;

it('sends the auth token and returns normalized rows', function () {
    Http::fake([
        '127.0.0.1:8787/scrape*' => Http::response([
            'source' => 'pcso.gov.ph',
            'fetchedAt' => '2026-05-18T09:00:00.000Z',
            'rows' => [
                ['game' => '2D Lotto 5PM', 'date' => '5/17/2026', 'numbers' => [10, 28]],
                ['game' => '3D Lotto 9PM', 'date' => '5/17/2026', 'numbers' => [4, 3, 1]],
            ],
        ], 200),
    ]);

    $client = new PlaywrightSidecarClient('http://127.0.0.1:8787', 'sekret', 5);
    $rows = $client->fetchAll();

    expect($rows)->toBe([
        ['game' => '2D Lotto 5PM', 'date' => '5/17/2026', 'numbers' => [10, 28]],
        ['game' => '3D Lotto 9PM', 'date' => '5/17/2026', 'numbers' => [4, 3, 1]],
    ]);

    Http::assertSent(fn ($req): bool => $req->hasHeader('X-Scraper-Token', 'sekret')
        && str_contains($req->url(), '127.0.0.1:8787/scrape'));
});

it('appends ?refresh=1 when requested', function () {
    Http::fake([
        '127.0.0.1:8787/*' => Http::response(['rows' => []], 200),
    ]);

    (new PlaywrightSidecarClient('http://127.0.0.1:8787/', 'tok'))->fetchAll(refresh: true);

    Http::assertSent(fn ($req): bool => str_contains($req->url(), 'refresh=1'));
});

it('throws on a non-2xx response with the upstream status in the message', function () {
    Http::fake([
        '127.0.0.1:8787/*' => Http::response(['error' => 'upstream_failure'], 502),
    ]);

    expect(fn () => (new PlaywrightSidecarClient('http://127.0.0.1:8787', 'tok'))->fetchAll())
        ->toThrow(RuntimeException::class, 'sidecar_upstream_502');
});

it('throws when rows is missing or malformed', function () {
    Http::fake([
        '127.0.0.1:8787/*' => Http::response(['source' => 'pcso.gov.ph'], 200),
    ]);

    expect(fn () => (new PlaywrightSidecarClient('http://127.0.0.1:8787', 'tok'))->fetchAll())
        ->toThrow(RuntimeException::class, 'sidecar_malformed_response');
});

it('skips rows that are missing required keys', function () {
    Http::fake([
        '127.0.0.1:8787/*' => Http::response([
            'rows' => [
                ['game' => '2D Lotto 5PM', 'date' => '5/17/2026', 'numbers' => [10, 28]],
                ['game' => '6D Lotto', 'date' => '5/16/2026'], // numbers missing — dropped
                ['game' => 'Bogus'], // date + numbers missing — dropped
                'not-an-array', // dropped
            ],
        ], 200),
    ]);

    $rows = (new PlaywrightSidecarClient('http://127.0.0.1:8787', 'tok'))->fetchAll();

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toBe(['game' => '2D Lotto 5PM', 'date' => '5/17/2026', 'numbers' => [10, 28]]);
});

it('coerces non-int number values defensively', function () {
    Http::fake([
        '127.0.0.1:8787/*' => Http::response([
            'rows' => [
                ['game' => '2D Lotto 5PM', 'date' => '5/17/2026', 'numbers' => ['10', '28']],
            ],
        ], 200),
    ]);

    $rows = (new PlaywrightSidecarClient('http://127.0.0.1:8787', 'tok'))->fetchAll();

    expect($rows[0]['numbers'])->toBe([10, 28]);
});
