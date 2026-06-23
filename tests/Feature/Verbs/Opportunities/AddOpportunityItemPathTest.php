<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * A real event-sourced opportunity so the ItemAdded events have a usable state.
 */
function makePathOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Paths']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function addItem(Opportunity $opportunity, array $overrides = []): void
{
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from(array_merge([
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 1000,
    ], $overrides)));
}

it('allocates sequential top-level paths to appended product lines', function () {
    $opportunity = makePathOpportunity();

    addItem($opportunity, ['name' => 'First', 'item_type' => 'product']);
    addItem($opportunity, ['name' => 'Second', 'item_type' => 'product']);

    $items = $opportunity->items()->orderBy('id')->get();

    expect($items)->toHaveCount(2)
        ->and($items[0]->path)->toBe('0001')
        ->and($items[0]->item_type->value)->toBe('product')
        ->and($items[1]->path)->toBe('0002');
});

it('nests a child line under the given parent_path', function () {
    $opportunity = makePathOpportunity();

    // A top-level group at 0001 …
    addItem($opportunity, ['name' => 'Group', 'item_type' => 'group']);
    // … then a product nested beneath it.
    addItem($opportunity, ['name' => 'Child', 'item_type' => 'product', 'parent_path' => '0001']);

    $group = $opportunity->items()->where('path', '0001')->firstOrFail();
    $child = $opportunity->items()->where('path', '00010001')->firstOrFail();

    expect($group->item_type->value)->toBe('group')
        ->and($child->item_type->value)->toBe('product')
        ->and($child->path)->toBe('00010001');
});

it('persists the polymorphic itemable reference and revenue group from the data', function () {
    $opportunity = makePathOpportunity();

    addItem($opportunity, [
        'name' => 'Referenced',
        'itemable_id' => 42,
        'itemable_type' => 'App\\Models\\Product',
        'revenue_group_id' => 7,
    ]);

    $item = $opportunity->items()->latest('id')->firstOrFail();

    expect($item->itemable_id)->toBe(42)
        ->and($item->itemable_type)->toBe('App\\Models\\Product')
        ->and($item->revenue_group_id)->toBe(7)
        ->and($item->path)->toBe('0001');
});
