<?php

declare(strict_types=1);

use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    $this->settings = new SettingsService;
});

it('returns the default when no value is stored', function () {
    expect($this->settings->get('scraper.suggestions_enabled', true))->toBeTrue()
        ->and($this->settings->get('scraper.suggestions_enabled', false))->toBeFalse()
        ->and($this->settings->get('missing.key'))->toBeNull();
});

it('round-trips booleans', function () {
    $this->settings->set('scraper.suggestions_enabled', false);

    expect($this->settings->get('scraper.suggestions_enabled', true))->toBeFalse();
});

it('round-trips arbitrary scalar types', function () {
    $this->settings->set('lotto.window_days', 14);
    $this->settings->set('lotto.source_label', 'lottopcso.com');

    expect($this->settings->get('lotto.window_days'))->toBe(14)
        ->and($this->settings->get('lotto.source_label'))->toBe('lottopcso.com');
});

it('persists across SettingsService instances (same backing cache)', function () {
    (new SettingsService)->set('foo', 'bar');

    expect((new SettingsService)->get('foo'))->toBe('bar');
});

it('forgets a key', function () {
    $this->settings->set('foo', 'bar');
    $this->settings->forget('foo');

    expect($this->settings->get('foo', 'default'))->toBe('default');
});

it('is a no-op when set() is called with the existing value', function () {
    $this->settings->set('foo', 'bar');

    // Same value again — should not duplicate audit logs (we infer by
    // ensuring the value is unchanged after a re-set; behavior tested via
    // SettingsControllerTest will assert log count).
    $this->settings->set('foo', 'bar');

    expect($this->settings->get('foo'))->toBe('bar');
});
