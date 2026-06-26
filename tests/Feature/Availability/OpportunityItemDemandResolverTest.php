<?php

use App\Enums\DemandPhase;
use App\Enums\OpportunityStatus;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\AvailabilityService;
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
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
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

it('persists buffered bounds reflecting the product buffers', function () {
    $product = Product::factory()->create([
        'stock_method' => StockMethod::Bulk->value,
        'buffer_before_minutes' => 120,
        'post_rent_unavailability' => 240,
    ]);
    $store = Store::factory()->create();

    $item = makeDemandItem(OpportunityStatus::OrderActive, $product, $store, [
        'starts_at' => null,
        'ends_at' => null,
    ]);

    demandResolver()->syncDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->first();

    // Raw dates unbuffered; buffered bounds widened by the product buffers.
    expect($demand->starts_at->toIso8601String())->toBe('2026-08-01T09:00:00+00:00')
        ->and($demand->ends_at->toIso8601String())->toBe('2026-08-05T17:00:00+00:00')
        ->and($demand->buffered_starts_at->toIso8601String())->toBe('2026-08-01T07:00:00+00:00')
        ->and($demand->buffered_ends_at->toIso8601String())->toBe('2026-08-05T21:00:00+00:00')
        ->and($demand->bufferedStartsAt()->toIso8601String())->toBe('2026-08-01T07:00:00+00:00')
        ->and($demand->bufferedEndsAt()->toIso8601String())->toBe('2026-08-05T21:00:00+00:00');
});

it('does not apply turnaround buffers for non-occupying phases (Draft/Void)', function () {
    $product = Product::factory()->create([
        'stock_method' => StockMethod::Bulk->value,
        'buffer_before_minutes' => 120,
        'post_rent_unavailability' => 240,
    ]);
    $store = Store::factory()->create();

    // DraftOpen → Draft phase → no turnaround buffer applied.
    $item = makeDemandItem(OpportunityStatus::DraftOpen, $product, $store, [
        'starts_at' => null,
        'ends_at' => null,
    ]);

    demandResolver()->syncDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->first();

    // Buffered bounds equal the raw dates — buffers suppressed for the phase.
    expect($demand->buffered_starts_at->toIso8601String())->toBe('2026-08-01T09:00:00+00:00')
        ->and($demand->buffered_ends_at->toIso8601String())->toBe('2026-08-05T17:00:00+00:00');
});

