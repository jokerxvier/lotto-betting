<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('forbids non-admins from viewing the user list', function (): void {
    $user = User::factory()->withWallet()->create();

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});

it('lists users paginated for an admin', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    User::factory()->count(30)->withWallet('25.00')->create();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users.data', 25)
            ->where('filters.search', '')
        );
});

it('filters users by username substring', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $alice = User::factory()->withWallet()->state(['username' => 'alice_target'])->create();
    User::factory()->withWallet()->state(['username' => 'bob_unrelated'])->create();

    $this->actingAs($admin)
        ->get('/admin/users?search=alice')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->where('filters.search', 'alice')
            ->has('users.data', 1)
            ->where('users.data.0.id', $alice->id)
        );
});

it('filters users by wallet code', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $needle = User::factory()->withWallet()->create();

    $this->actingAs($admin)
        ->get('/admin/users?search='.$needle->wallet_code)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $needle->id)
        );
});

it('filters users by exact telegram id when search is numeric', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $needle = User::factory()->withWallet()->telegramLinked(987654321)->create();
    User::factory()->withWallet()->telegramLinked(123456789)->create();

    $this->actingAs($admin)
        ->get('/admin/users?search=987654321')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $needle->id)
        );
});

it('exposes balance and held_balance strings per row', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $player = User::factory()->withWallet('123.45')->create();

    $this->actingAs($admin)
        ->get('/admin/users?search='.$player->wallet_code)
        ->assertInertia(fn ($page) => $page
            ->where('users.data.0.balance', '123.45')
            ->where('users.data.0.held_balance', '0.00')
        );
});
