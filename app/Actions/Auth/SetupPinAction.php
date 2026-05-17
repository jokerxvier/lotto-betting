<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finish account setup for a Telegram-linked user that has no PIN/username
 * yet. Caller (SetupPinController) validates uniqueness + complexity via
 * the FormRequest before calling.
 */
final class SetupPinAction
{
    public function execute(User $user, string $username, string $pin): User
    {
        return DB::transaction(function () use ($user, $username, $pin): User {
            $user->forceFill([
                'username' => $username,
                'pin_hash' => $pin,
                'name' => $user->name ?? $username,
            ])->save();

            Log::channel('audit')->info('auth.pin.setup', [
                'user_id' => $user->id,
                'source' => 'telegram',
            ]);

            return $user->refresh();
        });
    }
}
