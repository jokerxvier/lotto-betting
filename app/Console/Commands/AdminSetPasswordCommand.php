<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Bootstrap an admin password from the command line.
 *
 * The only intended way to set a `users.password` value for production
 * admins. Interactive (prompts + confirms) by default; pass `--password=`
 * for scripted deploys (tests + CI). Also sets `is_admin = true` if the
 * user wasn't already an admin.
 *
 * Min enforced in code: 6 chars. Complexity (uppercase / digits) is left
 * to operator discipline — production admins can pick a strong password
 * voluntarily, and an env-gated strict rule can be added later if needed.
 */
#[Signature('admin:set-password
                            {username : Username of the admin to (re-)set}
                            {--password= : Skip the prompt and use this password (CI only)}')]
#[Description('Interactively set the password for an admin user; promotes to admin if needed.')]
final class AdminSetPasswordCommand extends Command
{
    public function handle(): int
    {
        $username = (string) $this->argument('username');

        /** @var User|null $user */
        $user = User::query()->where('username', $username)->first();
        if ($user === null) {
            $this->error("No user with username [{$username}].");

            return self::FAILURE;
        }

        $password = $this->option('password');
        if (! is_string($password) || $password === '') {
            $password = (string) $this->secret('Password (min 6 chars)');
            $confirm = (string) $this->secret('Confirm password');

            if ($password !== $confirm) {
                $this->error('Passwords did not match.');

                return self::FAILURE;
            }
        }

        $issue = $this->validateStrength($password);
        if ($issue !== null) {
            $this->error($issue);

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'is_admin' => true,
        ])->save();

        Log::channel('audit')->info('admin.password.set', [
            'user_id' => $user->id,
            'username' => $user->username,
            'source' => 'admin:set-password',
        ]);

        $this->info("Password set for admin [{$user->username}] (id={$user->id}).");

        return self::SUCCESS;
    }

    private function validateStrength(string $pw): ?string
    {
        if (strlen($pw) < 6) {
            return 'Password must be at least 6 characters.';
        }

        return null;
    }
}
