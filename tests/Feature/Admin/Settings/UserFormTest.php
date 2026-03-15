<?php

use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('renders the edit user page', function () {
    $user = User::factory()->create();

    $this->get(route('admin.settings.users.edit', $user))
        ->assertOk()
        ->assertSee('Edit User');
});

it('populates form with user data', function () {
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $user->assignRole('Read Only');

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->assertSet('userName', 'Jane Doe')
        ->assertSet('userEmail', 'jane@example.com')
        ->assertSet('selectedRoles', ['Read Only']);
});

it('updates user name and email', function () {
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->set('userName', 'New Name')
        ->set('userEmail', 'new@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.settings.users'));

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
});

it('assigns roles to a user', function () {
    $user = User::factory()->create();

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->set('selectedRoles', ['Sales', 'Read Only'])
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->hasRole('Sales'))->toBeTrue();
    expect($user->hasRole('Read Only'))->toBeTrue();
});

it('removes roles from a user', function () {
    $user = User::factory()->create();
    $user->assignRole(['Sales', 'Read Only']);

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->set('selectedRoles', ['Read Only'])
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->hasRole('Sales'))->toBeFalse();
    expect($user->hasRole('Read Only'))->toBeTrue();
});

it('validates required fields', function () {
    $user = User::factory()->create();

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->set('userName', '')
        ->set('userEmail', 'not-email')
        ->call('save')
        ->assertHasErrors(['userName', 'userEmail']);
});

it('validates email uniqueness', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->set('userEmail', 'taken@example.com')
        ->call('save')
        ->assertHasErrors(['userEmail']);
});

it('allows saving with the same email', function () {
    $user = User::factory()->create(['email' => 'same@example.com']);

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->set('userEmail', 'same@example.com')
        ->call('save')
        ->assertHasNoErrors();
});

it('deactivates a user from the edit page', function () {
    $user = User::factory()->create();

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->call('deactivate')
        ->assertDispatched('user-deactivated')
        ->assertSet('isDeactivated', true);

    expect($user->fresh()->isActive())->toBeFalse();
});

it('reactivates a user from the edit page', function () {
    $user = User::factory()->deactivated()->create();

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->call('reactivate')
        ->assertDispatched('user-reactivated')
        ->assertSet('isDeactivated', false);

    expect($user->fresh()->isActive())->toBeTrue();
});

it('sends a password reset', function () {
    Password::shouldReceive('sendResetLink')
        ->once()
        ->andReturn(Password::RESET_LINK_SENT);

    $user = User::factory()->create();

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->call('sendPasswordReset')
        ->assertDispatched('password-reset-sent');
});

it('resends an invitation', function () {
    Notification::fake();

    $user = User::factory()->invited()->create();

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->call('resendInvitation')
        ->assertDispatched('invitation-resent');

    Notification::assertSentTo($user, UserInvitedNotification::class);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.users.edit', $target))
        ->assertForbidden();
});

it('shows roles section for owner with explanation', function () {
    Volt::test('admin.settings.user-form', ['user' => $this->owner])
        ->assertSet('isOwner', true)
        ->assertSee('Owners have implicit access to everything');
});

it('shows roles section for non-owner', function () {
    $user = User::factory()->create();

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->assertSet('isOwner', false)
        ->assertSee('Roles');
});

it('prevents deactivating the owner', function () {
    Volt::test('admin.settings.user-form', ['user' => $this->owner])
        ->call('deactivate')
        ->assertHasErrors();

    expect($this->owner->fresh()->isActive())->toBeTrue();
});

it('rejects non-existent role names', function () {
    $user = User::factory()->create();

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->set('selectedRoles', ['NonExistentRole'])
        ->call('save')
        ->assertHasErrors(['selectedRoles.0']);
});

it('rejects resend invitation for non-invited user', function () {
    $user = User::factory()->create();

    Volt::test('admin.settings.user-form', ['user' => $user])
        ->call('resendInvitation')
        ->assertHasErrors(['user']);
});
