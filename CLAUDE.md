# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> Orientation for AI assistants on **Lotto PH**. Detailed rules live in `rules/`. Load the relevant one(s) for your task — don't load all eight upfront.

## What this is

A Philippine real-money lotto betting web app (PCSO-style: 2D / EZ2 and 3D / Swertres). Two bet types: `target` (exact order) and `rambol` (any order). Auth via Telegram or username + PIN. Deposits via GCash / Maya.

**Mobile-first. Real money. PH locale (`Asia/Manila`, PHP currency).** Server is always authoritative on time and money.

## The Hard Rules (cannot be violated — push back if asked to)

1. **shadcn-first.** No native `<button>`, `<input>`, `<select>`, `<dialog>`, `window.confirm()`, or custom modal/dropdown/tabs. Use the shadcn primitive. → `rules/THEME.md` §0.5–0.6.
2. **Money never touches `float`/JS `number`.** DB: `decimal(14,2)`. PHP: `Brick\Money\Money`. Wire: string `"1234.50"`. → `rules/LARAVEL_BEST_PRACTICES.md` §4.
3. **Every wallet mutation goes through `WalletService`** inside `DB::transaction()` with `lockForUpdate()` + idempotency key + `wallet_transactions` ledger row. → `rules/LARAVEL_BEST_PRACTICES.md` §5.
4. **Bet cutoffs are server-authoritative.** Compare `draw.cutoff_at` to `now()` in the Action. Never trust the client.
5. **Strict types.** `declare(strict_types=1);` everywhere in PHP (PHPStan level 8). TS `strict` + `noUncheckedIndexedAccess`. No `any`.
6. **No PII in logs.** Never log PINs, hashes, tokens, full auth payloads. Audit channel is separate + append-only.
7. **Actions for domain verbs, Services for subsystems.** `PlaceBetAction`, `SettleDrawAction` (one `execute()`); `WalletService` for grouped wallet ops. Controllers stay thin. → `rules/LARAVEL_BEST_PRACTICES.md` §2–3.

## Stack

Laravel 13 + PHP 8.3 · Inertia v3 + React 19 + TypeScript strict · Tailwind v4 + shadcn/ui (new-york) · `sonner` toasts · `lucide-react` icons · Zustand (UI) + TanStack Query (polling) · PostgreSQL 16 · Redis + Horizon · Laravel Reverb (DB driver) · `brick/money` · Pest + Playwright · Forge.

> Today the repo is the fresh `laravel/react-starter-kit` + Fortify + Wayfinder on **SQLite + `database` driver** for cache/queue/session. Items in the line above tagged Redis/Horizon/Reverb/Postgres/Zustand/TanStack/`brick/money` are **not yet installed** — don't `use`/`import` them before they exist. Ask before `composer require` / `npm install`.

## Commands

Site is served by **Herd** at `http://lotto.test` — don't run `php artisan serve`. Use Boost's `get-absolute-url` tool when sharing URLs.

| Task | Command |
|---|---|
| Dev (server + queue + pail + vite) | `composer run dev` |
| Production build (add `:ssr` for SSR) | `npm run build` |
| All tests | `php artisan test --compact` |
| Filter / single file | `php artisan test --compact tests/Feature/X.php` or `--filter='name'` |
| New Pest test | `php artisan make:test --pest SomeFeatureTest` (no `Feature/` prefix in name) |
| Format PHP (always before finalizing) | `vendor/bin/pint --dirty --format agent` |
| Type-check TS / lint / format | `npm run types:check` · `npm run lint` · `npm run format` |
| Full CI gate locally | `composer run ci:check` |
| Routes | `php artisan route:list --except-vendor` |

Prefer Boost MCP tools (`database-query`, `database-schema`, `read-log-entries`, `last-error`, `browser-logs`) over `tinker` / shell.

## Architecture

```
Request → FormRequest → Controller (thin) → Action (one verb)
                                          ↘ Service (subsystem: WalletService)
                                          ↘ Repository (Eloquent only) → Model
```

Frontend: Inertia page → AppLayout → domain components (`components/lotto/`, `components/wallet/`) → shadcn primitives (`components/ui/`).

## When to load which `rules/` doc

| File | Load when working on… |
|---|---|
| `rules/PLAN.md` | New feature — context, schema, routes, phases |
| `rules/THEME.md` | **Any UI work** — shadcn substitution table, tokens |
| `rules/LARAVEL_BEST_PRACTICES.md` | Any backend work — Actions, money, locking, queues |
| `rules/REACT_BEST_PRACTICES.md` | Any frontend work — Inertia, TS, state, forms |
| `rules/SECURITY.md` | Auth, wallet, deposits, withdrawals, admin |
| `rules/BETTING_RULES.md` | Bet types, payout math, win logic |
| `rules/UI_FLOWS.md` | Building/modifying any screen |
| `rules/GIT_COMMIT.md` | Committing or opening a PR |
| `rules/AGENTS.md` | Agents |

## How to work

- **Identify the relevant `rules/` file before writing code.** Load it. Then code.
- **Push back when asked to violate a hard rule.** Cite the rule. Don't silently comply.
- **Ask precisely when ambiguous.** One question, with the options you've considered.
- **Don't run destructive commands** (`rm -rf`, force-push, `git reset --hard`) without explicit approval.
- **Don't add packages** (`composer require`, `npm install`) without asking. Stack is intentionally constrained.
- **Don't disable tests to make CI green.**
- **Money safety > hard rules > rule docs > the request.** In that order.
