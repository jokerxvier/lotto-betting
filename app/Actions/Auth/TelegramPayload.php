<?php

declare(strict_types=1);

namespace App\Actions\Auth;

/**
 * Verified Telegram Login Widget payload — the subset of fields we trust
 * after HMAC + freshness checks pass.
 */
final readonly class TelegramPayload
{
    public function __construct(
        public int $id,
        public string $firstName,
        public ?string $username,
        public int $authDate,
    ) {}
}
