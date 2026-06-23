<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ClearAssetContainer;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DeallocateAsset;
use App\Actions\Opportunities\PrepareAsset;
use App\Actions\Opportunities\QuickAllocateAssets;
use App\Actions\Opportunities\QuickPrepareAssets;
use App\Actions\Opportunities\RevertAssetPreparation;
use App\Actions\Opportunities\SetAssetContainer;
use App\Actions\Opportunities\SubstituteAsset;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\QuickAllocateAssetsData;
use App\Data\Opportunities\QuickPrepareAssetsData;
use App\Data\Opportunities\SetAssetContainerData;
use App\Data\Opportunities\SubstituteAssetData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityStatus;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->rental()->serialised()->create();
});

/**
 * Build an event-sourced Order with a single serialised line for `$quantity`
 * units, returning the line item ready for allocation.
 */
function makeOrderLine(Store $store, Product $product, string $quantity = '4'): OpportunityItem
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Allocation',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    // Items are added while still a quotation — an order must have at least one
    // line item to be confirmed (opportunity-lifecycle.md §12.1 convert guard).
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => $quantity,
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    (new ConvertToOrder)($opportunity->refresh());

    return $opportunity->items()->firstOrFail();
}

function makeSerialisedAsset(Store $store, Product $product): StockLevel
{
    return StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
}

it('allocates an asset: creates the row, increments quantity_allocated, transitions demand', function () {
    $item = makeOrderLine($this->store, $this->product, '4');
    $asset = makeSerialisedAsset($this->store, $this->product);

    // Before allocation: one quantity-based demand of 4.
    $before = Demand::query()->where('source_id', $item->id)->get();
    expect($before)->toHaveCount(1)
        ->and($before->first()->asset_id)->toBeNull()
        ->and((int) $before->first()->quantity)->toBe(4);

    $result = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    expect($result->stock_level_id)->toBe($asset->id)
        ->and($result->status)->toBe(AssetAssignmentStatus::Allocated->value);

    // Projection row exists.
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
    expect($row->stock_level_id)->toBe($asset->id)
        ->and($row->status)->toBe(AssetAssignmentStatus::Allocated);

    // quantity_allocated incremented.
    expect((float) $asset->refresh()->quantity_allocated)->toBe(1.0);

    // §9.3 demand transition: one asset-specific demand + residual qty-3 demand.
    $after = Demand::query()->where('source_id', $item->id)->get();
    expect($after->whereNotNull('asset_id')->count())->toBe(1)
        ->and($after->firstWhere('asset_id', $asset->id)->quantity)->toBe(1)
        ->and((int) $after->whereNull('asset_id')->first()->quantity)->toBe(3);
});

it('deallocates an asset: removes the row, decrements quantity_allocated, restores demand', function () {
    $item = makeOrderLine($this->store, $this->product, '4');
    $asset = makeSerialisedAsset($this->store, $this->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
    expect((float) $asset->refresh()->quantity_allocated)->toBe(1.0);

    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();

    (new DeallocateAsset)($row);

    expect(OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->count())->toBe(0)
        ->and((float) $asset->refresh()->quantity_allocated)->toBe(0.0);

    // Demand reverts to a single quantity-based demand of 4.
    $demands = Demand::query()->where('source_id', $item->id)->get();
    expect($demands)->toHaveCount(1)
        ->and($demands->first()->asset_id)->toBeNull()
        ->and((int) $demands->first()->quantity)->toBe(4);
});

it('rejects reducing a line quantity below its already-allocated asset count', function () {
    $item = makeOrderLine($this->store, $this->product, '3');
    $assetA = makeSerialisedAsset($this->store, $this->product);
    $assetB = makeSerialisedAsset($this->store, $this->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $assetA->id]));
    (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $assetB->id]));

    // Two assets allocated — reducing to 1 strands one of them.
    expect(fn () => (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '1'])))
        ->toThrow(EventNotValid::class);

    expect((string) $item->refresh()->quantity)->toBe('3.00');
});

