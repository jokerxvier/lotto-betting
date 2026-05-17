<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Bet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TicketsController extends Controller
{
    private const RECENT_LIMIT = 100;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $bets = Bet::query()
            ->where('user_id', $user->id)
            ->with([
                'draw.game',
                'draw.result',
                'legs.betType',
            ])
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();

        return Inertia::render('tickets/index', [
            'tickets' => $bets->map(fn (Bet $bet) => $this->toCardPayload($bet))->values(),
        ]);
    }

    public function show(Request $request, Bet $bet): Response
    {
        if ($bet->user_id !== $request->user()->id) {
            throw new NotFoundHttpException;
        }

        $bet->load([
            'draw.game',
            'draw.result',
            'legs.betType',
        ]);

        return Inertia::render('tickets/show', [
            'ticket' => [
                ...$this->toCardPayload($bet),
                'legs' => $bet->legs->map(fn ($leg) => [
                    'id' => $leg->id,
                    'bet_type_code' => $leg->betType->code,
                    'bet_type_label' => $leg->betType->label,
                    'numbers' => $leg->numbers,
                    'amount' => $leg->amount,
                    'potential_payout' => $leg->potential_payout,
                    'payout' => $leg->payout,
                ])->values(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toCardPayload(Bet $bet): array
    {
        $game = $bet->draw->game;
        $result = $bet->draw->result;

        return [
            'id' => $bet->id,
            'status' => $bet->status,
            'amount' => $bet->amount,
            'potential_payout' => $bet->potential_payout,
            'placed_at' => $bet->created_at?->toIso8601String(),
            'settled_at' => $bet->settled_at?->toIso8601String(),
            'game' => [
                'code' => $game->code,
                'name' => $game->name,
                'picks_count' => $game->picks_count,
            ],
            'draw' => [
                'id' => $bet->draw->id,
                'draw_at' => $bet->draw->draw_at->toIso8601String(),
                'cutoff_at' => $bet->draw->cutoff_at->toIso8601String(),
                'status' => $bet->draw->status,
                'result_numbers' => $result?->numbers,
            ],
            // Single-leg UI for now; legs[] is full detail on /tickets/{bet}.
            'preview_leg' => $bet->legs->first() ? [
                'bet_type_code' => $bet->legs->first()->betType->code,
                'bet_type_label' => $bet->legs->first()->betType->label,
                'numbers' => $bet->legs->first()->numbers,
            ] : null,
        ];
    }
}
