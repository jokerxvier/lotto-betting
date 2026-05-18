<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('errors when no such user', function () {
    $this->artisan('admin:set-password', ['username' => 'nobody'])
        ->expectsOutputToContain('No user with username')
        ->assertFailed();
});

it('sets password + promotes a non-admin user with --password flag', function () {
    $user = User::factory()->create(['username' => 'opsguy', 'is_admin' => false]);

    $this->artisan('admin:set-password', [
        'username' => 'opsguy',
        '--password' => 'Operator-2026-Strong',
    ])->assertSuccessful();

    $fresh = $user->fresh();
    expect($fresh->is_admin)->toBeTrue()
        ->and(Hash::check('Operator-2026-Strong', $fresh->password))->toBeTrue();
});

it('accepts a simple 6-char password (no complexity required)', function () {
    $user = User::factory()->create(['username' => 'opsguy', 'is_admin' => false]);

    $this->artisan('admin:set-password', [
        'username' => 'opsguy',
        '--password' => 'pinpin',
    ])->assertSuccessful();

    expect(Hash::check('pinpin', $user->fresh()->password))->toBeTrue();
});

it('rejects passwords shorter than 6 chars', function () {
    User::factory()->create(['username' => 'opsguy']);

    $this->artisan('admin:set-password', [
        'username' => 'opsguy',
        '--password' => 'abc12',
    ])
        ->expectsOutputToContain('at least 6 characters')
        ->assertFailed();
});
