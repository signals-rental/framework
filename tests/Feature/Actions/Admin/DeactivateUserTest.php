<?php

use App\Actions\Admin\DeactivateUser;
use App\Actions\Admin\ReactivateUser;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
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

it('reactivates a deactivated user', function () {
    $user = User::factory()->deactivated()->create();

    $result = (new ReactivateUser)($user);

    expect($result->isActive())->toBeTrue();
    expect($result->deactivated_at)->toBeNull();
});
