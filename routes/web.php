<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DrawResultController as AdminDrawResultController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\WalletController as AdminWalletController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\LottoHomeController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\TicketsController;
use App\Http\Controllers\WalletController;
use App\Http\Middleware\EnsureAccountSetupIsComplete;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return Auth::user()->is_admin === true
        ? redirect()->route('admin.dashboard')
        : redirect()->route('lotto');
})->name('home');

Route::middleware(['auth', EnsureAccountSetupIsComplete::class])->group(function (): void {
    Route::get('lotto', [LottoHomeController::class, 'index'])->name('lotto');

    Route::get('wallet', [WalletController::class, 'index'])
        ->name('wallet.index');

    Route::post('games/{game}/bet', [BetController::class, 'store'])
        ->middleware('throttle:bet-place')
        ->name('games.bet.store');

    Route::post('bets/cart', [CartController::class, 'store'])
        ->middleware('throttle:bet-place')
        ->name('bets.cart.store');

    Route::get('tickets', [TicketsController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{bet}', [TicketsController::class, 'show'])->name('tickets.show');

    Route::get('results', [ResultsController::class, 'index'])->name('results.index');
});

Route::middleware(['auth', EnsureAccountSetupIsComplete::class, EnsureAdmin::class])
    ->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('wallets', [AdminWalletController::class, 'create'])
            ->name('wallets.create');

        Route::post('wallets/top-up', [AdminWalletController::class, 'topUp'])
            ->name('wallets.top-up');

        Route::get('draws', [AdminDrawResultController::class, 'index'])
            ->name('draws.index');

        Route::get('draws/{draw}/result', [AdminDrawResultController::class, 'create'])
            ->name('draws.result.create');

        Route::post('draws/{draw}/result', [AdminDrawResultController::class, 'store'])
            ->name('draws.result.store');

        Route::get('settings', [AdminSettingsController::class, 'edit'])
            ->name('settings.edit');

        Route::post('settings', [AdminSettingsController::class, 'update'])
            ->name('settings.update');
    });

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
