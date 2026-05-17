# Lotto PH — Project Plan

A Philippine lotto betting web app (PCSO-style: EZ2 / Swertres / 6-digit games) built on **Laravel 12 + Inertia.js v2 + React 18 + TypeScript**.

> ⚠️ Domain note: This is a real-money betting product. Treat **wallet integrity, bet cutoffs, and audit logging** as P0. See `SECURITY.md` for non-negotiables.

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
- Fresh Laravel 12 starter (React + TS variant).
- shadcn/ui setup, Tailwind theme tokens from `THEME.md`.
- Database schema + migrations + factories + seeders.
- CI: GitHub Actions running Pest + typecheck + ESLint.
- Forge staging environment provisioned.

### Phase 1 — Core MVP (2–3 weeks)
- Auth (both methods).
- Wallet read + admin top-up.
- Game cards on home (matching screenshot).
- Place bet (current draw only).
- Tickets list + detail.
- Results page.
- Draw settlement job.

### Phase 2 — Hardening (1–2 weeks)
- Advance betting.
- Idempotency + race tests under load (k6 or Locust).
- Audit log surface in admin.
- Telegram bot for draw-result push.
- Polish + accessibility pass.

### Phase 3 — Payments (3–4 weeks)
- GCash / Maya integration for deposits.
- Manual review for withdrawals → automated tier.
- KYC partner integration.

---

## 7. Open Questions
1. **Legal**: PCSO is the only entity authorised to operate the nationwide lotto. Are we (a) a *re-sale agent* with a PCSO franchise, (b) an *aggregator* settling internally without official tickets, or (c) operating in a different jurisdiction? This decides whether bets are forwarded upstream or settled locally — and the entire compliance posture.
2. Result source: scrape PCSO site, manual admin entry, or licensed feed?
3. Wallet float: held in operator's bank account? Segregated trust account is the safer pattern.
4. Min/max bet limits per user per draw?
5. Tax handling: PH lotto winnings >₱10k are taxable (20% TRAIN law) — withhold at payout?

These need answers **before** Phase 1 wraps.
