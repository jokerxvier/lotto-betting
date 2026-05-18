<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-controllable runtime toggles at `/admin/settings`.
 *
 * Toggles live in `SettingsService` (cache-backed), NOT `.env` — so an
 * admin flips them in one click without redeploy. See the request class
 * for the auto-publish confirmation gate.
 */
final class SettingsController extends Controller
{
    public function edit(Request $request, SettingsService $settings): Response
    {
        return Inertia::render('admin/settings/index', [
            'settings' => [
                'suggestions_enabled' => (bool) $settings->get(
                    'scraper.suggestions_enabled',
                    true,
                ),
                'auto_publish_enabled' => (bool) $settings->get(
                    'scraper.auto_publish_enabled',
                    false,
                ),
                'push_enabled' => (bool) $settings->get(
                    'telegram.push_enabled',
                    true,
                ),
            ],
            'source_label' => (string) config(
                'lotto.scraper.source_label',
                'lottopcso.com',
            ),
        ]);
    }

    public function update(
        UpdateSettingsRequest $request,
        SettingsService $settings,
    ): RedirectResponse {
        $settings->set(
            'scraper.suggestions_enabled',
            (bool) $request->boolean('suggestions_enabled'),
        );
        $settings->set(
            'scraper.auto_publish_enabled',
            (bool) $request->boolean('auto_publish_enabled'),
        );
        $settings->set(
            'telegram.push_enabled',
            (bool) $request->boolean('push_enabled'),
        );

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Settings updated.');
    }
}
