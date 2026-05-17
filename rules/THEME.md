# THEME.md — Design System

Mobile-first design system for Lotto PH, built on **shadcn/ui (new-york style) + Tailwind CSS v4**. Tokens live in `resources/css/app.css`. Component primitives are copied into `resources/js/components/ui/` via the shadcn CLI — we own them, we customise them, we don't `npm install` them.

---

> ## 🛑 The One Rule
> **Always use shadcn components before native HTML or custom builds.**
>
> Before writing `<button>`, `<input>`, `<select>`, `<dialog>`, `<form>`, `confirm()`, a custom modal, or your own dropdown — the answer is **always check shadcn first.** This applies to every PR, every component, every file. See §0.5 for the decision tree and §0.6 for the substitution table. Violating this rule is grounds for a PR comment and a revert.

---

## Design Principles
1. **shadcn first.** Use shadcn primitives before native elements or custom code. See the One Rule above.
2. **Mobile-first.** Layouts target ~380px viewport; desktop is just a centered max-width container.
3. **One primary action per screen.** The blue `NEW BET` is the focal point; everything else is secondary.
4. **Money is the protagonist.** Wallet balance, bet amounts, and payouts get bold, high-contrast typography.
5. **Lotto balls are iconic.** The yellow circular number chip is the most recognisable element — protect its visual weight.
6. **No dark patterns.** No fake scarcity, no hidden cutoffs, no obscured costs. The cutoff time, payout, and balance are always visible.

---

## 0. Why shadcn/ui

It's not a component library — it's a **collection of copy-paste primitives** built on Radix UI + Tailwind. Practical implications:

1. **We own every component file.** `resources/js/components/ui/button.tsx` is ours to edit. No black-box.
2. **No version pinning to upstream.** Once a primitive is copied, it's frozen until we re-run the CLI. We can confidently customise CVA variants without worrying about npm upgrades breaking us.
3. **Tokens, not theme objects.** Colors live as CSS variables in `app.css`. Tailwind utilities (`bg-primary`, `text-muted-foreground`) read those vars. Dark mode flips a single set of vars.
4. **Radix underneath** for accessibility — focus trapping, keyboard nav, screen-reader semantics come free.

Rule: **never write a custom UI primitive when shadcn has one.** Don't roll our own `Modal` when `Dialog` exists. Customise via CVA variants, not duplication.

---

## 0.5 Decision Tree — "Which component do I use?"

Run this checklist for **every** interactive UI element before writing any code:

```
1. Is it already in components/ui/ (we've added it before)?
   └── YES → Use it. Done.
   └── NO ↓

2. Is it on https://ui.shadcn.com/docs/components ?
   └── YES → `npx shadcn@latest add <name>`. Use it. Done.
   └── NO ↓

3. Is it a Radix UI primitive (https://www.radix-ui.com/primitives)?
   └── YES → Wrap it in components/ui/<name>.tsx following shadcn conventions
            (CVA variants, `cn()` for class merge, `data-slot` attrs).
   └── NO ↓

4. Is it truly novel to our domain (LottoBall, GameCard, NumberPicker)?
   └── YES → Build in components/<domain>/, composing shadcn primitives where possible.
   └── NO → Stop. Re-read the list above. The answer is almost certainly in step 2.
```

**Most "I need a custom X" instincts dissolve at step 2.** shadcn has 60+ primitives now. If you find yourself building something from scratch, you're probably wrong.

---

## 0.6 Substitution Table — Native → shadcn

These swaps are **mandatory**, not suggestions. The shadcn version exists for a reason (accessibility, keyboard nav, focus management, consistency).

