<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use RuntimeException;

/**
 * Thrown by WalletService when a debit would overdraw the wallet. The
 * controller catches this and surfaces it as a friendly form error;
 * it carries no balance figures so it's safe to log the exception itself.
 */
final class InsufficientFundsException extends RuntimeException {}
