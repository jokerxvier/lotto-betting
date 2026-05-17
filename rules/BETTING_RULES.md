# BETTING_RULES.md

Configurable betting rules for Lotto PH. Defines bet types, payout math, the DB schema that backs them, win determination, and the admin configuration surface.

> Payout values here are **defaults seeded at install**. Everything is editable by admins at runtime — see §6.

---

## 1. Concepts

- **Game** — a draw format (e.g. 2D, 3D, 4D). Defines picks_count and number range.
- **Bet type** — a variant of a game with its own payout rules. Same game can have multiple bet types (e.g. 3D has `target` and `rambol`).
- **Leg** — one combination on one ticket. A ticket can have multiple legs.
- **Permutation count** — how many orderings of a number set are distinct. Drives rambol payout.

---

## 2. Bet Types — MVP Defaults

| Game | Bet Type | Label       | Base Bet | Base Payout | Payout Strategy        |
|------|----------|-------------|----------|-------------|------------------------|
| 2D   | `target` | Target      | ₱10      | ₱5,500      | `fixed`                |
| 2D   | `rambol` | Rambolito   | ₱10      | ₱5,500      | `split_permutations`   |
| 3D   | `target` | Target      | ₱10      | ₱6,000      | `fixed`                |
| 3D   | `rambol` | Rambolito   | ₱10      | ₱6,000      | `split_permutations`   |

**Where "base payout" comes in for rambol:** under `split_permutations` it represents the total pool, which is divided across permutations at payout time. So a rambol bet of ₱10 on `112` (3 permutations) pays `6000 / 3 = 2000`.

> The screenshot's `₱4,000` / `₱4,500` values were placeholder defaults — replace them when you seed with the table above.

---

## 3. Payout Math

```
target_payout(amount)        = amount × (base_payout / base_bet)
rambol_payout(amount, picks) = amount × (base_payout / base_bet) ÷ unique_permutations(picks)
```

Where `unique_permutations` is the standard multinomial:

```
unique_permutations(picks) = factorial(N) / Π factorial(count(d)) for each unique digit d
                                                  in picks
```

### Worked Examples

| Game | Picks  | Unique perms | Bet  | Target payout | Rambol payout |
|------|--------|--------------|------|---------------|---------------|
| 3D   | `1-2-3`| 3!/(1!1!1!) = 6 | ₱10 | ₱6,000 | ₱1,000 |
| 3D   | `1-1-2`| 3!/(2!1!) = 3   | ₱10 | ₱6,000 | ₱2,000 |
| 3D   | `1-1-1`| 3!/3! = 1       | ₱10 | ₱6,000 | ₱6,000 |
| 2D   | `1-4`  | 2!/(1!1!) = 2   | ₱10 | ₱5,500 | ₱2,750 |
| 2D   | `1-1`  | 2!/2! = 1       | ₱10 | ₱5,500 | ₱5,500 |
| 3D   | `2-2-2`| 1               | ₱20 | ₱12,000 | ₱12,000 |
| 3D   | `1-2-3`| 6               | ₱30 | ₱18,000 | ₱3,000 |

### Linearity in bet amount
All payouts scale linearly with the bet amount: `bet ₱20 on rambol 112 = 2 × ₱2,000 = ₱4,000`. The base_bet is the unit, not a minimum.

### Rounding
Use `RoundingMode::DOWN` (truncate) on every division. Better to leave 1¢ in the house than to overpay due to rounding. Cents are tracked exactly because everything is `decimal(14,2)` — see `LARAVEL_BEST_PRACTICES.md` §4.

---

## 4. Database Schema

Extends the model from `PLAN.md` §3.

### `games`
```php
Schema::create('games', function (Blueprint $table) {
    $table->id();
    $table->string('code', 16)->unique();          // '2d', '3d', '4d', …
    $table->string('name', 64);
    $table->unsignedTinyInteger('picks_count');
    $table->unsignedSmallInteger('number_min');
    $table->unsignedSmallInteger('number_max');
    $table->boolean('active')->default(true);
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();
});
```