| Don't write…                              | Use instead…                                     | Why |
|-------------------------------------------|--------------------------------------------------|-----|
| `<button onClick={…}>`                    | `<Button>` from `@/components/ui/button`         | Variants, sizes, loading state, consistent focus ring |
| `<input type="text">`                     | `<Input>` from `@/components/ui/input`           | Token-aligned styling, error states |
| `<input type="checkbox">`                 | `<Checkbox>` from `@/components/ui/checkbox`     | Radix accessibility, keyboard navigation |
| `<input type="radio">` group              | `<RadioGroup>` from `@/components/ui/radio-group`| Proper grouping semantics, arrow-key nav |
| `<select>`                                | `<Select>` from `@/components/ui/select`         | Searchable, keyboard nav, mobile-friendly |
| `<textarea>`                              | `<Textarea>` from `@/components/ui/textarea`     | Token-aligned styling |
| `<label>` (standalone)                    | `<Label>` from `@/components/ui/label`           | Cursor + click target consistency |
| `<form>` + manual field wiring            | `<Form>` from `@/components/ui/form` (+ react-hook-form) | Field-level errors, accessible markup, validation hookup |
| `<dialog>` HTML element                   | `<Dialog>` from `@/components/ui/dialog`         | Focus trap, scroll lock, escape-to-close, portal |
| Custom modal built from divs              | `<Dialog>` or `<Drawer>`                         | Same as above |
| Bottom sheet built from scratch           | `<Drawer>` from `@/components/ui/drawer`         | Drag-to-dismiss, snap points, safe-area aware |
| `window.confirm()` / `window.alert()`     | `<AlertDialog>` from `@/components/ui/alert-dialog` | Themed, accessible, doesn't break Inertia |
| Custom dropdown                           | `<DropdownMenu>` from `@/components/ui/dropdown-menu` | Radix keyboard nav, positioning, escape-to-close |
| Custom tooltip                            | `<Tooltip>` from `@/components/ui/tooltip`       | Hover delay, keyboard accessible, portal |
| Custom toast / flash message              | `Sonner` (from `sonner` package + shadcn wrapper) | Stacking, dismiss, screen-reader friendly |
| Custom popover                            | `<Popover>` from `@/components/ui/popover`       | Positioning, focus management |
| Custom badge / status pill                | `<Badge>` from `@/components/ui/badge`           | Variants for token alignment |
| Custom tabs                               | `<Tabs>` from `@/components/ui/tabs`             | ARIA roles, keyboard nav |
| Custom collapsible / accordion            | `<Accordion>` from `@/components/ui/accordion`   | Animation, ARIA roles |
| `<details>` / `<summary>` HTML            | `<Accordion>` or `<Collapsible>`                 | Consistent animation + tokens |
| Custom loading spinner                    | `<Skeleton>` from `@/components/ui/skeleton`     | Matches layout, no layout shift |
| Plain styled `<a>` for nav                | `<Button asChild>` wrapping Inertia `<Link>`     | Variant + accessibility consistency |
| Custom date picker                        | `<Calendar>` + `<Popover>`                       | Don't even think about it |
| Custom table                              | `<Table>` from `@/components/ui/table`           | Header/row/cell semantics |

### Acceptable native elements
The rule is "shadcn before native primitives" — these structural / non-interactive elements stay native:
- `<header>`, `<footer>`, `<main>`, `<section>`, `<nav>`, `<article>`, `<aside>` — semantic structure.
- `<h1>` … `<h6>`, `<p>`, `<span>`, `<div>` — text and layout.
- `<a>` for **external** links only (`target="_blank"` to non-app URLs). Internal nav uses Inertia `<Link>` wrapped by `<Button asChild>` or `<NavigationMenuLink>`.
- `<img>` for now (revisit if we add a media pipeline).
- `<svg>` only via `lucide-react` imports.
- `<ul>`/`<ol>`/`<li>` for plain content lists.

If a native element would normally take an `onClick`, `aria-*`, or styling that imitates a control — **stop, you need a shadcn primitive.**

---

## 1. Setup

### Stack versions (May 2026)
- Tailwind CSS v4 (CSS-first config; no `tailwind.config.js`).
- shadcn/ui CLI latest, **style: new-york** (the old `default` style is deprecated).
- `tw-animate-css` (replaces the deprecated `tailwindcss-animate`).
- `sonner` for toasts (the old `Toast` primitive is deprecated).
- React 19 (shadcn components have `forwardRef` removed; primitives use `data-slot` attributes).

### Install
```bash
# In the Laravel project root
npm install tailwindcss @tailwindcss/vite
npm install -D tw-animate-css

npx shadcn@latest init
```

