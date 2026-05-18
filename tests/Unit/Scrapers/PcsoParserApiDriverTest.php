<?php

declare(strict_types=1);

use App\Services\Scrapers\PcsoParserApiDriver;
use Carbon\Carbon;

/**
 * Build a Carbon in Manila for the given calendar date + 12-hr slot,
 * then shift to UTC — matches what Eloquent hands the driver.
 */
function apiManilaDrawAt(int $year, int $month, int $day, int $hour): Carbon
{
    return Carbon::create($year, $month, $day, $hour, 0, 0, 'Asia/Manila')
        ->setTimezone('UTC');
}

/** @return list<array{_id:string, game:string, date:string, winning:string}> */
function apiSampleRows(): array
{
    return [
        // 5/17
        ['_id' => '2026-05-17-2D-200PM', 'game' => '2D', 'date' => 'May 17, 2026', 'winning' => '10-09'],
        ['_id' => '2026-05-17-2D-500PM', 'game' => '2D', 'date' => 'May 17, 2026', 'winning' => '10-28'],
        ['_id' => '2026-05-17-2D-900PM', 'game' => '2D', 'date' => 'May 17, 2026', 'winning' => '29-19'],
        ['_id' => '2026-05-17-3D-200PM', 'game' => '3D', 'date' => 'May 17, 2026', 'winning' => '5-1-9'],
        ['_id' => '2026-05-17-3D-500PM', 'game' => '3D', 'date' => 'May 17, 2026', 'winning' => '2-5-5'],
        ['_id' => '2026-05-17-3D-900PM', 'game' => '3D', 'date' => 'May 17, 2026', 'winning' => '4-3-1'],
        // 5/16
        ['_id' => '2026-05-16-2D-200PM', 'game' => '2D', 'date' => 'May 16, 2026', 'winning' => '10-12'],
        ['_id' => '2026-05-16-3D-900PM', 'game' => '3D', 'date' => 'May 16, 2026', 'winning' => '3-6-8'],
        // a non-2D/3D row to confirm we ignore it
        ['_id' => '2026-05-17-658', 'game' => '658', 'date' => 'May 17, 2026', 'winning' => '39-37-35-45-16-52'],
    ];
}

it('urlFor() returns a diagnostic path (not the live URL)', function () {
    // Real URL is constructed by PcsoResultScraper / PcsoParserApiClient.
    expect((new PcsoParserApiDriver)->urlFor('2d', apiManilaDrawAt(2026, 5, 17, 17)))
        ->toBe('/fetch');
});

it('exposes pcso-parser-api as its label', function () {
    expect((new PcsoParserApiDriver)->label())->toBe('pcso-parser-api');
});

it('parse() returns null — driver has no HTML path', function () {
    expect((new PcsoParserApiDriver)->parse('<html/>', '2d', apiManilaDrawAt(2026, 5, 17, 17)))
        ->toBeNull();
});

it('picks the matching row by reconstructed _id', function (
    string $game,
    int $hour,
    int $day,
    array $expected,
) {
    $numbers = (new PcsoParserApiDriver)
        ->pickFromRows(apiSampleRows(), $game, apiManilaDrawAt(2026, 5, $day, $hour));

    expect($numbers)->toBe($expected);
})->with([
    '2D 2PM 5/17' => ['2d', 14, 17, [10, 9]],
    '2D 5PM 5/17' => ['2d', 17, 17, [10, 28]],
    '2D 9PM 5/17' => ['2d', 21, 17, [29, 19]],
    '3D 2PM 5/17' => ['3d', 14, 17, [5, 1, 9]],
    '3D 5PM 5/17' => ['3d', 17, 17, [2, 5, 5]],
    '3D 9PM 5/17' => ['3d', 21, 17, [4, 3, 1]],
    '2D 2PM 5/16' => ['2d', 14, 16, [10, 12]],
    '3D 9PM 5/16' => ['3d', 21, 16, [3, 6, 8]],
]);

it('returns null when no row matches the date/slot', function () {
    expect((new PcsoParserApiDriver)->pickFromRows(apiSampleRows(), '2d', apiManilaDrawAt(2026, 5, 18, 17)))
        ->toBeNull();
});

it('returns null for unsupported game codes', function () {
    expect((new PcsoParserApiDriver)->pickFromRows(apiSampleRows(), '6d', apiManilaDrawAt(2026, 5, 17, 21)))
        ->toBeNull();
});

it('returns null for off-schedule hours (6 AM / 9 AM / 1 PM Manila)', function () {
    $rows = apiSampleRows();
    expect((new PcsoParserApiDriver)->pickFromRows($rows, '2d', apiManilaDrawAt(2026, 5, 17, 6)))
        ->toBeNull()
        ->and((new PcsoParserApiDriver)->pickFromRows($rows, '2d', apiManilaDrawAt(2026, 5, 17, 9)))
        ->toBeNull()
        ->and((new PcsoParserApiDriver)->pickFromRows($rows, '2d', apiManilaDrawAt(2026, 5, 17, 13)))
        ->toBeNull();
});

it('returns null when the matched row has the wrong number count', function () {
    $broken = [
        ['_id' => '2026-05-17-2D-500PM', 'game' => '2D', 'date' => 'May 17, 2026', 'winning' => '10-28-99'],
    ];

    expect((new PcsoParserApiDriver)->pickFromRows($broken, '2d', apiManilaDrawAt(2026, 5, 17, 17)))
        ->toBeNull();
});

it('returns null when winning has non-numeric parts', function () {
    $broken = [
        ['_id' => '2026-05-17-2D-500PM', 'game' => '2D', 'date' => 'May 17, 2026', 'winning' => 'foo-28'],
    ];

    expect((new PcsoParserApiDriver)->pickFromRows($broken, '2d', apiManilaDrawAt(2026, 5, 17, 17)))
        ->toBeNull();
});

it('returns null on empty rows', function () {
    expect((new PcsoParserApiDriver)->pickFromRows([], '2d', apiManilaDrawAt(2026, 5, 17, 17)))
        ->toBeNull();
});
