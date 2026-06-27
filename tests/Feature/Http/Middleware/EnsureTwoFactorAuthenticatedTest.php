<?php

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('allows through users without 2FA enabled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

it('redirects to challenge when 2FA is enabled and not yet confirmed', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('two-factor.challenge'));
});

it('allows through when 2FA is confirmed in session', function () {
    $user = User::factory()->withTwoFactor()->create();

    Session::put('two_factor_confirmed', true);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

it('redirects settings routes to challenge when 2FA not confirmed', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('settings.profile'))
        ->assertRedirect(route('two-factor.challenge'));
});

it('allows unauthenticated requests through without redirect to challenge', function () {
    $this->get('/dashboard')
        ->assertRedirect(route('login'));
});

it('redirects to profile when 2FA required for all users but not configured', function () {
    app(SettingsService::class)->set('security.require_2fa_all', true, 'boolean');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('settings.profile'));
});

it('redirects admin to profile when 2FA required for admins but not configured', function () {
    app(SettingsService::class)->set('security.require_2fa_admin', true, 'boolean');

    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('settings.profile'));
});

it('allows through profile page when 2FA redirect is active', function () {
    app(SettingsService::class)->set('security.require_2fa_all', true, 'boolean');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.profile'))
        ->assertOk();
});

it('does not redirect when 2FA not required by settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

it('allows non-admin user through when only admin 2FA is required', function () {
    app(SettingsService::class)->set('security.require_2fa_admin', true, 'boolean');

    $user = User::factory()->create(); // regular user, not admin, not owner

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

it('passes guest requests straight through when the 2FA guard runs without auth', function () {
    // A route guarded by `2fa` but NOT `auth`: the middleware sees a null user and
    // returns $next($request) immediately (the unauthenticated short-circuit).
    Route::middleware(['web', '2fa'])->get('/2fa-guest-probe', fn (): string => 'passed-through');

    $this->get('/2fa-guest-probe')
        ->assertOk()
        ->assertSee('passed-through');
});
