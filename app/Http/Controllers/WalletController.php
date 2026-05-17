<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WalletController extends Controller
{
    private const RECENT_TX_LIMIT = 50;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = $user?->wallet;

        $transactions = $wallet
            ? $wallet->transactions()
                ->orderByDesc('id')
                ->limit(self::RECENT_TX_LIMIT)
                ->get(['id', 'type', 'amount', 'balance_after', 'created_at'])
            : collect();

        return Inertia::render('wallet/index', [
            'wallet' => [
                'balance' => $wallet?->balance ?? '0.00',
                'held_balance' => $wallet?->held_balance ?? '0.00',
                'wallet_code' => $user?->wallet_code,
            ],
            'transactions' => $transactions,
        ]);
    }
}
