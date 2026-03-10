<?php

use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('renders the api settings page', function () {
    $this->get(route('admin.settings.api'))
        ->assertOk()
        ->assertSee('API Tokens');
});

it('shows empty state when no tokens exist', function () {
    $this->get(route('admin.settings.api'))
        ->assertSee('No API tokens');
});

it('can create a new api token', function () {
    Volt::test('admin.settings.api')
        ->set('tokenName', 'My Token')
        ->set('selectedAbilities', ['users:read', 'settings:read'])
        ->call('createToken')
        ->assertHasNoErrors();

    expect($this->owner->tokens()->count())->toBe(1);

    $token = $this->owner->tokens()->first();
    expect($token->name)->toBe('My Token');
    expect($token->abilities)->toBe(['users:read', 'settings:read']);
});

it('shows the token value after creation', function () {
    $component = Volt::test('admin.settings.api')
        ->set('tokenName', 'My Token')
        ->set('selectedAbilities', ['users:read'])
        ->call('createToken');

    $component->assertSet('showTokenValue', true);
    $component->assertNotSet('plainTextToken', '');
});

it('requires token name', function () {
    Volt::test('admin.settings.api')
        ->set('tokenName', '')
        ->set('selectedAbilities', ['users:read'])
        ->call('createToken')
        ->assertHasErrors(['tokenName']);
});

it('requires at least one ability', function () {
    Volt::test('admin.settings.api')
        ->set('tokenName', 'My Token')
        ->set('selectedAbilities', [])
        ->call('createToken')
        ->assertHasErrors(['selectedAbilities']);
});

it('validates ability values', function () {
    Volt::test('admin.settings.api')
        ->set('tokenName', 'My Token')
        ->set('selectedAbilities', ['invalid:ability'])
        ->call('createToken')
        ->assertHasErrors(['selectedAbilities.0']);
});

it('can revoke a token', function () {
    $token = $this->owner->createToken('Test', ['users:read']);

    Volt::test('admin.settings.api')
        ->call('revokeToken', $token->accessToken->id)
        ->assertHasNoErrors();

    expect($this->owner->tokens()->count())->toBe(0);
});

it('lists existing tokens', function () {
    $this->owner->createToken('First Token', ['users:read']);
    $this->owner->createToken('Second Token', ['settings:read']);

    $this->get(route('admin.settings.api'))
        ->assertSee('First Token')
        ->assertSee('Second Token');
});

it('dispatches event on token creation', function () {
    Volt::test('admin.settings.api')
        ->set('tokenName', 'My Token')
        ->set('selectedAbilities', ['users:read'])
        ->call('createToken')
        ->assertDispatched('token-created');
});

it('dispatches event on token revocation', function () {
    $token = $this->owner->createToken('Test', ['users:read']);

    Volt::test('admin.settings.api')
        ->call('revokeToken', $token->accessToken->id)
        ->assertDispatched('token-revoked');
});

it('clears token display on dismiss', function () {
    $component = Volt::test('admin.settings.api')
        ->set('tokenName', 'My Token')
        ->set('selectedAbilities', ['users:read'])
        ->call('createToken');

    $component->assertSet('showTokenValue', true);

    $component->call('closeTokenDisplay')
        ->assertSet('showTokenValue', false)
        ->assertSet('plainTextToken', '');
});

it('requires admin access', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.settings.api'))
        ->assertForbidden();
});
