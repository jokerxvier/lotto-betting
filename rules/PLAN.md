# Lotto PH — Project Plan

A Philippine lotto betting web app (PCSO-style: EZ2 / Swertres / 6-digit games) built on **Laravel 12 + Inertia.js v2 + React 18 + TypeScript**.
---

## 0. Status legend

Every numbered Phase item below carries a status marker. **Mark items as `✅ done` only when the feature is shipped, tested, and merged.** Half-done work stays `🟡 in-progress`. Update this file in the same PR that ships the feature — never let docs drift behind code.

| Marker | Meaning |
|---|---|
| ✅ done | Shipped, tested, merged on `main` |
| 🟡 in-progress | Actively being built on a branch / not yet merged |
| ⬜ todo | Not started |
| ⛔ blocked | Cannot proceed (depends on an open question or external decision — link the blocker) |
| 🗑 dropped | Removed from scope — keep the line with a note explaining why |

---

## 1. Product Scope

### Reference UI (from screenshot)
Mobile-first layout with:
- Top bar: brand logo + wallet balance
- Stack of **game cards** (one per active game type):
  - Game logo, payout label (`₱10 BET WINS ₱4,000`)
  - Latest result chip row (yellow number balls)
  - Next draw row with clock icon and `+` action
  - Primary `NEW BET` (filled blue) + secondary `ADVANCE` (outline)
- Bottom tab bar: **Lotto · Results · Tickets · Wallet**

### MVP Game Types
| Game     | Picks              | Range    | Target ₱10 → | Rambol ₱10 → | Draws (PH time)   |
|----------|--------------------|----------|--------------|--------------|-------------------|
| 2D / EZ2 | 2 numbers          | 1–31     | ₱5,500       | ₱2,750       | 11AM / 4PM / 9PM  |
| 3D / Swertres | 3 numbers     | 0–9 each | ₱6,000       | ₱1,000–₱6,000* | 2PM / 5PM / 9PM |

\* Rambol payout depends on permutations of the picks — see `BETTING_RULES.md` for the formula and worked examples. All values are **defaults**, editable by admins at runtime.

Out of MVP (Phase 2): 4D, 6/42, 6/45, 6/49, 6/58, Stl/jackpot variants.

### MVP Features
1. **Auth** — Telegram Login Widget *or* username + numeric PIN (4–6 digits).
2. **Wallet** — top up via **GCash / Maya payment links** (async lifecycle), balance, transaction history, unique per-user **wallet code** for deposit reconciliation.
3. **Place bet** — single combination, current draw or `Advance` (future draw), with `target` and `rambol` bet types.
4. **Tickets** — list of bets with status (pending / won / lost / void), two view modes: **Schedule** (group by draw) and **Status** (group by outcome).
5. **Results** — last-7-days view, grouped by date and game, with per-draw states: `settled` (numbers shown), `awaiting` (drawn, result pending), `open` (cutoff countdown).
6. **Draw processing** — admin enters/imports results; system settles bets atomically.
7. **Payout credit** — winnings auto-credited to wallet on settlement.

### Out of MVP (later phases)
- Withdrawals + payment gateway (GCash / Maya / bank).
- KYC / age verification flow.
- Referral / agent hierarchy.
- Push notifications (Telegram bot for draw results).
- Multi-language (EN / Tagalog).
- Admin: dispute handling, manual void.

---

## 2. Tech Stack

- **Backend:** Laravel 13 (released March 17, 2026), PHP 8.3 minimum, strict types everywhere.
- **Frontend:** Inertia.js v2, React 18, TypeScript strict, Tailwind, shadcn/ui.
- **State:** Zustand (UI), TanStack Query (polling draw results & live wallet).
- **DB:** PostgreSQL 16 (numeric/decimal for money; `SELECT … FOR UPDATE` for wallet ops).
- **Queue:** Redis + Horizon (bet settlement jobs, deposit polling, Telegram webhooks). Routes declared centrally via Laravel 13's `Queue::route()`.
- **WebSockets:** Laravel Reverb with the new DB driver — no second Redis instance needed. Used to push draw results live to `/lotto` and `/results` clients.
- **Auth:** Laravel Fortify (session) + custom Telegram OAuth + PIN guard. **Passkeys** (Laravel 13 native) considered for admin and opt-in for high-balance users (Phase 2).
- **Testing:** Pest (PHP) + React Testing Library + Playwright (critical flows).
- **CI/CD:** GitHub Actions → Laravel Forge (per your usual deploy path).

---

## 3. Domain Model

```
users (wallet_code) ──< wallets ──< wallet_transactions
  │                       ↑
  │                       │
  │                  deposits, withdrawals
  │
  └──< bets ──< bet_legs ──> game_bet_types ──> games
              │
              └── draw_id → draws ──< draw_results
                                │
                                └── game_id → games
```

