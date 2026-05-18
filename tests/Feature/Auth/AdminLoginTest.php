<?php

declare(strict_types=1);

use App\Models\User;

it('renders the admin login form for guests', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/admin-login'));
});

it('lets an admin sign in with username + password and redirects to dashboard', function () {
    $admin = User::factory()
        ->admin('Operator-2026-Strong')
        ->create(['username' => 'opsadmin']);

    $response = $this->post('/admin/login', [
        'username' => 'opsadmin',
        'password' => 'Operator-2026-Strong',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin);
});

it('rejects wrong password with a generic error', function () {
    User::factory()
        ->admin('Operator-2026-Strong')
        ->create(['username' => 'opsadmin']);

    $this->from('/admin/login')
        ->post('/admin/login', [
            'username' => 'opsadmin',
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('rejects unknown username with a generic error (no enumeration)', function () {
    $this->from('/admin/login')
        ->post('/admin/login', [
            'username' => 'nobody',
            'password' => 'anything',
        ])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('rejects a non-admin user even with correct password and logs them out', function () {
    // Player user with a `password` set (e.g. some legacy account that
    // somehow has both). is_admin is false so /admin/login must refuse.
    $player = User::factory()->create([
        'username' => 'player1',
        'is_admin' => false,
        'password' => bcrypt('Player-2026-Strong'),
    ]);

    $this->from('/admin/login')
        ->post('/admin/login', [
            'username' => 'player1',
            'password' => 'Player-2026-Strong',
        ])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('rejects requests missing username or password (form validation)', function () {
    $this->from('/admin/login')
        ->post('/admin/login', [])
        ->assertSessionHasErrors(['username', 'password']);
});