it('uses the opportunity charge window when demand_date_source is charge', function () {
    settings()->set('availability.demand_date_source', 'charge');

    $product = Product::factory()->create([
        'stock_method' => StockMethod::Bulk->value,
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);
    $store = Store::factory()->create();

    $opportunity = Opportunity::factory()->create([
        'state' => OpportunityStatus::OrderActive->state()->value,
        'status' => OpportunityStatus::OrderActive->statusValue(),
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-08-05T17:00:00Z'),
        'charge_starts_at' => Carbon::parse('2026-08-02T00:00:00Z'),
        'charge_ends_at' => Carbon::parse('2026-08-04T00:00:00Z'),
    ]);

    $item = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => 1,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    demandResolver()->syncDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->first();

    // The demand window follows the charge dates, not the operational dates.
    expect($demand->starts_at->toIso8601String())->toBe('2026-08-02T00:00:00+00:00')
        ->and($demand->ends_at->toIso8601String())->toBe('2026-08-04T00:00:00+00:00');
});

it('falls back to operational dates when charge bounds are unset under the charge source', function () {
    settings()->set('availability.demand_date_source', 'charge');

    $product = Product::factory()->create([
        'stock_method' => StockMethod::Bulk->value,
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);
    $store = Store::factory()->create();

    $opportunity = Opportunity::factory()->create([
        'state' => OpportunityStatus::OrderActive->state()->value,
        'status' => OpportunityStatus::OrderActive->statusValue(),
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-08-05T17:00:00Z'),
        'charge_starts_at' => null,
        'charge_ends_at' => null,
    ]);

    $item = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => 1,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    demandResolver()->syncDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->first();

    expect($demand->starts_at->toIso8601String())->toBe('2026-08-01T09:00:00+00:00')
        ->and($demand->ends_at->toIso8601String())->toBe('2026-08-05T17:00:00+00:00');
});

it('treats an open-ended item as indefinite via the sentinel', function () {
    // Freeze "now" so the rolling-horizon clamp (and therefore the slot maths the
    // demand sync triggers downstream) is deterministic regardless of wall-clock.
    Carbon::setTestNow(Carbon::parse('2026-06-18T00:00:00Z'));

    $product = Product::factory()->create(['stock_method' => StockMethod::Bulk->value]);
    $store = Store::factory()->create();

    $opportunity = Opportunity::factory()->order()->create([
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
        'ends_at' => null,
    ]);

    $item = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    // syncDemands fans out to the recalc job and proactive shortage detection,
    // both of which slot the item's window. An indefinite (sentinel-ended) window
    // must be clamped to the rolling horizon before slot generation, so this must
    // NOT throw the SlotCalculator's 50000-slot safety cap.
    demandResolver()->syncDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->first();

    expect($demand->is_indefinite)->toBeTrue();

    Carbon::setTestNow();
});

it('reads an indefinite item over a bounded, horizon-sized slot set without tripping the cap', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-18T00:00:00Z'));

    $product = Product::factory()->create([
        'stock_method' => StockMethod::Bulk->value,
        'track_availability' => true,
    ]);
    $store = Store::factory()->create();

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);

    $opportunity = Opportunity::factory()->order()->create([
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
        'ends_at' => null,
    ]);

    $item = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => 2,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    demandResolver()->syncDemands($item);

    // The live read path slots the item's effective window — which ends at the
    // 2199 sentinel. With the clamp in place this resolves to the worst horizon
    // slot rather than throwing: 5 in stock, this item excluded → all 5 free.
    $available = app(AvailabilityService::class)->availableForItem(
        $product->id,
        $store->id,
        Carbon::parse('2026-08-01T09:00:00Z'),
        Demand::sentinel(),
        'opportunity_item',
        (int) $item->id,
    );

    expect($available)->toBe(5);

    Carbon::setTestNow();
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
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
    ]);

    demandResolver()->syncDemands($item);

    expect(Demand::query()->where('source_id', $item->id)->count())->toBe(0);
});

