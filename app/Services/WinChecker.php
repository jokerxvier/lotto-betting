<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BetLeg;
use App\Models\DrawResult;

/**
 * Pure win-determination per `rules/BETTING_RULES.md` §8.
 *
 *  - `target`  → exact-position match (picked === drawn, order matters)
 *  - `rambol`  → any-order match (sorted equality)
 *  - anything else defaults to false so an unknown bet type can never
 *    accidentally pay out.
 *
 * Stateless, no DB access. The caller (SettleDrawAction) is responsible
 * for loading the BetLeg with its `betType` relation; this service just
 * reads `code`.
 */
final class WinChecker
{
    public function isWinner(BetLeg $leg, DrawResult $result): bool
    {
        /** @var list<int> $picked */
        $picked = array_values(array_map('intval', (array) $leg->numbers));
        /** @var list<int> $drawn */
        $drawn = array_values(array_map('intval', (array) $result->numbers));

        if (count($picked) === 0 || count($picked) !== count($drawn)) {
            return false;
        }

        // Read the relation only if eager-loaded — never trigger lazy load
        // from a pure service. SettleDrawAction always loads `betType`.
        $code = $leg->relationLoaded('betType')
            ? (string) ($leg->getRelation('betType')?->code ?? '')
            : '';

        return match ($code) {
            'target' => $picked === $drawn,
            'rambol' => $this->sorted($picked) === $this->sorted($drawn),
            default => false,
        };
    }

    /**
     * @param  list<int>  $a
     * @return list<int>
     */
    private function sorted(array $a): array
    {
        sort($a, SORT_NUMERIC);

        return $a;
    }
}
