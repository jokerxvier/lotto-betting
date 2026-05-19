<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminWalletAdjustmentRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Exceptions\InsufficientFundsException;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin user-management endpoints: list users with search, view one user
 * (profile + wallet + recent transactions), and credit/debit the user's
 * wallet through {@see WalletService}. Every wallet mutation here is a
 * server-authoritative ledger row tagged with the acting admin's id.
 */
final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with('wallet')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('username', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('wallet_code', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $q->orWhere('telegram_id', (int) $search);
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'telegram_id' => $user->telegram_id,
                'wallet_code' => $user->wallet_code,
                'is_admin' => $user->is_admin,
                'status' => $user->status,
                'created_at' => $user->created_at?->toIso8601String(),
                'balance' => (string) ($user->wallet?->balance ?? '0.00'),
                'held_balance' => (string) ($user->wallet?->held_balance ?? '0.00'),
            ]),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $user->load('wallet');

        $transactions = WalletTransaction::query()
            ->where('wallet_id', $user->wallet?->id)
            ->with('actor:id,name,username')
            ->latest('id')
            ->paginate(20, pageName: 'tx_page')
            ->withQueryString();

        return Inertia::render('admin/users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'telegram_id' => $user->telegram_id,
                'wallet_code' => $user->wallet_code,
                'is_admin' => $user->is_admin,
                'status' => $user->status,
                'locked_until' => $user->locked_until?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'wallet' => $user->wallet === null ? null : [
                'id' => $user->wallet->id,
                'balance' => (string) $user->wallet->balance,
                'held_balance' => (string) $user->wallet->held_balance,
                'version' => $user->wallet->version,
            ],
            'transactions' => $transactions->through(fn (WalletTransaction $tx): array => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => (string) $tx->amount,
                'balance_after' => (string) $tx->balance_after,
                'note' => $tx->note,
                'actor' => $tx->actor === null ? null : [
                    'id' => $tx->actor->id,
                    'name' => $tx->actor->name,
                    'username' => $tx->actor->username,
                ],
                'created_at' => $tx->created_at?->toIso8601String(),
            ]),
            'can_adjust' => $request->user()?->id !== $user->id,
        ]);
    }

    public function credit(
        AdminWalletAdjustmentRequest $request,
        User $user,
        WalletService $wallets,
    ): RedirectResponse {
        abort_if($user->wallet === null, 422, 'User has no wallet.');

        $wallets->credit(
            $user,
            (string) $request->validated('amount'),
            'admin_credit',
            (string) $request->validated('idempotency_key'),
            reference: null,
            actorUserId: (int) $request->user()?->id,
            note: $request->validated('note'),
        );

        return back()->with('status', "Credited ₱{$request->validated('amount')} to {$user->wallet_code}.");
    }

    public function debit(
        AdminWalletAdjustmentRequest $request,
        User $user,
        WalletService $wallets,
    ): RedirectResponse {
        abort_if($user->wallet === null, 422, 'User has no wallet.');

        try {
            $wallets->debit(
                $user,
                (string) $request->validated('amount'),
                'admin_debit',
                (string) $request->validated('idempotency_key'),
                reference: null,
                actorUserId: (int) $request->user()?->id,
                note: $request->validated('note'),
            );
        } catch (InsufficientFundsException) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient funds.',
            ]);
        }

        return back()->with('status', "Debited ₱{$request->validated('amount')} from {$user->wallet_code}.");
    }
}
