# GIT_COMMIT.md

Commit and branching conventions for Lotto PH. Follows **Conventional Commits 1.0** + a small set of project-specific scopes.

---

## 1. Commit Message Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Subject line rules
- Max 72 chars.
- Imperative mood: `add`, `fix`, `remove` — not `added`, `fixed`, `removes`.
- No trailing period.
- Lowercase after the colon, except proper nouns and code identifiers.

### Body (optional but encouraged)
- Wrap at 72 chars.
- Explain **why**, not what (the diff shows what). Use the body to capture: the bug's symptom, the reproduction, the trade-offs considered.
- Reference past commits with `cf. abc1234`.

### Footer (optional)
- `Refs: #123` for a related issue.
- `Closes: #123` to auto-close on merge.
- `BREAKING CHANGE: <description>` for incompatible changes (rare in this project).
- `Co-authored-by: Name <email>` for pair work.

---

## 2. Types

| Type       | When to use                                                              |
|------------|--------------------------------------------------------------------------|
| `feat`     | A user-visible feature (page, button, capability).                       |
| `fix`      | A bug fix.                                                                |
| `refactor` | Internal restructure with no behaviour change.                            |
| `perf`     | A performance improvement.                                                |
| `test`     | Adding or fixing tests, no production code.                               |
| `docs`     | README, MD files, code comments only.                                     |
| `style`    | Whitespace, formatting, lint fixes — no logic change.                     |
| `chore`    | Build config, dependencies, tooling. Nothing app-functional.              |
| `ci`       | GitHub Actions / pipeline changes only.                                   |
| `build`    | Composer/npm dependency bumps, Vite config.                               |
| `revert`   | Revert a previous commit. Body must reference the reverted hash.          |
| `security` | Security fix worth flagging even if it's also a `fix`. Use sparingly.     |

---

## 3. Scopes — Lotto PH

Use a scope that maps to a feature domain or layer:

- `auth` — login, PIN, Telegram, session
- `wallet` — wallet, transactions, top-up
- `bets` — bet placement, ticket, draft
- `games` — game catalog, game cards
- `draws` — draw schedule, cutoffs
- `results` — draw results, settlement
- `tickets` — ticket list/detail
- `admin` — admin console, dual-control
- `notif` — Telegram bot, email, push
- `ui` — theme, design tokens, layouts, bottom nav
- `db` — migrations, seeders, factories
- `infra` — Forge, Horizon, CI/CD, ops
- `deps` — dependency upgrades

Combine if needed: `feat(bets,wallet): …`. Keep to ≤ 2 scopes.

---

## 4. Examples

### Good
```
feat(bets): place bet with idempotency key

Bets now carry a client-generated UUID. Repeated submits with the same
key short-circuit in WalletService::debit and return the existing bet,
preventing double-debits on network retries.

Closes: #42
```

```
fix(wallet): apply pessimistic lock before balance check

Two concurrent bets could both pass the balance check before either
committed. Added lockForUpdate() inside the transaction and a regression
test that runs 100 concurrent bets against a wallet that can fund only 5.

Refs: #58
```

```
security(auth): reject Telegram payloads older than 5 minutes

Verifies auth_date against now() to prevent replay of leaked auth
hashes. hash_equals used for constant-time comparison.
```

```
refactor(bets): extract BetSettlement into job

No behaviour change. Moves the per-bet win/lose computation out of
SettleDrawJob into a dedicated SettleBetJob so we can retry individual
bets if one fails mid-batch.
```

```
docs: add SECURITY.md threat model and pre-launch checklist
```

```
chore(deps): bump laravel/framework from 12.1.0 to 12.2.0
```

### Bad — don't do these
```
update stuff                              # no type, no info
fix bug                                   # which bug?
WIP                                       # never in main; use a draft PR
feat: added new feature for placing bets  # past tense + scope missing + vague
Fix: Wallet.                              # capital after colon, trailing dot
```

---

## 5. Atomic Commits

One commit = one logical change. If your diff touches both an unrelated typo and a real fix, split with `git add -p`.

