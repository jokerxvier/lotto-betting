# UI_FLOWS.md

Per-screen specifications based on the reference screenshots. Each section describes the screen's states, the data it needs, the actions it exposes, and the components from `THEME.md` it composes.

> Scope: this doc is the source of truth for **screen behaviour and state transitions**. Layout/typography/colors live in `THEME.md`. Backend contracts live in `PLAN.md` and `BETTING_RULES.md`.

---

## 0. Navigation Map

```
┌──────────────────────────────────────────────┐
│  Bottom Nav (always visible when authed)     │
│  Lotto • Results • Tickets • Wallet          │
└──────────────────────────────────────────────┘

Unauthed
  /login
    ├── Telegram tab
    └── Username + PIN tab
  /register (post-Telegram PIN setup)

Authed
  /lotto                        ← bottom nav: Lotto
    ├── /games/{code}/bet       (sheet or page)
    └── /games/{code}/advance   ← opens Select Draw sheet → /games/{code}/bet?draw_id=…

  /tickets                      ← bottom nav: Tickets
    └── /tickets/{id}

  /results                      ← bottom nav: Results

  /wallet                       ← bottom nav: Wallet
    ├── Deposit tab (default)
    │     └── /wallet/deposits/{id}/pay  (external — opens payment link)
    └── Withdraw tab
          └── /wallet/withdrawals/new
```

Header (`AppHeader`) is **per-screen variable**: the home shows the small brand mark; deeper screens show a back arrow + large screen icon. Wallet pill is always on the right.

---

## 1. Lotto Home (`/lotto`)

The hub. A stack of `GameCard`s, one per active game.

### Data
```ts
interface LottoHomeProps {
  games: Array<{
    id: number;
    code: '2d' | '3d';
    name: string;
    logo_url: string;
    bet_types: GameBetType[];     // see BETTING_RULES.md
    latest_result: {
      label: string;              // "2PM"
      numbers: number[];          // [10, 12]
      drawn_at: string;
    } | null;
    next_draw: {
      id: number;
      label: string;              // "5PM - SAT MAY 16"
      draw_at: string;            // ISO UTC
      cutoff_at: string;
      status: 'scheduled' | 'closed';
    } | null;
    upcoming_draws: Draw[];       // for ADVANCE sheet
  }>;
}
```

### Per-card states

| State              | Trigger                                | NEW BET   | ADVANCE | Draw row                   |
|--------------------|----------------------------------------|-----------|---------|----------------------------|
| **Open**           | next_draw exists & `now < cutoff_at`   | enabled   | enabled | clock icon (blue) + time   |
| **Closed**         | next_draw exists & `now >= cutoff_at` & not settled | disabled  | enabled | clock icon (grey) + "CLOSED" badge |
| **No draw scheduled** | next_draw is null                   | disabled  | disabled | em-dash / "No draw"        |
| **Inactive game**  | game.active = false                    | hidden    | hidden  | card hidden                |

The disabled `NEW BET` keeps full width (no layout shift) — `opacity-50 cursor-not-allowed`.

### Actions
- **Tap NEW BET** → `router.visit(route('games.bet', { game: code, draw_id: next_draw.id }))`.
- **Tap ADVANCE** → opens `<SelectDrawSheet>` bottom sheet (see §2).
- **Tap `+`** on draw row → same as ADVANCE (alt entry point).
- **Tap wallet pill** → `/wallet`.

### Refresh
TanStack Query refetches `latest_result` every 10s when within 2 minutes of any `draw_at`, otherwise stale-while-revalidate on visit. Inertia's normal navigation refreshes the rest.

---

## 2. Select Draw Sheet (Advance flow)

A bottom sheet (`shadcn/ui` Drawer on mobile, Dialog on desktop) triggered from `ADVANCE` or the `+` icon.