it('allows reducing a line quantity down to (not below) its allocated asset count', function () {
    $item = makeOrderLine($this->store, $this->product, '3');
    $assetA = makeSerialisedAsset($this->store, $this->product);
    $assetB = makeSerialisedAsset($this->store, $this->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $assetA->id]));
    (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $assetB->id]));

    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '2']));

    expect((string) $item->refresh()->quantity)->toBe('2.00');
});

it('prepares and reverts an asset through the Allocated→Prepared→Allocated cycle', function () {
    $item = makeOrderLine($this->store, $this->product, '2');
    $asset = makeSerialisedAsset($this->store, $this->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();

    (new PrepareAsset)($row);
    $row->refresh();
    expect($row->status)->toBe(AssetAssignmentStatus::Prepared)
        ->and($row->prepared_at)->not->toBeNull();

    (new RevertAssetPreparation)($row);
    $row->refresh();
    expect($row->status)->toBe(AssetAssignmentStatus::Allocated)
        ->and($row->prepared_at)->toBeNull();
});

it('rejects preparing an already-prepared asset', function () {
    $item = makeOrderLine($this->store, $this->product, '2');
    $asset = makeSerialisedAsset($this->store, $this->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
    (new PrepareAsset)($row);

    (new PrepareAsset)($row->refresh());
})->throws(EventNotValid::class);

it('rejects reverting preparation on an allocated (non-prepared) asset', function () {
    $item = makeOrderLine($this->store, $this->product, '2');
    $asset = makeSerialisedAsset($this->store, $this->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();

    (new RevertAssetPreparation)($row);
})->throws(EventNotValid::class);

it('sets and clears an asset container', function () {
    $item = makeOrderLine($this->store, $this->product, '2');
    $asset = makeSerialisedAsset($this->store, $this->product);
    $container = StockLevel::factory()->create(['store_id' => $this->store->id]);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();

    (new SetAssetContainer)($row, SetAssetContainerData::from(['container_stock_level_id' => $container->id]));
    expect($row->refresh()->container_stock_level_id)->toBe($container->id);

    (new ClearAssetContainer)($row);
    expect($row->refresh()->container_stock_level_id)->toBeNull();
});

it('rejects clearing a container when none is set', function () {
    $item = makeOrderLine($this->store, $this->product, '2');
    $asset = makeSerialisedAsset($this->store, $this->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();

    (new ClearAssetContainer)($row);
})->throws(EventNotValid::class);

it('substitutes an asset: swaps the stock level, preserves status, moves demand and allocation', function () {
    $item = makeOrderLine($this->store, $this->product, '2');
    $assetA = makeSerialisedAsset($this->store, $this->product);
    $assetB = makeSerialisedAsset($this->store, $this->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $assetA->id]));
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
    (new PrepareAsset)($row);

    (new SubstituteAsset)($row->refresh(), SubstituteAssetData::from(['new_stock_level_id' => $assetB->id, 'reason' => 'damaged']));
    $row->refresh();

    // Status preserved (still Prepared), asset swapped.
    expect($row->stock_level_id)->toBe($assetB->id)
        ->and($row->status)->toBe(AssetAssignmentStatus::Prepared);

    // Allocation moved off A onto B.
    expect((float) $assetA->refresh()->quantity_allocated)->toBe(0.0)
        ->and((float) $assetB->refresh()->quantity_allocated)->toBe(1.0);

    // Demand now references B, not A.
    $demands = Demand::query()->where('source_id', $item->id)->whereNotNull('asset_id')->get();
    expect($demands->pluck('asset_id')->all())->toBe([$assetB->id]);
});

it('quick-allocates several assets in one atomic commit', function () {
    $item = makeOrderLine($this->store, $this->product, '3');
    $a = makeSerialisedAsset($this->store, $this->product);
    $b = makeSerialisedAsset($this->store, $this->product);
    $c = makeSerialisedAsset($this->store, $this->product);

    $opportunity = $item->opportunity()->firstOrFail();

    (new QuickAllocateAssets)($opportunity, QuickAllocateAssetsData::from([
        'allocations' => [
            ['opportunity_item_id' => $item->id, 'stock_level_id' => $a->id],
            ['opportunity_item_id' => $item->id, 'stock_level_id' => $b->id],
            ['opportunity_item_id' => $item->id, 'stock_level_id' => $c->id],
        ],
    ]));

    expect(OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->count())->toBe(3);

    // Line fully allocated → 3 asset demands, no residual qty demand.
    $demands = Demand::query()->where('source_id', $item->id)->get();
    expect($demands->whereNotNull('asset_id')->count())->toBe(3)
        ->and($demands->whereNull('asset_id')->count())->toBe(0);
});

it('rolls back the whole quick_allocate batch when one allocation is invalid', function () {
    $item = makeOrderLine($this->store, $this->product, '3');
    $good = makeSerialisedAsset($this->store, $this->product);
    // A bulk asset of a DIFFERENT product — fails the product/serialised guard.
    $badProduct = Product::factory()->rental()->bulk()->create();
    $bad = StockLevel::factory()->bulk()->create(['product_id' => $badProduct->id, 'store_id' => $this->store->id]);

    $opportunity = $item->opportunity()->firstOrFail();

    expect(fn () => (new QuickAllocateAssets)($opportunity, QuickAllocateAssetsData::from([
        'allocations' => [
            ['opportunity_item_id' => $item->id, 'stock_level_id' => $good->id],
            ['opportunity_item_id' => $item->id, 'stock_level_id' => $bad->id],
        ],
    ])))->toThrow(EventNotValid::class);

    // Atomic: the good allocation rolled back with the batch.
    expect(OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->count())->toBe(0)
        ->and((float) $good->refresh()->quantity_allocated)->toBe(0.0);
});

it('blocks over-allocating an asset already claimed by another opportunity', function () {
    $itemA = makeOrderLine($this->store, $this->product, '1');
    $asset = makeSerialisedAsset($this->store, $this->product);

    // First opportunity claims the asset over the overlapping window.
    (new AllocateAsset)($itemA, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    // A second opportunity over the SAME window cannot claim the same asset.
    $itemB = makeOrderLine($this->store, $this->product, '1');

    (new AllocateAsset)($itemB, AllocateAssetData::from(['stock_level_id' => $asset->id]));
})->throws(EventNotValid::class);

it('rejects allocating a non-serialised stock level', function () {
    $item = makeOrderLine($this->store, $this->product, '1');
    $bulkProduct = Product::factory()->rental()->bulk()->create();
    $bulk = StockLevel::factory()->bulk()->create(['product_id' => $bulkProduct->id, 'store_id' => $this->store->id]);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $bulk->id]));
})->throws(EventNotValid::class);

