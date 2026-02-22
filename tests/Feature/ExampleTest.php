<?php

use App\Models\User;

test('returns a successful response when not installed', function () {
    config(['signals.installed' => false, 'signals.setup_complete' => false]);

    $response = $this->get('/');

    $response->assertOk();
});

test('redirects to setup when installed but setup not complete', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => false]);

    $response = $this->get('/');

    $response->assertRedirect(route('setup.wizard'));
});

test('redirects to login when setup is complete and guest', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

test('redirects to dashboard when setup is complete and authenticated', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect(route('dashboard'));
});
