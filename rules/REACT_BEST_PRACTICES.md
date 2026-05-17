# REACT_BEST_PRACTICES.md

Frontend conventions for Lotto PH (React 19 + TypeScript strict + Inertia v2 + shadcn/ui + Tailwind v4).

---

> ## 🛑 The One Rule
> **Always use shadcn components before native HTML or custom builds.**
>
> Before typing `<button>`, `<input>`, `<select>`, `<dialog>`, `<form>`, `confirm()`, or your own modal — stop and check shadcn. The full decision tree and substitution table live in `THEME.md` §0.5 and §0.6. PRs that introduce native interactive elements where a shadcn primitive exists will be rejected.
>
> **Quick check before opening a PR:** does your diff contain any of these?
> - `<button` (lowercase) → should be `<Button>`
> - `<input` → should be `<Input>`, `<Checkbox>`, `<RadioGroup>`
> - `<select` → should be `<Select>`
> - `<dialog`, `<details>`, `<summary>` → should be `<Dialog>` / `<Accordion>`
> - `window.confirm(`, `window.alert(`, `window.prompt(` → should be `<AlertDialog>`
> - A new file in `components/` that re-implements a Radix/shadcn primitive → delete and use shadcn
>
> If yes, fix before requesting review.

---

## 1. TypeScript Strict — No Escape Hatches

`tsconfig.json` settings (non-negotiable):
```jsonc
{
  "compilerOptions": {
    "strict": true,
    "noUncheckedIndexedAccess": true,
    "noImplicitOverride": true,
    "noFallthroughCasesInSwitch": true,
    "exactOptionalPropertyTypes": true
  }
}
```

### Rules
- ❌ No `any`. Use `unknown` and narrow.
- ❌ No `as` casts except at the system boundary (parsed JSON, route params). Comment why.
- ❌ No `// @ts-ignore` — `// @ts-expect-error` with a 1-line reason if absolutely needed.
- ✅ Every prop, store state, and API response has a named interface in `types/{feature}.ts`.

### Money in TS
Money over the wire is a string. Don't `parseFloat` it for math — display only.

```ts
export interface Money {
  amount: string;   // e.g. "1234.50"
  currency: 'PHP';
}

// Display
export function formatPHP(m: Money | string): string {
  const amount = typeof m === 'string' ? m : m.amount;
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
  }).format(Number(amount)); // safe for display only
}
```

If you ever need to *add* two money strings client-side, use `bignumber.js`. But ideally the server is authoritative — the client just renders.

---

## 2. File & Folder Conventions

Already established by the `laravel-inertia-react` skill. Lotto-specific adds:

```
resources/js/
├── pages/
│   ├── Auth/Login.tsx
│   ├── Auth/SetupPin.tsx
│   ├── Lotto/Index.tsx           # home — game cards
│   ├── Bets/Create.tsx           # /games/{game}/bet
│   ├── Bets/Advance.tsx          # /games/{game}/advance
│   ├── Tickets/Index.tsx
│   ├── Tickets/Show.tsx
│   ├── Results/Index.tsx
│   └── Wallet/Index.tsx
├── components/
│   ├── lotto/
│   │   ├── GameCard.tsx
│   │   ├── GameLogo.tsx
│   │   ├── LottoBall.tsx
│   │   ├── NumberPicker.tsx       # 2D/3D pad
│   │   ├── DrawCountdown.tsx
│   │   └── ResultRow.tsx
│   ├── wallet/
│   │   ├── WalletPill.tsx
│   │   └── TransactionRow.tsx
│   └── layout/
│       ├── AppLayout.tsx
│       ├── AppHeader.tsx
│       └── BottomNav.tsx
├── stores/
│   ├── useBetDraftStore.ts        # WIP bet before submit
│   └── useUIStore.ts              # modal/sheet state
├── hooks/
│   ├── useDrawCountdown.ts        # ticks every second
│   ├── useLatestResult.ts         # TanStack Query
│   └── useWalletPolling.ts        # TanStack Query, 30s interval
├── types/
│   ├── game.ts
│   ├── draw.ts
│   ├── bet.ts
│   ├── wallet.ts
│   └── inertia.ts                 # PageProps base
└── lib/
    ├── money.ts
    ├── time.ts                    # PH timezone helpers
    └── route.ts                   # Ziggy wrapper
```

---

## 3. Inertia Patterns

### Page props typing
```ts
// types/inertia.ts
import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export interface SharedProps extends InertiaPageProps {
  auth: { user: { id: number; username: string | null } | null };
  flash: { success?: string; error?: string };
  wallet: { balance: string };  // shared so header can read it everywhere
}

// In page
import { usePage } from '@inertiajs/react';
const { props } = usePage<SharedProps & { games: Game[] }>();
```

