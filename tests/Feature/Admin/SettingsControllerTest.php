<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

it('forbids non-admins from viewing settings', function () {
    $u = User::factory()->withWallet()->create();
    $this->actingAs($u)->get('/admin/settings')->assertForbidden();
});

it('forbids non-admins from updating settings', function () {
    $u = User::factory()->withWallet()->create();
    $this->actingAs($u)->post('/admin/settings', [
        'suggestions_enabled' => false,
        'auto_publish_enabled' => false,
        'push_enabled' => false,
    ])->assertForbidden();
});

it('renders current settings with sane defaults', function () {
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/index')
            ->where('settings.suggestions_enabled', true)
            ->where('settings.auto_publish_enabled', false)
            ->where('settings.push_enabled', true)
            ->has('source_label')
        );
});

it('allows flipping suggestions_enabled without confirmation', function () {
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)
        ->post('/admin/settings', [
            'suggestions_enabled' => false,
            'auto_publish_enabled' => false,
            'push_enabled' => true,
        ])
        ->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHas('status');

    expect((new SettingsService)->get('scraper.suggestions_enabled'))->toBeFalse();
});

it('refuses to flip auto_publish_enabled ON without confirm_auto_publish=true', function () {
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)
        ->from('/admin/settings')
        ->post('/admin/settings', [
            'suggestions_enabled' => true,
            'auto_publish_enabled' => true,
            'push_enabled' => true,
            // confirm_auto_publish missing
        ])
        ->assertSessionHasErrors('confirm_auto_publish');

    expect((new SettingsService)->get('scraper.auto_publish_enabled', false))->toBeFalse();
});

it('accepts auto_publish_enabled ON when confirm_auto_publish=true', function () {
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)
        ->post('/admin/settings', [
            'suggestions_enabled' => true,
            'auto_publish_enabled' => true,
            'confirm_auto_publish' => true,
            'push_enabled' => true,
        ])
        ->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHas('status');

    expect((new SettingsService)->get('scraper.auto_publish_enabled'))->toBeTrue();
});

it('lets admin flip auto_publish back off without confirmation', function () {
    (new SettingsService)->set('scraper.auto_publish_enabled', true);
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)
        ->post('/admin/settings', [
            'suggestions_enabled' => true,
            'auto_publish_enabled' => false,
            'push_enabled' => true,
        ])
        ->assertRedirect(route('admin.settings.edit'));

    expect((new SettingsService)->get('scraper.auto_publish_enabled'))->toBeFalse();
});

it('flips push_enabled', function () {
    $admin = User::factory()->admin()->withWallet()->create();

    $this->actingAs($admin)
        ->post('/admin/settings', [
            'suggestions_enabled' => true,
            'auto_publish_enabled' => false,
            'push_enabled' => false,
        ])
        ->assertRedirect(route('admin.settings.edit'));

    expect((new SettingsService)->get('telegram.push_enabled'))->toBeFalse();
});