When the CLI prompts:
- Style: **new-york**
- Base color: **neutral**
- CSS variables: **yes**
- Tailwind CSS file: `resources/css/app.css`
- Components alias: `@/components`
- Utils alias: `@/lib/utils`
- React Server Components: **no** (we're using Inertia, not Next.js)

### `components.json` (generated, then committed)
```jsonc
{
  "$schema": "https://ui.shadcn.com/schema.json",
  "style": "new-york",
  "rsc": false,
  "tsx": true,
  "tailwind": {
    "config": "",
    "css": "resources/css/app.css",
    "baseColor": "neutral",
    "cssVariables": true,
    "prefix": ""
  },
  "aliases": {
    "components": "@/components",
    "utils": "@/lib/utils",
    "ui": "@/components/ui",
    "lib": "@/lib",
    "hooks": "@/hooks"
  },
  "iconLibrary": "lucide"
}
```

### Vite plugin (Tailwind v4 — preferred over PostCSS)
```ts
// vite.config.ts
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
  plugins: [
    laravel(['resources/js/app.tsx', 'resources/css/app.css']),
    react(),
    tailwindcss(),
  ],
  resolve: {
    alias: { '@': path.resolve(__dirname, 'resources/js') },
  },
});
```

---

## 2. Color Tokens (`resources/css/app.css`)

Tailwind v4 uses **CSS-first configuration**: tokens live in CSS, the `@theme inline` directive exposes them as Tailwind utilities, no `tailwind.config.js` needed.

```css
@import "tailwindcss";
@import "tw-animate-css";

@custom-variant dark (&:is(.dark *));

/* ─── Base tokens (raw colors) ─────────────────────────── */
:root {
  /* shadcn surfaces */
  --background:        oklch(0.97 0.005 240);
  --foreground:        oklch(0.18 0.02 240);
  --card:              oklch(1 0 0);
  --card-foreground:   oklch(0.18 0.02 240);
  --popover:           oklch(1 0 0);
  --popover-foreground:oklch(0.18 0.02 240);
  --muted:             oklch(0.95 0.01 240);
  --muted-foreground:  oklch(0.50 0.02 240);
  --border:            oklch(0.91 0.01 240);
  --input:             oklch(0.91 0.01 240);
  --ring:              oklch(0.58 0.20 255);

  /* shadcn brand — Lotto blue */
  --primary:           oklch(0.58 0.20 255);   /* NEW BET, tabs, links */
  --primary-foreground:oklch(0.99 0 0);

  --secondary:           oklch(0.93 0.01 240);
  --secondary-foreground:oklch(0.18 0.02 240);

  --accent:           oklch(0.93 0.01 240);
  --accent-foreground:oklch(0.18 0.02 240);

  --destructive:           oklch(0.58 0.22 25);   /* errors, loss */
  --destructive-foreground:oklch(0.99 0 0);

  /* Custom Lotto PH tokens (not in shadcn defaults) */
  --lotto-ball:           oklch(0.88 0.18 95);    /* yellow result chip */
  --lotto-ball-foreground:oklch(0.20 0.02 60);

  --success:           oklch(0.65 0.18 145);      /* win, wallet credit */
  --success-foreground:oklch(0.99 0 0);
  --warning:           oklch(0.75 0.16 75);       /* cutoff approaching */
  --warning-foreground:oklch(0.20 0.04 60);

  --game-2d: oklch(0.55 0.22 25);                 /* red logo bg */
  --game-3d: oklch(0.45 0.22 285);                /* purple logo bg */
  --game-4d: oklch(0.55 0.20 145);
  --game-6d: oklch(0.60 0.20 50);

  --surface-nav: oklch(0.25 0.01 240);            /* bottom nav dark surface */
  --surface-nav-foreground: oklch(0.95 0 0);

  /* Radius scale — shadcn pattern */
  --radius: 0.75rem;
}

.dark {
  --background:        oklch(0.15 0.02 240);
  --foreground:        oklch(0.95 0.005 240);
  --card:              oklch(0.20 0.02 240);
  --card-foreground:   oklch(0.95 0.005 240);
  --popover:           oklch(0.20 0.02 240);
  --popover-foreground:oklch(0.95 0.005 240);
  --muted:             oklch(0.25 0.02 240);
  --muted-foreground:  oklch(0.65 0.02 240);
  --border:            oklch(0.28 0.02 240);
  --input:             oklch(0.28 0.02 240);
  --ring:              oklch(0.62 0.20 255);

  --primary:           oklch(0.62 0.20 255);
  --primary-foreground:oklch(0.99 0 0);
  --secondary:           oklch(0.28 0.02 240);
  --secondary-foreground:oklch(0.95 0 0);
  --accent:           oklch(0.28 0.02 240);
  --accent-foreground:oklch(0.95 0 0);
  --destructive:           oklch(0.55 0.22 25);
  --destructive-foreground:oklch(0.99 0 0);

  --lotto-ball:           oklch(0.85 0.18 95);
  --lotto-ball-foreground:oklch(0.20 0.04 60);
  --success: oklch(0.60 0.18 145);
  --warning: oklch(0.72 0.16 75);
  --surface-nav: oklch(0.18 0.01 240);
  --surface-nav-foreground: oklch(0.95 0 0);
}

/* ─── Expose tokens to Tailwind utilities ─────────────── */
@theme inline {
  --color-background:        var(--background);
  --color-foreground:        var(--foreground);
  --color-card:              var(--card);
  --color-card-foreground:   var(--card-foreground);
  --color-popover:           var(--popover);
  --color-popover-foreground:var(--popover-foreground);
  --color-primary:           var(--primary);
  --color-primary-foreground:var(--primary-foreground);
  --color-secondary:           var(--secondary);
  --color-secondary-foreground:var(--secondary-foreground);
  --color-muted:           var(--muted);
  --color-muted-foreground:var(--muted-foreground);
  --color-accent:           var(--accent);
  --color-accent-foreground:var(--accent-foreground);
  --color-destructive:           var(--destructive);
  --color-destructive-foreground:var(--destructive-foreground);
  --color-border: var(--border);
  --color-input:  var(--input);
  --color-ring:   var(--ring);

  /* Custom Lotto PH utilities — usable as bg-lotto-ball, text-success, etc. */
  --color-lotto-ball:           var(--lotto-ball);
  --color-lotto-ball-foreground:var(--lotto-ball-foreground);
  --color-success:           var(--success);
  --color-success-foreground:var(--success-foreground);
  --color-warning:           var(--warning);
  --color-warning-foreground:var(--warning-foreground);
  --color-game-2d: var(--game-2d);
  --color-game-3d: var(--game-3d);
  --color-game-4d: var(--game-4d);
  --color-game-6d: var(--game-6d);
  --color-surface-nav:           var(--surface-nav);
  --color-surface-nav-foreground:var(--surface-nav-foreground);

  /* Radius scale */
  --radius-sm: calc(var(--radius) * 0.6);
  --radius-md: calc(var(--radius) * 0.8);
  --radius-lg: var(--radius);
  --radius-xl: calc(var(--radius) * 1.4);
}

@layer base {
  * { @apply border-border outline-ring/50; }
  body { @apply bg-background text-foreground; font-feature-settings: 'tnum'; }
}
```

### Token usage rules
- **Never use raw hex or `oklch()` inline in components.** Always go through tokens (`bg-primary`, `text-success`).
- **All custom tokens (`lotto-ball`, `success`, `warning`, `game-2d`, `surface-nav`) follow the shadcn naming convention** — `name` + `name-foreground` pair. This lets us drop them into any shadcn primitive's CVA variants without surprises.
- **Wallet balance**: `text-success` when > 0, `text-muted-foreground` when 0.
- **Cutoff time**: `text-warning` within 15 min of cutoff, `text-destructive` within 2 min.
- **Bet status badges**: `won` → success, `lost` → muted, `pending` → primary, `void` → destructive.


## 3. Typography

```css
font-family: 'Inter', system-ui, -apple-system, sans-serif;
/* Numeric tabular for money/balls: */
font-feature-settings: 'tnum';
```

| Token              | Tailwind class           | Use                                    |
|--------------------|--------------------------|----------------------------------------|
| `display`          | `text-3xl font-bold`     | Wallet balance, jackpot               |
| `h1`               | `text-2xl font-bold`     | Screen title                          |
| `h2`               | `text-xl font-semibold`  | Section header                        |
| `body`             | `text-base`              | Default body                          |
| `body-strong`      | `text-base font-semibold`| Money in cards (`₱10 BET WINS ₱4,000`)|
| `caption`          | `text-sm text-muted-foreground` | Timestamps, hints              |
| `label-mono`       | `text-xs font-mono uppercase tracking-wider` | "2PM RESULT" badge |

Numbers use `tabular-nums` everywhere — non-negotiable for money columns.

---

## 4. Spacing & Layout

- 4px base unit (`gap-1` = 4px). Cards use `p-4` (16px) inside, `gap-3` between rows.
- Container: `max-w-md mx-auto` for all auth'd screens. Desktop is intentionally narrow — this is a mobile product first.
- Safe areas: respect iOS notch and Android nav bar — `pt-safe pb-safe` utilities (via tailwindcss-safe-area plugin or manual `env(safe-area-inset-*)`).

```tsx
// resources/js/layouts/AppLayout.tsx
<div className="min-h-dvh bg-background pb-20">
  <header className="sticky top-0 bg-background/90 backdrop-blur border-b border-border">
    {/* logo + wallet */}
  </header>
  <main className="mx-auto max-w-md px-4 py-4 space-y-4">{children}</main>
  <BottomNav />
</div>
```

---

## 5. Core Components

### 5.1 LottoBall
The signature element. Always tabular numerals, fixed size, full-rounded.

```tsx
// components/lotto/LottoBall.tsx
interface Props {
  value: number | string;
  size?: 'sm' | 'md' | 'lg';
  variant?: 'result' | 'pick' | 'empty';
}

export function LottoBall({ value, size = 'md', variant = 'result' }: Props) {
  return (
    <span
      className={cn(
        'inline-flex items-center justify-center rounded-full tabular-nums font-bold',
        size === 'sm' && 'h-6 w-6 text-xs',
        size === 'md' && 'h-8 w-8 text-sm',
        size === 'lg' && 'h-12 w-12 text-base',
        variant === 'result' && 'bg-[var(--lotto-ball)] text-[var(--lotto-ball-foreground)]',
        variant === 'pick' && 'bg-primary text-primary-foreground',
        variant === 'empty' && 'border-2 border-dashed border-border text-muted-foreground',
      )}
    >
      {value}
    </span>
  );
}
```

### 5.2 GameCard
Matches the screenshot exactly: header row (logo + payout + result), divider, draw row, button row.

```tsx
<Card className="overflow-hidden">
  <div className="flex items-start justify-between p-4">
    <div className="flex items-center gap-3">
      <GameLogo code={game.code} />
      <p className="text-sm font-semibold italic">
        ₱{game.base_bet_amount} BET WINS ₱{game.base_payout_amount.toLocaleString()}
      </p>
    </div>
    <div className="text-right">
      <p className="text-xs font-mono uppercase tracking-wider text-muted-foreground">
        {latestResult.label} RESULT
      </p>
      <div className="mt-1 flex gap-1 justify-end">
        {latestResult.numbers.map((n) => <LottoBall key={n} value={n} size="sm" />)}
      </div>
    </div>
  </div>

  <Separator />

  <div className="flex items-center justify-between px-4 py-3 bg-muted/40">
    <div className="flex items-center gap-2 text-sm">
      <ClockIcon className="h-5 w-5 text-primary" />
      <span>{formatDrawTime(nextDraw)}</span>
    </div>
    <button aria-label="Quick add" className="text-primary"><PlusIcon /></button>
  </div>

  <div className="grid grid-cols-2 gap-2 p-4 pt-0">
    <Button asChild><Link href={route('games.bet', game.code)}>NEW BET</Link></Button>
    <Button variant="outline" asChild>
      <Link href={route('games.advance', game.code)}>ADVANCE</Link>
    </Button>
  </div>
</Card>
```

### 5.3 BottomNav
Sticky, four equal columns, icon + label.

```tsx
const tabs = [
  { href: route('lotto.index'), icon: HomeIcon, label: 'Lotto' },
  { href: route('results.index'), icon: TrophyIcon, label: 'Results' },
  { href: route('tickets.index'), icon: TicketIcon, label: 'Tickets' },
  { href: route('wallet.index'), icon: WalletIcon, label: 'Wallet' },
];
```

Active state: solid icon + `text-primary`. Inactive: `text-muted-foreground`.

Container: `bg-zinc-800 text-white` per screenshot, but token it as `--surface-nav` so it can switch in dark mode.

### 5.4 WalletPill (header)
```tsx
<div className="flex items-center gap-1.5">
  <WalletIcon className="h-5 w-5 text-muted-foreground" />
  <span className="font-bold tabular-nums text-success">
    {formatPHP(balance)}
  </span>
</div>
```

`formatPHP(1234.5)` → `1,234.50`. Always 2 decimals, no currency symbol in pill (the icon implies it).

### 5.5 Tabs (underline style)
Used on `/wallet` (Deposit / Withdraw) and `/login` (Telegram / Username).

```tsx
<div className="border-b border-border flex">
  {tabs.map((t) => (
    <button
      key={t.key}
      onClick={() => setActive(t.key)}
      className={cn(
        'flex-1 py-3 text-sm font-bold uppercase tracking-wider transition-colors',
        active === t.key
          ? 'border-b-2 border-primary text-primary'
          : 'text-muted-foreground'
      )}
    >
      {t.label}
    </button>
  ))}
</div>
```

### 5.6 Bottom Sheet (Select Draw, Confirm Bet)
Use shadcn `Drawer` (`vaul`-based) — has drag handle, snap points, and respects safe-area.

```tsx
<Drawer open={open} onOpenChange={setOpen}>
  <DrawerContent>
    <DrawerHeader>
      <DrawerTitle className="text-center uppercase tracking-wider">Select Draw</DrawerTitle>
    </DrawerHeader>
    <div className="px-4 pb-6 space-y-2">
      {draws.map((d) => (
        <Button key={d.id} className="w-full" onClick={() => choose(d)}>
          {d.label}
        </Button>
      ))}
    </div>
  </DrawerContent>
</Drawer>
```

### 5.7 Status Pill (deposit / withdrawal / bet)
Left-border accent + caption. One unit, no shadow.

```tsx
const colors = {
  pending:   'border-l-warning',
  completed: 'border-l-success',
  failed:    'border-l-destructive',
  expired:   'border-l-muted-foreground',
};

<div className={cn('border-l-4 bg-card p-3 rounded-r-md', colors[status])}>
  <div className="flex items-baseline justify-between">
    <span className="font-medium">{method}</span>
    <span className="tabular-nums font-bold">{formatPHP(amount)}</span>
  </div>
  <div className="flex items-baseline justify-between text-xs">
    <span className="font-mono uppercase tracking-wider text-muted-foreground">
      {status} {paymentLink && <a href={paymentLink} className="text-primary underline">- payment link</a>}
    </span>
    <span className="text-muted-foreground">{formatDateTime(createdAt)}</span>
  </div>
</div>
```

### 5.8 Wallet Identity Card
The big card at the top of `/wallet`. Three stacked elements, centered.

```tsx
<Card className="flex flex-col items-center gap-1 py-6">
  <div className="size-20 rounded-full bg-muted flex items-center justify-center mb-2">
    <WalletIcon className="size-10 text-muted-foreground" />
  </div>
  <button className="flex items-center gap-1.5 text-sm text-muted-foreground"
          onClick={copyCode}>
    <span className="font-mono tracking-wider">{walletCode}</span>
    <CopyIcon className="size-3.5" />
  </button>
  <p className="text-3xl font-bold tabular-nums text-success">
    {formatPHP(balance, { symbol: false })}
  </p>
  <p className="text-xs font-mono uppercase tracking-wider text-muted-foreground">
    Wallet Balance
  </p>
</Card>
```

### 5.9 Amount Chip Grid
The preset-amount selector on the deposit form.

```tsx
<div className="grid grid-cols-3 gap-2">
  {[200, 400, 800, 1000, 1500, 2000].map((n) => (
    <button key={n}
            onClick={() => setAmount(n)}
            className={cn(
              'border rounded-md py-3 font-bold tabular-nums',
              amount === n ? 'border-primary text-primary' : 'border-border'
            )}>
      {n.toLocaleString()}
    </button>
  ))}
</div>
```

---

## 6. shadcn Component Inventory

These are the primitives we install via `npx shadcn@latest add <name>`. Listed in install order so dependencies (Slot, etc.) come in early.

### Phase 0 (bootstrap)
```bash
npx shadcn@latest add button card input label separator skeleton sonner
```
- **button** — every primary/secondary action
- **card** — game cards, wallet card, ticket rows
- **input** — text/number inputs
- **label** — form labels (paired with Input)
- **separator** — divider inside game card, between sections
- **skeleton** — loading placeholders (per `UI_FLOWS.md` §12)
- **sonner** — flash messages and toasts (replaces deprecated Toast)

### Phase 1 (core MVP)
```bash
npx shadcn@latest add tabs drawer dialog sheet badge alert avatar
npx shadcn@latest add tooltip dropdown-menu select form
```
- **tabs** — Deposit/Withdraw, Login (Telegram/PIN), Bet Type (Target/Rambol)
- **drawer** — Select Draw bottom sheet, Confirm Bet (vaul-based, mobile-first)
- **dialog** — desktop fallback for drawer; PIN re-auth; destructive confirms
- **sheet** — slide-out side panel (admin filters, support drawer)
- **badge** — bet status, deposit status, "CLOSED" labels
- **alert** — inline error/warning banners
- **avatar** — Telegram profile pic in header
- **tooltip** — helper hints on disabled buttons (e.g. why NEW BET is disabled)
- **dropdown-menu** — header user menu
- **select** — game / status filters on Tickets
- **form** — `react-hook-form` integration helpers (used inside Bet form, Deposit form)

### Phase 2 (admin + polish)
```bash
npx shadcn@latest add table pagination calendar popover command
npx shadcn@latest add radio-group switch checkbox textarea progress
```
For admin tables (draws, deposits, withdrawals), date pickers (results filter), command palettes, settings forms.

### What we do NOT install
- **Toast** — deprecated, use Sonner.
- **Accordion** — no current use case; revisit if FAQ grows.
- **Carousel** — no marketing surfaces in MVP.
- **Hover Card** — desktop-only, not worth on mobile.

---

## 7. Customisation Patterns

### 7.1 Extending Button variants (CVA)
The button needs a `success` variant (used on the "Confirm Bet" CTA in the confirmation sheet) that isn't in shadcn defaults. Edit `components/ui/button.tsx` directly — it's ours.

```tsx
// Before — shadcn default
const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 ...",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground hover:bg-primary/90",
        destructive: "bg-destructive text-destructive-foreground hover:bg-destructive/90",
        outline: "border border-input bg-background hover:bg-accent",
        secondary: "bg-secondary text-secondary-foreground hover:bg-secondary/80",
        ghost: "hover:bg-accent hover:text-accent-foreground",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: { default: "h-9 px-4 py-2", sm: "h-8 rounded-md px-3", lg: "h-10 rounded-md px-8", icon: "h-9 w-9" },
    },
    defaultVariants: { variant: "default", size: "default" },
  }
);

// After — add success (and an XL size for the big "PLACE BET" CTA)
variants: {
  variant: {
    // …existing variants
    success: "bg-success text-success-foreground hover:bg-success/90",
  },
  size: {
    // …existing sizes
    xl: "h-12 rounded-md px-8 text-base font-bold",
  },
},
```

Now `<Button variant="success" size="xl">PLACE BET</Button>` just works.

### 7.2 The `cn()` rule for class composition
`cn()` (from `@/lib/utils`) wraps `clsx` + `tailwind-merge`. It resolves conflicting Tailwind classes left-to-right.

```tsx
// ✅ Lets the caller override our defaults
<Button className={cn("w-full", className)} />

// ❌ Forces our class to win, ignoring caller intent
<Button className={cn(className, "w-full")} />
```

Rule for our own components: **consumer className wins**, so put it last.

### 7.3 Composing shadcn primitives — don't fight Radix
Most lotto-specific components compose multiple primitives. Don't reach into Radix internals; compose at the shadcn layer.

```tsx
// components/lotto/ConfirmBetSheet.tsx — composes Drawer + Card + Button
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerFooter } from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';

export function ConfirmBetSheet({ open, onOpenChange, bet, onConfirm }: Props) {
  return (
    <Drawer open={open} onOpenChange={onOpenChange}>
      <DrawerContent>
        <DrawerHeader>
          <DrawerTitle>Confirm bet</DrawerTitle>
        </DrawerHeader>
        <div className="px-4 space-y-2">
          {/* …bet summary, balance before/after… */}
        </div>
        <DrawerFooter>
          <Button variant="success" size="xl" onClick={onConfirm}>Confirm</Button>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
        </DrawerFooter>
      </DrawerContent>
    </Drawer>
  );
}
```

### 7.4 Custom primitives (`LottoBall`) — keep them in `components/lotto/`, not `components/ui/`
`components/ui/` is shadcn territory — if we re-run the CLI to update a primitive, our custom files in there could be touched. Domain components live in `components/lotto/`, `components/wallet/`, etc. Imports stay clean via the aliases set in `components.json`.

```tsx
// resources/js/components/lotto/LottoBall.tsx
// (see §5.1 for the full code — references bg-lotto-ball token, which now flows
//  through @theme inline → Tailwind utility automatically.)
```

### 7.5 Dark mode
shadcn's pattern: add `class="dark"` on `<html>` to flip. We follow that. The toggle (Phase 2) stores user preference in `users.preferences.theme` (column) and respects `prefers-color-scheme` for unauthed visitors.

```tsx
// resources/js/lib/theme.ts
export function applyTheme(theme: 'light' | 'dark' | 'system') {
  const root = document.documentElement;
  const resolved = theme === 'system'
    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
    : theme;
  root.classList.toggle('dark', resolved === 'dark');
}
```

### 7.6 Icons — lucide-react only
`iconLibrary: "lucide"` in `components.json`. Don't mix icon sets. Sizes via `size={n}` prop (Lucide accepts a number), not Tailwind classes — keeps the icon sharp at any scale.

```tsx
import { Wallet, Trophy, Ticket, Home, Plus, Clock, LogOut, HeadphonesIcon, Copy } from 'lucide-react';
```

---

## 8. Motion
- Page transitions: none — Inertia's default is correct.
- Press feedback: `active:scale-[0.98] transition-transform` on all primary buttons (haptic-like on mobile).
- Balls reveal animation (results page): stagger fade + scale, `framer-motion`, 60ms per ball — optional, not in MVP.

---

## 9. Accessibility
- Touch targets ≥ 44×44px. Bottom nav tabs and `+` buttons must hit this.
- All icons that act as buttons have `aria-label`.
- Color contrast: text on cards meets AA (4.5:1) — verify the yellow ball foreground passes against the yellow bg (we use near-black `oklch(0.20)` for this).
- Screen reader: result row should announce as `"2PM result: 1, 0, 1, 2"` — implement via visually-hidden joined string.
- Respect `prefers-reduced-motion` for any animation we add.

---

## 10. Don'ts
- ❌ No raw hex or `oklch()` literals in TSX — always tokens.
- ❌ No CSS modules, no styled-components, no `style={{ ... }}` — Tailwind utilities only.
- ❌ No drop shadows on cards beyond `shadow-sm` — the design is flat.
- ❌ No gradients on primary buttons — flat blue.
- ❌ Don't change the yellow of result balls. It's the brand.
- ❌ Don't write a custom modal / dropdown / tooltip when shadcn has it. Reach for the primitive first.
- ❌ Don't `npm install` shadcn primitives — they get *copied* via the CLI into `components/ui/`. We own them.
- ❌ Don't edit Radix internals directly. If a primitive needs more flexibility than its shadcn wrapper exposes, extend the wrapper, not the Radix package.
- ❌ Don't put custom domain components (`LottoBall`, `GameCard`) under `components/ui/`. That namespace belongs to shadcn primitives. Domain components go in `components/lotto/`, `components/wallet/`, etc.
- ❌ Don't mix icon libraries. Lucide everywhere.
