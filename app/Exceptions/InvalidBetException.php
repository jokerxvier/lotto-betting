<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when bet validation fails inside PlaceBetAction (defensive — the
 * FormRequest should catch most cases first). Examples: bet type doesn't
 * belong to the draw's game, bet type is inactive, leg amount out of
 * configured min/max bounds.
 */
final class InvalidBetException extends RuntimeException {}
