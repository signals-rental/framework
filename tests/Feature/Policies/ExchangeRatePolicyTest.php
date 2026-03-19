<?php

use App\Models\User;
use App\Policies\ExchangeRatePolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->policy = new ExchangeRatePolicy;
});

it('allows user with settings.manage permission', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    expect($this->policy->viewAny($user))->toBeTrue();
    expect($this->policy->view($user))->toBeTrue();
    expect($this->policy->create($user))->toBeTrue();
    expect($this->policy->update($user))->toBeTrue();
    expect($this->policy->delete($user))->toBeTrue();
});

it('denies user without settings.manage permission', function () {
    $user = User::factory()->create();
    $user->assignRole('Read Only');

    expect($this->policy->viewAny($user))->toBeFalse();
    expect($this->policy->view($user))->toBeFalse();
    expect($this->policy->create($user))->toBeFalse();
    expect($this->policy->update($user))->toBeFalse();
    expect($this->policy->delete($user))->toBeFalse();
});
