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
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

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
        // Fake the queue so the async observer-dispatched RecalculateAvailabilityJob
        // (which would recompute the WHOLE rolling horizon) does not run; this test
        // exercises the pipeline directly over a narrow window in isolation.
        Queue::fake();

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 8,
        ]);

        // Calling the pipeline directly proves the computation independently of
        // the (now async/debounced) observer trigger.
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

    it('bounds the snapshot count for an indefinite (sentinel-dated) demand to the rolling horizon', function () {
        config([
            'availability.snapshot_horizon.past_days' => 90,
            'availability.snapshot_horizon.future_days' => 365,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-18T00:00:00Z'));

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        // An open-ended hire: ends at the sentinel "no known end" date.
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-06-18T00:00:00Z'), Demand::sentinel())
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 3,
            ]);

        // The observer-driven recalc on create requests the sentinel window but
        // must be clamped to the horizon: ~455 days, NOT the ~63k days to 2199.
        $count = AvailabilitySnapshot::query()
            ->forProductStore($product->id, $this->store->id)
            ->count();

        // Daily resolution across a 90-past + 365-future window from now. The
        // demand starts at "now", so only the future side carries demand, but the
        // bound is what matters: a few hundred rows, never tens of thousands.
        expect($count)->toBeGreaterThan(300)
            ->and($count)->toBeLessThan(500);

        // Point availability beyond the horizon is still correct — served live
        // from the demands table, not from a snapshot.
        $beyond = app(AvailabilityService::class)->getAvailability(
            $product->id,
            $this->store->id,
            Carbon::parse('2030-01-01T00:00:00Z'),
        );

        expect($beyond->total_stock)->toBe(10)
            ->and($beyond->total_demanded)->toBe(3)
            ->and($beyond->available)->toBe(7);

        // And no snapshot was materialised that far out.
        expect(AvailabilitySnapshot::query()
            ->forProductStore($product->id, $this->store->id)
            ->where('slot_start', '>=', Carbon::parse('2028-01-01T00:00:00Z'))
            ->count())->toBe(0);

        Carbon::setTestNow();
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
    // The observer now dispatches a RecalculateAvailabilityJob instead of
    // recalculating inline. Under the suite's `sync` queue the job runs
    // immediately, but it recomputes the WHOLE rolling horizon for the
    // product/store — so these tests assert on the snapshot covering the
    // demand's own day (pinned with setTestNow so the window is inside the
    // horizon) rather than on `first()`, which is now the earliest horizon slot.
    beforeEach(function () {
        Carbon::setTestNow(Carbon::parse('2026-06-18T00:00:00Z'));
    });

    afterEach(function () {
        Carbon::setTestNow();
    });

    it('creates snapshots via the dispatched job when a demand is created', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-06-20T00:00:00Z'), Carbon::parse('2026-06-21T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 4,
            ]);

        $snapshot = AvailabilitySnapshot::query()
            ->forProductStore($product->id, $this->store->id)
            ->where('slot_start', Carbon::parse('2026-06-20T00:00:00Z'))
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
            ->window(Carbon::parse('2026-06-20T00:00:00Z'), Carbon::parse('2026-06-21T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 4,
            ]);

        // Release it — observer fires updated() and dispatches a recalc.
        $demand->update(['phase' => DemandPhase::Void, 'is_active' => false]);

        $snapshot = AvailabilitySnapshot::query()
            ->forProductStore($product->id, $this->store->id)
            ->where('slot_start', Carbon::parse('2026-06-20T00:00:00Z'))
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
