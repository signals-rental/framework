<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\MergeOpportunityItems;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\MergeOpportunityItemsData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

/**
 * Build an Order/Quotation with two lines of the same product (qty 2 + 3).
 *
 * @return array{0: Opportunity, 1: OpportunityItem, 2: OpportunityItem}
 */
function duplicateLineFixture(Store $store, Product $product): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Merge fixture',
        'store_id' => $store->id,
        'starts_at' => '2026-12-01T09:00:00Z',
        'ends_at' => '2026-12-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    foreach (['2', '3'] as $qty) {
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'name' => $product->name,
            'quantity' => $qty,
            'unit_price' => 1000,
        ]));
    }

    $items = $opportunity->allItems()->orderBy('id')->get();

    return [$opportunity->refresh(), $items[0], $items[1]];
}

it('merges duplicate lines into the survivor with the summed quantity', function () {
    $product = Product::factory()->create();
    [$opportunity, $survivor, $duplicate] = duplicateLineFixture($this->store, $product);

    (new MergeOpportunityItems)($survivor, MergeOpportunityItemsData::from([
        'duplicate_item_ids' => [$duplicate->id],
    ]));

    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(1)
        ->and((float) $survivor->refresh()->quantity)->toBe(5.0)
        ->and((int) $opportunity->refresh()->charge_total)->toBe(20000);
});

it('keeps the pricing of the most expensive duplicate when merging (#377)', function () {
    $product = Product::factory()->create();
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Merge richest pricing',
        'store_id' => $this->store->id,
        'starts_at' => '2026-12-01T09:00:00Z',
        'ends_at' => '2026-12-04T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'name' => $product->name,
        'quantity' => '2',
        'unit_price' => 1000,
    ]));
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'name' => $product->name,
        'quantity' => '3',
        'unit_price' => 5000,
    ]));

    $items = $opportunity->allItems()->orderBy('id')->get();
    $survivor = $items->first();
    $duplicate = $items->last();

    (new MergeOpportunityItems)($survivor, MergeOpportunityItemsData::from([
        'duplicate_item_ids' => [$duplicate->id],
    ]));

    $merged = $survivor->refresh();

    expect((float) $merged->quantity)->toBe(5.0)
        ->and((int) $merged->unit_price)->toBe(5000);
});

it('rejects merging lines that are not the same charge', function () {
    [$opportunity, $survivor] = duplicateLineFixture($this->store, Product::factory()->create());

    // A different product is not a duplicate.
    $other = Product::factory()->create();
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'itemable_id' => $other->id,
        'itemable_type' => Product::class,
        'name' => $other->name,
        'quantity' => '1',
        'unit_price' => 500,
    ]));

    $mismatched = $opportunity->allItems()->where('itemable_id', $other->id)->firstOrFail();

    expect(fn () => (new MergeOpportunityItems)($survivor->refresh(), MergeOpportunityItemsData::from([
        'duplicate_item_ids' => [$mismatched->id],
    ])))->toThrow(ValidationException::class, 'Only identical lines');
});

it('rejects a merge with no matching duplicates supplied', function () {
    [, $survivor] = duplicateLineFixture($this->store, Product::factory()->create());

    expect(fn () => (new MergeOpportunityItems)($survivor->refresh(), MergeOpportunityItemsData::from([
        'duplicate_item_ids' => [999999],
    ])))->toThrow(ValidationException::class, 'No matching duplicate');
});

it('rejects merging lines in different tree groups even when the product matches', function () {
    $product = Product::factory()->create();
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Merge tree groups',
        'store_id' => $this->store->id,
        'starts_at' => '2026-12-01T09:00:00Z',
        'ends_at' => '2026-12-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'name' => $product->name,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'name' => $product->name,
        'quantity' => '1',
        'unit_price' => 1000,
        'parent_path' => '0001',
    ]));

    $items = $opportunity->refresh()->allItems()->orderBy('path')->get();

    expect(fn () => (new MergeOpportunityItems)($items[0], MergeOpportunityItemsData::from([
        'duplicate_item_ids' => [$items[1]->id],
    ])))->toThrow(ValidationException::class, 'tree group');
});
