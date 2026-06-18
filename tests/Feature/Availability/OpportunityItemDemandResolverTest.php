<?php

use App\Enums\DemandPhase;
use App\Enums\OpportunityStatus;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\OpportunityItemDemandResolver;
use Illuminate\Support\Carbon;

/**
 * Build an opportunity (in the given state/status) with a single product-backed
 * line item, returning the line item ready for the resolver.
 *
 * @param  array<string, mixed>  $itemAttributes
 */
function makeDemandItem(
    OpportunityStatus $status,
    Product $product,
    Store $store,
    array $itemAttributes = [],
): OpportunityItem {
    $opportunity = Opportunity::factory()->create([
        'state' => $status->state()->value,
        'status' => $status->statusValue(),
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-08-05T17:00:00Z'),
    ]);

    return OpportunityItem::factory()->for($opportunity)->create(array_merge([
        'item_type' => Product::class,
        'item_id' => $product->id,
        'quantity' => 3,
    ], $itemAttributes));
}

function demandResolver(): OpportunityItemDemandResolver
{
    return new OpportunityItemDemandResolver;
}

it('exposes the opportunity_item source type', function () {
    expect(demandResolver()->sourceType())->toBe('opportunity_item');
});

it('maps the parent opportunity status to the demand phase (ceiling principle)', function (OpportunityStatus $status, DemandPhase $expected) {
    $product = Product::factory()->create(['stock_method' => StockMethod::Bulk->value]);
    $store = Store::factory()->create();
    $item = makeDemandItem($status, $product, $store);

    expect(demandResolver()->resolvePhase($item))->toBe($expected);
})->with([
    'draft → draft' => [OpportunityStatus::DraftOpen, DemandPhase::Draft],
    'provisional → draft' => [OpportunityStatus::QuotationProvisional, DemandPhase::Draft],
    'reserved → committed' => [OpportunityStatus::QuotationReserved, DemandPhase::Committed],
    'active → committed' => [OpportunityStatus::OrderActive, DemandPhase::Committed],
    'dispatched → operational' => [OpportunityStatus::OrderDispatched, DemandPhase::Operational],
    'returned → closed' => [OpportunityStatus::OrderReturned, DemandPhase::Closed],
    'cancelled → void' => [OpportunityStatus::OrderCancelled, DemandPhase::Void],
]);

it('creates a single bulk demand for a bulk product', function () {
    $product = Product::factory()->create([
        'stock_method' => StockMethod::Bulk->value,
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);
    $store = Store::factory()->create();
    $item = makeDemandItem(OpportunityStatus::OrderActive, $product, $store, ['quantity' => 4]);

    demandResolver()->syncDemands($item);

    $demands = Demand::query()->where('source_id', $item->id)->get();

    expect($demands)->toHaveCount(1);

    $demand = $demands->first();

    expect($demand->asset_id)->toBeNull()
        ->and($demand->quantity)->toBe(4)
        ->and($demand->product_id)->toBe($product->id)
        ->and($demand->store_id)->toBe($store->id)
        ->and($demand->phase)->toBe(DemandPhase::Committed)
        ->and($demand->is_active)->toBeTrue()
        ->and($demand->source_type)->toBe('opportunity_item')
        ->and($demand->metadata['opportunity_id'])->toBe($item->opportunity_id);
});

it('creates one serialised demand per allocated asset', function () {
    $product = Product::factory()->create(['stock_method' => StockMethod::Serialised->value]);
    $store = Store::factory()->create();
    $item = makeDemandItem(OpportunityStatus::OrderDispatched, $product, $store, ['quantity' => 2]);

    $assetA = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $store->id]);
    $assetB = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $store->id]);

    OpportunityItemAsset::factory()->for($item, 'item')->create(['stock_level_id' => $assetA->id]);
    OpportunityItemAsset::factory()->for($item, 'item')->create(['stock_level_id' => $assetB->id]);

    demandResolver()->syncDemands($item);

    $demands = Demand::query()->where('source_id', $item->id)->get();

    expect($demands)->toHaveCount(2)
        ->and($demands->pluck('asset_id')->sort()->values()->all())->toBe(collect([$assetA->id, $assetB->id])->sort()->values()->all())
        ->and($demands->every(fn (Demand $d): bool => $d->quantity === 1))->toBeTrue()
        ->and($demands->every(fn (Demand $d): bool => $d->phase === DemandPhase::Operational))->toBeTrue();
});

