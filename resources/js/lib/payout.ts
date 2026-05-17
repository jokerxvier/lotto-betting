/**
 * Browser-side mirror of `App\Services\PayoutCalculator` — purely for the
 * live preview while the user picks numbers. The server is authoritative;
 * if these ever diverge, trust the server.
 */

function factorial(n: number): number {
    let r = 1;

    for (let i = 2; i <= n; i++) {
        r *= i;
    }

    return r;
}

export function uniquePermutations(numbers: number[]): number {
    const counts: Record<number, number> = {};

    for (const n of numbers) {
        counts[n] = (counts[n] ?? 0) + 1;
    }

    let denom = 1;

    for (const c of Object.values(counts)) {
        denom *= factorial(c);
    }

    return Math.floor(factorial(numbers.length) / denom);
}

type PayoutInput = {
    strategy: 'fixed' | 'split_permutations' | string;
    baseBet: string; // "10.00"
    basePayout: string; // "5500.00"
    amount: string; // "10.00"
    numbers: number[];
};

/**
 * Returns the potential payout as a decimal string ("2750.00").
 * RoundingMode::DOWN to mirror the PHP calculator.
 */
export function computePayout({
    strategy,
    baseBet,
    basePayout,
    amount,
    numbers,
}: PayoutInput): string {
    const baseBetN = Number.parseFloat(baseBet);
    const basePayoutN = Number.parseFloat(basePayout);
    const amountN = Number.parseFloat(amount);

    if (
        Number.isNaN(baseBetN) ||
        Number.isNaN(basePayoutN) ||
        Number.isNaN(amountN) ||
        baseBetN <= 0
    ) {
        return '0.00';
    }

    const unitPayout =
        strategy === 'split_permutations' && numbers.length > 0
            ? Math.floor((basePayoutN * 100) / uniquePermutations(numbers)) /
              100
            : basePayoutN;

    const scale = Math.floor((amountN / baseBetN) * 100) / 100;

    const truncated = Math.floor(unitPayout * scale * 100) / 100;

    return truncated.toFixed(2);
}
