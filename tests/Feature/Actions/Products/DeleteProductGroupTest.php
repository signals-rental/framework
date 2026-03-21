<?php

use App\Actions\Products\DeleteProductGroup;
use App\Models\ProductGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('deletes a product group', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $group = ProductGroup::factory()->create();

    (new DeleteProductGroup)($group);

    expect(ProductGroup::find($group->id))->toBeNull();
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $group = ProductGroup::factory()->create();

    (new DeleteProductGroup)($group);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);
