<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'profile' => [
                'username' => $user?->username,
                'wallet_code' => $user?->wallet_code,
                'has_telegram' => $user?->telegram_id !== null,
            ],
            'status' => $request->session()->get('status'),
        ]);
    }
}