### `game_bet_types`
```php
Schema::create('game_bet_types', function (Blueprint $table) {
    $table->id();
    $table->foreignId('game_id')->constrained()->restrictOnDelete();
    $table->string('code', 32);                    // 'target', 'rambol'
    $table->string('label', 64);                   // 'Target', 'Rambolito'
    $table->decimal('base_bet_amount', 10, 2);     // 10.00
    $table->decimal('base_payout_amount', 14, 2);  // 6000.00
    $table->string('payout_strategy', 32);         // 'fixed' | 'split_permutations'
    $table->decimal('min_bet', 10, 2)->default(10);
    $table->decimal('max_bet', 14, 2)->default(10000);
    $table->boolean('active')->default(true);
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();

    $table->unique(['game_id', 'code']);
});
```

### `bet_legs` — additions
```php
$table->foreignId('game_bet_type_id')->constrained()->restrictOnDelete();
// Already there:
// $table->json('numbers');
// $table->decimal('amount', 10, 2);
// $table->decimal('payout', 14, 2)->nullable();
```

### Seeder
```php
final class GameSeeder extends Seeder
{
    public function run(): void
    {
        $twoD = Game::create([
            'code' => '2d', 'name' => 'EZ2',
            'picks_count' => 2, 'number_min' => 1, 'number_max' => 31,
            'sort_order' => 1,
        ]);
        $threeD = Game::create([
            'code' => '3d', 'name' => 'Swertres',
            'picks_count' => 3, 'number_min' => 0, 'number_max' => 9,
            'sort_order' => 2,
        ]);

        GameBetType::create([
            'game_id' => $twoD->id, 'code' => 'target', 'label' => 'Target',
            'base_bet_amount' => '10.00', 'base_payout_amount' => '5500.00',
            'payout_strategy' => 'fixed', 'sort_order' => 1,
        ]);
        GameBetType::create([
            'game_id' => $twoD->id, 'code' => 'rambol', 'label' => 'Rambolito',
            'base_bet_amount' => '10.00', 'base_payout_amount' => '5500.00',
            'payout_strategy' => 'split_permutations', 'sort_order' => 2,
        ]);
        GameBetType::create([
            'game_id' => $threeD->id, 'code' => 'target', 'label' => 'Target',
            'base_bet_amount' => '10.00', 'base_payout_amount' => '6000.00',
            'payout_strategy' => 'fixed', 'sort_order' => 1,
        ]);
        GameBetType::create([
            'game_id' => $threeD->id, 'code' => 'rambol', 'label' => 'Rambolito',
            'base_bet_amount' => '10.00', 'base_payout_amount' => '6000.00',
            'payout_strategy' => 'split_permutations', 'sort_order' => 2,
        ]);
    }
}
```

---

## 5. PayoutCalculator Service

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameBetType;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use InvalidArgumentException;

final class PayoutCalculator
{
    /**
     * Compute potential payout for a bet leg at the time of placement.
     * This is what we store as bet_legs.potential_payout — the user sees it before confirming.
     */
    public function potentialPayout(GameBetType $type, array $numbers, Money $bet): Money
    {
        $this->guardNumbersMatchGame($type, $numbers);

        $scale = $bet->dividedBy(
            $type->base_bet_amount,
            RoundingMode::DOWN,
        )->getAmount()->toFloat(); // safe: small integer multiplier

        $unitPayout = match ($type->payout_strategy) {
            'fixed' => Money::of($type->base_payout_amount, 'PHP'),
            'split_permutations' => Money::of($type->base_payout_amount, 'PHP')
                ->dividedBy(
                    $this->uniquePermutations($numbers),
                    RoundingMode::DOWN,
                ),
            default => throw new InvalidArgumentException(
                "Unknown payout_strategy: {$type->payout_strategy}"
            ),
        };

        return $unitPayout->multipliedBy($scale, RoundingMode::DOWN);
    }

    /** Multinomial: N! / (Πk!) for each unique digit's count k. */
    private function uniquePermutations(array $numbers): int
    {
        $n = count($numbers);
        $counts = array_count_values($numbers);

        $denominator = 1;
        foreach ($counts as $c) {
            $denominator *= $this->factorial($c);
        }

        return intdiv($this->factorial($n), $denominator);
    }

    private function factorial(int $n): int
    {
        $r = 1;
        for ($i = 2; $i <= $n; $i++) {
            $r *= $i;
        }
        return $r;
    }

