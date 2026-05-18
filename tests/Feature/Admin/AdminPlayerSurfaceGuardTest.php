<?php

declare(strict_types=1);

use App\Models\User;

it('redirects admin from / to /admin', function () {
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));
});

it('redirects player from / to /lotto', function () {
    $player = User::factory()->withWallet()->create();

    $this->actingAs($player)->get('/')->assertRedirect(route('lotto'));
});

it('redirects admin from a player surface to /admin', function (string $path) {
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)
        ->get($path)
        ->assertRedirect(route('admin.dashboard'));
})->with(['/lotto', '/results', '/tickets', '/wallet']);

it('lets a player reach /lotto', function () {
    $player = User::factory()->withWallet()->create();

    $this->actingAs($player)->get('/lotto')->assertOk();
});
