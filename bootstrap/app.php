<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
            'signals.setup-required' => \App\Http\Middleware\EnsureSetupRequired::class,
            'signals.setup-complete' => \App\Http\Middleware\EnsureSetupComplete::class,
            'signals.active-user' => \App\Http\Middleware\EnsureActiveUser::class,
            'signals.session-timeout' => \App\Http\Middleware\EnforceSessionTimeout::class,
            '2fa' => \App\Http\Middleware\EnsureTwoFactorAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
