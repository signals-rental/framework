<?php

use App\Actions\Opportunities\AddOpportunityAccessory;
use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityAccessoryData;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityItemType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
});

/**
 * A real event-sourced opportunity with concrete dates so product-backed lines
 * price deterministically.
 */
function makeGroupAccessoryOpportunity(Store $store): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Groups & Accessories',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('adds a top-level group with no demand and a zero total', function () {
    $opportunity = makeGroupAccessoryOpportunity($this->store);

    app(AddOpportunityGroup::class)($opportunity, AddOpportunityGroupData::from([
        'name' => 'Audio Package',
    ]));

    $group = $opportunity->items()->where('item_type', 'group')->firstOrFail();

    expect($group->item_type)->toBe(OpportunityItemType::Group)
        ->and($group->path)->toBe('0001')
        ->and((int) $group->total)->toBe(0)
        ->and($group->itemable_id)->toBeNull();

    $demands = Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $group->id)
        ->get();

    expect($demands)->toBeEmpty();
});

it('nests a group under an existing group path', function () {
    $opportunity = makeGroupAccessoryOpportunity($this->store);

    app(AddOpportunityGroup::class)($opportunity, AddOpportunityGroupData::from([
        'name' => 'Audio Package',
    ]));

    app(AddOpportunityGroup::class)($opportunity, AddOpportunityGroupData::from([
        'name' => 'Microphones',
        'parent_path' => '0001',
    ]));

    $child = $opportunity->items()->where('path', '00010001')->firstOrFail();

    expect($child->item_type)->toBe(OpportunityItemType::Group)
        ->and($child->path)->toBe('00010001')
        ->and((int) $child->total)->toBe(0);
});

it('adds an accessory under a product, nesting beneath it and syncing demand', function () {
    $opportunity = makeGroupAccessoryOpportunity($this->store);
    $product = Product::factory()->rental()->bulk()->create();
    $accessoryProduct = Product::factory()->rental()->bulk()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => 'product',
        'quantity' => '1',
        'unit_price' => 5000,
    ]));

    $principal = $opportunity->items()->where('item_type', 'product')->firstOrFail();

    app(AddOpportunityAccessory::class)($opportunity, AddOpportunityAccessoryData::from([
        'name' => $accessoryProduct->name,
        'principal_item_id' => $principal->id,
        'itemable_id' => $accessoryProduct->id,
        'itemable_type' => Product::class,
        'quantity' => '2',
    ]));

    $accessory = $opportunity->items()->where('item_type', 'accessory')->firstOrFail();

    expect($accessory->item_type)->toBe(OpportunityItemType::Accessory)
        ->and($accessory->path)->toBe($principal->path.'0001')
        ->and($accessory->itemable_id)->toBe($accessoryProduct->id);

    $demands = Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $accessory->id)
        ->get();

    expect($demands)->toHaveCount(1)
        ->and((int) $demands->first()->quantity)->toBe(2);
});

it('rejects an accessory under a non-product principal', function () {
    $opportunity = makeGroupAccessoryOpportunity($this->store);

    app(AddOpportunityGroup::class)($opportunity, AddOpportunityGroupData::from([
        'name' => 'Audio Package',
    ]));

    $group = $opportunity->items()->where('item_type', 'group')->firstOrFail();

    expect(fn () => app(AddOpportunityAccessory::class)($opportunity, AddOpportunityAccessoryData::from([
        'name' => 'Stray Accessory',
        'principal_item_id' => $group->id,
    ])))->toThrow(ValidationException::class);

    expect($opportunity->items()->where('item_type', 'accessory')->count())->toBe(0);
});
