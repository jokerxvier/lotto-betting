<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SetupPinAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetupPinRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SetupPinController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->username !== null && $user->pin_hash !== null) {
            return redirect()->route('lotto');
        }

        return Inertia::render('auth/setup-pin', [
            'first_name' => $user?->name,
            'has_telegram' => $user?->telegram_id !== null,
        ]);
    }

    public function store(SetupPinRequest $request, SetupPinAction $setup): RedirectResponse
    {
        $user = $request->user();

        $setup->execute(
            $user,
            (string) $request->validated('username'),
            (string) $request->validated('pin'),
        );

        return redirect()->intended(route('lotto'));
    }
}
