<?php

use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('renders the users page', function () {
    $this->get(route('admin.settings.users'))
        ->assertOk()
        ->assertSee('Users');
});

it('lists all users', function () {
    $other = User::factory()->create(['name' => 'Jane Doe']);

    Volt::test('admin.settings.users')
        ->assertSee($this->owner->name)
        ->assertSee('Jane Doe');
});

it('shows user status badges', function () {
    User::factory()->deactivated()->create(['name' => 'Deactivated User']);
    User::factory()->invited()->create(['name' => 'Invited User']);

    $this->get(route('admin.settings.users'))
        ->assertSee('Deactivated')
        ->assertSee('Invited')
        ->assertSee('Active');
});

it('invites a new user', function () {
    Notification::fake();

    Volt::test('admin.settings.users')
        ->call('openInviteModal')
        ->set('inviteName', 'New User')
        ->set('inviteEmail', 'new@example.com')
        ->set('inviteRoles', ['Read Only'])
        ->call('invite')
        ->assertHasNoErrors()
        ->assertDispatched('user-invited');

    $user = User::where('email', 'new@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('New User');
    expect($user->invited_at)->not->toBeNull();
    expect($user->password)->toBeNull();
    expect($user->hasRole('Read Only'))->toBeTrue();

    Notification::assertSentTo($user, UserInvitedNotification::class);
});

it('validates invite fields', function () {
    Volt::test('admin.settings.users')
        ->call('openInviteModal')
        ->set('inviteName', '')
        ->set('inviteEmail', 'not-email')
        ->call('invite')
        ->assertHasErrors(['inviteName', 'inviteEmail']);
});

it('requires at least one role when inviting', function () {
    Volt::test('admin.settings.users')
        ->call('openInviteModal')
        ->set('inviteName', 'No Role User')
        ->set('inviteEmail', 'norole@example.com')
        ->set('inviteRoles', [])
        ->call('invite')
        ->assertHasErrors(['inviteRoles']);
});

it('prevents inviting duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    Volt::test('admin.settings.users')
        ->call('openInviteModal')
        ->set('inviteName', 'Duplicate')
        ->set('inviteEmail', 'existing@example.com')
        ->call('invite')
        ->assertHasErrors(['inviteEmail']);
});

it('deactivates a user', function () {
    $user = User::factory()->create();

    Volt::test('admin.settings.users')
        ->call('deactivate', $user->id)
        ->assertDispatched('user-deactivated');

    expect($user->fresh()->isActive())->toBeFalse();
    expect($user->fresh()->deactivated_at)->not->toBeNull();
});

it('reactivates a user', function () {
    $user = User::factory()->deactivated()->create();

    Volt::test('admin.settings.users')
        ->call('reactivate', $user->id)
        ->assertDispatched('user-reactivated');

    expect($user->fresh()->isActive())->toBeTrue();
    expect($user->fresh()->deactivated_at)->toBeNull();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.users'))
        ->assertForbidden();
});
