<?php

use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin-role' => RoleMiddleware::class,
            'isSignin' => RedirectIfAuthenticated::class,
            // Tambahkan middleware lainnya di sini
        ]);
        $middleware->validateCsrfTokens(except: [
            // 'stripe/*',
            // 'foo/bar',  // Hanya path yang diperlukan, bukan URL lengkap
            // 'foo/*',
            '/notification/payment',
            '/notification/payout',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();
