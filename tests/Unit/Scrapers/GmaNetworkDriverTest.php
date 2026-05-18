<?php

declare(strict_types=1);

use App\Services\Scrapers\GmaNetworkDriver;
use Carbon\Carbon;

function gmaFixtureHtml(): string
{
    return (string) file_get_contents(
        __DIR__.'/../../fixtures/gma/lotto-listing-2026-05-17.html',
    );
}

/**
 * Build a Carbon in Manila for the given calendar date + 12-hr slot,
 * then shift to UTC — matches what Eloquent hands the driver.
 */
function gmaManilaDrawAt(int $year, int $month, int $day, int $hour): Carbon
{
    return Carbon::create($year, $month, $day, $hour, 0, 0, 'Asia/Manila')
        ->setTimezone('UTC');
}

it('returns the single listing URL regardless of game/draw', function () {
    expect((new GmaNetworkDriver)->urlFor('2d', gmaManilaDrawAt(2026, 5, 17, 17)))
        ->toBe('https://www.gmanetwork.com/news/lotto/');
});

it('exposes gmanetwork.com as its label', function () {
    expect((new GmaNetworkDriver)->label())->toBe('gmanetwork.com');
});

it('parses every 2D + 3D row across all three days in the listing', function (
    string $game,
    int $hour,
    int $day,
    array $expected,
) {
    $numbers = (new GmaNetworkDriver)
        ->parse(gmaFixtureHtml(), $game, gmaManilaDrawAt(2026, 5, $day, $hour));

    expect($numbers)->toBe($expected);
})->with([
    // 5/17/2026
    '2D 2PM 5/17' => ['2d', 14, 17, [10, 9]],
    '2D 5PM 5/17' => ['2d', 17, 17, [10, 28]],
    '2D 9PM 5/17' => ['2d', 21, 17, [29, 19]],
    '3D 2PM 5/17' => ['3d', 14, 17, [5, 1, 9]],
    '3D 5PM 5/17' => ['3d', 17, 17, [2, 5, 5]],
    '3D 9PM 5/17' => ['3d', 21, 17, [4, 3, 1]],
    // 5/16/2026
    '2D 2PM 5/16' => ['2d', 14, 16, [10, 12]],
    '2D 5PM 5/16' => ['2d', 17, 16, [27, 14]],
    '2D 9PM 5/16' => ['2d', 21, 16, [3, 6]],
    '3D 2PM 5/16' => ['3d', 14, 16, [0, 6, 9]],
    '3D 5PM 5/16' => ['3d', 17, 16, [7, 1, 2]],
    '3D 9PM 5/16' => ['3d', 21, 16, [3, 6, 8]],
    // 5/15/2026
    '2D 2PM 5/15' => ['2d', 14, 15, [25, 11]],
    '2D 5PM 5/15' => ['2d', 17, 15, [13, 8]],
    '2D 9PM 5/15' => ['2d', 21, 15, [5, 7]],
    '3D 2PM 5/15' => ['3d', 14, 15, [5, 1, 2]],
    '3D 5PM 5/15' => ['3d', 17, 15, [5, 9, 5]],
    '3D 9PM 5/15' => ['3d', 21, 15, [6, 6, 0]],
]);

it('returns null when the requested date is not present', function () {
    // The fixture doesn't include 5/18 yet
    expect((new GmaNetworkDriver)->parse(gmaFixtureHtml(), '2d', gmaManilaDrawAt(2026, 5, 18, 17)))
        ->toBeNull();
});

it('returns null for unsupported game codes', function () {
    expect((new GmaNetworkDriver)->parse(gmaFixtureHtml(), '6d', gmaManilaDrawAt(2026, 5, 17, 21)))
        ->toBeNull()
        ->and((new GmaNetworkDriver)->parse(gmaFixtureHtml(), '4d', gmaManilaDrawAt(2026, 5, 15, 21)))
        ->toBeNull();
});

it('returns null for draw hours outside the PCSO 2PM/5PM/9PM schedule', function () {
    // 6 AM / 9 AM bogus slots — never match
    expect((new GmaNetworkDriver)->parse(gmaFixtureHtml(), '2d', gmaManilaDrawAt(2026, 5, 17, 6)))
        ->toBeNull()
        ->and((new GmaNetworkDriver)->parse(gmaFixtureHtml(), '2d', gmaManilaDrawAt(2026, 5, 17, 9)))
        ->toBeNull();
});

it('does not pick up the jackpot column as winning numbers', function () {
    // Verify by parsing a 3D 5PM row: numbers cell has "2 5 5", jackpot cell
    // has "P 4,500" — the parser should bind the first <p> after the anchor,
    // never the .--jackpot sibling.
    expect((new GmaNetworkDriver)->parse(gmaFixtureHtml(), '3d', gmaManilaDrawAt(2026, 5, 17, 17)))
        ->toBe([2, 5, 5]); // not [4, 500] from the jackpot column
});

it('returns null on completely malformed input', function () {
    expect((new GmaNetworkDriver)->parse('<html>no lotto cards</html>', '2d', gmaManilaDrawAt(2026, 5, 17, 17)))
        ->toBeNull();
});