    private function guardNumbersMatchGame(GameBetType $type, array $numbers): void
    {
        $game = $type->game;
        if (count($numbers) !== $game->picks_count) {
            throw new InvalidArgumentException(
                "Expected {$game->picks_count} picks, got " . count($numbers)
            );
        }
        foreach ($numbers as $n) {
            if (! is_int($n) || $n < $game->number_min || $n > $game->number_max) {
                throw new InvalidArgumentException(
                    "Pick {$n} outside range [{$game->number_min}, {$game->number_max}]"
                );
            }
        }
    }
}
```

---

## 6. Admin Configuration

The admin can edit `game_bet_types` rows through the admin console at `/admin/games/{game}/bet-types`. Editable fields:

- `label`
- `base_bet_amount`
- `base_payout_amount`
- `payout_strategy`
- `min_bet`, `max_bet`
- `active`
- `sort_order`

### Rules
- **Audit log every change.** `Log::channel('audit')->info('bet_type.updated', [...])`.
- **No retroactive changes.** Already-placed bets store their potential_payout at the moment of placement (snapshot column). Editing the payout later does NOT change pending bets.
- **Dual control** for payout changes (per `SECURITY.md` §9): one admin edits, another approves before it goes live.

### Phase 2: effective windows
Add `effective_from` / `effective_to` columns so you can schedule promotional payouts (e.g. holiday boost) without manual ops at midnight.

```php
$table->timestamp('effective_from')->nullable();
$table->timestamp('effective_to')->nullable();
```

Resolution at bet-placement time: pick the bet type row where `now() BETWEEN effective_from AND effective_to AND active = true`. If multiple match, take the one with the latest `effective_from` (a more specific override beats the baseline).

---

## 7. Validation Rules (FormRequest)

```php
// StoreBetRequest::rules()
return [
    'draw_id' => ['required', Rule::exists('draws', 'id')->where('status', 'scheduled')],
    'idempotency_key' => ['required', 'uuid'],
    'legs' => ['required', 'array', 'min:1', 'max:20'],
    'legs.*.game_bet_type_id' => ['required', Rule::exists('game_bet_types', 'id')->where('active', true)],
    'legs.*.numbers' => ['required', 'array'],
    'legs.*.numbers.*' => ['required', 'integer'],
    'legs.*.amount' => ['required', 'numeric', 'decimal:0,2'],
];

public function withValidator(Validator $validator): void
{
    $validator->after(function (Validator $v) {
        foreach ($this->input('legs', []) as $i => $leg) {
            $type = GameBetType::with('game')->find($leg['game_bet_type_id'] ?? null);
            if (! $type) continue;

            // picks_count + range
            $game = $type->game;
            $nums = $leg['numbers'] ?? [];
            if (count($nums) !== $game->picks_count) {
                $v->errors()->add("legs.$i.numbers", "Expected {$game->picks_count} picks.");
            }
            foreach ($nums as $n) {
                if ($n < $game->number_min || $n > $game->number_max) {
                    $v->errors()->add("legs.$i.numbers", "Pick out of range.");
                }
            }

            // bet bounds
            $amount = (float) ($leg['amount'] ?? 0);
            if ($amount < (float) $type->min_bet || $amount > (float) $type->max_bet) {
                $v->errors()->add("legs.$i.amount", "Amount outside allowed range.");
            }

            // Rambol with all-identical digits: allow but UX should warn (see §10).
            // Don't block — math still works, it just equals target.
        }
    });
}
```

---

## 8. Win Determination

Computed by `SettleDrawJob` after results are published.

```php
final class WinChecker
{
    public function isWinner(BetLeg $leg, DrawResult $result): bool
    {
        $picked = $leg->numbers;     // ordered array
        $drawn  = $result->numbers;  // ordered array

        return match ($leg->gameBetType->code) {
            'target' => $picked === $drawn,
            'rambol' => $this->sorted($picked) === $this->sorted($drawn),
            default  => false,
        };
    }

    private function sorted(array $a): array
    {
        sort($a, SORT_NUMERIC);
        return $a;
    }
}
```

For exotic future bet types (e.g. "any-position-N"), add a new `code` and a new branch — the strategy belongs in `WinChecker`, not scattered across the codebase.

---

## 9. Tests

```php
// tests/Unit/PayoutCalculatorTest.php
use App\Services\PayoutCalculator;
use Brick\Money\Money;

beforeEach(function () {
    $this->calc = new PayoutCalculator();
});