### Tables
- `users` — id, telegram_id (nullable, unique), username (nullable, unique), pin_hash, status, **wallet_code** (unique short reference, e.g. `8RD6ZQZ2`), locked_until, created_at, …
- `games` — id, code (`2d`, `3d`), name, picks_count, number_min, number_max, active, sort_order.
- `game_bet_types` — id, game_id, code (`target`, `rambol`), label, base_bet_amount, base_payout_amount, payout_strategy (`fixed`/`split_permutations`), min_bet, max_bet, active, sort_order. **See `BETTING_RULES.md`.**
- `draws` — id, game_id, draw_at (datetime), cutoff_at (datetime), status (`scheduled`, `closed`, `settled`, `void`).
- `draw_results` — id, draw_id, numbers (json array, ordered), published_at.
- `bets` — id, user_id, draw_id, amount, potential_payout, status (`pending`, `won`, `lost`, `void`), settled_at, idempotency_key (unique per user).
- `bet_legs` — id, bet_id, game_bet_type_id, numbers (json array), amount, potential_payout (snapshot at placement), payout (nullable, set on settlement).
- `wallets` — id, user_id (unique), balance (decimal 14,2), held_balance (decimal 14,2, for pending withdrawals), version (optimistic lock).
- `wallet_transactions` — id, wallet_id, type (`deposit`, `withdrawal`, `bet_debit`, `bet_payout`, `refund`), amount (signed), balance_after, reference_type, reference_id, idempotency_key, created_at.
- **`deposits`** — id, user_id, wallet_id, provider (`gcash`, `maya`), amount, status (`pending`, `completed`, `failed`, `expired`), payment_link, provider_reference (nullable), idempotency_key, expires_at, completed_at (nullable), created_at, updated_at.
- **`withdrawals`** — id, user_id, wallet_id, provider, destination_account, destination_name, amount, status (`pending`, `approved`, `processing`, `completed`, `rejected`), processed_by (admin user id), notes, idempotency_key, created_at.

### Money rule
**Store money as `decimal(14,2)` in PG, transfer as integer minor units (centavos) in API, never as JS `number` past the boundary.** See `LARAVEL_BEST_PRACTICES.md` §Money.

---

## 4. Key Flows

### 4.1 Login (Telegram)
1. User taps "Continue with Telegram" → Telegram Widget opens.
2. Telegram returns signed payload (`id`, `first_name`, `auth_date`, `hash`).
3. Backend verifies HMAC-SHA256 of payload against `bot_token`; rejects if `auth_date` > 5 min old.
4. Find-or-create user by `telegram_id`; new users prompted to set a PIN.
5. Issue session.

### 4.2 Login (Username + PIN)
1. Submit `username` + `pin`.
2. Rate-limited per username+IP (5/min) and per IP (20/min).
3. `Hash::check($pin, $user->pin_hash)`; lock account after 5 consecutive failures.
4. Issue session; require PIN re-entry for sensitive ops (top-up, withdrawal).

### 4.3 Place bet
1. Client submits `{ draw_id, legs: [{ numbers, amount }], idempotency_key }`.
2. Server validates: draw exists, `now() < draw.cutoff_at`, amount > min, numbers within range, format matches game.
3. **In a single DB transaction**, with `SELECT … FOR UPDATE` on wallet row:
   - Check `wallet.balance >= total_amount`.
   - Insert `bet` + `bet_legs`.
   - Insert `wallet_transactions` row (`bet_debit`, signed −amount).
   - Update `wallet.balance` and increment `version`.
4. Emit `BetPlaced` event.
5. Return ticket payload.

Bets with the same `idempotency_key` for the same user are no-ops on retry.

### 4.4 Draw settlement
1. Admin (or scheduled importer) submits result numbers for a draw.
2. Job `SettleDrawJob` runs:
   - Marks draw `closed → settled`.
   - For each `bet` in draw: compute win/lose per leg, set status.
   - For winners, credit `wallet_transactions` (`bet_payout`) inside per-user transactions with wallet lock.
   - Emit `BetSettled` events.
3. Idempotent on the draw — re-running the job is a no-op if already settled.

---

## 5. Routes (sketch)

