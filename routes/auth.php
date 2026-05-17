<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SetupPinController;
use App\Http\Controllers\Auth\TelegramLoginController;
use App\Http\Controllers\Auth\TelegramWebAppController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::post('auth/telegram', TelegramLoginController::class)
        ->middleware('throttle:telegram-login')
        ->name('auth.telegram');

    Route::post('auth/telegram/web-app', TelegramWebAppController::class)
        ->middleware('throttle:telegram-login')
        ->name('auth.telegram.web-app');
});

Route::middleware('auth')->group(function (): void {
    Route::get('auth/setup-pin', [SetupPinController::class, 'create'])
        ->name('auth.setup-pin');

    Route::post('auth/setup-pin', [SetupPinController::class, 'store'])
        ->middleware('throttle:pin-login')
        ->name('auth.setup-pin.store');
});
