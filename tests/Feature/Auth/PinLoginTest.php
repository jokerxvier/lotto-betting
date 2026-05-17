<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs in on a correct username + PIN and redirects to /lotto', function () {
    User::factory()->create(['username' => 'alice', 'pin_hash' => '472901']);

    $response = $this->post('/login', [
        'username' => 'alice',
        'password' => '472901',
    ]);

    $response->assertRedirect(route('lotto'));
    $this->assertAuthenticated();
});

it('shows "Invalid password." when the username exists but PIN is wrong', function () {
    User::factory()->create(['username' => 'alice', 'pin_hash' => '472901']);

    $response = $this->from('/login')->post('/login', [
        'username' => 'alice',
        'password' => '000000',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors(['password' => 'Invalid password.']);
    $this->assertGuest();
});

it('auto-creates a brand-new account on an unknown username', function () {
    expect(User::query()->count())->toBe(0);

    $response = $this->post('/login', [
        'username' => 'newcomer',
        'password' => '472901',
    ]);

    $response->assertRedirect(route('lotto'));
    $user = User::query()->where('username', 'newcomer')->firstOrFail();
    expect($user->name)->toBe('newcomer')
        ->and(Hash::check('472901', $user->pin_hash))->toBeTrue()
        ->and($user->wallet_code)->toHaveLength(8);
    $this->assertAuthenticatedAs($user);
});

it('lowercases and trims the submitted username before lookup', function () {
    User::factory()->create(['username' => 'alice', 'pin_hash' => '472901']);

    $response = $this->post('/login', [
        'username' => '  ALICE  ',
        'password' => '472901',
    ]);

    $response->assertRedirect(route('lotto'));
    $this->assertAuthenticated();
});

it('rejects weak PINs on the auto-create path', function (string $weak) {
    $response = $this->from('/login')->post('/login', [
        'username' => 'newcomer',
        'password' => $weak,
    ]);

    $response->assertSessionHasErrors();
    expect(User::query()->where('username', 'newcomer')->exists())->toBeFalse();
    $this->assertGuest();
})->with(['111111', '000000', '123456', '654321']);

it('rejects PINs that are not exactly 6 digits', function (string $bad) {
    $response = $this->from('/login')->post('/login', [
        'username' => 'newcomer',
        'password' => $bad,
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
})->with(['1234', '12345', '1234567', 'abcdef']);

it('throttles after 5 attempts within a minute per username+IP', function () {
    User::factory()->create(['username' => 'alice', 'pin_hash' => '472901']);

    for ($i = 0; $i < 5; $i++) {
        $this->from('/login')->post('/login', [
            'username' => 'alice',
            'password' => '000000',
        ]);
    }

    $response = $this->from('/login')->post('/login', [
        'username' => 'alice',
        'password' => '472901',
    ]);

    $response->assertStatus(429);
    $this->assertGuest();
});

it('shows the lockout message when locked_until is in the future', function () {
    User::factory()->create([
        'username' => 'alice',
        'pin_hash' => '472901',
        'locked_until' => now()->addMinutes(30),
    ]);

    $response = $this->from('/login')->post('/login', [
        'username' => 'alice',
        'password' => '472901',
    ]);

    $response->assertSessionHasErrors(['password' => 'Too many attempts. Try again in 30 minutes.']);
    $this->assertGuest();
});
