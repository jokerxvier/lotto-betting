<?php

declare(strict_types=1);

use App\Services\Scrapers\PcsoGovDriver;
use Carbon\Carbon;

function pcsoGovFixtureHtml(): string
{
    return (string) file_get_contents(
        __DIR__.'/../../fixtures/pcso-gov/searchlottoresult.html',
    );
}

/**
 * Build a Carbon in Manila for the given M/D/YYYY date + 12-hr slot.
 * The driver receives draw_at from Eloquent (cast as UTC datetime); we
 * mimic that by setting Asia/Manila then shifting to UTC.
 */
function manilaDrawAt(int $year, int $month, int $day, int $hour): Carbon
{
    return Carbon::create($year, $month, $day, $hour, 0, 0, 'Asia/Manila')
        ->setTimezone('UTC');
}

it('returns the correct URL regardless of game/draw', function () {
    $driver = new PcsoGovDriver;
    $url = $driver->urlFor('2d', manilaDrawAt(2026, 5, 17, 17));

    expect($url)->toBe('https://www.pcso.gov.ph/searchlottoresult.aspx');
});

it('exposes pcso.gov.ph as its label', function () {
    expect((new PcsoGovDriver)->label())->toBe('pcso.gov.ph');
});

it('parses every 2D + 3D row from the official GridView', function (
    string $game,
    int $hour,
    int $day,
    array $expected,
) {
    $driver = new PcsoGovDriver;
    $drawAt = manilaDrawAt(2026, 5, $day, $hour);

    $numbers = $driver->parse(pcsoGovFixtureHtml(), $game, $drawAt);

    expect($numbers)->toBe($expected);
})->with([
    // 5/17/2026
    '2D 2PM 5/17' => ['2d', 14, 17, [10, 9]],
    '2D 5PM 5/17' => ['2d', 17, 17, [10, 28]],
    '2D 9PM 5/17' => ['2d', 21, 17, [29, 19]],
    '3D 2PM 5/17' => ['3d', 14, 17, [5, 1, 9]],
    '3D 5PM 5/17' => ['3d', 17, 17, [2, 5, 5]],
    '3D 9PM 5/17' => ['3d', 21, 17, [4, 3, 1]],
    // 5/16/2026 — different date, same parser
    '2D 2PM 5/16' => ['2d', 14, 16, [10, 12]],
    '2D 9PM 5/16' => ['2d', 21, 16, [3, 6]],
    '3D 2PM 5/16' => ['3d', 14, 16, [0, 6, 9]],
    // 5/15/2026
    '2D 5PM 5/15' => ['2d', 17, 15, [13, 8]],
    '3D 9PM 5/15' => ['3d', 21, 15, [6, 6, 0]],
]);

it('returns null when the requested slot is not present in the HTML', function () {
    $driver = new PcsoGovDriver;
    // 5/18 isn't in the fixture
    $drawAt = manilaDrawAt(2026, 5, 18, 17);

    expect($driver->parse(pcsoGovFixtureHtml(), '2d', $drawAt))->toBeNull();
});

it('returns null for unsupported game codes', function () {
    $driver = new PcsoGovDriver;

    expect($driver->parse(pcsoGovFixtureHtml(), '6d', manilaDrawAt(2026, 5, 16, 21)))
        ->toBeNull()
        ->and($driver->parse(pcsoGovFixtureHtml(), '4d', manilaDrawAt(2026, 5, 15, 21)))
        ->toBeNull();
});

it('returns null for draw hours outside the PCSO 2PM/5PM/9PM schedule', function () {
    $driver = new PcsoGovDriver;
    // 6 AM and 9 AM — the bogus slots from the awaiting-draw cleanup
    expect($driver->parse(pcsoGovFixtureHtml(), '2d', manilaDrawAt(2026, 5, 17, 6)))
        ->toBeNull()
        ->and($driver->parse(pcsoGovFixtureHtml(), '2d', manilaDrawAt(2026, 5, 17, 9)))
        ->toBeNull();
});

it('does not pick up the jackpot column as winning numbers', function () {
    // Synthetic row whose COMBINATIONS cell is empty — the regex must NOT
    // fall back to the jackpot ("4,000.00" has no hyphens, so safe) or
    // confuse the date ("5/17/2026" uses /, not -).
    $broken = <<<'HTML'
        <table><tr>
            <td>2D Lotto 5PM</td><td>   </td><td>5/17/2026</td><td>4,000.00</td><td>396</td>
        </tr></table>
    HTML;

    $numbers = (new PcsoGovDriver)
        ->parse($broken, '2d', manilaDrawAt(2026, 5, 17, 17));

    expect($numbers)->toBeNull();
});

