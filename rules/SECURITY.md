# SECURITY.md

Security baseline for Lotto PH. This is a real-money product — these requirements are **non-negotiable** and must be in place before public launch.

---

## 0. Threat Model (1-page summary)

| Asset                       | Threat                                                  | Mitigation                                                       |
|-----------------------------|---------------------------------------------------------|------------------------------------------------------------------|
| User wallet balance         | Double-spend via concurrent requests                    | `lockForUpdate()` + idempotency keys + DB `CHECK (balance >= 0)` |
| User wallet balance         | Negative balance via float rounding                     | `decimal(14,2)` + `brick/money` arithmetic                       |
| User account                | Credential stuffing / PIN brute force                   | Rate limit, account lockout, optional 2FA                        |
| Telegram login              | Forged auth payload                                     | HMAC-SHA256 verify + `auth_date` ≤ 5 min                         |
| Bet placement               | Bet placed after cutoff                                 | Server-side cutoff check, never trust client                     |
| Draw result                 | Tampered result (insider threat)                        | Dual-control approval + audit log + offsite backups              |
| Session                     | Hijack / CSRF                                           | HTTPS + Secure/HttpOnly/SameSite cookies + CSRF tokens           |
| Database                    | SQL injection                                           | Eloquent / parameterised queries only — never raw concat         |
| API webhooks (payments)     | Forged callbacks                                        | Signature verification + IP allowlist                            |
| PII (telegram_id, phone)    | Exfiltration                                            | Encrypt at rest, minimal logging, S3 access policies             |

---

## 1. Authentication

### 1.1 Telegram Login Widget
Telegram returns a payload signed with your bot token. **Verify or reject.**

```php
public function verifyTelegram(array $data): bool
{
    $hash = $data['hash'] ?? null;
    unset($data['hash']);

    ksort($data);
    $checkString = collect($data)
        ->map(fn ($v, $k) => "{$k}={$v}")
        ->implode("\n");

    $secret = hash('sha256', config('services.telegram.bot_token'), true);
    $computed = hash_hmac('sha256', $checkString, $secret);

    if (! hash_equals($computed, (string) $hash)) {
        return false;
    }

    // Reject stale payloads (replay protection)
    if ((int) $data['auth_date'] < now()->subMinutes(5)->timestamp) {
        return false;
    }

    return true;
}
```

Notes:
- `hash_equals` for constant-time comparison (no timing leaks).
- 5-minute window is industry standard; tighter if your clocks are reliable.
- Store `telegram_id` as `BIGINT UNSIGNED`. It's stable; usernames change.

### 1.2 Username + PIN
- **PIN format**: 4–6 digits. Reject sequential (`1234`), repeating (`1111`), and the user's `dob` if known.
- **Storage**: `Hash::make($pin)` — bcrypt cost 12. Never store plaintext, never log the PIN.
- **Verification**: `Hash::check($pin, $user->pin_hash)`. Constant-time by Laravel.
- **Forbidden**: do NOT store the PIN in the session, JWT, or cookie — only the user id.

### 1.3 Rate limiting
```php
// routes/web.php
Route::post('/auth/pin', PinLoginController::class)
    ->middleware(['throttle:5,1', 'throttle:pin-login']);

// app/Providers/RouteServiceProvider.php
RateLimiter::for('pin-login', function (Request $request) {
    return [
        Limit::perMinute(5)->by($request->input('username') . '|' . $request->ip()),
        Limit::perMinute(20)->by($request->ip()),
    ];
});
```

### 1.4 Account lockout
After 5 consecutive failed PIN attempts within 15 minutes, lock the account for 30 minutes. Track in `users.locked_until` column. Log the event to audit channel.

### 1.5 Session
```php
// config/session.php
'lifetime' => 60 * 24, // 1 day
'expire_on_close' => false,
'encrypt' => true,
'secure' => env('SESSION_SECURE_COOKIE', true),  // ALWAYS true in prod
'http_only' => true,
'same_site' => 'lax',
```

Force `SESSION_SECURE_COOKIE=true` in `.env.production` — assert this in a deployment smoke test.

### 1.6 Sensitive action re-auth
Top-up confirmation, withdrawal, PIN change, telegram unlink: require **PIN re-entry** even if the session is valid. Stash `'reauth_at' => now()` in session, valid for 5 minutes.

---

## 2. Wallet & Bet Integrity

### 2.1 Race conditions — the cardinal sin
Two simultaneous bet requests by the same user can both pass a balance check and both succeed if you're sloppy. The fix is non-negotiable:

