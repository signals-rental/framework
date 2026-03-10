<?php

use App\Actions\Admin\DeactivateUser;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('deactivates a user', function () {
    $user = User::factory()->create();

    $result = (new DeactivateUser)($user);

    expect($result->isActive())->toBeFalse();
    expect($result->deactivated_at)->not->toBeNull();
});

it('prevents deactivating the owner', function () {
    $owner = User::factory()->owner()->create();

    (new DeactivateUser)($owner);
})->throws(ValidationException::class);

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $user = User::factory()->create();

    (new DeactivateUser)($user);
})->throws(AuthorizationException::class);