it('returns null on completely malformed input', function () {
    expect((new PcsoGovDriver)->parse('<html>no table</html>', '2d', manilaDrawAt(2026, 5, 17, 17)))
        ->toBeNull();
});

// ── pickFromRows() — the Playwright-sidecar JSON path ────────────────────────

/** @return list<array{game:string,date:string,numbers:list<int>}> */
function sidecarRowsFromFixture(): array
{
    // Same source-of-truth as the HTML fixture. Hand-rolled to mirror what
    // scraper/parse.mjs would extract from searchlottoresult.html.
    return [
        ['game' => 'Ultra Lotto 6/58', 'date' => '5/17/2026', 'numbers' => [39, 37, 35, 45, 16, 52]],
        ['game' => '3D Lotto 2PM', 'date' => '5/17/2026', 'numbers' => [5, 1, 9]],
        ['game' => '3D Lotto 5PM', 'date' => '5/17/2026', 'numbers' => [2, 5, 5]],
        ['game' => '3D Lotto 9PM', 'date' => '5/17/2026', 'numbers' => [4, 3, 1]],
        ['game' => '2D Lotto 2PM', 'date' => '5/17/2026', 'numbers' => [10, 9]],
        ['game' => '2D Lotto 5PM', 'date' => '5/17/2026', 'numbers' => [10, 28]],
        ['game' => '2D Lotto 9PM', 'date' => '5/17/2026', 'numbers' => [29, 19]],
        ['game' => '2D Lotto 2PM', 'date' => '5/16/2026', 'numbers' => [10, 12]],
        ['game' => '2D Lotto 9PM', 'date' => '5/16/2026', 'numbers' => [3, 6]],
        ['game' => '3D Lotto 2PM', 'date' => '5/16/2026', 'numbers' => [0, 6, 9]],
        ['game' => '2D Lotto 5PM', 'date' => '5/15/2026', 'numbers' => [13, 8]],
        ['game' => '3D Lotto 9PM', 'date' => '5/15/2026', 'numbers' => [6, 6, 0]],
    ];
}

it('picks the matching row from a sidecar JSON payload', function (
    string $game,
    int $hour,
    int $day,
    array $expected,
) {
    $numbers = (new PcsoGovDriver)
        ->pickFromRows(sidecarRowsFromFixture(), $game, manilaDrawAt(2026, 5, $day, $hour));

    expect($numbers)->toBe($expected);
})->with([
    '2D 2PM 5/17' => ['2d', 14, 17, [10, 9]],
    '2D 5PM 5/17' => ['2d', 17, 17, [10, 28]],
    '2D 9PM 5/17' => ['2d', 21, 17, [29, 19]],
    '3D 2PM 5/17' => ['3d', 14, 17, [5, 1, 9]],
    '3D 9PM 5/17' => ['3d', 21, 17, [4, 3, 1]],
    '2D 2PM 5/16' => ['2d', 14, 16, [10, 12]],
    '3D 9PM 5/15' => ['3d', 21, 15, [6, 6, 0]],
]);

it('pickFromRows returns null when no row matches the date/slot', function () {
    // 5/18 isn't in the rows
    $rows = sidecarRowsFromFixture();
    expect((new PcsoGovDriver)->pickFromRows($rows, '2d', manilaDrawAt(2026, 5, 18, 17)))
        ->toBeNull();
});

it('pickFromRows returns null for unsupported game codes', function () {
    $rows = sidecarRowsFromFixture();
    expect((new PcsoGovDriver)->pickFromRows($rows, '6d', manilaDrawAt(2026, 5, 17, 21)))
        ->toBeNull();
});

it('pickFromRows returns null when the matched row has the wrong number count', function () {
    $broken = [
        ['game' => '2D Lotto 5PM', 'date' => '5/17/2026', 'numbers' => [10, 28, 99]], // 3 ints for a 2D row
    ];

    expect((new PcsoGovDriver)->pickFromRows($broken, '2d', manilaDrawAt(2026, 5, 17, 17)))
        ->toBeNull();
});

it('pickFromRows returns null on empty rows', function () {
    expect((new PcsoGovDriver)->pickFromRows([], '2d', manilaDrawAt(2026, 5, 17, 17)))
        ->toBeNull();
});
