<?php

declare(strict_types=1);

return [
    /*
    |---------------------------------------------------------------------------
    | Draw schedule
    |---------------------------------------------------------------------------
    | PCSO draw times in Asia/Manila as `HH:MM` 24-hour strings. The cron
    | (App\Console\Commands\GenerateUpcomingDrawsCommand) uses this list to
    | seed upcoming draws for every active game N days ahead. Override per
    | game by adding a `<game_code>` key (e.g. `'2d' => [...]`) — falls back
    | to `default` when no game-specific entry exists.
    */
    'draw_schedule' => [
        'default' => ['14:00', '17:00', '21:00'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Bet cutoff window
    |---------------------------------------------------------------------------
    | How many minutes before each draw bets stop being accepted. Drives the
    | cron's `cutoff_at = draw_at - cutoff_minutes`. Cutoff is also enforced
    | server-side in PlaceBetAction (Hard Rule 4), so changing this here
    | only affects newly-created draws — historical draws keep their values.
    */
    'cutoff_minutes' => 60,
];
