<?php

declare(strict_types=1);

use App\Models\BetLeg;
use App\Models\DrawResult;
use App\Models\GameBetType;
use App\Services\WinChecker;

beforeEach(function (): void {
    $this->checker = new WinChecker;
});

/**
 * Build a BetLeg with an in-memory GameBetType — no DB required.
 *
 * @param  list<int>  $numbers
 */
function legWith(string $code, array $numbers): BetLeg
{
    $type = new GameBetType;
    $type->code = $code;

    $leg = new BetLeg;
    $leg->numbers = $numbers;
    $leg->setRelation('betType', $type);

    return $leg;
}

/**
 * @param  list<int>  $numbers
 */
function resultWith(array $numbers): DrawResult
{
    $r = new DrawResult;
    $r->numbers = $numbers;

    return $r;
}

dataset('wins', [
    // [code, picked, drawn, expected]
    'target exact win 2D' => ['target', [1, 4], [1, 4], true],
    'target wrong order 2D' => ['target', [4, 1], [1, 4], false],
    'target exact win 3D' => ['target', [1, 2, 3], [1, 2, 3], true],
    'target leading zero 2D' => ['target', [0, 7], [0, 7], true],
    'rambol any-order win 3D' => ['rambol', [3, 2, 1], [1, 2, 3], true],
    'rambol exact-order win 3D' => ['rambol', [1, 2, 3], [1, 2, 3], true],
    'rambol all-same win 3D' => ['rambol', [1, 1, 1], [1, 1, 1], true],
    'rambol mismatch 3D' => ['rambol', [1, 2, 4], [1, 2, 3], false],
    'rambol 2D any-order' => ['rambol', [4, 1], [1, 4], true],
    'unknown bet type never wins' => ['mystery', [1, 4], [1, 4], false],
    'length mismatch loses' => ['target', [1, 2], [1, 2, 3], false],
    'empty picks loses' => ['target', [], [1, 2, 3], false],
]);

it('decides wins correctly across bet types', function (
    string $code,
    array $picked,
    array $drawn,
    bool $expected,
) {
    expect($this->checker->isWinner(legWith($code, $picked), resultWith($drawn)))
        ->toBe($expected);
})->with('wins');

it('returns false when the BetLeg has no loaded betType relation', function () {
    $leg = new BetLeg;
    $leg->numbers = [1, 4];

    expect($this->checker->isWinner($leg, resultWith([1, 4])))->toBeFalse();
});
