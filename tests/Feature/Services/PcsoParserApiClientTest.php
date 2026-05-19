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

it('POSTs to /fetch with from/to and returns rows grouped by ISO date', function () {
    Http::fake([
        '127.0.0.1:3001/fetch' => Http::response([
            'from' => '2026-05-13',
            'to' => '2026-05-14',
            'fetchedAt' => '2026-05-19T06:00:00.000Z',
            'totals' => ['results' => 3, 'prizes' => 0, 'days' => 2],
            'days' => [
                [
                    'date' => '2026-05-13',
                    'url' => 'https://www.lottopcso.com/pcso-lotto-result-may-13-2026',
                    'counts' => ['results' => 2, 'prizes' => 0],
                    'results' => [
                        ['_id' => '2026-05-13-2D-500PM', 'game' => '2D', 'date' => 'May 13, 2026', 'time' => '5:00 PM', 'winning' => '13-25'],
                        ['_id' => '2026-05-13-3D-500PM', 'game' => '3D', 'date' => 'May 13, 2026', 'time' => '5:00 PM', 'winning' => '3-2-4'],
                    ],
                ],
                [
                    'date' => '2026-05-14',
                    'url' => 'https://www.lottopcso.com/pcso-lotto-result-may-14-2026',
                    'counts' => ['results' => 1, 'prizes' => 0],
                    'results' => [
                        ['_id' => '2026-05-14-2D-200PM', 'game' => '2D', 'date' => 'May 14, 2026', 'time' => '2:00 PM', 'winning' => '07-08'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $byDate = (new PcsoParserApiClient('http://127.0.0.1:3001'))
        ->fetchForRange('2026-05-13', '2026-05-14');

    expect($byDate)->toHaveKeys(['2026-05-13', '2026-05-14'])
        ->and($byDate['2026-05-13'])->toHaveCount(2)
        ->and($byDate['2026-05-13'][0]['_id'])->toBe('2026-05-13-2D-500PM')
        ->and($byDate['2026-05-14'])->toHaveCount(1)
        ->and($byDate['2026-05-14'][0]['_id'])->toBe('2026-05-14-2D-200PM');

    Http::assertSent(function ($req): bool {
        return str_contains($req->url(), '127.0.0.1:3001/fetch')
            && $req['from'] === '2026-05-13'
            && $req['to'] === '2026-05-14';
    });
});

it('range: throws when the response is missing the `days` array', function () {
    Http::fake([
        '127.0.0.1:3001/*' => Http::response(['from' => '2026-05-13', 'to' => '2026-05-14'], 200),
    ]);

    expect(fn () => (new PcsoParserApiClient('http://127.0.0.1:3001'))
        ->fetchForRange('2026-05-13', '2026-05-14'))
        ->toThrow(RuntimeException::class, 'pcso_api_malformed_range_response');
});

it('range: skips days missing date/results and drops malformed rows', function () {
    Http::fake([
        '127.0.0.1:3001/*' => Http::response([
            'days' => [
                ['date' => '2026-05-13', 'results' => [
                    ['_id' => '2026-05-13-2D-500PM', 'game' => '2D', 'date' => 'May 13, 2026', 'winning' => '13-25'],
                    ['_id' => '2026-05-13-2D-200PM', 'game' => '2D', 'date' => 'May 13, 2026'], // no winning
                ]],
                ['results' => []], // no date
                ['date' => '2026-05-14'], // no results
                'not-an-array',
            ],
        ], 200),
    ]);

    $byDate = (new PcsoParserApiClient('http://127.0.0.1:3001'))
        ->fetchForRange('2026-05-13', '2026-05-14');

    expect($byDate)->toHaveCount(1)
        ->and($byDate['2026-05-13'])->toHaveCount(1)
        ->and($byDate['2026-05-13'][0]['_id'])->toBe('2026-05-13-2D-500PM');
});

it('range: retries once on 429 then returns on the second response', function () {
    Http::fake([
        '127.0.0.1:3001/*' => Http::sequence()
            ->push(['error' => 'busy'], 429)
            ->push(['days' => [
                ['date' => '2026-05-13', 'results' => [
                    ['_id' => '2026-05-13-2D-500PM', 'game' => '2D', 'date' => 'May 13, 2026', 'winning' => '13-25'],
                ]],
            ]], 200),
    ]);

    $byDate = (new PcsoParserApiClient('http://127.0.0.1:3001'))
        ->fetchForRange('2026-05-13', '2026-05-13');

    expect($byDate['2026-05-13'])->toHaveCount(1);
    Http::assertSentCount(2);
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
