# Lotto PH — Phase 0 Bootstrap Prompt

> Paste this into Claude Code at the start of the first working session. The project should already have `CLAUDE.md` and `rules/*.md` at the repo root — nothing else.

---

## Context

You're bootstrapping **Lotto PH**, a Philippine real-money lotto betting web app (PCSO-style: 2D / EZ2 and 3D / Swertres). Detailed conventions live in `CLAUDE.md` (orientation) and `rules/*.md` (eight detailed docs). **This is a real-money product** — wallet integrity, idempotency, and audit logging are P0.

## Before you write a single line of code

1. **Read `CLAUDE.md` in full.** It defines seven hard rules that cannot be violated. Internalize them.
2. **Load these rule docs for this session:**
   - `rules/PLAN.md` — schema, routes, phase plan
   - `rules/LARAVEL_BEST_PRACTICES.md` — Actions, Services, money handling, locking
   - `rules/SECURITY.md` — §1–2 (auth, wallet integrity), §8 (deposits)
   - `rules/BETTING_RULES.md` — schema, payout math, win logic
   - `rules/THEME.md` — §1–2 (setup + tokens) only; full file when doing UI later
   - `rules/GIT_COMMIT.md` — commit format
3. **Skim file headers** of `rules/REACT_BEST_PRACTICES.md` and `rules/UI_FLOWS.md` so you know what's there for later sessions.
4. **Make a todo plan** for the work below before touching files. Confirm with me if any step needs a decision you can't make alone.

## Your task: Phase 0

Bootstrap the project to a **green-CI baseline with the architectural reference implementation in place**. The point is to lay down a canonical example of every layer so every future feature follows the same shape. Work in this order, committing after each step.

### Step 1 — Fresh Laravel 13 install
- Laravel 13 with the Inertia v2 + React + TypeScript starter.
- `composer require`: `brick/money`, `inertiajs/inertia-laravel`, `tightenco/ziggy`.
- `composer require --dev`: `pestphp/pest`, `pestphp/pest-plugin-laravel`, `larastan/larastan` (PHPStan level 8), `laravel/pint`.
- `npm install`: `@inertiajs/react`, `react@^19`, `react-dom@^19`, `typescript`, `@types/react`, `@types/react-dom`, `tailwindcss@^4`, `@tailwindcss/vite`, `tw-animate-css`, `sonner`, `lucide-react`, `zustand`, `@tanstack/react-query`, `clsx`, `tailwind-merge`, `class-variance-authority`.
- TypeScript `strict: true` + `noUncheckedIndexedAccess` (per `rules/REACT_BEST_PRACTICES.md` §1).
- `phpstan.neon` at level 8.
- `pint.json` with Laravel preset.

**Commit:** `chore(infra): scaffold Laravel 13 + Inertia + React + TypeScript`

### Step 2 — shadcn/ui setup
- Init with `style: new-york`, `baseColor: neutral`, CSS vars enabled (see `rules/THEME.md` §1).
- Generate `components.json` exactly as specified there.
- Replace `resources/css/app.css` with the token block from `rules/THEME.md` §2 verbatim — both `:root` and `.dark`, plus the `@theme inline` block. Include custom Lotto tokens (`--lotto-ball`, `--success`, `--warning`, `--game-2d`/`-3d`, `--surface-nav`).
- Install Phase 0 primitives: `npx shadcn@latest add button card input label separator skeleton sonner`.

**Commit:** `chore(ui): init shadcn/ui (new-york) + Tailwind v4 with Lotto tokens`

### Step 3 — Database schema
Migrations in dependency order, per `rules/PLAN.md` §3 + `rules/BETTING_RULES.md` §4:

1. `users` — add `wallet_code` (unique, 8 chars), `pin_hash`, `telegram_id` (nullable, unique), `username` (nullable, unique), `locked_until` (nullable), `status` enum, `is_admin` boolean.
2. `wallets` — `decimal(14,2) balance` and `held_balance`, `version` integer, **`CHECK (balance >= 0)` constraint**, `CHECK (held_balance >= 0)`.
3. `wallet_transactions` — signed `amount`, `balance_after` snapshot, unique `(wallet_id, idempotency_key)`.
4. `games`, `game_bet_types` (see `rules/BETTING_RULES.md` §4 for exact columns).
5. `draws`, `draw_results`.
6. `bets`, `bet_legs` (with `game_bet_type_id` FK and snapshot `potential_payout`).
7. `deposits`, `withdrawals`.

For each table: factory + seeder. Indexes on every column you'd filter/sort by.

**Commit:** `feat(db): initial schema (users, wallets, games, draws, bets, deposits, withdrawals)`

### Step 4 — Reference implementation (the architectural canon)

This is the most important step. Future features will copy this shape.

