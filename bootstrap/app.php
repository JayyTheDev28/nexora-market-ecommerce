<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\LogisticsMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
|--------------------------------------------------------------------------
| NOTE
|--------------------------------------------------------------------------
| Your Laravel project already ships with this file. Don't overwrite it
| wholesale — the only Nexora-specific addition is the `role` alias inside
| withMiddleware() below. Copy just that line into your existing file.
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'admin' => AdminMiddleware::class,
            'logistics' => LogisticsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