### Content
- Title: "SELECT DRAW" (centered, all-caps).
- Stack of full-width primary buttons, one per upcoming scheduled draw, ordered chronologically:
  - Label format: `<TIME> - <DAY> <MONTH> <DD>` → e.g. `9PM - SAT MAY 16`, `2PM - SUN MAY 17`.
  - Tap → close sheet, then `router.visit(route('games.bet', { game, draw_id }))`.

### Window
Show up to **N upcoming draws** (config: `LOTTO_ADVANCE_WINDOW_DAYS`, default 7). Exclude any draw whose `cutoff_at` is in the past.

### Empty
If no upcoming draws (rare — operational gap), show: "No upcoming draws scheduled. Try again later." and a Close button.

### Sheet behaviour
- Drag handle at top.
- Tap-outside or swipe-down dismisses.
- Snap points: full content height (no half-state).

---

## 3. Bet Form (`/games/{code}/bet?draw_id=…`)

Not in the provided screenshots — spec inferred from the rest of the app and `BETTING_RULES.md`. **Confirm/correct when you have the design.**

### Structure (proposed)
```
┌─────────────────────────────────────┐
│ ← [back]                  💰 0.00   │
├─────────────────────────────────────┤
│ [GameLogo]  3D LOTTO                │
│ Draw: 5PM - SAT MAY 16              │
│ Closes in: 02:43:18  ⏱              │
├─────────────────────────────────────┤
│  Bet Type: ( Target ) ( Rambol )    │ ← tabs
│                                     │
│  Pick 3 numbers (0–9)               │
│  ┌──┐ ┌──┐ ┌──┐                     │
│  │ ?│ │ ?│ │ ?│  ← LottoBall pick   │
│  └──┘ └──┘ └──┘                     │
│                                     │
│  ┌──────────────────────────────┐   │
│  │ 0 1 2 3 4                    │   │
│  │ 5 6 7 8 9                    │   │
│  │ [ RANDOM ]   [ CLEAR ]       │   │
│  └──────────────────────────────┘   │
│                                     │
│  Amount: [ ₱ 10.00 ]                │
│  [₱10] [₱20] [₱50] [₱100]           │ ← quick chips
│                                     │
│  Wins: ₱2,000 (rambol, 3 perms)     │
│                                     │
│  [ + Add another leg ]              │
├─────────────────────────────────────┤
│ Total: ₱10.00   Potential: ₱2,000   │
│ [        PLACE BET        ]         │
└─────────────────────────────────────┘
```

### State machine
- `idle` — no picks yet, PLACE BET disabled.
- `picking` — partial picks, PLACE BET disabled.
- `ready` — picks_count met + amount in range, PLACE BET enabled.
- `confirming` — confirmation sheet open.
- `submitting` — `form.processing`, PLACE BET shows spinner.
- `error` — server rejected (insufficient funds, draw closed, etc.).

### Confirmation sheet (before submit)
Shows: legs summary, total amount, potential payout, **wallet balance before / after**, and `Confirm` + `Cancel`. Required for every bet — no "I trust you" shortcut.

### Idempotency
Generate `idempotency_key` (UUID v4) when the page mounts. Store in `useBetDraftStore`. Reset on successful submit.

### Cutoff handling
The header shows a live countdown (`DrawCountdown` component). When `now >= cutoff_at`:
- Disable PLACE BET.
- Show banner: "This draw is closed. Choose a different draw."
- Surface an `Advance` link back to the Select Draw sheet.

If the countdown hits zero mid-form, switch to the closed state without losing picks (so the user can re-target to an upcoming draw if they want).

---

## 4. Tickets (`/tickets`)

Lists the user's bets. Two view modes via header toggle.

### View modes
- **SCHEDULE** (default) — group by `draw.draw_at` (upcoming first, then past). Useful for "what am I waiting on?"
- **STATUS** — group by `bet.status` (Pending → Won → Lost → Void). Useful for "what did I win?"

Toggle is a two-link row: `SCHEDULE | STATUS`, the active one in `text-primary`. Implemented as a query string (`?view=status`) so it's shareable and back-button-friendly.

