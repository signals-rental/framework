<?php

use App\Enums\AssetAssignmentStatus;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\DemandPhase;
use App\Enums\OpportunityStatus;
use App\Enums\ReleasePoint;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\OpportunityItemDemandResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @param  array<string, mixed>  $itemAttributes
 */
function coverageDemandItem(
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

it('exposes resolveContext with dispatch_store override and quantity floor', function () {
    $primary = Store::factory()->create();
    $dispatch = Store::factory()->create();
    $product = Product::factory()->bulk()->create();
    $item = coverageDemandItem(OpportunityStatus::OrderActive, $product, $primary, [
        'dispatch_store_id' => $dispatch->id,
        'quantity' => 0.5,
    ]);

    $context = app(OpportunityItemDemandResolver::class)->resolveContext($item);

    expect($context['product_id'])->toBe($product->id)
        ->and($context['store_id'])->toBe($dispatch->id)
        ->and($context['quantity'])->toBe(1)
        ->and($context['from']->toIso8601String())->toBe('2026-08-01T09:00:00+00:00');
});

it('honours the availability.release_point setting when mapping phase', function () {
    settings()->set('availability.release_point', ReleasePoint::Checked->value);

    $product = Product::factory()->bulk()->create();
    $store = Store::factory()->create();
    $item = coverageDemandItem(OpportunityStatus::OrderReturned, $product, $store);

    expect(app(OpportunityItemDemandResolver::class)->resolvePhase($item))
        ->toBe(DemandPhase::Operational);
});

it('nets bulk returned quantity against the active demand quantity', function () {
    $product = Product::factory()->bulk()->create([
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);
    $store = Store::factory()->create();
    $item = coverageDemandItem(OpportunityStatus::OrderActive, $product, $store, [
        'quantity' => 5,
        'returned_quantity' => '2',
    ]);

    app(OpportunityItemDemandResolver::class)->syncDemands($item);

    expect(Demand::query()->where('source_id', $item->id)->first()->quantity)->toBe(3);
});

it('writes no bulk demand when the line is fully returned', function () {
    $product = Product::factory()->bulk()->create();
    $store = Store::factory()->create();
    $item = coverageDemandItem(OpportunityStatus::OrderActive, $product, $store, [
        'quantity' => 2,
        'returned_quantity' => '2',
    ]);

    app(OpportunityItemDemandResolver::class)->syncDemands($item);

    expect(Demand::query()->where('source_id', $item->id)->count())->toBe(0);
});

it('keeps a remainder demand when only part of a serialised line is allocated', function () {
    $product = Product::factory()->serialised()->create();
    $store = Store::factory()->create();
    $item = coverageDemandItem(OpportunityStatus::OrderActive, $product, $store, ['quantity' => 3]);

    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    OpportunityItemAsset::factory()->for($item, 'item')->create(['stock_level_id' => $asset->id]);

    app(OpportunityItemDemandResolver::class)->syncDemands($item);

    $demands = Demand::query()->where('source_id', $item->id)->get();

    expect($demands)->toHaveCount(2)
        ->and($demands->whereNotNull('asset_id')->first()->quantity)->toBe(1)
        ->and($demands->whereNull('asset_id')->first()->quantity)->toBe(2);
});

it('pulls asset demand start forward when dispatched before the planned start', function () {
    $product = Product::factory()->serialised()->create([
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);
    $store = Store::factory()->create();
    $item = coverageDemandItem(OpportunityStatus::OrderDispatched, $product, $store, ['quantity' => 1]);

    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    OpportunityItemAsset::factory()->for($item, 'item')->create([
        'stock_level_id' => $asset->id,
        'status' => AssetAssignmentStatus::Dispatched->value,
        'dispatched_at' => Carbon::parse('2026-07-30T08:00:00Z'),
    ]);

    app(OpportunityItemDemandResolver::class)->syncDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->first();

    expect($demand->starts_at->toIso8601String())->toBe('2026-07-30T08:00:00+00:00')
        ->and($demand->phase)->toBe(DemandPhase::Operational);
});

it('closes returned asset demand at the actual return and flags pending check-in', function () {
    $product = Product::factory()->serialised()->create([
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);
    $store = Store::factory()->create();
    $item = coverageDemandItem(OpportunityStatus::OrderDispatched, $product, $store, ['quantity' => 1]);

    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    OpportunityItemAsset::factory()->for($item, 'item')->create([
        'stock_level_id' => $asset->id,
        'status' => AssetAssignmentStatus::CheckedIn->value,
        'returned_at' => Carbon::parse('2026-08-02T10:00:00Z'),
        'checked_at' => null,
    ]);

    app(OpportunityItemDemandResolver::class)->syncDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->first();

    expect($demand->ends_at->toIso8601String())->toBe('2026-08-02T10:00:00+00:00')
        ->and($demand->phase)->toBe(DemandPhase::Closed)
        ->and($demand->metadata['pending_checkin'])->toBeTrue();
});

it('explodes pool components and claims hybrid housing as a serialised unit', function () {
    $pool = Product::factory()->bulk()->create([
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);
    $housing = Product::factory()->containerable(ContainerAvailabilityMode::Hybrid)->create([
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);

    SerialisedComponent::factory()->pool()->quantity(2)->create([
        'product_id' => $housing->id,
        'component_product_id' => $pool->id,
    ]);

    $store = Store::factory()->create();
    $item = coverageDemandItem(OpportunityStatus::OrderActive, $housing, $store, ['quantity' => 2]);

    app(OpportunityItemDemandResolver::class)->syncDemands($item);

    $demands = Demand::query()->where('source_id', $item->id)->get();

    expect($demands->where('product_id', $pool->id)->first()->quantity)->toBe(4)
        ->and($demands->where('product_id', $housing->id)->first()->quantity)->toBe(2);
});

it('voids demands for inactive version items and resyncs the active version on opportunity transition', function () {
    $product = Product::factory()->bulk()->create();
    $store = Store::factory()->create();
    $opportunity = Opportunity::factory()->order()->create(['store_id' => $store->id]);
    $version = OpportunityVersion::factory()->for($opportunity)->create();
    $opportunity->forceFill(['active_version_id' => $version->id])->save();

    $staleItem = OpportunityItem::factory()->for($opportunity)->create([
        'version_id' => $version->id + 1,
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
    ]);
    $activeItem = OpportunityItem::factory()->for($opportunity)->create([
        'version_id' => $version->id,
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
    ]);

    $resolver = app(OpportunityItemDemandResolver::class);
    $resolver->syncDemands($staleItem);
    $resolver->syncDemands($activeItem);

    expect(Demand::query()->where('source_id', $staleItem->id)->where('is_active', true)->count())->toBe(1);

    $opportunity->forceFill([
        'status' => OpportunityStatus::OrderCancelled->statusValue(),
        'state' => OpportunityStatus::OrderCancelled->state()->value,
    ])->save();

    $resolver->resyncForOpportunity($opportunity->fresh());

    expect(Demand::query()->where('source_id', $staleItem->id)->where('phase', DemandPhase::Void->value)->exists())
        ->toBeTrue()
        ->and(Demand::query()->where('source_id', $activeItem->id)->where('phase', DemandPhase::Void->value)->exists())
        ->toBeTrue();
});

it('rejects the wrong model type', function () {
    expect(fn () => app(OpportunityItemDemandResolver::class)->syncDemands(Product::factory()->create()))
        ->toThrow(InvalidArgumentException::class, 'OpportunityItem');
});

it('includes opportunity metadata on each demand row', function () {
    $product = Product::factory()->bulk()->create();
    $store = Store::factory()->create();
    $item = coverageDemandItem(OpportunityStatus::OrderActive, $product, $store);

    $metadata = app(OpportunityItemDemandResolver::class)->buildMetadata($item);

    expect($metadata)->toHaveKeys(['opportunity_id', 'opportunity_state', 'opportunity_status', 'returned_quantity'])
        ->and($metadata['opportunity_id'])->toBe($item->opportunity_id);
});

it('accepts only OpportunityItem models through the contract type hint', function () {
    $wrong = new class extends Model {};

    expect(fn () => app(OpportunityItemDemandResolver::class)->syncDemands($wrong))
        ->toThrow(InvalidArgumentException::class);
});
