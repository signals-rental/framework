<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('renders guest auth pages with the split layout', function (string $uri) {
    $this->get($uri)
        ->assertOk()
        ->assertSee('s-auth-split');
})->with([
    'login' => '/login',
    'forgot password' => '/forgot-password',
    'reset password' => '/reset-password/dummy-token',
]);

it('renders the two-factor challenge with the split layout', function () {
    $user = User::factory()->create();

    $this->withSession(['two_factor_user_id' => $user->id])
        ->get(route('two-factor.challenge'))
        ->assertOk()
        ->assertSee('s-auth-split');
});

it('renders verify-email with the split layout', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('s-auth-split');
});

it('renders confirm-password with the split layout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertSee('s-auth-split');
});

it('renders accept-invitation with the split layout', function () {
    $user = User::factory()->invited()->create();

    $this->get(URL::signedRoute('invitation.accept', ['user' => $user->id]))
        ->assertOk()
        ->assertSee('s-auth-split');
});
