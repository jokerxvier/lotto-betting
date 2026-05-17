# LARAVEL_BEST_PRACTICES.md

Backend conventions for Lotto PH. Follows the existing `laravel-inertia-react` skill (Repository + Service pattern, strict types, Pest tests) with **betting-domain additions** below.

> **Framework version:** Laravel 13 (released March 17, 2026), PHP 8.3 minimum. This is a smooth upgrade from Laravel 12 — zero breaking changes — but Laravel 13 adds some patterns worth adopting in new code. See §0 below.

---

## 0. Laravel 13 — What to Adopt

These are the Laravel 13 features that meaningfully improve our specific stack. The rest (AI SDK, JSON:API resources) we ignore for now.

### 0.1 PHP Attributes on models — **adopt for new models**
Laravel 13 lets you replace `protected $table`, `$fillable`, `$hidden`, `$casts` with class-level attributes. Cleaner, colocated with the class name.

```php
// Old (still works fine)
class Bet extends Model
{
    protected $table = 'bets';
    protected $fillable = ['user_id', 'draw_id', 'amount', 'idempotency_key'];
    protected $hidden = ['idempotency_key'];
}

// Laravel 13 — preferred for new models
#[Table('bets')]
#[Fillable(['user_id', 'draw_id', 'amount', 'idempotency_key'])]
#[Hidden(['idempotency_key'])]
class Bet extends Model
{
    protected function casts(): array
    {
        return ['settled_at' => 'datetime'];
    }
}
```

Rule: **attributes for declarative config**, the `casts()` method stays a method (it returns a dynamic array).

### 0.2 Controller authorization attributes
`#[Authorize]` replaces the `$this->authorize(...)` call.

```php
final class BetController extends Controller
{
    public function __construct(private readonly BetService $bets) {}

    #[Middleware('throttle:bets')]
    public function store(StoreBetRequest $request): RedirectResponse
    {
        $bet = $this->bets->place($request->user(), $request->validated());
        return redirect()->route('tickets.show', $bet)->with('success', 'Bet placed.');
    }

    #[Authorize('view', 'bet')]   // resolves the {bet} route param
    public function show(Bet $bet): Response
    {
        return inertia('Tickets/Show', ['bet' => $bet->load('legs.gameBetType.game')]);
    }
}
```

This makes the auth check **statically visible** at the method signature — easier to grep, easier to audit. For policies, we still keep one Policy class per resource as before.

### 0.3 Centralized queue routing — `Queue::route()`
In `AppServiceProvider::boot()` (or a dedicated `QueueServiceProvider`), declare which queue each job class lives on:

```php
use App\Jobs\{SettleDrawJob, PollDepositStatus, SendBetConfirmationTelegram};
use Illuminate\Support\Facades\Queue;

public function boot(): void
{
    Queue::route(SettleDrawJob::class,            connection: 'redis', queue: 'settlement');
    Queue::route(PollDepositStatus::class,        connection: 'redis', queue: 'deposits');
    Queue::route(SendBetConfirmationTelegram::class, connection: 'redis', queue: 'notifications');
}
```

Benefits over per-job `$queue` properties:
- One file to grep for queue topology.
- Easier to change queue names without touching every job.
- Horizon config maps cleanly to the routes.

### 0.4 Job attributes — `#[Tries]`, `#[Backoff]`, `#[Timeout]`, `#[FailOnTimeout]`
Replace the matching properties on jobs.

```php
#[Tries(5)]
#[Backoff([10, 30, 60, 120, 300])]    // exponential, in seconds
#[Timeout(120)]
#[FailOnTimeout]
final class PollDepositStatus implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $depositId) {}

    public function handle(DepositService $service): void { /* … */ }

    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->depositId))->expireAfter(180)];
    }
}
```

