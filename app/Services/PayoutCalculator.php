<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameBetType;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use InvalidArgumentException;

/**
 * Computes the potential payout for a bet leg at placement time. Matches
 * rules/BETTING_RULES.md §3 — `target` is `amount × (base_payout / base_bet)`,
 * `rambol` divides that pool by the number of unique permutations of the
 * picked numbers. All divisions round DOWN (truncate) so a rounding glitch
 * can never cause an overpay.
 *
 * The value this returns is stored as `bet_legs.potential_payout` and is
 * the authoritative pay-on-win figure for that leg — even if an admin later
 * edits the bet type's payout settings.
 */
final class PayoutCalculator
{
    /**
     * @param  list<int>  $numbers
     */
    public function potentialPayout(GameBetType $type, array $numbers, Money $bet): Money
    {
        $this->guardNumbersMatchGame($type, $numbers);

        $basePayout = Money::of($type->base_payout_amount, 'PHP');
        $baseBet = Money::of($type->base_bet_amount, 'PHP');

        $unitPayout = match ($type->payout_strategy) {
            'fixed' => $basePayout,
            'split_permutations' => $basePayout->dividedBy(
                $this->uniquePermutations($numbers),
                RoundingMode::DOWN,
            ),
            default => throw new InvalidArgumentException(
                "Unknown payout_strategy: {$type->payout_strategy}",
            ),
        };

        // amount × (unitPayout / baseBet), each step truncated.
        $scale = $bet->dividedBy(
            $baseBet->getAmount()->toFloat(),
            RoundingMode::DOWN,
        );

        return $unitPayout->multipliedBy(
            $scale->getAmount()->toFloat(),
            RoundingMode::DOWN,
        );
    }

    /**
     * Standard multinomial: N! / (Πk!) for each unique digit's count k.
     *
     * @param  list<int>  $numbers
     */
    public function uniquePermutations(array $numbers): int
    {
        $counts = array_count_values($numbers);

        $denominator = 1;
        foreach ($counts as $c) {
            $denominator *= $this->factorial($c);
        }

        return intdiv($this->factorial(count($numbers)), $denominator);
    }

    private function factorial(int $n): int
    {
        $r = 1;
        for ($i = 2; $i <= $n; $i++) {
            $r *= $i;
        }

        return $r;
    }

    /**
     * @param  list<int>  $numbers
     */
    private function guardNumbersMatchGame(GameBetType $type, array $numbers): void
    {
        $game = $type->game;

        if (count($numbers) !== $game->picks_count) {
            throw new InvalidArgumentException(
                "Expected {$game->picks_count} picks, got ".count($numbers),
            );
        }

        foreach ($numbers as $n) {
            if ($n < $game->number_min || $n > $game->number_max) {
                throw new InvalidArgumentException(
                    "Pick {$n} outside range [{$game->number_min}, {$game->number_max}]",
                );
            }
        }
    }
}
