<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Betting\BetLegIntent;
use App\Actions\Betting\PlaceBetAction;
use App\Actions\Betting\PlaceBetIntent;
use App\Exceptions\DrawClosedException;
use App\Exceptions\InvalidBetException;
use App\Http\Requests\Bet\PlaceBetRequest;
use App\Services\Exceptions\InsufficientFundsException;
use Brick\Money\Money;
use Illuminate\Http\RedirectResponse;

final class BetController extends Controller
{
    public function store(
        PlaceBetRequest $request,
        PlaceBetAction $action,
        string $game,
    ): RedirectResponse {
        $validated = $request->validated();

        $intent = new PlaceBetIntent(
            drawId: (int) $validated['draw_id'],
            idempotencyKey: (string) $validated['idempotency_key'],
            legs: array_map(
                fn (array $leg) => new BetLegIntent(
                    gameBetTypeId: (int) $leg['game_bet_type_id'],
                    numbers: array_map('intval', $leg['numbers']),
                    amount: Money::of((string) $leg['amount'], 'PHP'),
                ),
                (array) $validated['legs'],
            ),
        );

        try {
            $bet = $action->execute($request->user(), $intent);
        } catch (DrawClosedException $e) {
            return back()->withErrors(['draw_id' => $e->getMessage()])->withInput();
        } catch (InsufficientFundsException) {
            return back()->withErrors(['amount' => 'Insufficient funds.'])->withInput();
        } catch (InvalidBetException $e) {
            return back()->withErrors(['legs' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('lotto')
            ->with('status', "Bet #{$bet->id} placed.");
    }
}
