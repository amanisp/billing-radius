<?php

namespace App\Listeners;

use App\Events\ActivityLogged;
use Google\Service\AnalyticsReporting\Activity;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Request;

class LogAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        ActivityLogged::dispatch(
            'LOGIN',
            'users',
            'User logged in: ' . $event->user->username . '  \nUser Agent: ' . Request::userAgent(),
        );
    }

    public function handleLogout(Logout $event): void
    {
        ActivityLogged::dispatch(
            'LOGOUT',
            'users',
            'User logged out: ' . $event->user->username . '  \nUser Agent: ' . Request::userAgent(),
        );
    }
    public function handleFailedLogin(Failed $event): void
    {
        ActivityLogged::dispatch(
            'LOGIN_FAILED',
            'users',
            'Login attempt failed for: ' . $event->credentials['username'] . '  \nUser Agent: ' . Request::userAgent(),
        );
    }
}
