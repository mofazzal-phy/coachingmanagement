<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Alias register korar sothik niyom ekhane
        $middleware->alias([
            'api.auth'   => \Modules\Core\app\Http\Middleware\ApiAuthenticate::class,
            'role'               => \Modules\Core\app\Http\Middleware\RoleMiddleware::class,
            'permission'         => \Modules\Core\app\Http\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Modules\Core\app\Http\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();