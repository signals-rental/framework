<?php

use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\EnsureSetupRequired;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Thunk\Verbs\Exceptions\EventNotValid;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ResolveTenant::class);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'module' => EnsureModuleEnabled::class,
            'signals.setup-required' => EnsureSetupRequired::class,
            'signals.setup-complete' => EnsureSetupComplete::class,
            'signals.active-user' => EnsureActiveUser::class,
            'signals.session-timeout' => EnforceSessionTimeout::class,
            '2fa' => EnsureTwoFactorAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A Verbs event guard (`validate()` → `assert()`) throws EventNotValid when
        // a state-mutating event is fired against an invalid state — e.g. converting
        // an Order back to a quotation. That is a client-input error, not a server
        // fault, so map it to a 422 across every current and future event-sourced
        // transition endpoint, in the project's standard validation JSON shape
        // ({"message": ..., "errors": {...}}). The guard message is surfaced under
        // the `state` key, since it always describes an invalid lifecycle position.
        $exceptions->render(function (EventNotValid $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            $message = $e->getMessage();

            return response()->json([
                'message' => $message,
                'errors' => ['state' => [$message]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    })->create();