it('rejects allocating against a closed opportunity', function () {
    $item = makeOrderLine($this->store, $this->product, '1');
    $asset = makeSerialisedAsset($this->store, $this->product);

    // Cancel the order (closed/terminal).
    $opportunity = $item->opportunity()->firstOrFail();
    (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::OrderCancelled);

    (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $asset->id]));
})->throws(EventNotValid::class);

it('rebuilds assets, allocation, and demands identically on replay', function () {
    $item = makeOrderLine($this->store, $this->product, '3');
    $a = makeSerialisedAsset($this->store, $this->product);
    $b = makeSerialisedAsset($this->store, $this->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $a->id]));
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $b->id]));

    $assetsBefore = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)
        ->orderBy('id')->get(['id', 'stock_level_id', 'status'])->toArray();
    $allocatedBefore = [
        $a->id => (float) $a->refresh()->quantity_allocated,
        $b->id => (float) $b->refresh()->quantity_allocated,
    ];
    $demandAssetsBefore = Demand::query()->where('source_id', $item->id)
        ->whereNotNull('asset_id')->orderBy('asset_id')->pluck('asset_id')->all();

    // Wipe the asset projection, then replay the entire event stream.
    OpportunityItemAsset::query()->delete();
    // Reset stock allocation so replay re-derives it from zero.
    StockLevel::query()->update(['quantity_allocated' => 0]);

    Verbs::replay();

    $assetsAfter = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)
        ->orderBy('id')->get(['id', 'stock_level_id', 'status'])->toArray();

    expect($assetsAfter)->toBe($assetsBefore)
        ->and((float) $a->refresh()->quantity_allocated)->toBe($allocatedBefore[$a->id])
        ->and((float) $b->refresh()->quantity_allocated)->toBe($allocatedBefore[$b->id]);

    // Demands are NOT rebuilt by replay (unlessReplaying); they remain as last synced.
    expect(Demand::query()->where('source_id', $item->id)->whereNotNull('asset_id')->orderBy('asset_id')->pluck('asset_id')->all())
        ->toBe($demandAssetsBefore);
});

