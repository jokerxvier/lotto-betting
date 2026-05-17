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
| `rules/PLAN.md` | New feature — context, schema, routes, phases. **Update the status marker on the matching Phase item when the feature ships.** |
| `rules/THEME.md` | **Any UI work** — shadcn substitution table, tokens |
| `rules/LARAVEL_BEST_PRACTICES.md` | Any backend work — Actions, money, locking, queues |
| `rules/REACT_BEST_PRACTICES.md` | Any frontend work — Inertia, TS, state, forms |
| `rules/SECURITY.md` | Auth, wallet, deposits, withdrawals, admin |
| `rules/BETTING_RULES.md` | Bet types, payout math, win logic |
| `rules/UI_FLOWS.md` | Building/modifying any screen |
| `rules/GIT_COMMIT.md` | Committing or opening a PR |
| `rules/AGENTS.md` | Agents |

## Skills

Project skills live in `.claude/skills/` (some symlinked from `.agents/skills/`, tracked in `skills-lock.json`). See `rules/AGENTS.md` → *Skills Activation* for the Boost-side activation rule. **Rule docs always win over skills** when they conflict — skills give generic guidance, rule docs encode Lotto PH hard rules.

| Skill | Use when… | Rule doc that overrides |
|---|---|---|
| `laravel-best-practices` | Writing/reviewing any Laravel PHP code | `rules/LARAVEL_BEST_PRACTICES.md` (money, wallet, cutoffs) |
| `pest-testing` | Writing/fixing any Pest test (Feature/Unit/Browser, datasets, arch) | — |
| `wayfinder-development` | Wiring frontend to backend routes/controllers, `@/actions`, `@/routes` | — |
| `shadcn` | Adding/searching/composing shadcn components, `components.json` | `rules/THEME.md` §0.5–0.6 (substitution table) |
| `vercel-react-best-practices` | React performance, data fetching, bundle/component optimization | `rules/REACT_BEST_PRACTICES.md` (Inertia specifics) |
| `git-commit` | Executing the commit (staging + conventional message) | `rules/GIT_COMMIT.md` (style rules) |
| `fortify-development` | Use only for Fortify primitives that back the custom auth (username+PIN credential check via `Fortify::authenticateUsing`, `app/Actions/Fortify/*`, login throttling, password-confirmation). Do **not** adopt default email/password flows or email-coupled features. Telegram login is custom — Fortify is **not** involved. | `rules/SECURITY.md` |
| `deploying-laravel-cloud` | **Do not use.** Production is **Forge**, not Laravel Cloud | — |

### UI work: always invoke `/frontend-design:frontend-design`

When building or modifying any UI (page, component, layout, screen), invoke the **`/frontend-design:frontend-design`** skill. It must run alongside — not instead of — the project rules:

1. Load `rules/THEME.md` (shadcn-first substitution, tokens) **and** `rules/UI_FLOWS.md` (screen patterns) first.
2. Invoke `/frontend-design:frontend-design` for design quality / production polish.
3. Hard rule still applies: no native `<button>`/`<input>`/`<select>`/`<dialog>`/`window.confirm()` — shadcn primitives only.

## How to work

- **Identify the relevant `rules/` file before writing code.** Load it. Then code.
- **Push back when asked to violate a hard rule.** Cite the rule. Don't silently comply.
- **Ask precisely when ambiguous.** One question, with the options you've considered.
- **Don't run destructive commands** (`rm -rf`, force-push, `git reset --hard`) without explicit approval.
- **Don't add packages** (`composer require`, `npm install`) without asking. Stack is intentionally constrained.
- **Don't disable tests to make CI green.**
- **Money safety > hard rules > rule docs > the request.** In that order.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
