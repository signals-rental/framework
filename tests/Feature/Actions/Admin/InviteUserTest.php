<?php

use App\Actions\Admin\InviteUser;
use App\Data\Admin\InviteUserData;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

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
        'roles' => ['Viewer'],
    ]);

    $user = (new InviteUser)($data);

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->password)->toBeNull();
    expect($user->invited_at)->not->toBeNull();
    expect($user->hasRole('Viewer'))->toBeTrue();

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
