<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\OpportunityStatus;
use App\Enums\StockMethod;
use App\Models\AvailabilityDailySummary;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Buffered demand window correctness (SQLite lane)
|--------------------------------------------------------------------------
|
| Regression coverage for the buffer/`period` under-count bug: a demand whose
| operational window ends Tue but whose buffered (turnaround) window extends into
| Wed must still occupy the Wed slot. Before the fix, candidate fetch used the
| buffered window but per-slot attribution re-filtered on the RAW ends_at, so a
| bulk unit appeared AVAILABLE during its own turnaround slot. These tests pin
| Daily resolution and assert across every read path.
|
*/

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->service = app(AvailabilityService::class);
    $this->pipeline = app(RecalculationPipeline::class);
});

/**
 * Create a bulk demand whose RAW window is `[rawStart, rawEnd)` but whose
 * BUFFERED window (the one the engine queries) is `[bufStart, bufEnd)`.
 */
function makeBufferedDemand(int $productId, int $storeId, int $quantity, string $rawStart, string $rawEnd, string $bufStart, string $bufEnd): Demand
{
    return Demand::factory()
        ->phase(DemandPhase::Committed)
        ->buffered(
            Carbon::parse($rawStart),
            Carbon::parse($rawEnd),
            Carbon::parse($bufStart),
            Carbon::parse($bufEnd),
        )
        ->create([
            'product_id' => $productId,
            'store_id' => $storeId,
            'quantity' => $quantity,
            'metadata' => [],
        ]);
}

describe('bulk getAvailability honours the buffered window', function () {
    it('counts a demand as occupied during its turnaround slot (raw ends_at already passed)', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 5,
        ]);

        // Operational window Mon–Tue, but the 1-day turnaround keeps it occupied
        // through Wed: buffered end is Thu 00:00.
        makeBufferedDemand(
            $product->id,
            $this->store->id,
            3,
            '2026-04-13T00:00:00Z', // Mon raw start
            '2026-04-15T00:00:00Z', // Wed raw end (operational ends Tue end / Wed 00:00)
            '2026-04-13T00:00:00Z',
            '2026-04-16T00:00:00Z', // buffered end Thu 00:00 (turnaround into Wed)
        );

        // The Wed slot lies AFTER the raw ends_at but inside the buffered window:
        // the unit must still be counted as demanded.
        $wed = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-15T06:00:00Z'));

        expect($wed->total_stock)->toBe(5)
            ->and($wed->total_demanded)->toBe(3)
            ->and($wed->available)->toBe(2);

        // And it is released the slot after the buffer ends (Thu).
        $thu = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-16T06:00:00Z'));

        expect($thu->total_demanded)->toBe(0)
            ->and($thu->available)->toBe(5);
    });
});

describe('snapshot getAvailabilityRange honours the buffered window', function () {
    it('materialises the turnaround slot as occupied in snapshots', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 4,
        ]);

        makeBufferedDemand(
            $product->id,
            $this->store->id,
            4,
            '2026-04-13T00:00:00Z',
            '2026-04-15T00:00:00Z',
            '2026-04-13T00:00:00Z',
            '2026-04-16T00:00:00Z', // buffered into Wed
        );

        $this->pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-04-13T00:00:00Z'),
            Carbon::parse('2026-04-17T00:00:00Z'),
        );

        $range = $this->service->getAvailabilityRange(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-04-13T00:00:00Z'),
            Carbon::parse('2026-04-17T00:00:00Z'),
        );

        $byDay = collect($range->slots)->keyBy(fn ($slot) => Carbon::parse($slot->slot_start)->toDateString());

        // Wed (the turnaround slot) is fully consumed → available 0.
        expect($byDay->get('2026-04-15')->available)->toBe(0)
            // Thu (after the buffer) is free again.
            ->and($byDay->get('2026-04-16')->available)->toBe(4);
    });
});

describe('daily summary has_shortage honours the buffered window', function () {
    it('flags a shortage on the turnaround day when demand exceeds stock', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 2,
        ]);

        // 3 units demanded against 2 in stock; the shortage must also surface on
        // the buffered (turnaround) day, not just the operational days.
        makeBufferedDemand(
            $product->id,
            $this->store->id,
            3,
            '2026-04-13T00:00:00Z',
            '2026-04-15T00:00:00Z',
            '2026-04-13T00:00:00Z',
            '2026-04-16T00:00:00Z', // buffered into Wed
        );

        $result = $this->pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-04-13T00:00:00Z'),
            Carbon::parse('2026-04-17T00:00:00Z'),
        );

        expect($result->hasShortage)->toBeTrue();

        $wedSummary = AvailabilityDailySummary::query()
            ->forProductStore($product->id, $this->store->id)
            ->where('date', Carbon::parse('2026-04-15')->startOfDay())
            ->first();

        expect($wedSummary)->not->toBeNull()
            ->and($wedSummary->has_shortage)->toBeTrue()
            ->and($wedSummary->min_available)->toBe(-1);

        // Thu (after buffer) has no shortage.
        $thuSummary = AvailabilityDailySummary::query()
            ->forProductStore($product->id, $this->store->id)
            ->where('date', Carbon::parse('2026-04-16')->startOfDay())
            ->first();

        expect($thuSummary->has_shortage)->toBeFalse();
    });
});

describe('availableForItem (shortage detector) honours the buffered window', function () {
    it('sees a turnaround-buffered competing demand as occupying stock', function () {
        // Product with a 1-day (1440 min) turnaround buffer.
        $product = Product::factory()->create([
            'stock_method' => StockMethod::Bulk->value,
            'buffer_before_minutes' => 0,
            'post_rent_unavailability' => 1440,
            'track_availability' => true,
        ]);

        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 3,
        ]);

        // A competing committed demand created via the resolver so the real
        // product buffer is baked into its buffered bounds.
        $competingOpp = Opportunity::factory()->create([
            'state' => OpportunityStatus::OrderActive->state()->value,
            'status' => OpportunityStatus::OrderActive->statusValue(),
            'store_id' => $this->store->id,
            'starts_at' => Carbon::parse('2026-04-13T00:00:00Z'),
            'ends_at' => Carbon::parse('2026-04-15T00:00:00Z'), // operational ends Wed 00:00
        ]);
        $competingItem = OpportunityItem::factory()->for($competingOpp)->create([
            'item_type' => Product::class,
            'item_id' => $product->id,
            'quantity' => 3,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        (new OpportunityItemDemandResolver)->syncDemands($competingItem);

        // The competing demand's buffered window now extends one day past the raw
        // Wed-00:00 end → into Wed. A NEW item requesting 1 unit over the Wed slot
        // must see zero free (all 3 occupied by the buffered competitor), so a
        // shortfall is detected.
        $available = $this->service->availableForItem(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-04-15T00:00:00Z'), // Wed (turnaround slot of the competitor)
            Carbon::parse('2026-04-16T00:00:00Z'),
            'opportunity_item',
            999_999, // a different, non-existent item id → competitor not excluded
        );

        expect($available)->toBe(0);
    });
});
