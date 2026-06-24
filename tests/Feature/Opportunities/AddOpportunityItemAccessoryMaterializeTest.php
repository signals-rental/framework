<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityItemType;
use App\Models\Accessory;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
});

function materializeTestOpportunity(Store $store): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Accessory materialize',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('auto-materialises included catalogue accessories under a newly added product', function () {
    $opportunity = materializeTestOpportunity($this->store);
    $product = Product::factory()->create(['name' => 'Speaker']);
    $included = Product::factory()->create(['name' => 'Included Cable']);
    $suggested = Product::factory()->create(['name' => 'Suggested Stand']);

    Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $included->id,
        'quantity' => '2',
        'included' => true,
    ]);
    Accessory::factory()->optional()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $suggested->id,
        'quantity' => '1',
    ]);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
        'unit_price' => 1000,
    ]));

    $items = $opportunity->refresh()->items()->orderBy('path')->get();

    expect($items)->toHaveCount(2)
        ->and($items[0]->item_type)->toBe(OpportunityItemType::Product)
        ->and($items[0]->path)->toBe('0001')
        ->and($items[1]->item_type)->toBe(OpportunityItemType::Accessory)
        ->and($items[1]->path)->toBe('00010001')
        ->and($items[1]->itemable_id)->toBe($included->id)
        ->and((float) $items[1]->quantity)->toBe(6.0);
});

it('does not auto-materialise accessories when the flag is disabled', function () {
    $opportunity = materializeTestOpportunity($this->store);
    $product = Product::factory()->create();
    $included = Product::factory()->create();

    Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $included->id,
        'included' => true,
    ]);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 1000,
        'materialize_included_accessories' => false,
    ]));

    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(1);
});
