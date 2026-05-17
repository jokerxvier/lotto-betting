<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a bet is placed against a draw whose cutoff has passed or
 * whose status has moved past `scheduled`. Carries no payload — controller
 * maps to a friendly "Draw is closed" form error.
 */
final class DrawClosedException extends RuntimeException {}