### 0.5 `PreventRequestForgery` middleware
Laravel 13 formalizes the old `VerifyCsrfToken` as `PreventRequestForgery` with **origin-aware verification** on top of token CSRF. Adopt as the default for the `web` middleware group — see `SECURITY.md` §4.1.

### 0.6 `Cache::touch()`
Useful for the wallet balance cache and the latest-result cache — extends TTL without re-fetching.

```php
Cache::touch("wallet:balance:{$walletId}", now()->addMinutes(5));
```

Use sparingly: the wallet balance is authoritative in the DB, not the cache. We cache only as a read-path optimisation for the header pill.

### 0.7 Reverb DB driver — live result broadcasting
For pushing draw results live to clients on `/lotto` and `/results`, we use Laravel Reverb with the **database driver** instead of Redis pub/sub. One less moving part. Channels:

- `draw.{drawId}` — broadcasts the result the moment `draw_results` is inserted.
- `user.{userId}` — broadcasts wallet credits after settlement.

In `BroadcastServiceProvider`:
```php
Broadcast::channel('user.{userId}', fn (User $user, int $userId) => $user->id === $userId);
```

### 0.8 What we are NOT adopting (yet)
- **Laravel AI SDK** — no AI features in MVP. We're settling lotto bets, not summarising them. Maybe Phase 3 for support-ticket triage.
- **JSON:API resources** — we use Inertia, not REST. Internal API endpoints are few; plain controllers + arrays serve fine.
- **Passkeys for end users** — kept as Phase 2 opt-in. PIN + Telegram is sufficient for MVP; passkeys add UX complexity we don't need to take on day one. **Admin passkeys, on the other hand, are MVP** — see `SECURITY.md` §9.

---

## 1. Mandatory File Header
Every PHP file starts with:
```php
<?php

declare(strict_types=1);

namespace App\…;
```

PHPStan level 8 in CI. No untyped properties, no untyped returns.

---

## 2. Layered Architecture

```
HTTP Request
    ↓
Form Request (validate)
    ↓
Controller (thin — auth, call action, return inertia())
    ↓
Action (one domain verb: PlaceBet, SettleDraw, ReconcileDeposit)
    │   composes ↓                ↘
    │       Service (subsystem: WalletService)
    │           ↓
    └─→ Repository (Eloquent queries only)
            ↓
        Model
```

### Rules
- **Controllers**: max ~15 lines per action. Authorize → validate (via FormRequest) → invoke Action → `inertia(...)` or `redirect()`.
- **Actions** *(new)*: encapsulate **one domain verb**. Wrap multi-step work in a DB transaction, dispatch events, return the domain object. One `execute()` method per Action.
- **Services**: coherent subsystems with related operations that share state or invariants (e.g. `WalletService::debit`/`credit`/`balance` belong together). Called *by* Actions, not by Controllers directly.
- **Repositories**: data access only. Return models, collections, paginators. No business rules.
- **Models**: relationships, casts, accessors/mutators, scopes. No I/O, no events dispatched from models (dispatch from the Action that performs the work).

### Actions vs Services — when to use which

| Use an **Action** when…                                | Use a **Service** when…                          |
|--------------------------------------------------------|--------------------------------------------------|
| The operation is a single domain verb                  | You have a set of related operations that share invariants (debit/credit/lock-and-check on the same wallet) |
| It involves multiple steps + a transaction             | You need a stateful component with lifecycle (broadcast manager, payment provider client) |
| It dispatches events or side effects                   | The operations are stateless utilities (`PayoutCalculator`, `WinChecker`) |
| It's called from more than one entry point (HTTP, queue, console) | The "verb" doesn't really need its own file (`updateUserEmail` is fine as a Service method) |

### Anti-patterns
- ❌ **Action sprawl** — don't create `GetWalletBalanceAction`, `FindBetByIdAction`. Simple reads belong in Controllers or Repositories.
- ❌ **Fat actions** — if an Action's `execute()` exceeds ~60 lines, split into smaller Actions or push detail into Services.
- ❌ **Action chaining via static calls** — `OtherAction::run()` hides dependencies. Inject in constructor instead.
- ❌ **Service-in-Action-clothing** — if a class has 5 public methods, it's a Service, not an Action. Rename and move.

