<?php

declare(strict_types=1);

use App\Services\Scrapers\PcsoParserApiClient;
use Illuminate\Support\Facades\Http;

it('POSTs to /fetch with the requested date and returns normalized rows', function () {
    Http::fake([
        '127.0.0.1:3001/fetch' => Http::response([
            'url' => 'https://www.lottopcso.com/pcso-lotto-result-may-17-2026',
            'fetchedAt' => '2026-05-18T16:30:00.000Z',
            'counts' => ['results' => 2, 'prizes' => 0],
            'results' => [
                [
                    '_id' => '2026-05-17-2D-500PM',
                    'game' => '2D',
                    'date' => 'May 17, 2026',
                    'time' => '5:00 PM',
                    'winning' => '10-28',
                    'prize' => '4,000.00',
                    'winners' => '396',
                ],
                [
                    '_id' => '2026-05-17-3D-900PM',
                    'game' => '3D',
                    'date' => 'May 17, 2026',
                    'time' => '9:00 PM',
                    'winning' => '4-3-1',
                    'prize' => '4,500.00',
                    'winners' => '292',
                ],
            ],
            'prizes' => [],
        ], 200),
    ]);

    $rows = (new PcsoParserApiClient('http://127.0.0.1:3001'))->fetchFor('2026-05-17');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['_id'])->toBe('2026-05-17-2D-500PM')
        ->and($rows[0]['winning'])->toBe('10-28')
        ->and($rows[1]['_id'])->toBe('2026-05-17-3D-900PM')
        ->and($rows[1]['winning'])->toBe('4-3-1');

    Http::assertSent(function ($req): bool {
        return str_contains($req->url(), '127.0.0.1:3001/fetch')
            && $req['date'] === '2026-05-17';
    });
});

it('strips trailing slashes from baseUrl', function () {
    Http::fake([
        '127.0.0.1:3001/fetch' => Http::response(['results' => []], 200),
    ]);

    (new PcsoParserApiClient('http://127.0.0.1:3001/'))->fetchFor('2026-05-17');

    Http::assertSent(fn ($req): bool => str_contains($req->url(), '127.0.0.1:3001/fetch')
        && ! str_contains($req->url(), '//fetch'));
});

it('retries once on 429 then returns on the second response', function () {
    Http::fake([
        '127.0.0.1:3001/*' => Http::sequence()
            ->push(['error' => 'busy'], 429)
            ->push(['results' => [
                ['_id' => '2026-05-17-2D-500PM', 'game' => '2D', 'date' => 'May 17, 2026', 'winning' => '10-28'],
            ]], 200),
    ]);

    $rows = (new PcsoParserApiClient('http://127.0.0.1:3001'))->fetchFor('2026-05-17');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['winning'])->toBe('10-28');

    Http::assertSentCount(2);
});

it('throws when 429 persists after the retry', function () {
    Http::fake([
        '127.0.0.1:3001/*' => Http::response(['error' => 'busy'], 429),
    ]);

    expect(fn () => (new PcsoParserApiClient('http://127.0.0.1:3001'))->fetchFor('2026-05-17'))
        ->toThrow(RuntimeException::class, 'pcso_api_upstream_429');
});

it('throws on a 5xx response with the upstream status in the message', function () {
    Http::fake([
        '127.0.0.1:3001/*' => Http::response(['error' => 'oops'], 502),
    ]);

    expect(fn () => (new PcsoParserApiClient('http://127.0.0.1:3001'))->fetchFor('2026-05-17'))
        ->toThrow(RuntimeException::class, 'pcso_api_upstream_502');
});

it('throws when the response is missing the `results` array', function () {
    Http::fake([
        '127.0.0.1:3001/*' => Http::response(['url' => '...', 'counts' => []], 200),
    ]);

    expect(fn () => (new PcsoParserApiClient('http://127.0.0.1:3001'))->fetchFor('2026-05-17'))
        ->toThrow(RuntimeException::class, 'pcso_api_malformed_response');
});

it('drops rows missing any of _id/game/date/winning', function () {
    Http::fake([
        '127.0.0.1:3001/*' => Http::response([
            'results' => [
                ['_id' => '2026-05-17-2D-500PM', 'game' => '2D', 'date' => 'May 17, 2026', 'winning' => '10-28'],
                ['_id' => '2026-05-17-2D-200PM', 'game' => '2D', 'date' => 'May 17, 2026'], // no winning
                ['game' => '3D', 'date' => 'May 17, 2026', 'winning' => '4-3-1'],           // no _id
                'not-an-array',
            ],
        ], 200),
    ]);

    $rows = (new PcsoParserApiClient('http://127.0.0.1:3001'))->fetchFor('2026-05-17');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['_id'])->toBe('2026-05-17-2D-500PM');
});