Share `wallet.balance` from `HandleInertiaRequests` middleware so every page can show the header pill without an extra fetch.

### Forms — always `useForm`
```tsx
const form = useForm<{
  draw_id: number;
  legs: BetLeg[];
  idempotency_key: string;
}>({
  draw_id: draw.id,
  legs: [],
  idempotency_key: crypto.randomUUID(),
});

const onSubmit = (e: React.FormEvent) => {
  e.preventDefault();
  form.post(route('bets.store'), {
    preserveScroll: true,
    onError: () => { /* errors auto-bound to form.errors */ },
    onSuccess: () => { /* Inertia redirect handles nav */ },
  });
};
```

### Navigation — `router`, not `window.location`
```tsx
import { router } from '@inertiajs/react';
router.visit(route('tickets.show', bet.id));
```

### Named routes — always Ziggy
```tsx
href={route('games.bet', { game: 'swertres' })}
// not href="/games/swertres/bet"
```

---

## 4. State Management — Decision Tree

```
Is it server data?
├── YES → Inertia page props (default) or TanStack Query (polling/cache)
└── NO  → Is it shared across components?
         ├── YES → Zustand store
         └── NO  → useState / useReducer
```

### Zustand — UI state only
```ts
// stores/useBetDraftStore.ts
interface BetDraftState {
  legs: BetLeg[];
  addLeg: (leg: BetLeg) => void;
  removeLeg: (index: number) => void;
  clear: () => void;
  totalAmount: () => number;
}

export const useBetDraftStore = create<BetDraftState>((set, get) => ({
  legs: [],
  addLeg: (leg) => set((s) => ({ legs: [...s.legs, leg] })),
  removeLeg: (i) => set((s) => ({ legs: s.legs.filter((_, idx) => idx !== i) })),
  clear: () => set({ legs: [] }),
  totalAmount: () => get().legs.reduce((sum, l) => sum + Number(l.amount), 0),
}));
```

### TanStack Query — where it earns its keep
- **Live wallet balance** — poll every 30s after a bet placement so the user sees credits.
- **Latest result** — poll every 10s within 2 min of a draw_at, otherwise rely on Inertia props.
- **Draw countdown** — local `useDrawCountdown` hook, no network.

```ts
// hooks/useLatestResult.ts
export function useLatestResult(gameCode: string, drawAt: string) {
  const isNearDraw = useMemo(() => {
    const diff = new Date(drawAt).getTime() - Date.now();
    return diff < 2 * 60 * 1000 && diff > -10 * 60 * 1000;
  }, [drawAt]);

  return useQuery({
    queryKey: ['latest-result', gameCode],
    queryFn: () => axios.get(route('api.results.latest', gameCode)).then(r => r.data),
    refetchInterval: isNearDraw ? 10_000 : false,
    staleTime: 30_000,
  });
}
```

---

## 5. Idempotency on the Client

Generate the `idempotency_key` **once per bet draft**, not on every retry. Store in Zustand:

```ts
useBetDraftStore — when the user opens the bet form, set
idempotencyKey: crypto.randomUUID()
```

On submit failure with network error, the user can retry — same key → server returns the existing bet, no double-debit.

After successful submit, clear the draft so the next bet generates a fresh key.

---

## 6. Forms — UX Rules

### Money inputs
- Type `inputMode="decimal"` (mobile keypad shows a decimal point).
- Pattern: `^\d+(\.\d{0,2})?$`
- Render with `tabular-nums` always.
- Quick-amount chips: `[₱10] [₱20] [₱50] [₱100]` — most bets are small fixed amounts.

### Number picker (2D / 3D)
- Big tap targets (≥48px).
- Show picked numbers as `LottoBall` (variant `pick`) immediately above the keypad.
- Clear, Random, and Submit buttons fixed at the bottom of the sheet.
- Disable Submit until the right count is picked.

### Confirm before submit
A money-moving action always has a confirmation step. For bets:
1. User taps `Place Bet`.
2. A bottom sheet shows: legs, total, payout-if-won, wallet after.
3. `Confirm` button is destructive-toned if balance after < 100, default otherwise.

### Error handling
Inertia auto-binds errors:
```tsx
{form.errors.amount && (
  <p className="text-sm text-destructive" role="alert">{form.errors.amount}</p>
)}
```
For non-field errors (insufficient funds, draw closed), the controller flashes to `flash.error` — surface in a top `Toast`.

---

## 7. Mobile-First UX