```php
DB::transaction(function () use ($user, $amount, $key) {
    // 1. Pessimistic lock the wallet row
    $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

    // 2. Idempotency short-circuit
    if (WalletTransaction::where('idempotency_key', $key)->exists()) {
        return; // already processed
    }

    // 3. Balance check after lock
    if (bccomp($wallet->balance, $amount, 2) < 0) {
        throw new InsufficientFundsException();
    }

    // 4. Mutate + log
    $wallet->decrement('balance', $amount);
    WalletTransaction::create([...]);
});
```

**Tested under load.** Write a Pest test using `pcntl_fork` or concurrent HTTP calls — confirm 100 simultaneous bets of ₱100 against a ₱500 balance result in exactly 5 successes, 95 rejections, final balance ₱0.

### 2.2 DB constraints as last-line defense
```sql
ALTER TABLE wallets ADD CONSTRAINT wallets_balance_nonneg CHECK (balance >= 0);
ALTER TABLE wallet_transactions ADD CONSTRAINT wt_idempotency_unique UNIQUE (wallet_id, idempotency_key);
```

If your application code has a bug, the DB will reject the write with a constraint violation. Catch it and treat it as a P1 alert — the constraint should never actually fire.

### 2.3 Bet cutoff
Computed **server-side** from `draws.cutoff_at` (UTC) compared to `now()`. Never accept a cutoff value from the client.

### 2.4 No client-side balance authority
The client displays the balance, but every action validates against the DB. Don't trust `body.current_balance`.

### 2.5 Settlement is idempotent
Re-running `SettleDrawJob` on an already-settled draw must be a no-op. Guard at the start of the job:

```php
if ($draw->status === DrawStatus::Settled) {
    return;
}
```

Plus `WithoutOverlapping` middleware keyed by `draw_id`.

---

## 3. Input Validation

### 3.1 Always use FormRequests for writes
Never call `$request->all()` directly into `Model::create()`. Whitelist via FormRequest's `validated()` or `safe()`.

### 3.2 Mass assignment protection
- Use `$fillable` (allowlist) on every model, not `$guarded = []`.
- For admin-only fields (e.g. `is_admin`, `status`, `locked_until`), set them via service methods, never via `fill()`.

### 3.3 SQL injection
- Eloquent / Query Builder only. **No `DB::raw()` with user input concatenated.**
- If you must use raw SQL: parameter bindings only.
  ```php
  DB::select('SELECT … WHERE user_id = ?', [$userId]); // ✅
  DB::select("SELECT … WHERE user_id = $userId");      // ❌ NEVER
  ```

### 3.4 Numeric coercion
PHP loose comparison is dangerous: `"0e123" == "0"` is true. Use:
- `(int)`, `(float)` casts at the boundary, or
- `bccomp($a, $b, 2)` for money comparisons.

---

## 4. CSRF / XSS / Headers

### 4.1 CSRF — `PreventRequestForgery`
Laravel 13 ships `PreventRequestForgery` as the replacement for the old `VerifyCsrfToken` middleware. It does everything the old one did **plus origin-aware verification** — i.e. it also checks the `Origin`/`Referer` headers against the app URL on state-changing requests. Default is on; don't disable it.

