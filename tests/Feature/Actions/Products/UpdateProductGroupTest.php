<?php

use App\Actions\Products\UpdateProductGroup;
use App\Data\Products\UpdateProductGroupData;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\ProductGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;

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

it('sets the parent group when a parent id is provided', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $parent = ProductGroup::factory()->create();
    $group = ProductGroup::factory()->create();

    $dto = UpdateProductGroupData::from(['parent_id' => $parent->id]);
    (new UpdateProductGroup)($group, $dto);

    expect($group->fresh()->parent_id)->toBe($parent->id);
});

it('clears the parent group when parent id is explicitly null', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $parent = ProductGroup::factory()->create();
    $group = ProductGroup::factory()->create(['parent_id' => $parent->id]);

    $dto = UpdateProductGroupData::from(['parent_id' => null]);
    (new UpdateProductGroup)($group, $dto);

    expect($group->fresh()->parent_id)->toBeNull();
});

it('leaves the parent group untouched when parent id is omitted', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $parent = ProductGroup::factory()->create();
    $group = ProductGroup::factory()->create(['parent_id' => $parent->id]);

    $dto = UpdateProductGroupData::from(['name' => 'Renamed Only']);
    (new UpdateProductGroup)($group, $dto);

    $group->refresh();
    expect($group->parent_id)->toBe($parent->id)
        ->and($group->name)->toBe('Renamed Only');
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $group = ProductGroup::factory()->create();

    $dto = UpdateProductGroupData::from(['name' => 'Nope']);

    (new UpdateProductGroup)($group, $dto);
})->throws(AuthorizationException::class);

it('persists custom field values on update', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    CustomField::factory()->string()->create([
        'name' => 'colour_code',
        'module_type' => 'ProductGroup',
    ]);

    $group = ProductGroup::factory()->create(['name' => 'Lighting']);

    $result = (new UpdateProductGroup)($group, UpdateProductGroupData::from([
        'name' => 'Lighting',
        'custom_fields' => ['colour_code' => 'BLUE'],
    ]));

    $cfv = CustomFieldValue::query()
        ->where('entity_type', ProductGroup::class)
        ->where('entity_id', $group->id)
        ->first();

    expect($cfv)->not->toBeNull()
        ->and($cfv->value_string)->toBe('BLUE')
        ->and((array) $result->custom_fields)->toMatchArray(['colour_code' => 'BLUE']);
});

it('updates an existing custom field value on update', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    CustomField::factory()->string()->create([
        'name' => 'colour_code',
        'module_type' => 'ProductGroup',
    ]);

    $group = ProductGroup::factory()->create(['name' => 'Lighting']);
    $group->syncCustomFields(['colour_code' => 'BLUE']);

    (new UpdateProductGroup)($group, UpdateProductGroupData::from([
        'custom_fields' => ['colour_code' => 'GREEN'],
    ]));

    $values = CustomFieldValue::query()
        ->where('entity_type', ProductGroup::class)
        ->where('entity_id', $group->id)
        ->get();

    expect($values)->toHaveCount(1)
        ->and($values->first()->value_string)->toBe('GREEN');
});

it('leaves custom fields untouched when custom_fields key is omitted', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    CustomField::factory()->string()->create([
        'name' => 'colour_code',
        'module_type' => 'ProductGroup',
    ]);

    $group = ProductGroup::factory()->create(['name' => 'Lighting']);
    $group->syncCustomFields(['colour_code' => 'BLUE']);

    (new UpdateProductGroup)($group, UpdateProductGroupData::from(['name' => 'Renamed']));

    $cfv = CustomFieldValue::query()
        ->where('entity_type', ProductGroup::class)
        ->where('entity_id', $group->id)
        ->first();

    expect($cfv->value_string)->toBe('BLUE');
});
