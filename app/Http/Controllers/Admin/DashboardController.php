<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bet;
use App\Models\Draw;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin landing at `/admin`. Stat strip + quick-link cards to every
 * admin tool we ship today (awaiting draws, settings, wallet top-up).
 * Cheap queries — render is fast regardless of bet volume.
 */
final class DashboardController extends Controller
{
    public function index(Request $request, SettingsService $settings): Response
    {
        $awaitingCount = Draw::query()
            ->where('status', '!=', 'settled')
            ->where('draw_at', '<=', now())
            ->whereDoesntHave('result')
            ->count();

        $betsTodayCount = Bet::query()
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $settledTodayPayout = (string) Bet::query()
            ->where('status', 'won')
            ->whereDate('settled_at', now()->toDateString())
            ->sum('potential_payout');

        return Inertia::render('admin/dashboard/index', [
            'stats' => [
                'awaiting_count' => $awaitingCount,
                'bets_today_count' => $betsTodayCount,
                'settled_today_payout' => $settledTodayPayout,
            ],
            'settings' => [
                'suggestions_enabled' => (bool) $settings->get(
                    'scraper.suggestions_enabled',
                    true,
                ),
                'auto_publish_enabled' => (bool) $settings->get(
                    'scraper.auto_publish_enabled',
                    false,
                ),
            ],
        ]);
    }
}
