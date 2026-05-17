<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('redirects guests to /login', function () {
    $this->get('/auth/setup-pin')->assertRedirect('/login');
});

it('shows the setup form to a Telegram-onboarded user with no PIN', function () {
    $user = User::factory()->telegramOnly()->create(['name' => 'Jane']);
    $this->actingAs($user);

    $response = $this->get('/auth/setup-pin');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/setup-pin')
        ->where('first_name', 'Jane')
        ->where('has_telegram', true)
    );
});

it('redirects to /lotto if the user has already completed setup', function () {
    $user = User::factory()->create(['username' => 'alice', 'pin_hash' => '472901']);
    $this->actingAs($user);

    $this->get('/auth/setup-pin')->assertRedirect(route('lotto'));
});

it('completes setup on a valid POST and redirects to /lotto', function () {
    $user = User::factory()->telegramOnly()->create(['name' => 'Jane']);
    $this->actingAs($user);

    $response = $this->post('/auth/setup-pin', [
        'username' => 'jane_t',
        'pin' => '472901',
        'pin_confirmation' => '472901',
    ]);

    $response->assertRedirect(route('lotto'));
    $fresh = $user->fresh();
    expect($fresh->username)->toBe('jane_t')
        ->and(Hash::check('472901', $fresh->pin_hash))->toBeTrue()
        ->and($fresh->name)->toBe('Jane'); // preserved from Telegram
});

it('forbids POST when setup is already complete', function () {
    $user = User::factory()->create(['username' => 'alice', 'pin_hash' => '472901']);
    $this->actingAs($user);

    $this->post('/auth/setup-pin', [
        'username' => 'newname',
        'pin' => '802143',
        'pin_confirmation' => '802143',
    ])->assertForbidden();
});

it('rejects weak PINs at setup', function () {
    $user = User::factory()->telegramOnly()->create();
    $this->actingAs($user);

    $response = $this->from('/auth/setup-pin')->post('/auth/setup-pin', [
        'username' => 'jane_t',
        'pin' => '111111',
        'pin_confirmation' => '111111',
    ]);

    $response->assertSessionHasErrors('pin');
});

it('rejects PINs that are not exactly 6 digits at setup', function () {
    $user = User::factory()->telegramOnly()->create();
    $this->actingAs($user);

    $response = $this->from('/auth/setup-pin')->post('/auth/setup-pin', [
        'username' => 'jane_t',
        'pin' => '4729',
        'pin_confirmation' => '4729',
    ]);

    $response->assertSessionHasErrors('pin');
});

it('rejects a duplicate username at setup', function () {
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->telegramOnly()->create();
    $this->actingAs($user);

    $response = $this->from('/auth/setup-pin')->post('/auth/setup-pin', [
        'username' => 'taken',
        'pin' => '472901',
        'pin_confirmation' => '472901',
    ]);

    $response->assertSessionHasErrors('username');
});
