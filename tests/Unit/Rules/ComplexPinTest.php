<?php

declare(strict_types=1);

use App\Rules\ComplexPin;

/**
 * Returns the list of validation errors a given PIN produces, using the
 * supplied rule instance (default = 4–6 digits per SECURITY.md §1.2).
 */
function validatePin(mixed $value, ?ComplexPin $rule = null): array
{
    $errors = [];
    ($rule ?? new ComplexPin)->validate('pin', $value, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });

    return $errors;
}

it('accepts non-trivial PINs in the default 4–6 digit range', function (string $pin) {
    expect(validatePin($pin))->toBeEmpty();
})->with(['4729', '8016', '5028', '90213', '748206']);

it('rejects PINs outside the configured digit length', function (mixed $pin) {
    expect(validatePin($pin))->not->toBeEmpty();
})->with(['123', '1234567', 'abcd', '12a4', '', 1234]);

it('rejects PINs that repeat the same digit', function (string $pin) {
    expect(validatePin($pin))->not->toBeEmpty();
})->with(['1111', '0000', '99999', '777777']);

it('rejects strictly ascending PINs', function (string $pin) {
    expect(validatePin($pin))->not->toBeEmpty();
})->with(['1234', '0123', '4567', '23456', '012345']);

it('rejects strictly descending PINs', function (string $pin) {
    expect(validatePin($pin))->not->toBeEmpty();
})->with(['4321', '9876', '98765', '543210']);

it('enforces a custom min/max when constructed with arguments', function () {
    $rule = new ComplexPin(min: 6, max: 6);

    expect(validatePin('4729', $rule))->not->toBeEmpty()    // too short
        ->and(validatePin('472901', $rule))->toBeEmpty()
        ->and(validatePin('1234567', $rule))->not->toBeEmpty(); // too long
});
