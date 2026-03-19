<?php

use App\Actions\Admin\InviteUser;
use App\Data\Admin\InviteUserData;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a user with invitation data', function () {
    Notification::fake();

    $data = InviteUserData::from([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'roles' => ['Read Only'],
    ]);

    $user = (new InviteUser)($data);

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->password)->toBeNull();
    expect($user->invited_at)->not->toBeNull();
    expect($user->hasRole('Read Only'))->toBeTrue();

    Notification::assertSentTo($user, UserInvitedNotification::class);
});

it('creates a user without roles', function () {
    Notification::fake();

    $data = InviteUserData::from([
        'name' => 'No Role User',
        'email' => 'norole@example.com',
        'roles' => [],
    ]);

    $user = (new InviteUser)($data);

    expect($user->roles)->toHaveCount(0);
});

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $data = InviteUserData::from([
        'name' => 'Unauthorized Invite',
        'email' => 'unauth@example.com',
        'roles' => [],
    ]);

    (new InviteUser)($data);
})->throws(AuthorizationException::class);

it('allows owner to assign any role', function () {
    Notification::fake();

    $data = InviteUserData::from([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'roles' => ['Admin'],
    ]);

    $user = (new InviteUser)($data);

    expect($user->hasRole('Admin'))->toBeTrue();
});

it('prevents non-owner admin from assigning Admin role', function () {
    Notification::fake();

    $adminUser = User::factory()->create();
    $adminUser->assignRole('Admin');
    $this->actingAs($adminUser);

    $data = InviteUserData::from([
        'name' => 'Another Admin',
        'email' => 'another-admin@example.com',
        'roles' => ['Admin'],
    ]);

    (new InviteUser)($data);
})->throws(ValidationException::class);

it('allows admin to assign lower roles', function () {
    Notification::fake();

    $adminUser = User::factory()->create();
    $adminUser->assignRole('Admin');
    $this->actingAs($adminUser);

    $data = InviteUserData::from([
        'name' => 'Sales User',
        'email' => 'sales@example.com',
        'roles' => ['Sales'],
    ]);

    $user = (new InviteUser)($data);

    expect($user->hasRole('Sales'))->toBeTrue();
});

it('prevents operations manager from assigning admin role', function () {
    Notification::fake();

    $opsManager = User::factory()->create();
    $opsManager->assignRole('Operations Manager');
    $opsManager->givePermissionTo('users.invite');
    $this->actingAs($opsManager);

    $data = InviteUserData::from([
        'name' => 'Admin Attempt',
        'email' => 'admin-attempt@example.com',
        'roles' => ['Admin'],
    ]);

    (new InviteUser)($data);
})->throws(ValidationException::class);

it('prevents assigning a role at the same level as the acting user', function () {
    Notification::fake();

    $salesUser = User::factory()->create();
    $salesUser->assignRole('Sales');
    $salesUser->givePermissionTo('users.invite');
    $this->actingAs($salesUser);

    $data = InviteUserData::from([
        'name' => 'Another Sales',
        'email' => 'another-sales@example.com',
        'roles' => ['Sales'],
    ]);

    (new InviteUser)($data);
})->throws(ValidationException::class);

it('creates a linked User-type member for the invited user', function () {
    Notification::fake();

    $data = InviteUserData::from([
        'name' => 'Member Link Test',
        'email' => 'memberlink@example.com',
        'roles' => [],
    ]);

    $user = (new InviteUser)($data);

    expect($user->member_id)->not->toBeNull();

    $member = Member::find($user->member_id);
    expect($member)->not->toBeNull()
        ->and($member->name)->toBe('Member Link Test')
        ->and($member->membership_type)->toBe(MembershipType::User)
        ->and($member->is_active)->toBeTrue();
});