Inertia handles the CSRF token automatically. The only change from Laravel 12: the middleware class name in `bootstrap/app.php` becomes `PreventRequestForgery`. If you fork the middleware to add exceptions (don't, for our app — no exceptions), keep `prevent-request-forgery` in the alias.

Test: a POST to `/bets` with a valid token but a foreign `Origin` header (e.g. attacker's site embedding our app) should be rejected. Add this to the security smoke suite.

### 4.2 XSS
- React escapes by default. **Never use `dangerouslySetInnerHTML`** with anything user-derived.
- On the PHP side, Blade's `{{ }}` escapes. `{!! !!}` is forbidden in this codebase — add a CI grep.

### 4.3 Headers (via middleware)
```php
// app/Http/Middleware/SecurityHeaders.php
$response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('X-Frame-Options', 'DENY');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
$response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
$response->headers->set('Content-Security-Policy',
    "default-src 'self'; img-src 'self' data: https:; script-src 'self' 'unsafe-inline' https://telegram.org; style-src 'self' 'unsafe-inline'; connect-src 'self' wss:");
```

Test with [securityheaders.com](https://securityheaders.com) — target A+.

---

## 5. Transport

- HTTPS only. HTTP redirects to HTTPS at the load balancer.
- TLS 1.2 minimum, prefer 1.3.
- HSTS preload after 6 months of stable HTTPS.
- Use Cloudflare or equivalent for DDoS + WAF.

---

## 6. PII & Data at Rest

- **Encrypt at rest** in DB for: `phone_number`, `email`, `telegram_username` (if collected). Use Laravel's `encrypted` cast.
  ```php
  protected function casts(): array {
      return ['phone' => 'encrypted', 'email' => 'encrypted'];
  }
  ```
- **Never log PII** — `Log::info("user logged in: {$user->phone}")` is forbidden. Log `user_id` only.
- **Database backups**: encrypted, off-region, retention ≥ 7 years for financial records (PH BSP / AMLC guidance).
- **`.env`** never committed. Use Laravel Forge env management or Vault.

---

## 7. Telegram Bot (Phase 2)

If you add an outbound bot for notifications:
- Bot token in `.env`, never client-side.
- Webhook URL is HTTPS with a secret path token (`/webhooks/telegram/<random-32-chars>`).
- Set `secret_token` on `setWebhook` and verify the `X-Telegram-Bot-Api-Secret-Token` header on every callback.

---

## 8. Deposits, Payment Links & Webhooks

The deposit flow is a real, MVP-day-one path now (GCash / Maya) — not deferred to Phase 3. That changes the threat surface.

### 8.1 Wallet code (deposit reference)
Every user gets a unique `wallet_code` (e.g. `8RD6ZQZ2`) at signup. It's the reference shown in payment-provider messages so support can match orphaned deposits.

- **Format**: 8 characters, uppercase alphanumeric, excluding ambiguous chars (`0`, `O`, `1`, `I`, `L`).
- **Generation**: cryptographically random, retry on collision, store with unique index.
- **Not a secret**, but **not for auth either** — never accept a wallet_code as a login factor. It's just a label.
- **Display only** — server matches deposits by the provider's `payment_link` reference (stored at creation), not by the user typing the code somewhere.

### 8.2 Deposit lifecycle
```
[CREATE] → pending → (provider success)  → completed → wallet credited
                  → (provider failure)   → failed
                  → (expires_at passes)  → expired
```

States are mutually exclusive. Transitions are one-way (no resurrecting an `expired` deposit).

### 8.3 Payment link integrity
- Server creates the deposit row in `pending` **before** calling the provider's API.
- The `payment_link` stored on the row is **the URL we received from the provider** — never built client-side, never trust a URL in a webhook payload.
- The link is opaque to the user; we don't append our own params.
- Expiry: `expires_at = created_at + 30 minutes` (configurable per provider).

### 8.4 Webhook handling (the dangerous part)

**Webhooks are hints, not authority.** Treat them as a *signal to poll*, not as the source of truth.

```php
public function handle(Request $request, string $provider): Response
{
    // 1. Verify signature first — reject if bad
    if (! $this->verifier->verify($provider, $request)) {
        Log::channel('audit')->warning('webhook.bad_signature', ['provider' => $provider]);
        return response()->noContent(401);
    }

    // 2. IP allowlist check
    if (! in_array($request->ip(), config("payments.$provider.allowed_ips"))) {
        return response()->noContent(403);
    }

    // 3. Find the deposit — by OUR id stored in the provider's metadata,
    //    never by trusting an id in the webhook body alone.
    $deposit = Deposit::where('provider', $provider)
        ->where('provider_reference', $request->input('reference'))
        ->first();

    if (! $deposit || $deposit->status !== DepositStatus::Pending) {
        // Idempotent: ignore replays of already-finalized deposits
        return response()->noContent(200);
    }

    // 4. Don't apply the webhook payload directly. Queue a job
    //    that calls the provider's authoritative API to confirm.
    PollDepositStatus::dispatch($deposit);

    return response()->noContent(200);
}
```

### 8.5 Reconciliation job
A scheduled job runs every minute:
- For every `pending` deposit with `created_at < now() - 60s`, poll the provider's GET-payment-status endpoint.
- If provider says `completed`: inside a wallet transaction, credit via `WalletService::credit()` with the deposit id as idempotency key, mark deposit `completed`.
- If provider says `failed`: mark deposit `failed`.
- If `expires_at` is past and provider still says `pending`: mark deposit `expired` and cancel the provider link if the API supports it.

### 8.6 Crediting the wallet — idempotency is everything
The provider can — and will — fire the webhook multiple times, or the poll might race the webhook. Two paths can both decide "this deposit just completed".

The `WalletService::credit()` call uses `deposit.id` as the idempotency key. If the credit already happened, the second call is a no-op (returns the existing `wallet_transaction`). **There is no scenario where a single deposit can credit twice.** Test this with concurrent webhook + poll calls.

### 8.7 Forbidden patterns
- ❌ Trusting an `amount` from a webhook body to credit the wallet. Always read the amount from `deposit.amount` (which we set at creation).
- ❌ Letting the client confirm a deposit ("I paid! Please credit me!"). The server confirms with the provider, period.
- ❌ Storing the provider's API keys in the same env file as Telegram tokens without scoping — separate by purpose so a leak of one doesn't unlock the other.
- ❌ Logging the full webhook payload — providers sometimes include card last-4 or other PII. Log only the deposit id, signature verification result, and resulting state.

### 8.8 Withdrawals
Withdrawals are higher-risk than deposits — money leaving the operator. Phase 1 is manual:
1. User submits withdrawal request → row inserted in `pending`.
2. Amount **moves from `wallets.balance` to `wallets.held_balance`** atomically (still under the user's name, but unspendable).
3. Admin reviews, then either:
   - **Approve** → manually sends GCash/Maya → marks `completed` → debits `held_balance` and creates `wallet_transactions` row.
   - **Reject** → releases `held_balance` back to `balance`.
4. Re-PIN required at user-submit time.
5. Daily/monthly caps enforced server-side per user.
6. Withdrawals over a configurable threshold (e.g. ₱20,000) require two-admin approval (dual-control, like draw results).

---

## 9. Admin Console

The admin area (`/admin/*`) is the highest-risk surface — it can void bets, top up wallets, publish draw results.

- **Separate guard**: `admin` middleware, not just `auth`.
- **2FA mandatory** for admin accounts. **Use Laravel 13 native passkeys** (WebAuthn) — they're phishing-resistant and don't require an authenticator app. Fall back to TOTP via `pragmarx/google2fa-laravel` only if a hardware key is unavailable at registration.
- **Dual control** for high-impact actions: a draw result must be entered by one admin and approved by a second admin before the settlement job fires. Implement as `DrawResultPendingApproval` state.
- **IP allowlist** for admin login if feasible.
- **Every admin action logged** to the audit channel with admin id, target, before/after values.

---

## 10. Dependency Hygiene

- `composer audit` and `npm audit` in CI on every PR. Block on high/critical.
- Dependabot or Renovate enabled.
- Pin Laravel patch versions in production; review minor upgrades quarterly.
- Lock files committed (`composer.lock`, `package-lock.json`).

---

## 11. Secrets in Code Scanning

- Pre-commit hook: `gitleaks` or `trufflehog` to catch accidental secret commits.
- GitHub secret scanning enabled.
- Rotate bot tokens / payment keys quarterly.

---

## 12. Logging — What NOT to Log
**Forbidden in any log channel**:
- PINs (raw or hashed — even the hash is sensitive)
- Full request bodies on auth endpoints
- Session tokens / cookie values
- Payment card details (we shouldn't even receive these — use hosted fields)
- Full Telegram auth payloads (the `hash` field at minimum)

Use `request()->safe()->except(['pin', 'hash', 'token'])` when logging request context.

---

## 13. Incident Response

Have a written runbook for:
1. **Suspected wallet exploit**: how to freeze withdrawals, pause new bets, snapshot DB.
2. **Compromised admin account**: revoke sessions, rotate 2FA, audit recent actions.
3. **Lost/leaked DB**: notify users per PH Data Privacy Act within 72h.
4. **Stuck settlement job**: how to safely retry, how to manually settle if Horizon is down.

Contact list (on-call dev, ops, legal) in `RUNBOOK.md`.

---

## 14. Compliance — Open Items
- **PH Data Privacy Act (RA 10173)** — register as Personal Information Controller with NPC.
- **AMLC** — for the cash-in/cash-out flows, KYC + STR reporting may apply depending on volume.
- **PCSO franchise** — see PLAN.md §7. The legal model determines whether you're an agent or a separate operator.
- **TRAIN Law** — winnings >₱10,000 subject to 20% withholding tax (since 2018).
- **Age verification** — 18+ minimum; build into KYC flow.

These need legal counsel input before launch. Don't ship without answers.

---

## 15. Pre-Launch Security Checklist
- [ ] All wallet ops go through `WalletService` (CI grep test)
- [ ] All money in `decimal(14,2)`, no `float` columns (migration audit)
- [ ] Concurrent bet test passes (load test in staging)
- [ ] Telegram HMAC verification active + stale payload test
- [ ] PIN brute-force lockout active + tested
- [ ] CSP, HSTS, X-Frame-Options headers verified on securityheaders.com (A+)
- [ ] CSRF enabled on all `web` routes
- [ ] All FormRequests have `authorize()` returning true ONLY after a real check
- [ ] No `DB::raw()` with user input in codebase (grep)
- [ ] No `dangerouslySetInnerHTML` in codebase (grep)
- [ ] No `{!! !!}` in Blade (grep)
- [ ] Audit log channel writing, no PII in it
- [ ] DB CHECK constraints on `wallets.balance >= 0`
- [ ] Admin 2FA enforced
- [ ] Dependency audit clean (composer + npm)
- [ ] Secret scanner clean on git history
- [ ] Penetration test report received and findings closed
