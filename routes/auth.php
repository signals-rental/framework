<?php

use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('signals.setup-complete')->group(function () {
    Volt::route('two-factor-challenge', 'auth.two-factor-challenge')
        ->name('two-factor.challenge');

    Route::middleware('guest')->group(function () {
        Volt::route('login', 'auth.login')
            ->name('login');

        Volt::route('forgot-password', 'auth.forgot-password')
            ->name('password.request');

        Volt::route('reset-password/{token}', 'auth.reset-password')
            ->name('password.reset');

        // Single Sign-On (Google / Microsoft 365) — OAuth handshake.
        // The provider is constrained to the supported allow-list so unknown
        // providers 404, and both routes are rate-limited (spec §7, §10). The
        // constraint is applied per-route — a `whereIn` chained after `group()`
        // is a no-op because the routes are already registered.
        Route::middleware('throttle:6,1')->group(function () {
            Route::get('auth/{provider}/redirect', [SsoController::class, 'redirect'])
                ->whereIn('provider', ['google', 'microsoft'])
                ->name('sso.redirect');

            Route::get('auth/{provider}/callback', [SsoController::class, 'callback'])
                ->whereIn('provider', ['google', 'microsoft'])
                ->name('sso.callback');
        });
    });

    Route::middleware('auth')->group(function () {
        Volt::route('verify-email', 'auth.verify-email')
            ->name('verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Volt::route('confirm-password', 'auth.confirm-password')
            ->name('password.confirm');
    });

    Route::post('logout', Logout::class)
        ->name('logout');
});
