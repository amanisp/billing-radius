<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use App\Events\ActivityLogged;
use App\Listeners\StoreActivityLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use App\Listeners\LogAuthenticationActivity;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ActivityLogged::class => [
            StoreActivityLog::class,
        ],
        Login::class => [
            [LogAuthenticationActivity::class , 'handleLogin'],
        ],
        Logout::class => [
            [LogAuthenticationActivity::class, 'handleLogout'],
        ],
        Failed::class => [
            [LogAuthenticationActivity::class, 'handleFailedLogin'],
        ]
    ];

    public function boot()
    {
        parent::boot();

        Event::listen('*', function ($eventName, array $data) {
            Log::info('Broadcast Event:', [
                'event' => $eventName,
                'data' => $data,
                'time' => now()->toDateTimeString()
            ]);
        });
    }
}
