<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Settlement\BackfillDrawResultsAction;
use App\Actions\Settlement\ScrapeAndSettleAwaitingAction;
use App\Actions\Settlement\SettleDrawAction;
use App\Exceptions\DrawAlreadySettledException;
use App\Exceptions\DrawNotReadyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PublishDrawResultRequest;
use App\Models\Bet;
use App\Models\Draw;
use App\Models\DrawResult;
use App\Services\PcsoResultScraper;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin entry-point for publishing draw results and settling bets.
 *
 *  - GET  /admin/draws                  → list draws past their draw_at with
 *                                          no result yet ("awaiting")
 *  - GET  /admin/draws/{draw}/result    → show the publish form (number picker)
 *  - POST /admin/draws/{draw}/result    → validate + write DrawResult + run
 *                                          SettleDrawAction synchronously so
 *                                          the admin sees the outcome before
 *                                          leaving the page
 *
 * Settlement is sync (not queued) on purpose: this is real-money, the admin
 * must see how many bets paid and the total payout before navigating away.
 * Wrapped in DB::transaction so DrawResult + settle commit atomically.
 */
final class DrawResultController extends Controller
{
    public function index(Request $request): Response
    {
        $awaiting = Draw::query()
            ->where('status', '!=', 'settled')
            ->where('draw_at', '<=', now())
            ->whereDoesntHave('result')
            ->with(['game'])
            ->withCount(['bets as pending_bets_count' => function ($q) {
                $q->where('status', 'pending');
            }])
            ->orderByDesc('draw_at')
            ->limit(50)
            ->get();

        return Inertia::render('admin/draws/index', [
            'draws' => $awaiting->map(fn (Draw $d): array => [
                'id' => $d->id,
                'draw_at' => $d->draw_at->toIso8601String(),
                'cutoff_at' => $d->cutoff_at->toIso8601String(),
                'pending_bets_count' => (int) $d->pending_bets_count,
                'game' => [
                    'code' => $d->game->code,
                    'name' => $d->game->name,
                    'picks_count' => $d->game->picks_count,
                ],
            ])->values(),
        ]);
    }

    public function create(
        Request $request,
        Draw $draw,
        PcsoResultScraper $scraper,
    ): Response {
        $draw->loadMissing(['game', 'result']);

        if ($draw->result !== null) {
            return Inertia::location(route('admin.draws.index'));
        }

        $pendingBets = Bet::query()
            ->where('draw_id', $draw->id)
            ->where('status', 'pending')
            ->sum('potential_payout');

        // Best-effort: ask the scraper for the published numbers. If the
        // toggle is off / source is down / no match → null, form opens
        // empty (same as Option A).
        $suggestedNumbers = $scraper->fetchLatest(
            $draw->game->code,
            $draw->draw_at,
        );

        return Inertia::render('admin/draws/result', [
            'draw' => [
                'id' => $draw->id,
                'draw_at' => $draw->draw_at->toIso8601String(),
                'game' => [
                    'code' => $draw->game->code,
                    'name' => $draw->game->name,
                    'picks_count' => $draw->game->picks_count,
                    'number_min' => $draw->game->number_min,
                    'number_max' => $draw->game->number_max,
                ],
                'pending_bets_count' => Bet::query()
                    ->where('draw_id', $draw->id)
                    ->where('status', 'pending')
                    ->count(),
                'pending_potential_payout' => (string) $pendingBets,
            ],
            'suggested_numbers' => $suggestedNumbers,
            'suggestion_source' => $suggestedNumbers !== null
                ? $scraper->sourceLabel()
                : null,
        ]);
    }

    public function store(
        PublishDrawResultRequest $request,
        Draw $draw,
        SettleDrawAction $settle,
    ): RedirectResponse {
        $numbers = array_map('intval', (array) $request->validated('numbers'));

        try {
            $result = DB::transaction(function () use ($draw, $numbers, $settle) {
                DrawResult::query()->create([
                    'draw_id' => $draw->id,
                    'numbers' => $numbers,
                    'published_at' => now(),
                ]);

                return $settle->execute($draw->fresh());
            }, attempts: 3);
        } catch (DrawAlreadySettledException) {
            return back()->withErrors([
                'numbers' => 'This draw is already settled.',
            ])->withInput();
        } catch (DrawNotReadyException $e) {
            // Should be impossible — we just wrote the result row above.
            Log::channel('audit')->warning('draw.settle.unexpected_not_ready', [
                'draw_id' => $draw->id,
                'reason' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'numbers' => 'Could not settle the draw. Try again.',
            ])->withInput();
        }

        return redirect()
            ->route('admin.draws.index')
            ->with(
                'status',
                sprintf(
                    'Draw #%d settled — %d bet%s, %d winner%s, ₱%s paid out.',
                    $result->drawId,
                    $result->settledCount,
                    $result->settledCount === 1 ? '' : 's',
                    $result->wonCount,
                    $result->wonCount === 1 ? '' : 's',
                    $result->totalPayout,
                ),
            );
    }

    /**
     * Admin-triggered manual scrape of every awaiting draw via the same
     * PCSO scraper the auto-publish cron uses. Bypasses the
     * `scraper.auto_publish_enabled` toggle on purpose — an admin click
     * is explicit consent. Per-draw idempotency + range-validation still
     * apply.
     */
    public function scrape(
        Request $request,
        ScrapeAndSettleAwaitingAction $action,
    ): RedirectResponse {
        $summary = $action->execute();

        Log::channel('audit')->info('admin.draws.scrape', [
            'user_id' => $request->user()?->id,
            'settled_count' => $summary['settled_count'],
            'skipped_count' => $summary['skipped_count'],
            'total_payout' => $summary['total_payout'],
        ]);

        if ($summary['settled_count'] === 0 && $summary['skipped_count'] === 0) {
            return back()->with('status', 'No awaiting draws to scrape.');
        }

        return back()->with(
            'status',
            sprintf(
                'Scrape done — %d settled, %d skipped, ₱%s paid out.',
                $summary['settled_count'],
                $summary['skipped_count'],
                $summary['total_payout'],
            ),
        );
    }

    /**
     * Admin-triggered backfill of the last 7 days of PCSO results.
     * Upserts `DrawResult` rows for past draws in the range. DOES NOT
     * settle bets — sibling to `scrape()`, but read-mostly. Refuses to
     * overwrite numbers on already-settled draws.
     */
    public function backfill(
        Request $request,
        BackfillDrawResultsAction $action,
    ): RedirectResponse {
        $to = Carbon::now('Asia/Manila')->endOfDay();
        $from = $to->copy()->subDays(7)->startOfDay();

        $summary = $action->execute($from, $to);
        $c = $summary['counts'];

        Log::channel('audit')->info('admin.draws.backfill', [
            'user_id' => $request->user()?->id,
            'from' => $summary['from'],
            'to' => $summary['to'],
            'counts' => $c,
        ]);

        return back()->with(
            'status',
            sprintf(
                'Backfill done — %d created, %d updated, %d unchanged, %d skipped.',
                $c['created'],
                $c['updated'],
                $c['unchanged'],
                $c['skipped_no_match'] + $c['skipped_settled'] + $c['skipped_invalid'],
            ),
        );
    }
}
