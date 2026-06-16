<?php

use App\Models\User;
use Livewire\Volt\Volt as LivewireVolt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    Role::findOrCreate('Sales', 'web');
});

it('blocks password login for a user whose role enforces sso', function () {
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    $user = User::factory()->create();
    $user->assignRole('Sales');

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('directs the user to an SSO provider button when enforcement is on and a provider is enabled', function () {
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');
    settings()->set('sso.google_enabled', true, 'boolean');
    settings()->set('sso.google_client_id', 'google-id', 'encrypted');
    settings()->set('sso.google_client_secret', 'google-secret', 'encrypted');

    $user = User::factory()->create();
    $user->assignRole('Sales');

    $component = LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    expect($component->errors()->first('email'))
        ->toContain('Google or Microsoft buttons above');

    expect(auth()->check())->toBeFalse();
});

it('directs the user to their administrator when enforcement is on but no SSO provider is available', function () {
    // #227: with no provider enabled, the SSO buttons never render, so the
    // "use the Google or Microsoft buttons" guidance would point at nothing.
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    $user = User::factory()->create();
    $user->assignRole('Sales');

    $component = LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    expect($component->errors()->first('email'))
        ->toContain('contact your administrator');
    expect($component->errors()->first('email'))
        ->not->toContain('buttons above');

    expect(auth()->check())->toBeFalse();
});

it('allows password login for a user without an enforced role', function () {
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    $user = User::factory()->create();

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

it('never blocks the owner from password login even with an enforced role', function () {
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    $owner = User::factory()->owner()->create();
    $owner->assignRole('Sales');

    LivewireVolt::test('auth.login')
        ->set('email', $owner->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

it('does not block password login when no roles are enforced', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
