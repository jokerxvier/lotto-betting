<?php

declare(strict_types=1);

use App\Models\User;

it('forbids non-admins', function () {
    $u = User::factory()->withWallet()->create();
    $this->actingAs($u)->get('/admin')->assertForbidden();
});

it('redirects guests to /login', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('renders the dashboard for admins', function () {
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard/index')
            ->has('stats.awaiting_count')
            ->has('stats.bets_today_count')
            ->has('stats.settled_today_payout')
            ->has('settings.suggestions_enabled')
            ->has('settings.auto_publish_enabled')
        );
});