---

## 3. Actions — Convention

### 3.1 Shape
```php
<?php

declare(strict_types=1);

namespace App\Actions\Bets;

use App\Events\BetPlaced;
use App\Exceptions\DrawClosedException;
use App\Exceptions\InsufficientFundsException;
use App\Models\{Bet, User};
use App\Repositories\Contracts\{BetRepositoryInterface, DrawRepositoryInterface};
use App\Services\{PayoutCalculator, WalletService};
use App\ValueObjects\TransactionType;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

final class PlaceBetAction
{
    public function __construct(
        private readonly BetRepositoryInterface $bets,
        private readonly DrawRepositoryInterface $draws,
        private readonly WalletService $wallets,
        private readonly PayoutCalculator $payouts,
    ) {}

    /**
     * @param array{
     *     draw_id: int,
     *     idempotency_key: string,
     *     legs: array<int, array{game_bet_type_id: int, numbers: int[], amount: string}>
     * } $data
     */
    public function execute(User $user, array $data): Bet
    {
        return DB::transaction(function () use ($user, $data) {
            // 1. Idempotency short-circuit
            $existing = $this->bets->findByIdempotencyKey($user->id, $data['idempotency_key']);
            if ($existing !== null) {
                return $existing;
            }

            // 2. Cutoff check (server-authoritative)
            $draw = $this->draws->findOrFail($data['draw_id']);
            if ($draw->cutoff_at->isPast()) {
                throw new DrawClosedException();
            }

            // 3. Compute totals + potential payouts per leg
            $totalAmount = Money::zero('PHP');
            $totalPayout = Money::zero('PHP');
            $legsWithPayouts = [];

            foreach ($data['legs'] as $leg) {
                $type = $this->bets->findBetType($leg['game_bet_type_id']);
                $betMoney = Money::of($leg['amount'], 'PHP');
                $payout = $this->payouts->potentialPayout($type, $leg['numbers'], $betMoney);

                $totalAmount = $totalAmount->plus($betMoney);
                $totalPayout = $totalPayout->plus($payout);
                $legsWithPayouts[] = [...$leg, 'potential_payout' => (string) $payout->getAmount()];
            }

            // 4. Create the bet + legs
            $bet = $this->bets->createWithLegs($user, $draw, [
                'amount'           => (string) $totalAmount->getAmount(),
                'potential_payout' => (string) $totalPayout->getAmount(),
                'idempotency_key'  => $data['idempotency_key'],
            ], $legsWithPayouts);

            // 5. Debit the wallet (uses its own lock + idempotency)
            $this->wallets->debit(
                wallet:          $user->wallet,
                amount:          $totalAmount,
                type:            TransactionType::BetDebit,
                reference:       $bet,
                idempotencyKey:  $data['idempotency_key'],
            );

            // 6. Dispatch event for side effects (notifications, audit)
            BetPlaced::dispatch($bet);

            return $bet;
        }, attempts: 3);
    }
}
```

### 3.2 Calling from a controller
```php
final class BetController extends Controller
{
    public function __construct(private readonly PlaceBetAction $placeBet) {}

    public function store(StoreBetRequest $request): RedirectResponse
    {
        try {
            $bet = $this->placeBet->execute($request->user(), $request->validated());
        } catch (DrawClosedException) {
            return back()->with('error', 'This draw is closed. Pick another draw.');
        } catch (InsufficientFundsException) {
            return back()->with('error', 'Insufficient funds. Top up your wallet.');
        }

        return redirect()->route('tickets.show', $bet)->with('success', 'Bet placed.');
    }
}
```