dataset('payouts', [
    // [game, strategy, base_bet, base_payout, picks, bet, expected_payout]
    '3D target 123 ₱10' => ['3d', 'fixed',                10, 6000, [1,2,3], 10, 6000],
    '3D target 112 ₱10' => ['3d', 'fixed',                10, 6000, [1,1,2], 10, 6000],
    '3D rambol 123 ₱10' => ['3d', 'split_permutations',   10, 6000, [1,2,3], 10, 1000],
    '3D rambol 112 ₱10' => ['3d', 'split_permutations',   10, 6000, [1,1,2], 10, 2000],
    '3D rambol 111 ₱10' => ['3d', 'split_permutations',   10, 6000, [1,1,1], 10, 6000],
    '2D target 14 ₱10'  => ['2d', 'fixed',                10, 5500, [1,4],   10, 5500],
    '2D rambol 14 ₱10'  => ['2d', 'split_permutations',   10, 5500, [1,4],   10, 2750],
    '2D rambol 11 ₱10'  => ['2d', 'split_permutations',   10, 5500, [1,1],   10, 5500],
    '3D rambol 123 ₱30' => ['3d', 'split_permutations',   10, 6000, [1,2,3], 30, 3000],
    '3D target 222 ₱20' => ['3d', 'fixed',                10, 6000, [2,2,2], 20, 12000],
]);

it('computes payouts correctly', function (string $g, string $s, int $bb, int $bp, array $p, int $bet, int $exp) {
    $game = Game::factory()->code($g)->picksCount(count($p))->create();
    $type = GameBetType::factory()->for($game)->state([
        'base_bet_amount' => $bb, 'base_payout_amount' => $bp, 'payout_strategy' => $s,
    ])->create();

    $payout = $this->calc->potentialPayout($type, $p, Money::of($bet, 'PHP'));

    expect((string) $payout->getAmount())->toEqual(number_format($exp, 2, '.', ''));
})->with('payouts');
```

Plus integration tests for `SettleDrawJob` covering:
- Target winner credits exactly `potential_payout`.
- Rambol winner with `112` and draw `211` credits ₱2,000.
- Non-winners get status `lost` with no wallet movement.

---

## 10. UX Notes for the Bet Form

When the user selects **Rambolito** and picks numbers, show the computed payout live:

```tsx
const permutations = useMemo(() => uniquePermutations(picks), [picks]);
const rambolPayout = useMemo(
  () => splitPayout(betType.base_payout_amount, betType.base_bet_amount, amount, permutations),
  [betType, amount, permutations]
);

<p className="text-sm">
  Wins <span className="font-bold text-success">{formatPHP(rambolPayout)}</span>
  {permutations === 1 && (
    <span className="text-warning"> — same as Target, consider switching</span>
  )}
</p>
```

The all-same-digit warning is friendly, not blocking — let the user place the bet if they really want to.

---

## 11. Permutation Cheat Sheet (3D)

For quick mental math when supporting users:

| Pattern              | Count of unique perms | Rambol payout per ₱10 (base ₱6,000) |
|----------------------|-----------------------|-------------------------------------|
| All different (ABC)  | 6                     | ₱1,000                              |
| One pair (AAB / ABB) | 3                     | ₱2,000                              |
| All same (AAA)       | 1                     | ₱6,000 (== target)                  |

For 2D:

| Pattern              | Perms | Rambol payout per ₱10 (base ₱5,500) |
|----------------------|-------|-------------------------------------|
| Different (AB)       | 2     | ₱2,750                              |
| Same (AA)            | 1     | ₱5,500 (== target)                  |

---

## 12. Open Items

1. **Min/max bet per user per draw** — should this live on `game_bet_types` (current design) or per-user override on the user record? Most operators have a per-game cap + a global per-user cap.
2. **Payout caps per draw** — if too many people hit the same number, payout liability can blow past the operator's float. PCSO has historical precedent for capped jackpots. Worth a `max_payout_per_draw` column? Phase 2.
3. **Promotional bet types** — e.g. "Holiday Double" that pays 2× target on certain dates. Solvable with the `effective_from/to` columns in §6.
4. **Compound bets** — users selecting multiple numbers for a "system" bet that expands to all combinations? Defer to Phase 3 unless it's a launch requirement.
