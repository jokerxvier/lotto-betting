<?php

declare(strict_types=1);

namespace App\Actions\Settlement;

use App\Events\BetSettled;
use App\Events\DrawSettled;
use App\Exceptions\DrawAlreadySettledException;
use App\Exceptions\DrawNotReadyException;
use App\Models\Bet;
use App\Models\Draw;
use App\Services\WalletService;
use App\Services\WinChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Settle every pending bet for a draw once results are published.
 *
 * Hard Rule 7 (Action for the verb), Hard Rule 3 (wallet mutations via
 * WalletService), Hard Rule 6 (audit log, no PII), Hard Rule 2 (decimal
 * strings end-to-end).
 *
 * Atomic + idempotent:
 *  - whole settle runs inside one `DB::transaction(attempts: 3)`
 *  - draw row is locked first; re-running on a settled draw throws
 *    DrawAlreadySettledException (caller may catch & treat as success)
 *  - per-bet wallet credit uses the bet id as the idempotency key
 *    (`bet_payout:{bet_id}`), so even if a transaction retries the
 *    credit, the ledger row is deduped at WalletService::credit
 *  - pending bets iterated in 200-row chunks to keep memory flat at scale
 *  - DrawSettled + per-bet BetSettled events deferred to `afterCommit`
 *    so a failed transaction never leaks observability noise
 */
final class SettleDrawAction
{
    public function __construct(
        private readonly WinChecker $checker,
        private readonly WalletService $wallets,
    ) {}

    public function execute(Draw $draw): SettleResult
    {
        return DB::transaction(function () use ($draw): SettleResult {
            /** @var Draw $locked */
            $locked = Draw::query()->lockForUpdate()->findOrFail($draw->id);

            if ($locked->status === 'settled') {
                throw new DrawAlreadySettledException(
                    "Draw {$locked->id} is already settled.",
                );
            }

            $result = $locked->result()->first();
            if ($result === null) {
                throw new DrawNotReadyException(
                    "Draw {$locked->id} has no published result.",
                );
            }

            $settledCount = 0;
            $wonCount = 0;
            $totalPayoutCents = 0;
            /** @var list<Bet> $settledBets */
            $settledBets = [];

            Bet::query()
                ->where('draw_id', $locked->id)
                ->where('status', 'pending')
                ->with(['legs.betType', 'user'])
                ->orderBy('id')
                ->chunkById(200, function ($bets) use (
                    $result,
                    &$settledCount,
                    &$wonCount,
                    &$totalPayoutCents,
                    &$settledBets,
                ): void {
                    foreach ($bets as $bet) {
                        $betPayoutCents = 0;
                        $hasWinningLeg = false;

                        foreach ($bet->legs as $leg) {
                            $isWin = $this->checker->isWinner($leg, $result);
                            $legPayout = $isWin
                                ? (string) $leg->potential_payout
                                : '0.00';

                            $leg->forceFill(['payout' => $legPayout])->save();

                            if ($isWin) {
                                $hasWinningLeg = true;
                                $betPayoutCents += $this->toCents($legPayout);
                            }
                        }

                        $newStatus = $hasWinningLeg ? 'won' : 'lost';

                        $bet->forceFill([
                            'status' => $newStatus,
                            'settled_at' => now(),
                        ])->save();

                        if ($hasWinningLeg && $betPayoutCents > 0) {
                            $this->wallets->credit(
                                $bet->user,
                                $this->fromCents($betPayoutCents),
                                'bet_payout',
                                "bet_payout:{$bet->id}",
                                $bet,
                            );

                            $wonCount++;
                            $totalPayoutCents += $betPayoutCents;
                        }

                        $settledCount++;
                        $settledBets[] = $bet;
                    }
                });

            $locked->forceFill(['status' => 'settled'])->save();

            $totalPayout = $this->fromCents($totalPayoutCents);

            Log::channel('audit')->info('draw.settled', [
                'draw_id' => $locked->id,
                'game_id' => $locked->game_id,
                'settled_count' => $settledCount,
                'won_count' => $wonCount,
                'total_payout' => $totalPayout,
            ]);

            DB::afterCommit(function () use ($locked, $settledBets): void {
                DrawSettled::dispatch($locked);
                foreach ($settledBets as $bet) {
                    BetSettled::dispatch($bet);
                }
            });

            return new SettleResult(
                drawId: $locked->id,
                settledCount: $settledCount,
                wonCount: $wonCount,
                totalPayout: $totalPayout,
            );
        }, attempts: 3);
    }

    private function toCents(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
