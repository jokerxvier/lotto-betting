<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Middleware\EnsureAccountSetupIsComplete;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureAccountSetupIsComplete::class])->group(function (): void {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