it('falls back to a bulk-style demand for a serialised product with no allocations', function () {
    $product = Product::factory()->create(['stock_method' => StockMethod::Serialised->value]);
    $store = Store::factory()->create();
    $item = makeDemandItem(OpportunityStatus::OrderActive, $product, $store, ['quantity' => 2]);

    demandResolver()->syncDemands($item);

    $demands = Demand::query()->where('source_id', $item->id)->get();

    expect($demands)->toHaveCount(1)
        ->and($demands->first()->asset_id)->toBeNull()
        ->and($demands->first()->quantity)->toBe(2);
});

it('bakes product buffers and inherits opportunity dates into the period', function () {
    $product = Product::factory()->create([
        'stock_method' => StockMethod::Bulk->value,
        'buffer_before_minutes' => 120,
        'post_rent_unavailability' => 240,
    ]);
    $store = Store::factory()->create();

    // Item has no own dates → inherits the opportunity's.
    $item = makeDemandItem(OpportunityStatus::OrderActive, $product, $store, [
        'starts_at' => null,
        'ends_at' => null,
    ]);

    demandResolver()->syncDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->first();

    // starts_at / ends_at keep the inherited (pre-buffer) dates.
    expect($demand->starts_at->toIso8601String())->toBe('2026-08-01T09:00:00+00:00')
        ->and($demand->ends_at->toIso8601String())->toBe('2026-08-05T17:00:00+00:00');
});

it('treats an open-ended item as indefinite via the sentinel', function () {
    $product = Product::factory()->create(['stock_method' => StockMethod::Bulk->value]);
    $store = Store::factory()->create();

    $opportunity = Opportunity::factory()->order()->create([
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
        'ends_at' => null,
    ]);

    $item = OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => Product::class,
        'item_id' => $product->id,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    demandResolver()->syncDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->first();

    expect($demand->is_indefinite)->toBeTrue();
});

it('is idempotent — re-syncing converges rather than duplicating', function () {
    $product = Product::factory()->create(['stock_method' => StockMethod::Bulk->value]);
    $store = Store::factory()->create();
    $item = makeDemandItem(OpportunityStatus::OrderActive, $product, $store, ['quantity' => 3]);

    $resolver = demandResolver();
    $resolver->syncDemands($item);
    $resolver->syncDemands($item);
    $resolver->syncDemands($item);

    expect(Demand::query()->where('source_id', $item->id)->count())->toBe(1);
});

it('skips creating demands when the opportunity has no store', function () {
    $product = Product::factory()->create(['stock_method' => StockMethod::Bulk->value]);

    $opportunity = Opportunity::factory()->order()->create(['store_id' => null]);
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => Product::class,
        'item_id' => $product->id,
    ]);

    demandResolver()->syncDemands($item);

    expect(Demand::query()->where('source_id', $item->id)->count())->toBe(0);
});

it('skips non-product line items', function () {
    $store = Store::factory()->create();
    $opportunity = Opportunity::factory()->order()->create(['store_id' => $store->id]);

    $item = OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => null,
        'item_id' => null,
    ]);

    demandResolver()->syncDemands($item);

    expect(Demand::query()->where('source_id', $item->id)->count())->toBe(0);
});

it('voids demands on release', function () {
    $product = Product::factory()->create(['stock_method' => StockMethod::Bulk->value]);
    $store = Store::factory()->create();
    $item = makeDemandItem(OpportunityStatus::OrderActive, $product, $store);

    $resolver = demandResolver();
    $resolver->syncDemands($item);

    $resolver->releaseDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->first();

    expect($demand->phase)->toBe(DemandPhase::Void)
        ->and($demand->is_active)->toBeFalse();
});