### Row content (each bet)
- Game + bet type (e.g. "3D Rambol").
- Numbers as `LottoBall`s.
- Draw label.
- Amount and status badge.
- Tap → `/tickets/{id}`.

### Empty state
Exactly as shown:
- Centered ticket icon (muted).
- "TICKETS" title, "BET HISTORY" caption.
- Card with "MY TICKETS" header, `SCHEDULE | STATUS` toggle (still visible — set the affordance early).
- "You don't have any bets yet..." + outline `ADD BETS` button → `/lotto`.

### Pagination
Cursor pagination, 20 per page, "Load more" button (avoid infinite scroll — bet history is something users review deliberately).

### Filters (Phase 2)
- Game selector
- Date range
- Status

---

## 5. Ticket Detail (`/tickets/{id}`)

Not in screenshots — proposed spec.

### Sections
1. Header: bet id (short — e.g. `#1FZ4-AC2X`), placed at, status badge.
2. Draw info: game, draw_at, result if available.
3. Legs:
   - Each leg as a row: type, numbers, amount, payout (if settled).
   - For rambol, also show "X permutations".
4. Total amount, total payout (if settled).
5. Footer actions:
   - "Share to Telegram" (Phase 2)
   - "Bet again" — pre-fills the bet form with same picks (smart reuse).
6. Audit/timestamps in a collapsible section: placed_at, settled_at, idempotency_key.

---

## 6. Results (`/results`)

Last 7 days, reverse-chronological. The most data-dense screen.

### Structure
```
┌────────────────────────┐
│        🏆               │
│       RESULTS          │
│      LAST 7 DAYS       │
└────────────────────────┘
┌────────────────────────┐
│      SAT MAY 16        │   ← date header
├────────────────────────┤
│ 2D LOTTO               │   ← game subhead
│  2PM        [10][12]   │   ← settled
│  5PM   Waiting for result │ ← drawn, no result yet
│  9PM   Closes in 4 hours  │ ← still open
├────────────────────────┤
│ 3D LOTTO               │
│  2PM       [0][6][9]   │
│  …                     │
└────────────────────────┘
┌────────────────────────┐
│      FRI MAY 15        │
│ …                      │
```

### Row states
Per draw row:
- **Settled** — show numbers (right-aligned, `LottoBall size="sm"`).
- **Waiting for result** — draw_at passed, no `draw_result` yet. Grey caption.
- **Closes in {duration}** — still open. Format: `Closes in 4 hours`, `Closes in 45 min`, `Closes in 2 min`.

Use `DrawCountdown` for the "closes in" labels with a 1-minute tick (no need for second-precision on the results page).

### Data shape
Server returns last 7 days as a nested structure to minimise client work:

```php
return [
    'days' => [
        [
            'date' => '2026-05-16',
            'games' => [
                [
                    'code' => '2d',
                    'name' => '2D Lotto',
                    'draws' => [
                        ['label' => '2PM', 'state' => 'settled',  'numbers' => [10, 12], 'draw_at' => '...'],
                        ['label' => '5PM', 'state' => 'awaiting', 'cutoff_at' => '...'],
                        ['label' => '9PM', 'state' => 'open',     'cutoff_at' => '...'],
                    ],
                ],
                // 3d…
            ],
        ],
        // …7 days
    ],
];
```

Computing `state` server-side is cheaper than the client doing it from raw fields.

### Pagination (Phase 2)
"Load more 7 days" button. Most users won't go past the default window.

---

## 7. Wallet (`/wallet`)

The screen shown in screenshot 1. Three logical sections: identity card, action tabs, history.

### Header (special — different from other screens)
- Left: `Hi, {username}!` in **green bold** + headset icon (opens support — Phase 2 placeholder).
- Right: logout icon button.

### Identity card
- Large circular wallet icon (muted background).
- **Wallet code** in muted text, with a copy button next to it (`Copy` icon, `aria-label="Copy wallet code"`). Toast on copy.
- Balance: large bold green number, "WALLET BALANCE" caption.

