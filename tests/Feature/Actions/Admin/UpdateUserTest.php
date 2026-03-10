<?php

use App\Actions\Admin\UpdateUser;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('updates user name and email', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $updated = (new UpdateUser)($user, [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);

    expect($updated->name)->toBe('New Name');
    expect($updated->email)->toBe('new@example.com');
});

it('syncs roles on update', function () {
    $user = User::factory()->create();
    $user->assignRole('Viewer');

    $updated = (new UpdateUser)($user, [
        'roles' => ['Admin', 'Manager'],
    ]);

    expect($updated->hasRole('Admin'))->toBeTrue();
    expect($updated->hasRole('Manager'))->toBeTrue();
    expect($updated->hasRole('Viewer'))->toBeFalse();
});

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $target = User::factory()->create();

    (new UpdateUser)($target, [
        'name' => 'Hacked Name',
    ]);
})->throws(AuthorizationException::class);
