<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by SettleDrawAction when no DrawResult exists for the draw.
 * The controller maps this to a 422-flavoured flash error.
 */
final class DrawNotReadyException extends RuntimeException {}
