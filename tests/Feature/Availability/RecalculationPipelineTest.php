<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityEventType;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\AvailabilityEvent;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->pipeline = app(RecalculationPipeline::class);
});

describe('recalculate', function () {
    it('writes snapshots with correct stock, demand, availability and breakdown', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 8,
        ]);

        // The factory create() fires the observer, which already recalculates;
        // calling the pipeline directly here proves the computation independently.
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-05-01T00:00:00Z'), Carbon::parse('2026-05-03T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 3,
            ]);

        $this->pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-05-01T00:00:00Z'),
            Carbon::parse('2026-05-03T00:00:00Z'),
        );

        $snapshots = AvailabilitySnapshot::query()
            ->forProductStore($product->id, $this->store->id)
            ->orderBy('slot_start')
            ->get();

        expect($snapshots)->toHaveCount(2);

        $first = $snapshots->first();
        expect($first->total_stock)->toBe(8)
            ->and($first->total_demanded)->toBe(3)
            ->and($first->available)->toBe(5)
            ->and($first->demand_breakdown)->toBe(['opportunity_item' => 3]);
    });

    it('skips products that do not track availability', function () {
        $product = Product::factory()->bulk()->notTracked()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 8,
        ]);

        $this->pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-05-01T00:00:00Z'),
            Carbon::parse('2026-05-03T00:00:00Z'),
        );

        expect(AvailabilitySnapshot::query()->where('product_id', $product->id)->count())->toBe(0);
    });

    it('logs an availability_recalculated event', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 4,
        ]);

        $this->pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-05-01T00:00:00Z'),
            Carbon::parse('2026-05-02T00:00:00Z'),
        );

        $event = AvailabilityEvent::query()
            ->ofType(AvailabilityEventType::AvailabilityRecalculated)
            ->where('product_id', $product->id)
            ->latest('id')
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->payload['slots'])->toBe(1);
    });

    it('counts serialised stock rows as total_stock', function () {
        $product = Product::factory()->serialised()->create();
        StockLevel::factory()->serialised()->count(3)->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        $this->pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-05-01T00:00:00Z'),
            Carbon::parse('2026-05-02T00:00:00Z'),
        );

        $snapshot = AvailabilitySnapshot::query()->where('product_id', $product->id)->first();

        expect($snapshot->total_stock)->toBe(3)
            ->and($snapshot->available)->toBe(3);
    });
});

describe('DemandObserver keeps snapshots fresh', function () {
    it('creates snapshots synchronously when a demand is created', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-06-01T00:00:00Z'), Carbon::parse('2026-06-02T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 4,
            ]);

        $snapshot = AvailabilitySnapshot::query()
            ->forProductStore($product->id, $this->store->id)
            ->first();

        expect($snapshot)->not->toBeNull()
            ->and($snapshot->total_demanded)->toBe(4)
            ->and($snapshot->available)->toBe(6);
    });

    it('refreshes snapshots when a demand is released', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        $demand = Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-06-01T00:00:00Z'), Carbon::parse('2026-06-02T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 4,
            ]);

        // Release it — observer fires updated() and recalculates.
        $demand->update(['phase' => DemandPhase::Void, 'is_active' => false]);

        $snapshot = AvailabilitySnapshot::query()
            ->forProductStore($product->id, $this->store->id)
            ->first();

        expect($snapshot->total_demanded)->toBe(0)
            ->and($snapshot->available)->toBe(10);

        expect(AvailabilityEvent::query()->ofType(AvailabilityEventType::DemandReleased)->count())->toBe(1);
    });

    it('logs a demand_created event on creation', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        Demand::factory()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        expect(AvailabilityEvent::query()->ofType(AvailabilityEventType::DemandCreated)->count())->toBe(1);
    });
});
