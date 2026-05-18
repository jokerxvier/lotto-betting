<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tiny key-value store for admin-controllable runtime toggles.
 *
 * Backed by `Cache::forever()` on the configured cache driver (database in
 * production) — persists across restarts; clears on `php artisan cache:clear`.
 * That clear behavior is acceptable for low-traffic admin toggles where the
 * defaults are safe (we never default to "auto-publish ON"). If we ever need
 * stronger durability, swap the storage layer without changing the public
 * API.
 *
 * Every `set()` writes a `setting.changed` line to the `audit` channel so we
 * can trace who flipped what (Hard Rule 6: no PII, just key/old/new/user_id).
 */
final class SettingsService
{
    private const PREFIX = 'lotto.setting:';

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get(self::PREFIX.$key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $cacheKey = self::PREFIX.$key;
        $old = Cache::get($cacheKey);

        if ($old === $value) {
            return; // no-op; don't pollute the audit log
        }

        Cache::forever($cacheKey, $value);

        Log::channel('audit')->info('setting.changed', [
            'key' => $key,
            'old' => $old,
            'new' => $value,
            'user_id' => request()?->user()?->id,
            'ip' => request()?->ip(),
        ]);
    }

    public function forget(string $key): void
    {
        Cache::forget(self::PREFIX.$key);
    }
}
