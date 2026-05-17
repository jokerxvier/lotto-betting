<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Draw;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ResultsController extends Controller
{
    public function index(Request $request): Response
    {
        $from = now()->subDays(7)->startOfDay();
        $to = now()->addDay()->endOfDay();

        $draws = Draw::query()
            ->whereBetween('draw_at', [$from, $to])
            ->with(['game', 'result'])
            ->orderByDesc('draw_at')
            ->get();

        return Inertia::render('results/index', [
            'results' => $draws->map(function (Draw $draw): array {
                $state = $this->deriveState($draw);

                return [
                    'id' => $draw->id,
                    'draw_at' => $draw->draw_at->toIso8601String(),
                    'cutoff_at' => $draw->cutoff_at->toIso8601String(),
                    'state' => $state,
                    'numbers' => $draw->result?->numbers,
                    'game' => [
                        'code' => $draw->game->code,
                        'name' => $draw->game->name,
                        'picks_count' => $draw->game->picks_count,
                    ],
                ];
            })->values(),
        ]);
    }

    /**
     * `settled` — admin published numbers (result row exists).
     * `awaiting` — draw time has passed but the result isn't in yet.
     * `open`     — bet cutoff is still in the future.
     */
    private function deriveState(Draw $draw): string
    {
        if ($draw->result !== null || $draw->status === 'settled') {
            return 'settled';
        }

        if ($draw->cutoff_at->isFuture()) {
            return 'open';
        }

        return 'awaiting';
    }
}