```
GET   /                          → redirect to /lotto if auth, else /login
GET   /login                     → Login page (Telegram + username/PIN tabs)
POST  /auth/telegram             → Telegram callback
POST  /auth/pin                  → Username + PIN submit
POST  /auth/pin/setup            → First-time PIN set (post-Telegram register)
POST  /logout

# Authenticated
GET   /lotto                     → Home — list of games + latest result + next draw
GET   /games/{game}/bet          → Bet form (current or selected draw via ?draw_id=)
GET   /games/{game}/advance      → JSON list of upcoming draws for Select Draw sheet
POST  /bets                      → Place bet (idempotent)
GET   /tickets                   → My bets (?view=schedule|status, paginated, filterable)
GET   /tickets/{bet}             → Ticket detail
GET   /results                   → Results, grouped, last 7 days

GET   /wallet                    → Wallet home (Deposit tab default)
POST  /wallet/deposits           → Create a pending deposit, return payment link
GET   /wallet/deposits           → Recent deposits (JSON, for refresh button)
GET   /wallet/deposits/{deposit} → Deposit detail / retry payment link
POST  /webhooks/deposits/{provider} → Provider callback (signed)
POST  /wallet/withdrawals        → Create a pending withdrawal
GET   /wallet/withdrawals        → Recent withdrawals (JSON)

# Admin
GET   /admin/draws               → Manage draws
POST  /admin/draws/{draw}/result → Submit result → triggers settlement
GET   /admin/deposits            → Reconcile pending deposits
GET   /admin/withdrawals         → Approve / reject pending withdrawals
GET   /admin/games               → Manage games + bet types + payouts (dual-control)
```

---

## 6. Phases & Milestones

### Phase 0 — Bootstrap (3–4 days)
- ✅ Fresh Laravel 12 starter (React + TS variant).
- ✅ shadcn/ui setup, Tailwind theme tokens from `THEME.md`.
- ✅ Database schema + migrations + factories.
- ✅ Seeders — `GameSeeder` + `GameBetTypeSeeder` + `DevFixturesSeeder` (admin + 3 players each with funded wallets + 2 settled + 2 upcoming draws + sample bets; skipped in production).
- ✅ CI: GitHub Actions running Pest + typecheck + ESLint.
- ❓ Forge staging environment provisioned — confirm.

### Phase 1 — Core MVP (2–3 weeks)
- ✅ **Auth (both methods)** — Telegram Login Widget + combined username/PIN login-or-signup (single screen, two steps; auto-creates account on unknown username, explicit "Invalid password." on known username — see `rules/UI_FLOWS.md` §10 for the security trade-off). 6-digit PIN locked. Lockout state machine + audit-log channel + session hardening (1-day lifetime, encrypted) shipped together. See §4.1–4.2.
- ✅ Wallet read + admin top-up — `/wallet` shows balance + recent activity; `/admin/wallets` (gated by `EnsureAdmin` + `is_admin` flag) credits by `wallet_code`. All mutations flow through `App\Services\WalletService::credit` (Hard Rule 3: `DB::transaction` + `lockForUpdate` + idempotency-key dedupe + ledger row).
- ✅ Game cards on home — `/lotto` shows one card per active game with payout label, latest result chips, next-draw clock with live countdown, and (disabled) New Bet + Advance buttons. New `LottoLayout` (top bar + 4-tab bottom nav) is the mobile shell for `/lotto` + `/wallet`; Results + Tickets tabs are "Soon" pills until those pages ship. Wallet balance shared globally via `HandleInertiaRequests::share()`.
- ✅ Place bet (current draw only) — bottom-sheet wizard on `/lotto` (pick numbers → game type → preset/custom amount), `POST /games/{game}/bet` runs `PlaceBetAction` inside `DB::transaction` with `lockForUpdate` + cutoff re-check + payout snapshot via `App\Services\PayoutCalculator` (Brick\Money\Money, RoundingMode::DOWN) + wallet debit via `WalletService::debit` + `BetPlaced` event + audit log. Idempotency keyed per bet. Compound rate limit `bet-place` 30/min per user.
- ✅ Tickets list + detail — `/tickets` lists the auth user's recent bets (Schedule ↔ Status grouping toggle); `/tickets/{bet}` shows full detail with legs, draw result if settled, and payout. Route-model-bound + scoped to user (404 on others' tickets). Tickets tab in bottom nav un-stubbed.
- ✅ Results page — `/results` lists draws from the last 7 days (date-grouped sections), with state derived per draw: `settled` shows the winning numbers as `LottoBall variant=result`, `open` shows a live cutoff countdown, `awaiting` shows empty pip placeholders. Results tab in bottom nav un-stubbed.
- ✅ Cart-style bet builder — inline draft legs on each game card (numbers + bet type + amount + ✕), sticky PAY TICKETS bar at bottom showing leg count + total. `POST /bets/cart` runs each draft as its own Bet inside one outer `DB::transaction`; per-leg `leg_token` UUIDs flow into `bets.idempotency_key` so retries are safe. Replaces the prior single-bet POST flow.
- ✅ Telegram Mini App bridge — `https://telegram.org/js/telegram-web-app.js` loaded in `app.blade.php`; `bootTelegramWebApp()` in `app.tsx` auto-POSTs `WebApp.initData` to `POST /auth/telegram/web-app` on `/` or `/login`. Server validates HMAC via `VerifyTelegramInitDataAction` (`WebAppData` secret derivation, 5-min replay window), then hands off to the existing `RegisterWithTelegramAction`. Bot: **@ezswerte_bot** (token in `.env`, owner: jasonjavier06@gmail.com).
- ✅ Draw settlement job — `App\Services\WinChecker` (target = exact order; rambol = sorted equality), `App\Actions\Settlement\SettleDrawAction` (atomic `DB::transaction`, 200-row chunked pending-bet iteration, per-leg `WinChecker`, wallet credit via `WalletService::credit('bet_payout', …, "bet_payout:{bet_id}")` so retries dedupe at the ledger). Admin UI at `/admin/draws` (awaiting list) + `/admin/draws/{draw}/result` (number-picker form with preview + REAL MONEY warning); `App\Http\Controllers\Admin\DrawResultController` writes `DrawResult` + runs `SettleDrawAction` synchronously inside one outer transaction so the admin sees the settlement count + total payout before navigating away. Cron `php artisan draws:generate-upcoming --days=7` seeds the next-7-days × `config('lotto.draw_schedule')` × active games table; scheduled `dailyAt('00:05', timezone='Asia/Manila')` in `routes/console.php`. Events: `DrawSettled`, `BetSettled` (both deferred via `DB::afterCommit`).

