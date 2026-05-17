<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a Telegram Login Widget payload fails HMAC verification
 * or freshness checks. See rules/SECURITY.md §1.1.
 *
 * Carries no payload details so it is safe to log the exception itself
 * without leaking the Telegram hash or other PII.
 */
final class InvalidTelegramPayloadException extends RuntimeException {}
