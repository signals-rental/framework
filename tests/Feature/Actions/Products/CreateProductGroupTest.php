<?php

use App\Actions\Products\CreateProductGroup;
use App\Data\Products\CreateProductGroupData;
use App\Models\ProductGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('creates a product group with valid data', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $dto = CreateProductGroupData::from([
        'name' => 'Lighting Equipment',
    ]);

    $result = (new CreateProductGroup)($dto);

    expect($result->name)->toBe('Lighting Equipment');
    expect(ProductGroup::count())->toBe(1);
});

it('creates a product group with description', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $dto = CreateProductGroupData::from([
        'name' => 'Sound',
        'description' => 'Audio equipment',
    ]);

    $result = (new CreateProductGroup)($dto);

    expect($result->name)->toBe('Sound');
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $dto = CreateProductGroupData::from(['name' => 'Test']);

    (new CreateProductGroup)($dto);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);