**Phase 1 — complete.**

### Phase 1.x — small follow-ups
- ✅ PCSO result scraper — `App\Services\PcsoResultScraper` (driver pattern via `App\Services\Scrapers\ScraperDriver`; `LottopcsoDriver` for `lottopcso.com`) pre-fills the `/admin/draws/{draw}/result` form. Never throws (network/parse failures → `null` + audit log + manual fallback). 60s URL-keyed cache. 8s HTTP timeout. Gated by `App\Services\SettingsService` runtime toggle `scraper.suggestions_enabled` (default ON) — admin flips it at `/admin/settings` without a deploy.
- ✅ Auto-publish + settle (Option C) — `php artisan draws:auto-settle` (scheduled `everyFiveMinutes()->onOneServer()->withoutOverlapping()` in `routes/console.php`). Gated by the `scraper.auto_publish_enabled` toggle (default OFF, requires admin to type `AUTO-PUBLISH` to enable). For each awaiting draw: scraper fetch → range-validate (defense in depth) → wrap `DrawResult::create` + `SettleDrawAction` in a per-draw transaction. Idempotent via `DrawAlreadySettledException`. Every auto-settle + every skip writes an `audit` log line. `--force` flag bypasses the toggle for ops debugging; `--draw=N` targets a single draw.
- ✅ Admin auth split — `users.password` column (nullable, hashed cast); admins authenticate via `/admin/login` (username + password) at `App\Http\Controllers\Auth\AdminLoginController`, throttled `admin-login` (5/min per username+IP, 20/min per IP). Login success → redirect to `/admin` (`App\Http\Controllers\Admin\DashboardController`) with stat strip (awaiting draws, bets today, paid out today) + quick-link cards to /admin/draws, /admin/settings, /admin/wallets. Admins blocked from player surfaces (`/lotto`, `/results`, `/tickets`, `/wallet`, `/games/*`) via `EnsureAccountSetupIsComplete` — they 302 to `/admin` instead. Bootstrap a real admin password via `php artisan admin:set-password {username}` (interactive; min 12 chars + complexity). Player PIN flow at `/login` unchanged.

### Phase 2 — Hardening (1–2 weeks)
- ⬜ Advance betting.
- ⬜ Idempotency + race tests under load (k6 or Locust).
- ⬜ Audit log surface in admin.
- ⬜ Telegram bot for draw-result push.
- ⬜ Polish + accessibility pass.

### Phase 3 — Payments (3–4 weeks)
- ⬜ GCash / Maya integration for deposits.
- ⬜ Manual review for withdrawals → automated tier.
- ⬜ KYC partner integration.

---

## 7. Open Questions
1. **Legal**: PCSO is the only entity authorised to operate the nationwide lotto. Are we (a) a *re-sale agent* with a PCSO franchise, (b) an *aggregator* settling internally without official tickets, or (c) operating in a different jurisdiction? This decides whether bets are forwarded upstream or settled locally — and the entire compliance posture.
2. Result source: scrape PCSO site, manual admin entry, or licensed feed?
3. Wallet float: held in operator's bank account? Segregated trust account is the safer pattern.
4. Min/max bet limits per user per draw?
5. Tax handling: PH lotto winnings >₱10k are taxable (20% TRAIN law) — withhold at payout?

These need answers **before** Phase 1 wraps.
