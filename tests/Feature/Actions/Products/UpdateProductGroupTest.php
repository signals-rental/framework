<?php

use App\Actions\Products\UpdateProductGroup;
use App\Data\Products\UpdateProductGroupData;
use App\Models\ProductGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('updates a product group name', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $group = ProductGroup::factory()->create(['name' => 'Old Name']);

    $dto = UpdateProductGroupData::from(['name' => 'New Name']);
    $result = (new UpdateProductGroup)($group, $dto);

    expect($result->name)->toBe('New Name');
});

it('updates a product group description', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $group = ProductGroup::factory()->create();

    $dto = UpdateProductGroupData::from(['description' => 'Updated description']);
    $result = (new UpdateProductGroup)($group, $dto);

    $group->refresh();
    expect($group->description)->toBe('Updated description');
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $group = ProductGroup::factory()->create();

    $dto = UpdateProductGroupData::from(['name' => 'Nope']);

    (new UpdateProductGroup)($group, $dto);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);