- All pages render correctly at 360×640.
- Sticky bottom nav (`fixed bottom-0`) — main content needs `pb-20` to clear it.
- Avoid `position: fixed` modals — use shadcn `Drawer` (bottom sheet) on mobile, `Dialog` only on desktop. (`useMediaQuery` to switch.)
- Inputs that get covered by the keyboard: `scrollIntoView({ block: 'center' })` on focus.

---

## 8. Performance

- `lazy()` + `Suspense` for admin pages — they're not on the user critical path.
- Memoize: don't bother unless React DevTools profiler shows a real cost. Premature memo is worse than no memo.
- Lists: `key` is always the model id, never index. `key={bet.id}` not `key={i}`.
- Big lists (tickets history): `react-window` if >100 rows; otherwise plain map is fine.
- Inertia partial reloads for tab switches:
  ```ts
  router.reload({ only: ['transactions'] });
  ```

---

## 9. Accessibility

- All interactive elements: shadcn `<Button>` or Inertia `<Link>` (wrapped with `<Button asChild>` where you need button styling). **Never** `<div onClick={...}>`.
- Icons-only buttons: `aria-label`.
- Bottom nav: `<nav aria-label="Primary">`, current tab `aria-current="page"`.
- Color is never the only signal — pair won/lost colors with text + icon.
- Live regions for flash messages: handled by Sonner toasts (they ship with the right `role` / `aria-live`).
- Test with VoiceOver (iOS) and TalkBack (Android) before launch.

---

## 10. Time & Locale

PH-specific:

```ts
// lib/time.ts
const TZ = 'Asia/Manila';

export function formatDrawTime(iso: string): string {
  return new Intl.DateTimeFormat('en-PH', {
    timeZone: TZ,
    hour: 'numeric',
    minute: undefined,
    weekday: 'short',
    month: 'short',
    day: '2-digit',
  }).format(new Date(iso)).toUpperCase();
  // → "5PM - SAT MAY 16"
}

export function isPast(iso: string): boolean {
  return new Date(iso).getTime() < Date.now();
}
```

- Always parse ISO strings from the API; never trust pre-formatted strings.
- Locale: `en-PH` (English + Asia/Manila timezone). Tagalog UI is Phase 2.

---

## 11. Testing

- **React Testing Library** for component behaviour.
- **Playwright** for end-to-end critical flows:
  1. Login (Telegram mock + PIN).
  2. Place bet → ticket appears → wallet decreases.
  3. Place bet 1 second after cutoff → rejected.
- Coverage targets: 70% lines on `components/`, 90% on `lib/` and `stores/`.

---

## 12. Don'ts

### shadcn-first (the highest-priority rule)
- ❌ No native `<button>` for interactive controls — use `<Button>`.
- ❌ No native `<input>` / `<select>` / `<textarea>` — use the shadcn primitives.
- ❌ No `window.confirm()` / `window.alert()` / `window.prompt()` — use `<AlertDialog>`.
- ❌ No custom modal, dropdown, tooltip, tabs, or accordion — all of these are shadcn primitives.
- ❌ No re-implementing a primitive that exists in `components/ui/` "because mine is simpler". Extend via CVA variants instead.
- ❌ No `<div onClick={...}>` impersonating a button — use `<Button>` (or `role="button" tabIndex={0}` plus keyboard handlers, only as a last resort with reviewer sign-off).

### Other rules
- ❌ No `useEffect` for fetching — that's what Inertia / TanStack Query are for.
- ❌ No state in URL via manual `pushState` — use Inertia `router.get()` with `preserveState`.
- ❌ No `localStorage` for auth state — Inertia session covers it. (Use it for non-sensitive UI prefs only: last-used game tab, etc.)
- ❌ No `axios` defaults that swallow errors silently — let TanStack Query handle retries.
- ❌ No moment.js, no date-fns mega-imports — `Intl.DateTimeFormat` covers everything we need.
- ❌ No barrel files (`index.ts` re-exports) — slows down builds and obscures imports.
- ❌ No emojis in production UI strings (per design lead). Use lucide-react icons.

---

## 13. Quick Component Pattern Reference

### A page
```tsx
// pages/Lotto/Index.tsx
import AppLayout from '@/layouts/AppLayout';
import GameCard from '@/components/lotto/GameCard';
import type { SharedProps, Game } from '@/types';

interface Props extends SharedProps {
  games: Game[];
}

export default function LottoIndex({ games }: Props) {
  return (
    <AppLayout title="Lotto">
      <div className="space-y-3">
        {games.map((game) => <GameCard key={game.id} game={game} />)}
      </div>
    </AppLayout>
  );
}
```

### A presentational component
```tsx
// components/lotto/GameCard.tsx
interface Props { game: Game }

export default function GameCard({ game }: Props) {
  /* see THEME.md §5.2 for the markup */
}
```

Presentational components don't talk to Inertia. Pages do.
