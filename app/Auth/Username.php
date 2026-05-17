<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Shared username constraints — kept in one place so the FormRequest, the
 * combined login/sign-up action, and the frontend validators all agree.
 * The same regex lives in `resources/js/lib/auth.ts`; bump both together.
 */
final class Username
{
    public const REGEX = '/^[a-z0-9_]{3,32}$/';

    /** @var list<string> */
    public const RESERVED = [
        'admin',
        'root',
        'system',
        'api',
        'lotto',
        'pcso',
        'support',
        'staff',
    ];
}
