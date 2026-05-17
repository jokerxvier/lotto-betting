<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Betting\BetLegIntent;
use App\Actions\Betting\PlaceBetAction;
use App\Actions\Betting\PlaceBetIntent;
use App\Exceptions\DrawClosedException;
use App\Exceptions\InvalidBetException;
use App\Http\Requests\Bet\PlaceCartRequest;
use App\Services\Exceptions\InsufficientFundsException;
use Brick\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Cart-style multi-bet submit. Each draft leg becomes its own Bet (one leg
 * each). The whole cart is atomic — if any leg's draw closed mid-flight or
 * the wallet can't cover the total, nothing is placed.
 */
final class CartController extends Controller
{
    public function store(
        PlaceCartRequest $request,
        PlaceBetAction $action,
    ): RedirectResponse {
        $validated = $request->validated();

        /** @var array<int, array{leg_token: string, draw_id: int|string, game_bet_type_id: int|string, numbers: array<int, int>, amount: string}> $rawLegs */
        $rawLegs = (array) $validated['legs'];

        $intents = array_map(
            fn (array $leg): PlaceBetIntent => new PlaceBetIntent(
                drawId: (int) $leg['draw_id'],
                idempotencyKey: 'cart:'.(string) $leg['leg_token'],
                legs: [
                    new BetLegIntent(
                        gameBetTypeId: (int) $leg['game_bet_type_id'],
                        numbers: array_map('intval', $leg['numbers']),
                        amount: Money::of((string) $leg['amount'], 'PHP'),
                    ),
                ],
            ),
            $rawLegs,
        );

        try {
            $placedIds = DB::transaction(function () use ($action, $request, $intents): array {
                $ids = [];
                foreach ($intents as $intent) {
                    $ids[] = $action->execute($request->user(), $intent)->id;
                }

                return $ids;
            });
        } catch (DrawClosedException $e) {
            return back()
                ->withErrors(['legs' => 'One of the draws closed. Remove it and try again.'])
                ->withInput();
        } catch (InsufficientFundsException) {
            return back()
                ->withErrors(['legs' => 'Insufficient funds for this cart.'])
                ->withInput();
        } catch (InvalidBetException $e) {
            return back()
                ->withErrors(['legs' => $e->getMessage()])
                ->withInput();
        }

        $count = count($placedIds);

        return redirect()
            ->route('tickets.index')
            ->with('status', $count === 1 ? '1 ticket placed.' : "{$count} tickets placed.");
    }
}