- `App\Casts\MoneyCast` wrapping `Brick\Money\Money` (per `rules/LARAVEL_BEST_PRACTICES.md` §4.3).
- `App\ValueObjects\TransactionType` and `App\ValueObjects\BetStatus` enums.
- Repository interfaces under `App\Repositories\Contracts\`, Eloquent impls under `App\Repositories\Eloquent\`, bound in `App\Providers\RepositoryServiceProvider`.
- `App\Services\WalletService` — `debit()`, `credit()`, `balance()` with pessimistic lock + idempotency per `rules/LARAVEL_BEST_PRACTICES.md` §5 and `rules/SECURITY.md` §2.1.
- `App\Services\PayoutCalculator` — full impl per `rules/BETTING_RULES.md` §5.
- `App\Services\WinChecker` — per `rules/BETTING_RULES.md` §8.
- `App\Actions\Bets\PlaceBetAction` — full implementation per `rules/LARAVEL_BEST_PRACTICES.md` §3.1.
- Domain exceptions: `App\Exceptions\InsufficientFundsException`, `App\Exceptions\DrawClosedException`.
- Events: `App\Events\BetPlaced`, `App\Events\WalletDebited`.
- Audit log channel in `config/logging.php` (per `rules/LARAVEL_BEST_PRACTICES.md` §13).

**Commit:** `feat(wallet): WalletService with lockForUpdate + idempotency + ledger`
**Commit:** `feat(bets): PlaceBetAction + PayoutCalculator + WinChecker`

### Step 5 — Pest tests (non-negotiable)

- `tests/Unit/Services/WalletServiceTest.php`:
  - Happy debit / credit.
  - Idempotency: same key returns existing transaction, balance unchanged on second call.
  - Insufficient funds throws and doesn't mutate balance.
  - **Concurrent debit test**: 100 simultaneous bets of ₱100 against a ₱500 wallet must result in **exactly 5 successes**, 95 `InsufficientFundsException`, final balance ₱0. Use `pcntl_fork` or HTTP concurrent calls.
- `tests/Unit/Services/PayoutCalculatorTest.php`:
  - Full dataset from `rules/BETTING_RULES.md` §9 (all 10 payout rows). Use Pest datasets.
- `tests/Unit/Actions/PlaceBetActionTest.php`:
  - Happy: places bet, debits wallet, dispatches `BetPlaced`.
  - Idempotent: repeated call with same key returns same bet, single debit.
  - Cutoff: rejects with `DrawClosedException`, wallet unchanged.

**Commit:** `test(wallet): concurrent debit + idempotency regression`
**Commit:** `test(bets): PlaceBetAction happy + idempotent + cutoff`

### Step 6 — CI
`.github/workflows/ci.yml`:
- Matrix: PHP 8.3.
- Steps: composer install → `vendor/bin/pint --test` → `vendor/bin/phpstan analyse` → `vendor/bin/pest` → `npm ci` → `npm run lint` → `npm run typecheck`.
- Block PRs on any failure.

**Commit:** `ci: pint + phpstan + pest + lint + typecheck on PR`

## Hard constraints (the seven rules — re-read if you drift)

1. **shadcn-first** — no native `<button>`, `<input>`, `<select>`, `<dialog>`, `window.confirm()` (`rules/THEME.md` §0.5–0.6).
2. **Money never `float`** — DB `decimal(14,2)`, PHP `Brick\Money`, wire as string.
3. **Wallet ops through `WalletService`** — `DB::transaction` + `lockForUpdate` + idempotency key + ledger row. Every time.
4. **Cutoff server-authoritative** — compare `draw.cutoff_at` to `now()` in the Action.
5. **Strict types** — `declare(strict_types=1);` in every PHP file; PHPStan level 8 in CI; no TS `any`.
6. **No PII in logs** — strip PINs / hashes / tokens before logging.
7. **Actions for verbs, Services for subsystems** — controllers stay thin.

## Don'ts

- Don't `composer require` or `npm install` packages outside the list above without asking.
- Don't disable tests to make CI green.
- Don't bypass migrations to alter the DB.
- Don't run destructive shell commands (`rm -rf`, `git reset --hard`, force-push) without confirmation.
- Don't generate placeholder code that "would work once filled in" — produce working code or stop and ask.

## Definition of done

You're done with Phase 0 when **all** of these are true:

- [ ] `composer install` and `npm install` succeed on a clean checkout.
- [ ] `php artisan migrate:fresh --seed` runs cleanly with sample games + bet types.
- [ ] `vendor/bin/pest` is all green, including the concurrent debit test.
- [ ] `vendor/bin/phpstan analyse` is clean at level 8.
- [ ] `npm run lint` and `npm run typecheck` are clean.
- [ ] CI workflow passes on the branch.
- [ ] In `php artisan tinker`: `app(\App\Actions\Bets\PlaceBetAction::class)->execute($user, [...])` debits the wallet, creates a `wallet_transactions` row with `balance_after` snapshot, and fires `BetPlaced`.

## When you finish

Open a PR titled `feat(infra): Phase 0 bootstrap — architecture + schema + wallet reference`. Use the PR template from `rules/GIT_COMMIT.md` §7. Include in the description:
- What's in this PR (the seven commits above)
- The concurrent debit test output as proof
- The tinker transcript showing a successful bet placement

## When unclear

Ask **one precise question** with the options you've considered. Don't guess on real-money code paths. Examples of valid questions:

- "Should `WalletService::credit` accept a `Money` or a string? Both are valid per the docs; I'd prefer `Money` for type safety — confirm?"
- "The schema has `users.is_admin` as a boolean, but `rules/SECURITY.md` §9 hints at a separate admin guard. Do you want a single `users` table with a flag, or a separate `admins` table?"

Now: read `CLAUDE.md`, load the rule docs listed above, then make a todo plan and confirm it with me before starting Step 1.
