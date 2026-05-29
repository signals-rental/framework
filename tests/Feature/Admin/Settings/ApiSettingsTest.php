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

it('allows creating a token with phase-2, rate and currency abilities', function () {
    $abilities = ['products:read', 'products:write', 'stock:read', 'stock:write', 'activities:read', 'rates:read', 'rates:write', 'schema:read', 'currencies:read', 'exchange_rates:read', 'exchange_rates:write'];

    Volt::test('admin.settings.api')
        ->set('tokenName', 'Phase 2 Token')
        ->set('selectedAbilities', $abilities)
        ->call('createToken')
        ->assertHasNoErrors();

    expect($this->owner->tokens()->first()->abilities)->toBe($abilities);
});

it('pre-fills abilities when opening the edit modal', function () {
    $token = $this->owner->createToken('Editable', ['users:read', 'settings:read']);

    Volt::test('admin.settings.api')
        ->call('openEditModal', $token->accessToken->id)
        ->assertSet('showEditModal', true)
        ->assertSet('editingTokenId', $token->accessToken->id)
        ->assertSet('editAbilities', ['users:read', 'settings:read']);
});

it('can update an existing token\'s abilities', function () {
    $token = $this->owner->createToken('Editable', ['users:read']);

    Volt::test('admin.settings.api')
        ->call('openEditModal', $token->accessToken->id)
        ->set('editAbilities', ['users:read', 'products:read', 'rates:write'])
        ->call('updateToken')
        ->assertHasNoErrors()
        ->assertSet('showEditModal', false);

    expect($token->accessToken->fresh()->abilities)->toBe(['users:read', 'products:read', 'rates:write']);
});

it('requires at least one ability when editing', function () {
    $token = $this->owner->createToken('Editable', ['users:read']);

    Volt::test('admin.settings.api')
        ->call('openEditModal', $token->accessToken->id)
        ->set('editAbilities', [])
        ->call('updateToken')
        ->assertHasErrors(['editAbilities']);
});

it('validates ability values when editing', function () {
    $token = $this->owner->createToken('Editable', ['users:read']);

    Volt::test('admin.settings.api')
        ->call('openEditModal', $token->accessToken->id)
        ->set('editAbilities', ['invalid:ability'])
        ->call('updateToken')
        ->assertHasErrors(['editAbilities.0']);
});

it('dispatches event on token update', function () {
    $token = $this->owner->createToken('Editable', ['users:read']);

    Volt::test('admin.settings.api')
        ->call('openEditModal', $token->accessToken->id)
        ->set('editAbilities', ['users:read', 'products:read'])
        ->call('updateToken')
        ->assertDispatched('token-updated');
});

it('only edits the authenticated user\'s own tokens', function () {
    $otherUser = User::factory()->owner()->create();
    $otherToken = $otherUser->createToken('Foreign', ['users:read']);

    Volt::test('admin.settings.api')
        ->call('openEditModal', $otherToken->accessToken->id)
        ->assertSet('showEditModal', false)
        ->assertSet('editingTokenId', null);

    expect($otherToken->accessToken->fresh()->abilities)->toBe(['users:read']);
});

it('renders humanised group labels in the ability picker', function () {
    // Exercises abilityGroups() via with(): labels derived from the scope prefix.
    $this->get(route('admin.settings.api'))
        ->assertSee('Products')
        ->assertSee('Exchange Rates')
        ->assertSee('Action Log');
});

it('shows a collapsible scope summary in the token table', function () {
    // Exercises groupedAbilities() via the table loop, plus the scope-count summary.
    $this->owner->createToken('Triple', ['users:read', 'products:read', 'rates:read']);
    $this->owner->createToken('Single', ['users:read']);

    $this->get(route('admin.settings.api'))
        ->assertSee('3 scopes')
        ->assertSee('1 scope');
});
