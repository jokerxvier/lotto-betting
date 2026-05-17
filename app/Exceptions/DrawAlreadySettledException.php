<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by SettleDrawAction when a settled draw is re-submitted.
 * Re-runs are safe (idempotent) but the controller surfaces a friendly
 * "Already settled" message rather than silently no-op'ing.
 */
final class DrawAlreadySettledException extends RuntimeException {}
