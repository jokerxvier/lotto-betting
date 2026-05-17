<?php

declare(strict_types=1);

namespace App\Actions\Betting;

use App\Events\BetPlaced;
use App\Exceptions\DrawClosedException;
use App\Exceptions\InvalidBetException;
use App\Models\Bet;
use App\Models\BetLeg;
use App\Models\Draw;
use App\Models\GameBetType;
use App\Models\User;
use App\Services\PayoutCalculator;
use App\Services\WalletService;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Place a bet — the first real money-debiting verb in the domain.
 *
 * Inside one transaction (Hard Rule 3):
 *  - dedupes by `bets.idempotency_key`
 *  - locks + re-validates the draw against `cutoff_at` (Hard Rule 4)
 *  - computes each leg's potential payout via PayoutCalculator (snapshot
 *    stored on `bet_legs.potential_payout` — survives later admin payout
 *    edits per rules/BETTING_RULES.md §6)
 *  - debits the wallet through WalletService (which locks the row,
 *    enforces sufficiency, and writes the ledger entry)
 *  - dispatches BetPlaced
 *
 * Throws DrawClosedException / InvalidBetException / InsufficientFundsException
 * for the controller to surface as friendly form errors.
 */
final class PlaceBetAction
{
    public function __construct(
        private readonly PayoutCalculator $payouts,
        private readonly WalletService $wallets,
    ) {}

    public function execute(User $user, PlaceBetIntent $intent): Bet
    {
        return DB::transaction(function () use ($user, $intent): Bet {
            $existing = Bet::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $intent->idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing->load('legs');
            }

            $draw = Draw::query()->lockForUpdate()->findOrFail($intent->drawId);
            if ($draw->status !== 'scheduled' || $draw->cutoff_at->lessThanOrEqualTo(now())) {
                Log::channel('audit')->info('bet.rejected.draw_closed', [
                    'user_id' => $user->id,
                    'draw_id' => $draw->id,
                ]);

                throw new DrawClosedException('Draw is closed.');
            }

            $totalAmount = Money::of('0.00', 'PHP');
            $totalPayout = Money::of('0.00', 'PHP');
            $resolved = [];

            foreach ($intent->legs as $leg) {
                $type = GameBetType::query()->findOrFail($leg->gameBetTypeId);

                if (! $type->active || $type->game_id !== $draw->game_id) {
                    Log::channel('audit')->info('bet.rejected.invalid_type', [
                        'user_id' => $user->id,
                        'draw_id' => $draw->id,
                        'game_bet_type_id' => $type->id,
                    ]);

                    throw new InvalidBetException('Bet type does not belong to this draw.');
                }

                $payout = $this->payouts->potentialPayout($type, $leg->numbers, $leg->amount);

                $totalAmount = $totalAmount->plus($leg->amount);
                $totalPayout = $totalPayout->plus($payout);
                $resolved[] = ['type' => $type, 'leg' => $leg, 'payout' => $payout];
            }

            $bet = Bet::query()->create([
                'user_id' => $user->id,
                'draw_id' => $draw->id,
                'amount' => (string) $totalAmount->getAmount(),
                'potential_payout' => (string) $totalPayout->getAmount(),
                'status' => 'pending',
                'idempotency_key' => $intent->idempotencyKey,
            ]);

            foreach ($resolved as $entry) {
                BetLeg::query()->create([
                    'bet_id' => $bet->id,
                    'game_bet_type_id' => $entry['type']->id,
                    'numbers' => $entry['leg']->numbers,
                    'amount' => (string) $entry['leg']->amount->getAmount(),
                    'potential_payout' => (string) $entry['payout']->getAmount(),
                ]);
            }

            // Let WalletService handle locking + InsufficientFundsException;
            // a failed debit rolls back the bet + legs we just inserted.
            $this->wallets->debit(
                $user,
                (string) $totalAmount->getAmount(),
                'bet_debit',
                "bet_debit:{$intent->idempotencyKey}",
                $bet,
            );

            $logCtx = [
                'user_id' => $user->id,
                'bet_id' => $bet->id,
                'draw_id' => $draw->id,
                'amount' => (string) $totalAmount->getAmount(),
                'leg_count' => count($intent->legs),
            ];
            DB::afterCommit(function () use ($logCtx, $bet): void {
                Log::channel('audit')->info('bet.placed', $logCtx);
                BetPlaced::dispatch($bet);
            });

            return $bet->load('legs');
        }, attempts: 3);
    }
}