### Tabs: Deposit | Withdraw
- Underline-style tabs (active = blue underline + blue text).
- Each tab swaps the form below it.

### Deposit tab (see §8)
### Withdraw tab (see §9)

### Recent Deposits (below the form, on Deposit tab)
Shows last N deposits (5 default), with a refresh icon to manually refetch.

Each row:
- Left border colored by status (`pending` → warning amber, `completed` → success green, `failed` → destructive red).
- Method (GCash / Maya).
- Amount (right-aligned, bold).
- Status caption: `PENDING - PAYMENT LINK` (the link text is tappable, opens external payment URL).
- Timestamp below.

A parallel "Recent Withdrawals" list lives on the Withdraw tab.

---

## 8. Deposit Form (Wallet → Deposit tab)

### Fields
1. **Deposit Outlet** — provider chooser. Two outlined buttons: `GCash` (icon + name), `Maya` (icon + name). Selecting one highlights with `border-warning` (per screenshot: orange border on GCash).
2. **Select Amount** — grid of preset chips: `[200] [400] [800] [1,000] [1,500] [2,000]`. Two rows of three. Tapping fills the amount input.
3. **Enter Amount** — number input with `Min: ₱10.00 - Max: ₱50,000.00` as placeholder. Validates against `wallet.deposit_limits` per provider.
4. Submit button: **CONTINUE TO PAYMENT** (or similar — not shown in screenshot, infer).

### Flow on submit
1. POST `/wallet/deposits` with `{ provider, amount, idempotency_key }`.
2. Server creates a `deposits` row in `pending` state, calls the provider's create-payment API, stores the returned `payment_link` + `provider_reference`.
3. Server responds with the deposit id.
4. Client redirects (or opens in new tab) to `payment_link`.
5. On return to the app, the Recent Deposits list reflects the new pending deposit. User can also re-tap PAYMENT LINK to retry.
6. Webhook from provider eventually flips deposit to `completed` → triggers `WalletService::credit()` → balance updates.

### Reconciliation
- Pending deposits older than `deposit.expires_at` (default 30 min) auto-flip to `expired` by a scheduled job. Don't trust the provider to notify.
- Provider's authoritative API is polled every 60s by a queue job for pending deposits — webhook is a hint, the poll is the source of truth (see `SECURITY.md` §8).

---

## 9. Withdraw Form (Wallet → Withdraw tab)

Not in the screenshots — proposed.

### Phase 1 (manual)
- Provider chooser (GCash / Maya / Bank).
- Account number / mobile number field.
- Account name field.
- Amount.
- Submit creates a `withdrawals` row in `pending`. Admin processes manually, marks `completed`/`rejected`.
- Min/max bounds per provider + per-user daily cap.

### Phase 3 (automated)
- Provider's payout API. Same async lifecycle as deposits.
- KYC tier gates max daily/monthly amounts.

### Mandatory checks
- Re-PIN before submit (per `SECURITY.md` §1.6).
- Hold debit on wallet at withdrawal submission (move from `balance` to `held_balance` — a future schema addition). On approval → debit. On rejection → release back.

---

## 10. Auth Screens (`/login`)

Single full-bleed brand-blue page, two **sequential steps** (not tabs). Reference UI: s3app.live/login.

### Step 1 — Username
- Username input centered, auto-lowercase, no leading/trailing whitespace.
- `CONTINUE` button (cyan accent).
- Helper copy: "New username? We'll create the account on the next step."

### Step 2 — PIN
- 6-digit PIN entered via 6 `<InputOTPSlot>`s (numeric-only, `inputMode="numeric"`).
- Auto-submits on the 6th digit (no submit button).
- Back arrow returns to Step 1 (does not lose the username).

