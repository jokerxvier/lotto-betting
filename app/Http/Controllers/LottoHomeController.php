<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Draw;
use App\Models\Game;
use App\Models\GameBetType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LottoHomeController extends Controller
{
    public function index(Request $request): Response
    {
        $games = Game::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        $cards = $games->map(function (Game $game): array {
            $latest = Draw::query()
                ->where('game_id', $game->id)
                ->where('status', 'settled')
                ->with('result')
                ->orderByDesc('draw_at')
                ->first();

            // All scheduled draws in the next 7-day window for this game.
            // The first one (by cutoff) is the "next" we surface as a
            // dedicated convenience; the full list powers the ADVANCE
            // bottom sheet so a user can bet on any future draw.
            $upcoming = Draw::query()
                ->where('game_id', $game->id)
                ->where('status', 'scheduled')
                ->where('cutoff_at', '>', now())
                ->where('draw_at', '<=', now()->addDays(7))
                ->orderBy('cutoff_at')
                ->get(['id', 'draw_at', 'cutoff_at', 'status']);

            $next = $upcoming->first();

            $betTypes = GameBetType::query()
                ->where('game_id', $game->id)
                ->where('active', true)
                ->orderBy('sort_order')
                ->get([
                    'id',
                    'code',
                    'label',
                    'base_bet_amount',
                    'base_payout_amount',
                    'payout_strategy',
                    'min_bet',
                    'max_bet',
                ]);

            $target = $betTypes->firstWhere('code', 'target');

            return [
                'id' => $game->id,
                'code' => $game->code,
                'name' => $game->name,
                'picks_count' => $game->picks_count,
                'number_min' => $game->number_min,
                'number_max' => $game->number_max,
                'payout_label' => $target
                    ? sprintf(
                        '₱%s bet wins ₱%s',
                        $this->formatPesoCompact($target->base_bet_amount),
                        $this->formatPesoCompact($target->base_payout_amount),
                    )
                    : null,
                'target_bet_type_id' => $target?->id,
                'bet_types' => $betTypes,
                'latest_result_numbers' => $latest?->result?->numbers,
                'latest_drawn_at' => $latest?->draw_at?->toIso8601String(),
                'latest_drawn_label' => $latest
                    ? $this->slotLabel((int) $latest->draw_at->setTimezone('Asia/Manila')->format('H'))
                    : null,
                'next_draw_id' => $next?->id,
                'next_draw_at' => $next?->draw_at?->toIso8601String(),
                'next_cutoff_at' => $next?->cutoff_at?->toIso8601String(),
                'upcoming_draws' => $upcoming->map(fn (Draw $d): array => [
                    'id' => $d->id,
                    'draw_at' => $d->draw_at->toIso8601String(),
                    'cutoff_at' => $d->cutoff_at->toIso8601String(),
                ])->values(),
            ];
        });

        return Inertia::render('lotto/home', [
            'games' => $cards->values(),
        ]);
    }

    /**
     * "10.00" → "10", "10.50" → "10.50", "5500.00" → "5,500".
     * Drops the decimal part only when the amount is a whole number.
     */
    private function formatPesoCompact(string $decimal): string
    {
        $float = (float) $decimal;
        $isWhole = $float == (int) $float;

        return number_format($float, $isWhole ? 0 : 2, '.', ',');
    }

    /**
     * 14 → "2PM", 17 → "5PM", 21 → "9PM". For non-canonical hours falls
     * back to "Hh AM/PM" — matches the JS `slotLabel()` helper's
     * canonical-slot table so server- and client-rendered labels agree.
     */
    private function slotLabel(int $hour): string
    {
        return match ($hour) {
            14 => '2PM',
            17 => '5PM',
            21 => '9PM',
            default => date('g\\A', mktime($hour, 0, 0)),
        };
    }
}
