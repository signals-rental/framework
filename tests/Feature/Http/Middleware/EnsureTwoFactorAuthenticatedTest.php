<?php

use App\Models\User;
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
