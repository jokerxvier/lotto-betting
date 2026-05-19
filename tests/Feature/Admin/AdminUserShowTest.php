<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('forbids non-admins from viewing a user details page', function (): void {
    $user = User::factory()->withWallet()->create();
    $target = User::factory()->withWallet()->create();

    $this->actingAs($user)
        ->get("/admin/users/{$target->id}")
        ->assertForbidden();
});

it('renders profile + wallet for an admin', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $target = User::factory()->withWallet('250.00')->create();

    $this->actingAs($admin)
        ->get("/admin/users/{$target->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/show')
            ->where('user.id', $target->id)
            ->where('wallet.balance', '250.00')
            ->where('can_adjust', true)
        );
});

it('reports can_adjust=false on the admin own page', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)
        ->get("/admin/users/{$admin->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_adjust', false)
        );
});

it('paginates recent transactions and hydrates the actor', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $target = User::factory()->withWallet('1000.00')->create();
    $wallets = app(WalletService::class);

    foreach (range(1, 5) as $i) {
        $wallets->credit(
            $target,
            '10.00',
            'admin_credit',
            "key-{$i}",
            reference: null,
            actorUserId: $admin->id,
            note: "credit #{$i}",
        );
    }

    $this->actingAs($admin)
        ->get("/admin/users/{$target->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 5)
            ->where('transactions.data.0.actor.id', $admin->id)
            ->where('transactions.data.0.actor.name', $admin->name)
        );
});