### Combined login / sign-up (product decision)
A single submit handles both cases:
- **Unknown username** → account is auto-created with the entered PIN, after `App\Rules\ComplexPin` passes (6 digits, no `111111`, no `123456`). User is signed in immediately.
- **Known username + correct PIN** → signed in.
- **Known username + wrong PIN** → "Invalid password." (the PIN-side error is explicit on purpose).

> ⚠️ This intentionally relaxes the historical "don't disclose whether the username exists" stance. Trade-offs:
> 1. Username enumeration is trivial (probe → "Invalid password." vs auto-create).
> 2. Auto-create doubles as a username-squatting vector — any unowned name becomes yours with the first PIN you type.
>
> If you're hardening this later, reintroduce a generic error + a separate `/register` route and remove the auto-create branch.

### Errors (current strings)
| Scenario | Message |
|---|---|
| Wrong PIN on existing user | `Invalid password.` |
| Telegram-only user attempts PIN login | `This account uses Telegram sign-in. Finish setup from there.` |
| Locked (5 wrong PINs in 15 min → 30 min lockout) | `Too many attempts. Try again in 30 minutes.` |
| Throttled (5/min per username+IP, 20/min per IP) | `429 Too Many Requests` |
| Weak PIN on auto-create | `The pin cannot use the same digit repeated.` / `The pin cannot use a sequential pattern.` |
| Reserved or malformed username on auto-create | `Username must be 3-32 characters: lowercase letters, digits, or underscore.` |

### Post-Telegram first-login
If Telegram returns a verified user but no `username` or `pin_hash` is set, the middleware redirects them to `/auth/setup-pin`:
- Step 1: pick a username (same regex/reserved-list as auto-create).
- Step 2: enter a 6-digit PIN.
- Step 3: confirm the same PIN; submit happens automatically on the 6th digit of the confirmation.

---

## 11. Support Entry Point

The headset icon next to `Hi, {username}!` opens a help drawer. Phase 1 contents:
- FAQ links (How to deposit, How to read a result, Why is my deposit pending?)
- "Contact us on Telegram" button (deep-links to a support bot).
- App version + user id (for support tickets).

Phase 2: live chat embed.

---

## 12. Loading & Empty States — Conventions

| Screen          | Loading                                  | Empty                                                   |
|-----------------|------------------------------------------|---------------------------------------------------------|
| Lotto Home      | Skeleton cards (3)                       | "No games active. Check back later."                    |
| Tickets         | Skeleton rows (5)                        | As screenshot — icon + caption + ADD BETS               |
| Results         | Skeleton day-blocks (2)                  | "No results in the last 7 days." (operational gap)      |
| Wallet          | Skeleton balance number, no card flicker | Recent Deposits empty: "No recent deposits."            |
| Bet form        | Spinner over PLACE BET                   | n/a                                                     |

Never blank-screen during load — always a skeleton with the same layout to avoid jumps.

---

## 13. Error Surfaces

| Severity      | Mechanism                            | Example                              |
|---------------|--------------------------------------|--------------------------------------|
| Field error   | Inline under input, `role="alert"`   | "Amount must be at least ₱10"        |
| Action error  | Toast (top, dismissable)             | "Draw closed. Pick another draw."    |
| Auth error    | Banner above the login form          | "Invalid username or PIN"            |
| System error  | Full-page error boundary             | 500 / network down                   |
| Wallet error  | Modal (interrupts — money matters)   | "Insufficient funds. Top up to bet." |

For wallet/bet errors, **don't ever silently drop the user's input** — re-render the form with their picks preserved.

---

## 14. Refresh Behaviour

- **Wallet balance** — TanStack Query, 30s interval when on `/wallet` or `/lotto`, manual refresh on Recent Deposits list.
- **Latest results** — see §1 refresh logic.
- **Tickets** — Inertia `router.reload({ only: ['tickets'] })` when returning from `/tickets/{id}`. Polled every 60s if any pending bets exist and the user is on `/tickets`.
- **Pending deposits** — polled every 15s (faster, because the user is actively waiting).

All polling pauses when the tab is hidden (`document.visibilityState`).