Rules of thumb:
- A reviewer should be able to revert one commit without breaking unrelated features.
- Every commit on `main` should leave the test suite green.
- If you can't write the subject in 72 chars without `and`, the commit is too big.

---

## 6. Branching

```
main                  ← always deployable to production
└── develop           ← integration; deployable to staging
    ├── feat/<scope>-<short-slug>
    ├── fix/<scope>-<short-slug>
    ├── chore/<scope>-<short-slug>
    └── hotfix/<scope>-<short-slug>
```

Branch naming:
- `feat/bets-place-bet` ✅
- `fix/wallet-race-condition` ✅
- `jasons-branch` ❌

Hotfixes branch from `main`, merge back to both `main` and `develop`.

---

## 7. Pull Requests

### Title
Same format as a commit subject. The merge commit inherits it.

### Description template
```markdown
## What
One-sentence summary of the change.

## Why
Context, links to issue/spec, the user-visible reason.

## How
- Backend: <key changes>
- Frontend: <key changes>
- DB: <migrations? rollback story?>

## Testing
- [ ] Unit tests added/updated
- [ ] Manual test steps run on staging:
  1. …
  2. …

## Security
- [ ] No new secrets in code
- [ ] No new untrusted input paths
- [ ] If wallet/bet-touching: concurrency test added

## Frontend (if UI changes)
- [ ] **shadcn-first check**: no new `<button>`, `<input>`, `<select>`, `<dialog>`, `window.confirm()` etc. where a shadcn primitive exists (see `THEME.md` §0.6)
- [ ] No re-implemented modal / dropdown / tooltip / tabs
- [ ] All custom domain components live under `components/<domain>/`, not `components/ui/`
- [ ] Tokens only — no raw hex or `oklch()` literals in TSX

## Screenshots / Loom
(if UI)
```

### Merge strategy
- **Squash merge** for `feat/`, `fix/`, `chore/` branches → keeps `main` linear and readable.
- **Merge commit** when bringing hotfix back into `develop` (preserves the hotfix as a distinct point on the timeline).
- **Never force-push** to `main` or `develop`. Force-push fine on your own feature branch before merge.

---

## 8. Pre-commit Hook (recommended)

Add `.husky/pre-commit`:

```sh
#!/usr/bin/env sh
. "$(dirname -- "$0")/_/husky.sh"

# PHP
vendor/bin/pint --test --dirty || exit 1
vendor/bin/phpstan analyse --memory-limit=2G || exit 1

# JS/TS
npx lint-staged

# Secrets
gitleaks protect --staged --redact || exit 1
```

And `.husky/commit-msg`:

```sh
#!/usr/bin/env sh
npx --no -- commitlint --edit "$1"
```

`commitlint.config.js`:

```js
module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'scope-enum': [2, 'always', [
      'auth', 'wallet', 'bets', 'games', 'draws', 'results',
      'tickets', 'admin', 'notif', 'ui', 'db', 'infra', 'deps',
    ]],
    'subject-case': [2, 'always', 'lower-case'],
    'header-max-length': [2, 'always', 72],
  },
};
```

---

## 9. Tagging & Releases

Semver, prefixed with `v`:
- `v0.1.0` — first internal alpha
- `v0.x.y` — pre-launch iterations
- `v1.0.0` — public launch

Tag on `main` only, after a green CI run and a successful staging deploy. Cut release notes from the squashed PR titles between tags.

---

## 10. Cheat Sheet

```
feat(bets): place bet with idempotency
fix(wallet): apply lockForUpdate before balance check
refactor(games): extract GameCard from LottoIndex
perf(results): paginate results query with cursor
test(wallet): concurrent debit regression
docs(security): document Telegram replay window
chore(deps): bump react to 18.3.1
ci: add phpstan to PR pipeline
security(auth): rate-limit PIN attempts per username
revert: feat(bets): place bet with idempotency
```

When in doubt: write the subject as a verb-led imperative, pick the smallest accurate scope, and keep it boring.