it('rejects allocating more assets than the line quantity', function () {
    // A qty-2 line accepts two assets but rejects a third.
    $item = makeOrderLine($this->store, $this->product, '2');
    $a = makeSerialisedAsset($this->store, $this->product);
    $b = makeSerialisedAsset($this->store, $this->product);
    $c = makeSerialisedAsset($this->store, $this->product);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $a->id]));
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $b->id]));

    expect(fn () => (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $c->id])))
        ->toThrow(EventNotValid::class);

    expect(OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->count())->toBe(2);
});

it('rejects a quick_allocate batch that over-allocates a line atomically', function () {
    // A qty-2 line — a batch of three allocations exceeds the quantity and the whole
    // batch must roll back (no partial allocation left behind).
    $item = makeOrderLine($this->store, $this->product, '2');
    $a = makeSerialisedAsset($this->store, $this->product);
    $b = makeSerialisedAsset($this->store, $this->product);
    $c = makeSerialisedAsset($this->store, $this->product);

    $opportunity = $item->opportunity()->firstOrFail();

    expect(fn () => (new QuickAllocateAssets)($opportunity, QuickAllocateAssetsData::from([
        'allocations' => [
            ['opportunity_item_id' => $item->id, 'stock_level_id' => $a->id],
            ['opportunity_item_id' => $item->id, 'stock_level_id' => $b->id],
            ['opportunity_item_id' => $item->id, 'stock_level_id' => $c->id],
        ],
    ])))->toThrow(ValidationException::class);

    // Atomic: nothing allocated.
    expect(OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->count())->toBe(0);
});

it('quick-prepares several allocated assets in one atomic commit', function () {
    $item = makeOrderLine($this->store, $this->product, '2');
    $a = makeSerialisedAsset($this->store, $this->product);
    $b = makeSerialisedAsset($this->store, $this->product);

    $assetA = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $a->id]));
    $assetB = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $b->id]));

    $opportunity = $item->opportunity()->firstOrFail();

    (new QuickPrepareAssets)($opportunity, QuickPrepareAssetsData::from([
        'asset_ids' => [$assetA->id, $assetB->id],
    ]));

    $statuses = OpportunityItemAsset::query()
        ->where('opportunity_item_id', $item->id)
        ->pluck('status')
        ->all();

    expect($statuses)->each->toBe(AssetAssignmentStatus::Prepared);
});

it('rolls back the whole quick_prepare batch when one asset is not allocated', function () {
    $item = makeOrderLine($this->store, $this->product, '2');
    $a = makeSerialisedAsset($this->store, $this->product);
    $b = makeSerialisedAsset($this->store, $this->product);

    $assetA = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $a->id]));
    $assetB = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $b->id]));

    // Prepare asset B up-front so it can no longer be prepared — the batch must fail
    // and roll back asset A's preparation too.
    (new PrepareAsset)(OpportunityItemAsset::query()->whereKey($assetB->id)->firstOrFail());

    $opportunity = $item->opportunity()->firstOrFail();

    expect(fn () => (new QuickPrepareAssets)($opportunity, QuickPrepareAssetsData::from([
        'asset_ids' => [$assetA->id, $assetB->id],
    ])))->toThrow(EventNotValid::class);

    // Asset A stayed Allocated — the batch rolled back.
    expect(OpportunityItemAsset::query()->whereKey($assetA->id)->firstOrFail()->status)
        ->toBe(AssetAssignmentStatus::Allocated);
});
