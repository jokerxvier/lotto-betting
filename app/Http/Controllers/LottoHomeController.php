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

            $next = Draw::query()
                ->where('game_id', $game->id)
                ->where('status', 'scheduled')
                ->where('cutoff_at', '>', now())
                ->orderBy('cutoff_at')
                ->first();

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
                'next_draw_id' => $next?->id,
                'next_draw_at' => $next?->draw_at?->toIso8601String(),
                'next_cutoff_at' => $next?->cutoff_at?->toIso8601String(),
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
}
