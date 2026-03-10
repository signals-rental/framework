<?php

use App\Actions\Admin\ReactivateUser;
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

it('reactivates a deactivated user', function () {
    $user = User::factory()->deactivated()->create();

    $result = (new ReactivateUser)($user);

    expect($result->isActive())->toBeTrue();
    expect($result->deactivated_at)->toBeNull();
});

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $user = User::factory()->deactivated()->create();

    (new ReactivateUser)($user);
})->throws(AuthorizationException::class);
