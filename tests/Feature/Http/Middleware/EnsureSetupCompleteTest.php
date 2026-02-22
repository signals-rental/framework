<?php

use App\Models\User;

it('redirects to setup when installed but setup not complete', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => false]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('setup.wizard'));
});

it('allows access when setup is complete', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

it('redirects to home when signals is not installed', function () {
    config(['signals.installed' => false, 'signals.setup_complete' => false]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('home'));
});

it('gates auth routes when not installed', function () {
    config(['signals.installed' => false, 'signals.setup_complete' => false]);

    $this->get('/login')
        ->assertRedirect(route('home'));
});

it('gates auth routes when installed but setup not complete', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => false]);

    $this->get('/login')
        ->assertRedirect(route('setup.wizard'));
});

it('allows auth routes when setup is complete', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $this->get('/login')
        ->assertOk();
});

it('redirects to setup when env says incomplete even if settings show complete', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => false]);

    settings()->set('setup.completed_at', now()->toIso8601String());

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('setup.wizard'));
});