The controller stays thin: validation by `StoreBetRequest`, work by `PlaceBetAction`, HTTP-mapping of domain exceptions, redirect. No business logic leaked.

### 3.3 Calling from a job
The same Action drives async work too. `SettleDrawJob` doesn't repeat business logic — it loops over bets and calls `SettleBetAction`:

```php
final class SettleDrawJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $drawId) {}

    public function handle(SettleDrawAction $action): void
    {
        $action->execute($this->drawId);
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->drawId))->expireAfter(300)];
    }
}

final class SettleDrawAction
{
    public function __construct(
        private readonly SettleBetAction $settleBet,
        private readonly DrawRepositoryInterface $draws,
    ) {}

    public function execute(int $drawId): void
    {
        $draw = $this->draws->findOrFail($drawId);
        if ($draw->status === DrawStatus::Settled) return;   // idempotent

        $this->draws->forEachPendingBet($draw, function (Bet $bet) {
            $this->settleBet->execute($bet);
        });

        $this->draws->markSettled($draw);
    }
}
```

`SettleBetAction` is the per-bet atomic unit, reused from admin "void and re-settle" flows too. No duplication.

### 3.4 Naming
- `<Verb><Noun>Action` — `PlaceBetAction`, `SettleDrawAction`, `ApproveWithdrawalAction`, `ReconcileDepositAction`.
- Method name: **`execute()`**. Not `handle()` (that's for jobs/listeners), not `__invoke()` (less greppable).
- Suffix `Action` always — makes it instantly clear in IDE and grep.

### 3.5 Directory layout
```
app/
├── Actions/
│   ├── Bets/
│   │   ├── PlaceBetAction.php
│   │   └── SettleBetAction.php
│   ├── Draws/
│   │   ├── PublishResultAction.php
│   │   └── SettleDrawAction.php
│   ├── Deposits/
│   │   ├── CreateDepositAction.php
│   │   ├── ReconcileDepositAction.php
│   │   └── CompleteDepositAction.php
│   ├── Withdrawals/
│   │   ├── RequestWithdrawalAction.php
│   │   ├── ApproveWithdrawalAction.php
│   │   └── RejectWithdrawalAction.php
│   └── Auth/
│       ├── LoginWithTelegramAction.php
│       └── LoginWithPinAction.php
├── Services/
│   ├── WalletService.php          # subsystem: debit/credit/balance
│   ├── PayoutCalculator.php       # stateless utility
│   └── WinChecker.php             # stateless utility
├── Repositories/
│   ├── Contracts/
│   └── Eloquent…Repository.php
└── Models/
```

Group Actions by domain (Bets, Draws, etc.), not by HTTP verb or by entry point.

### 3.6 Testing
Actions are the highest-value test target. They're where the domain rules live.

```php
it('places a bet, debits the wallet, dispatches BetPlaced', function () {
    Event::fake([BetPlaced::class]);

    $user = User::factory()->withWallet('1000.00')->create();
    $draw = Draw::factory()->scheduled()->cutoffAt(now()->addHour())->create();
    $type = GameBetType::factory()->for($draw->game)->target()->create();

    $bet = app(PlaceBetAction::class)->execute($user, [
        'draw_id'         => $draw->id,
        'idempotency_key' => 'test-key-1',
        'legs'            => [[
            'game_bet_type_id' => $type->id,
            'numbers'          => [1, 2, 3],
            'amount'           => '10.00',
        ]],
    ]);

    expect($bet->status)->toBe(BetStatus::Pending)
        ->and($user->wallet->fresh()->balance)->toEqual('990.00');

    Event::assertDispatched(BetPlaced::class, fn (BetPlaced $e) => $e->bet->is($bet));
});

it('is idempotent — repeated call with same key returns the same bet', function () {
    $user = User::factory()->withWallet('1000.00')->create();
    /* …setup… */

    $first  = app(PlaceBetAction::class)->execute($user, $data);
    $second = app(PlaceBetAction::class)->execute($user, $data);

    expect($first->id)->toBe($second->id)
        ->and($user->wallet->fresh()->balance)->toEqual('990.00');  // not 980
});

it('rejects when cutoff has passed', function () {
    /* …setup with cutoff_at in the past… */
    expect(fn () => app(PlaceBetAction::class)->execute($user, $data))
        ->toThrow(DrawClosedException::class);
    expect($user->wallet->fresh()->balance)->toEqual('1000.00');   // unchanged
});
```

The Action is the natural seam for these tests — no HTTP, no auth, no view layer noise. Pure domain.

---

## 4. Money — Read This First

**The single most common bug class in betting apps is money math. Follow these rules.**

### 4.1 Storage
- DB column: `decimal(14, 2)` for any monetary value. Never `float`/`double`.
- Postgres: use `NUMERIC(14,2)`. MySQL: `DECIMAL(14,2)`.

### 4.2 In PHP
Use the [`brick/money`](https://github.com/brick/money) package or build a tiny `Money` value object — **never use raw `float`** for arithmetic.

```php
use Brick\Money\Money;

$balance = Money::of($wallet->balance, 'PHP');
$bet     = Money::of($request->validated('amount'), 'PHP');
$after   = $balance->minus($bet); // exact decimal math
```

### 4.3 Eloquent cast
```php
// app/Casts/MoneyCast.php — wraps decimal string ↔ Money
protected function casts(): array {
    return ['balance' => MoneyCast::class];
}
```

### 4.4 Over the wire (Inertia / API)
Send money as a **string** (`"1234.50"`) or as an object `{ amount: "1234.50", currency: "PHP" }`. Never as a JS `number` — floats lose precision past 2¹⁵ centavos.

### 4.5 Forbidden
```php
// ❌ NEVER
$wallet->balance += $amount;
$wallet->balance = $wallet->balance - $bet->amount;

// ✅ ALWAYS — go through a service that uses Money + a transaction
$walletService->debit($wallet, $amount, reference: $bet);
```

---

## 5. Wallet Service Pattern

The wallet is the heart of the app. **Every balance change goes through `WalletService` — no exceptions.**

```php
final class WalletService
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
        private readonly WalletTransactionRepositoryInterface $transactions,
    ) {}

    public function debit(
        Wallet $wallet,
        Money $amount,
        TransactionType $type,
        Model $reference,
        string $idempotencyKey,
    ): WalletTransaction {
        return DB::transaction(function () use ($wallet, $amount, $type, $reference, $idempotencyKey) {
            // Idempotency: if same key already processed for this wallet, return it
            $existing = $this->transactions->findByIdempotencyKey($wallet->id, $idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }

            // Pessimistic lock on this wallet row
            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $current = Money::of($locked->balance, 'PHP');
            if ($current->isLessThan($amount)) {
                throw new InsufficientFundsException();
            }

            $after = $current->minus($amount);
            $locked->balance = (string) $after->getAmount();
            $locked->save();

            return $this->transactions->create([
                'wallet_id'        => $locked->id,
                'type'             => $type->value,
                'amount'           => '-' . $amount->getAmount(),
                'balance_after'    => (string) $after->getAmount(),
                'reference_type'   => $reference::class,
                'reference_id'     => $reference->getKey(),
                'idempotency_key'  => $idempotencyKey,
            ]);
        });
    }

    public function credit(/* mirror of debit, signs flipped */) { /* … */ }
}
```

### Rules
- **Always** `lockForUpdate()` on the wallet row inside the transaction.
- **Always** create a `wallet_transactions` row (double-entry book — running ledger).
- **Always** store `balance_after` on the transaction. Reconstructing the ledger from sums is fragile; storing the snapshot lets you spot-check.
- **Never** update `wallet.balance` outside this service.
- **Idempotency keys** prevent double-debits on client retries — see §6.

---

## 6. Database Transactions & Locking

### Use `DB::transaction()` (closure form), not manual `beginTransaction`
Auto-rolls back on exception, supports nested savepoints, retries on deadlock with `attempts` param.

```php
DB::transaction(function () use ($user, $request) {
    // … bet placement + wallet debit
}, attempts: 3); // retry up to 3× on deadlock
```

### Choose the right lock
| Scenario                       | Lock                            |
|--------------------------------|---------------------------------|
| Wallet debit/credit            | `lockForUpdate()` (pessimistic) |
| Reading balance for display    | No lock                         |
| Settling a draw (admin job)    | `lockForUpdate()` on the draw row |
| Bet placement                  | `lockForUpdate()` on wallet only — bet row is new |

### Postgres-specific
- Use `SERIALIZABLE` isolation only for the draw settlement job; default `READ COMMITTED` for everything else.
- Add `CHECK (balance >= 0)` constraint on `wallets.balance` as a last-line defense.

---

## 7. Idempotency

Every bet POST carries an `idempotency_key` (client-generated UUID v4 stored for 24h).

### Migration
```php
$table->uuid('idempotency_key');
$table->unique(['user_id', 'idempotency_key']);
```

### Service
Before doing any work, check for an existing bet with the same `(user_id, idempotency_key)`. If found, return it.

### FormRequest
```php
'idempotency_key' => ['required', 'uuid'],
```

---

## 8. Time & Cutoffs

Bet acceptance is time-critical. **Use server time, never client time.**

- Store all draw times in UTC; convert at the boundary (`Asia/Manila`).
- `draws.cutoff_at` is the hard wall — typically 5 minutes before draw_at.
- Check cutoff in the service, not the controller:

```php
if ($draw->cutoff_at->isPast()) {
    throw new DrawClosedException();
}
```

- Sanity test: place a bet 1 second before cutoff and 1 second after — the second must be rejected.

---

## 9. Form Requests

One per write action. Pattern:

```php
final class StoreBetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return [
            'draw_id'         => ['required', 'integer', Rule::exists('draws', 'id')->where('status', 'scheduled')],
            'idempotency_key' => ['required', 'uuid'],
            'legs'            => ['required', 'array', 'min:1', 'max:20'],
            'legs.*.numbers'  => ['required', 'array'],
            'legs.*.numbers.*'=> ['required', 'integer'],
            'legs.*.amount'   => ['required', 'numeric', 'min:10', 'max:10000', 'decimal:0,2'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Game-specific number range check, done after base rules pass
        $validator->after(function (Validator $v) {
            // … check numbers fit the game's picks_count, min, max
        });
    }
}
```

---

## 10. Policies

One policy per resource. Auto-discovered. Always check ownership:

```php
final class BetPolicy
{
    public function view(User $user, Bet $bet): bool
    {
        return $user->id === $bet->user_id;
    }

    public function void(User $user, Bet $bet): bool
    {
        // Only admins, only if not yet settled
        return $user->is_admin && $bet->status === BetStatus::Pending;
    }
}
```

In controllers: `$this->authorize('view', $bet);`

---

## 11. Events & Listeners

Use events for **side effects**, not core flow.

```
BetPlaced       → SendBetConfirmationTelegram (queued)
BetSettled      → SendPayoutNotification (queued)
DrawPublished   → BroadcastDrawResult (websocket)
WalletDebited   → LogToAuditTrail (sync)
```

- Listeners that talk to external services must `implements ShouldQueue`.
- Never put core flow (e.g. "credit wallet on win") in a listener — put it in the service, deterministic and tested.

---

## 12. Queue Jobs

- Use Horizon. Configure per-queue: `bets`, `notifications`, `default`.
- All jobs are **idempotent** — re-running must not duplicate effects.
- Use `WithoutOverlapping` middleware on `SettleDrawJob` keyed by draw id.

```php
public function middleware(): array
{
    return [(new WithoutOverlapping((string) $this->drawId))->expireAfter(300)];
}
```

---

## 13. Logging & Audit

Two channels:
- `stack` — default Laravel log (errors, warnings, debug).
- `audit` — append-only, **never rotated/deleted**, structured JSON. Every wallet mutation, every bet, every admin action.

```php
// config/logging.php
'audit' => [
    'driver' => 'daily',
    'path' => storage_path('logs/audit.log'),
    'days' => 0, // keep forever
    'tap' => [JsonFormatter::class],
],
```

Helper:
```php
Log::channel('audit')->info('wallet.debit', [
    'user_id'       => $user->id,
    'amount'        => (string) $amount->getAmount(),
    'reference'     => [$reference::class, $reference->id],
    'idempotency'   => $key,
    'balance_after' => (string) $after->getAmount(),
]);
```

Ship audit logs to S3 nightly with versioning + object lock for tamper-evidence.

---

## 14. Database Migration Conventions

- One migration = one logical change.
- Always reversible — `down()` must mirror `up()`.
- Foreign keys: `->constrained()->cascadeOnDelete()` only when truly desired; bets/transactions should be `restrictOnDelete` (you can't delete a user with bets).
- Money columns: `$table->decimal('amount', 14, 2);`
- Add indexes for every column you filter/sort by (`user_id`, `draw_id`, `created_at`, `status`).
- Composite indexes for common queries — e.g. `(user_id, status, created_at)` for ticket history.

---

## 15. Testing

Pest tests required for:
- **Wallet service** — debit, credit, insufficient funds, idempotency, concurrent debit (use `DB::transaction` with race simulation).
- **Bet placement** — cutoff respected, valid numbers, atomicity (mock failure mid-transaction → balance unchanged).
- **Draw settlement** — winners credited, losers untouched, re-running is no-op.
- **Auth** — Telegram hash verification, PIN lockout after N failures.

```php
it('rejects bet placement after cutoff', function () {
    $user = User::factory()->withWallet(1000)->create();
    $draw = Draw::factory()->closedAt(now()->subSecond())->create();

    $response = actingAs($user)->postJson('/bets', [/* … */]);

    $response->assertStatus(422);
    expect($user->wallet->fresh()->balance)->toEqual('1000.00');
});
```

Aim for 90%+ coverage on the wallet and bet domain. UI tests can be lighter.

---

## 16. Config & Env

- All secrets in `.env`, **never** in code or version control.
- `config/lotto.php` for non-secret game config (cutoff windows, default payouts) — overridable per env.
- Use `config()` calls, never `env()` calls outside `config/*` files (env is only loaded in `bootstrap/cache/config.php` in production).

```php
// config/lotto.php
return [
    'cutoff_buffer_seconds' => env('LOTTO_CUTOFF_BUFFER', 300),
    'min_bet'               => env('LOTTO_MIN_BET', 10),
    'max_bet'               => env('LOTTO_MAX_BET', 10000),
];
```

---

## 17. Quick Checklist Per Feature
- [ ] Migration with indexes and constraints
- [ ] Model with strict types, casts, relations (Laravel 13 attributes optional)
- [ ] Factory + Seeder
- [ ] Repository Interface + Eloquent impl + binding in `RepositoryServiceProvider`
- [ ] **Action(s)** for each domain verb (`PlaceBet`, `SettleBet`, etc.) — see §3
- [ ] Service(s) for any coherent subsystem touched (e.g. `WalletService`)
- [ ] FormRequest(s)
- [ ] Controller (thin — delegates to Action)
- [ ] Policy
- [ ] Events + queued listeners (where applicable)
- [ ] Pest tests against the **Action**, not just the HTTP route: happy + edge + concurrent
- [ ] Audit log calls on every state change
- [ ] Route registration with proper middleware (`auth`, `verified`, `throttle`)
