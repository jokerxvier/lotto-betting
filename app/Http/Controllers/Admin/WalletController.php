<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminTopUpRequest;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class WalletController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('admin/wallets/top-up', [
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    public function topUp(AdminTopUpRequest $request, WalletService $wallets): RedirectResponse
    {
        $user = User::query()->where('wallet_code', $request->validated('wallet_code'))->firstOrFail();

        $wallets->credit(
            $user,
            (string) $request->validated('amount'),
            'admin_topup',
            (string) $request->validated('idempotency_key'),
        );

        return redirect()->route('admin.wallets.create')->with('status', "Credited ₱{$request->validated('amount')} to {$user->wallet_code}.");
    }
}
