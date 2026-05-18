<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Idempotently top up the 7-day window of scheduled draws every night at
// 12:05 AM Manila time. `onOneServer` is a no-op locally but prevents
// duplicate runs once we're on multi-node Forge.
Schedule::command('draws:generate-upcoming --days=7')
    ->dailyAt('00:05')
    ->timezone('Asia/Manila')
    ->onOneServer()
    ->name('draws-generate-upcoming');

// Auto-publish + settle awaiting draws via the PCSO scraper, when the admin
// toggle at /admin/settings is on. The command is a no-op when off, so this
// schedule is always-on; the toggle is the actual switch.
Schedule::command('draws:auto-settle')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping()
    ->name('draws-auto-settle');