it('skips non-product line items', function () {
    $store = Store::factory()->create();
    $opportunity = Opportunity::factory()->order()->create(['store_id' => $store->id]);

    $item = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_type' => null,
        'itemable_id' => null,
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

describe('catalogue kit explosion (M5-3a)', function () {
    it('explodes a kit line into per-pool-component demands (not a kit demand)', function () {
        $componentA = Product::factory()->bulk()->create([
            'buffer_before_minutes' => 0,
            'post_rent_unavailability' => 0,
        ]);
        $componentB = Product::factory()->bulk()->create([
            'buffer_before_minutes' => 0,
            'post_rent_unavailability' => 0,
        ]);

        $kit = Product::factory()->kit()->create();

        SerialisedComponent::factory()->pool()->quantity(2)->create([
            'product_id' => $kit->id,
            'component_product_id' => $componentA->id,
        ]);
        SerialisedComponent::factory()->pool()->quantity(3)->create([
            'product_id' => $kit->id,
            'component_product_id' => $componentB->id,
        ]);

        $store = Store::factory()->create();
        // line qty 4 → A: 4×2=8, B: 4×3=12
        $item = makeDemandItem(OpportunityStatus::OrderActive, $kit, $store, ['quantity' => 4]);

        demandResolver()->syncDemands($item);

        $demands = Demand::query()->where('source_id', $item->id)->get();

        // No demand against the kit product itself.
        expect($demands->where('product_id', $kit->id))->toHaveCount(0)
            ->and($demands)->toHaveCount(2);

        $byProduct = $demands->keyBy('product_id');

        expect($byProduct[$componentA->id]->quantity)->toBe(8)
            ->and($byProduct[$componentB->id]->quantity)->toBe(12)
            ->and($byProduct[$componentA->id]->asset_id)->toBeNull()
            ->and($byProduct[$componentA->id]->phase)->toBe(DemandPhase::Committed);
    });

    it('ignores fixed-binding components when exploding a kit (M5-3b seam)', function () {
        $pool = Product::factory()->bulk()->create();
        $fixed = Product::factory()->bulk()->create();
        $kit = Product::factory()->kit()->create();

        SerialisedComponent::factory()->pool()->quantity(1)->create([
            'product_id' => $kit->id,
            'component_product_id' => $pool->id,
        ]);
        SerialisedComponent::factory()->fixed()->quantity(1)->create([
            'product_id' => $kit->id,
            'component_product_id' => $fixed->id,
        ]);

        $store = Store::factory()->create();
        $item = makeDemandItem(OpportunityStatus::OrderActive, $kit, $store, ['quantity' => 1]);

        demandResolver()->syncDemands($item);

        $demands = Demand::query()->where('source_id', $item->id)->get();

        expect($demands)->toHaveCount(1)
            ->and($demands->first()->product_id)->toBe($pool->id);
    });

    it('terminates bounded on a cyclic nested-kit composition (write-path depth backstop)', function () {
        // Build a deliberately cyclic composition (kitA → kitB → kitA) directly,
        // bypassing the create-time KitCompositionGuard, to prove the resolver's
        // recursion is depth-bounded and cannot loop forever on corrupt data.
        $leaf = Product::factory()->bulk()->create();
        $kitA = Product::factory()->kit()->create();
        $kitB = Product::factory()->kit()->create();

        SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kitA->id, 'component_product_id' => $kitB->id]);
        SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kitA->id, 'component_product_id' => $leaf->id]);
        SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kitB->id, 'component_product_id' => $kitA->id]);

        $store = Store::factory()->create();
        $item = makeDemandItem(OpportunityStatus::OrderActive, $kitA, $store, ['quantity' => 1]);

        // Must return (bounded by availability.kit_nesting_max_depth) rather than
        // recursing until the stack overflows.
        demandResolver()->syncDemands($item);

        // The leaf component is reached and at least one demand is written.
        expect(Demand::query()->where('source_id', $item->id)->where('product_id', $leaf->id)->exists())->toBeTrue();
    });

    it('releases exploded kit-component demands as one set', function () {
        $a = Product::factory()->bulk()->create();
        $b = Product::factory()->bulk()->create();
        $kit = Product::factory()->kit()->create();

        SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kit->id, 'component_product_id' => $a->id]);
        SerialisedComponent::factory()->pool()->quantity(1)->create(['product_id' => $kit->id, 'component_product_id' => $b->id]);

        $store = Store::factory()->create();
        $item = makeDemandItem(OpportunityStatus::OrderActive, $kit, $store, ['quantity' => 2]);

        $resolver = demandResolver();
        $resolver->syncDemands($item);

        expect(Demand::query()->where('source_id', $item->id)->where('is_active', true)->count())->toBe(2);

        $resolver->releaseDemands($item);

        $active = Demand::query()->where('source_id', $item->id)->where('is_active', true)->count();
        $voided = Demand::query()->where('source_id', $item->id)->where('phase', DemandPhase::Void->value)->count();

        expect($active)->toBe(0)
            ->and($voided)->toBe(2);
    });

    it('explodes a nested kit recursively', function () {
        $leaf = Product::factory()->bulk()->create();
        $inner = Product::factory()->kit()->create();
        $outer = Product::factory()->kit()->create();

        // inner kit → 2× leaf ; outer kit → 3× inner
        SerialisedComponent::factory()->pool()->quantity(2)->create(['product_id' => $inner->id, 'component_product_id' => $leaf->id]);
        SerialisedComponent::factory()->pool()->quantity(3)->create(['product_id' => $outer->id, 'component_product_id' => $inner->id]);

        $store = Store::factory()->create();
        // line qty 1 → inner needed = 3 → leaf needed = 3×2 = 6
        $item = makeDemandItem(OpportunityStatus::OrderActive, $outer, $store, ['quantity' => 1]);

        demandResolver()->syncDemands($item);

        $demands = Demand::query()->where('source_id', $item->id)->get();

        expect($demands)->toHaveCount(1)
            ->and($demands->first()->product_id)->toBe($leaf->id)
            ->and($demands->first()->quantity)->toBe(6);
    });
});
