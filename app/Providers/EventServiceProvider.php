<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\DrawSettled;
use App\Listeners\NotifyUsersOfSettledDraw;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        DrawSettled::class => [
            NotifyUsersOfSettledDraw::class,
        ],
    ];
}
