<?php

use App\Models\User;

// M8-4a: the Echo client subscribes to private availability channels by POSTing
// to `/broadcasting/auth`. That route is auto-registered via the `channels`
// argument to `withRouting()` in bootstrap/app.php and authenticates against the
// app's default (web/session) guard. These tests pin that contract so the
// live-availability listeners on the opportunity Show + editor can authorize.

it('registers the broadcasting auth route', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'broadcasting/auth');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('POST');
});

it('rejects unauthenticated private-channel authorization requests', function () {
    // The `/broadcasting/auth` route carries the `auth` middleware (bootstrap/app.php),
    // so a guest is rejected before reaching the channel callbacks. A browser POST is
    // redirected to login; an Echo XHR subscription (JSON) gets a hard 401. Either way
    // the guest never receives a channel authorization signature.
    $this->post('/broadcasting/auth', [
        'channel_name' => 'private-availability.opportunity.1',
        'socket_id' => '123.456',
    ])->assertRedirect(route('login'));

    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-availability.opportunity.1',
        'socket_id' => '123.456',
    ])->assertUnauthorized();
});

it('authorizes an authenticated user for an opportunity availability channel', function () {
    $this->actingAs(User::factory()->owner()->create());

    $this->post('/broadcasting/auth', [
        'channel_name' => 'private-availability.opportunity.1',
        'socket_id' => '123.456',
    ])->assertOk();
});
