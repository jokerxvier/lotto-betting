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

// Nightly unconditional backfill of yesterday + today's draw_results so the
// public /results page always shows fresh data the next morning regardless
// of the auto-settle toggle. Independent of `draws:generate-upcoming` (which
// creates future Draw rows) — staggered by 5 minutes so the upcoming-pass
// completes first and any of today's slot rows exist before we try to
// attach DrawResult to them. Does NOT settle bets.
Schedule::command('draws:backfill-results --days=2')
    ->dailyAt('00:10')
    ->timezone('Asia/Manila')
    ->onOneServer()
    ->name('draws-backfill-nightly');

// PCSO slot-aligned passes — runs shortly after each draw (2/5/9 PM Manila)
// so today's results land in the DB within ~10 minutes of broadcast,
// regardless of the auto-settle toggle. Single-day backfill keeps the
// upstream call light. Slight stagger after the draw lets the source
// (pcso-parser API → lottopcso.com) publish numbers before we fetch.
foreach (['14:10', '17:10', '21:10'] as $slot) {
    Schedule::command('draws:backfill-results --days=1')
        ->dailyAt($slot)
        ->timezone('Asia/Manila')
        ->onOneServer()
        ->name("draws-backfill-slot-{$slot}");
}
