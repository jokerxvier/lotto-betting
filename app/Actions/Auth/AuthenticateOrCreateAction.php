<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Auth\Username;
use App\Models\User;
use App\Rules\ComplexPin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Combined login / sign-up entry point for the merged auth screen.
 *
 *  - Unknown username  → create the account with the supplied PIN (after
 *    enforcing ComplexPin). New account, logged in.
 *  - Known username    → verify the PIN. Wrong PIN → "Invalid password."
 *    Five wrong PINs within 15 min → 30-minute lockout on `users.locked_until`.
 *
 * NOTE: This action intentionally relaxes rules/UI_FLOWS.md §10
 * ("don't disclose whether the username exists") per product decision.
 * The trade-off is trivial username enumeration and a username-squatting
 * vector — any unowned username is created with the first PIN typed.
 */
final class AuthenticateOrCreateAction
{
    private const FAILURE_TTL_MINUTES = 15;

    private const LOCKOUT_MINUTES = 30;

    private const FAILURE_THRESHOLD = 5;

    public function execute(string $username, string $pin): User
    {
        $user = User::query()->where('username', $username)->first();

        return $user === null
            ? $this->create($username, $pin)
            : $this->authenticate($user, $pin);
    }

    private function create(string $username, string $pin): User
    {
        // Validation keys match the wire field names (`username` + `password`)
        // so the frontend's `errors.password` block surfaces weak-PIN errors.
        Validator::make(
            ['username' => $username, 'password' => $pin],
            [
                'username' => [
                    'required', 'string', 'lowercase',
                    'regex:'.Username::REGEX,
                    Rule::notIn(Username::RESERVED),
                    'unique:users,username',
                ],
                'password' => ['required', 'string', new ComplexPin(min: 6, max: 6)],
            ],
            [
                'username.regex' => 'Username must be 3-32 characters: lowercase letters, digits, or underscore.',
                'username.not_in' => 'That username is reserved. Please choose another.',
                'username.unique' => 'That username is taken.',
            ],
        )->validate();

        return DB::transaction(function () use ($username, $pin): User {
            $user = User::create([
                'username' => $username,
                'name' => $username,
                'pin_hash' => $pin,
                'status' => 'active',
            ]);

            $user->wallet()->create([
                'balance' => '0.00',
                'held_balance' => '0.00',
                'version' => 0,
            ]);

            Log::channel('audit')->info('auth.account.created', [
                'user_id' => $user->id,
                'source' => 'login_or_create',
                'ip' => request()?->ip(),
            ]);

            return $user;
        });
    }

    private function authenticate(User $user, string $pin): User
    {
        if ($user->pin_hash === null) {
            Log::channel('audit')->info('auth.pin.failure', [
                'user_id' => $user->id,
                'reason' => 'pin_not_set',
                'ip' => request()?->ip(),
            ]);

            throw ValidationException::withMessages([
                'password' => 'This account uses Telegram sign-in. Finish setup from there.',
            ]);
        }

        if ($user->isLocked()) {
            Log::channel('audit')->info('auth.pin.failure', [
                'user_id' => $user->id,
                'reason' => 'locked',
                'ip' => request()?->ip(),
            ]);

            throw ValidationException::withMessages([
                'password' => 'Too many attempts. Try again in 30 minutes.',
            ]);
        }

        if (! Hash::check($pin, $user->pin_hash)) {
            $this->recordFailure($user);

            throw ValidationException::withMessages([
                'password' => 'Invalid password.',
            ]);
        }

        $this->recordSuccess($user);

        return $user;
    }

    private function recordFailure(User $user): void
    {
        $key = $this->failureKey($user);
        // `Cache::add` is atomic on Redis / DB / array stores — seeds the key
        // only on first miss so `increment` then bumps it from 0 to N safely
        // under concurrent failures. Without this, two racing wrong-PIN
        // requests both read 3, both write 4, and the counter stalls.
        Cache::add($key, 0, now()->addMinutes(self::FAILURE_TTL_MINUTES));
        $count = (int) Cache::increment($key);

        Log::channel('audit')->info('auth.pin.failure', [
            'user_id' => $user->id,
            'reason' => 'pin_mismatch',
            'count' => $count,
            'ip' => request()?->ip(),
        ]);

        if ($count >= self::FAILURE_THRESHOLD) {
            $user->forceFill([
                'locked_until' => now()->addMinutes(self::LOCKOUT_MINUTES),
            ])->save();

            Cache::forget($key);

            Log::channel('audit')->info('auth.lockout.set', [
                'user_id' => $user->id,
                'until' => $user->locked_until?->toIso8601String(),
            ]);
        }
    }

    private function recordSuccess(User $user): void
    {
        Cache::forget($this->failureKey($user));

        if ($user->locked_until !== null) {
            $user->forceFill(['locked_until' => null])->save();

            Log::channel('audit')->info('auth.lockout.cleared', [
                'user_id' => $user->id,
            ]);
        }

        Log::channel('audit')->info('auth.pin.success', [
            'user_id' => $user->id,
            'ip' => request()?->ip(),
        ]);
    }

    private function failureKey(User $user): string
    {
        return "auth:pin:failures:{$user->id}";
    }
}
