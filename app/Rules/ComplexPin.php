<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects trivial PINs alongside a configurable digit-length check.
 *
 *  - all-same-digit (1111, 0000, …)
 *  - strictly ascending sequences  (1234, 0123, 23456, …)
 *  - strictly descending sequences (4321, 98765, …)
 *
 * Implements rules/SECURITY.md §1.2.
 */
final class ComplexPin implements ValidationRule
{
    public function __construct(
        public readonly int $min = 4,
        public readonly int $max = 6,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = sprintf('/^\d{%d,%d}$/', $this->min, $this->max);

        if (! is_string($value) || preg_match($pattern, $value) !== 1) {
            $fail(sprintf('The :attribute must be %d to %d digits.', $this->min, $this->max));

            return;
        }

        if (preg_match('/^(\d)\1+$/', $value) === 1) {
            $fail('The :attribute cannot use the same digit repeated.');

            return;
        }

        if ($this->isSequential($value)) {
            $fail('The :attribute cannot use a sequential pattern.');
        }
    }

    private function isSequential(string $value): bool
    {
        $digits = array_map('intval', str_split($value));
        $ascending = true;
        $descending = true;

        for ($i = 1, $n = count($digits); $i < $n; $i++) {
            $diff = $digits[$i] - $digits[$i - 1];
            $ascending = $ascending && $diff === 1;
            $descending = $descending && $diff === -1;
        }

        return $ascending || $descending;
    }
}
