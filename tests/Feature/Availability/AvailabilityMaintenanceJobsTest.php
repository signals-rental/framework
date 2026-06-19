<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityEventType;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Jobs\ExpirePutAsides;
use App\Jobs\PruneAvailabilityData;
use App\Jobs\RebuildSnapshotsJob;
use App\Jobs\VerifySnapshotIntegrity;
use App\Models\AvailabilityDailySummary;
use App\Models\AvailabilityEvent;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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
});

describe('RebuildSnapshotsJob', function () {
    it('materialises snapshots for a product/store over the rolling horizon', function () {
        Queue::fake();

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 6,
        ]);

        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::now('UTC')->addDay(), Carbon::now('UTC')->addDays(2))
            ->create(['product_id' => $product->id, 'store_id' => $this->store->id, 'quantity' => 2]);

        (new RebuildSnapshotsJob($product->id, $this->store->id))
            ->handle(app(RecalculationPipeline::class));

        expect(AvailabilitySnapshot::query()->where('product_id', $product->id)->exists())->toBeTrue();
    });
});

describe('PruneAvailabilityData', function () {
    it('removes snapshots, summaries and events beyond their retention windows', function () {
        $product = Product::factory()->bulk()->create();

        // Stale rows (well beyond retention) + fresh rows (inside it).
        AvailabilitySnapshot::query()->create([
            'product_id' => $product->id, 'store_id' => $this->store->id,
            'slot_start' => Carbon::now('UTC')->subDays(400),
            'total_stock' => 1, 'total_demanded' => 0, 'available' => 1,
            'demand_breakdown' => [], 'calculated_at' => Carbon::now('UTC'),
        ]);
        AvailabilitySnapshot::query()->create([
            'product_id' => $product->id, 'store_id' => $this->store->id,
            'slot_start' => Carbon::now('UTC')->addDays(5),
            'total_stock' => 1, 'total_demanded' => 0, 'available' => 1,
            'demand_breakdown' => [], 'calculated_at' => Carbon::now('UTC'),
        ]);

        AvailabilityDailySummary::query()->create([
            'product_id' => $product->id, 'store_id' => $this->store->id,
            'date' => Carbon::now('UTC')->subYears(5)->startOfDay(),
            'min_available' => 0, 'max_available' => 0, 'has_shortage' => false,
            'calculated_at' => Carbon::now('UTC'),
        ]);
        AvailabilityDailySummary::query()->create([
            'product_id' => $product->id, 'store_id' => $this->store->id,
            'date' => Carbon::now('UTC')->startOfDay(),
            'min_available' => 0, 'max_available' => 0, 'has_shortage' => false,
            'calculated_at' => Carbon::now('UTC'),
        ]);

        // `created_at` is not fillable (auto-stamped), so back-date the stale
        // event with a direct update after creation.
        $stale = AvailabilityEvent::query()->create([
            'event_type' => AvailabilityEventType::AvailabilityRecalculated,
            'product_id' => $product->id, 'store_id' => $this->store->id,
            'payload' => [],
        ]);
        AvailabilityEvent::query()->whereKey($stale->id)
            ->update(['created_at' => Carbon::now('UTC')->subMonths(24)]);

        AvailabilityEvent::query()->create([
            'event_type' => AvailabilityEventType::AvailabilityRecalculated,
            'product_id' => $product->id, 'store_id' => $this->store->id,
            'payload' => [],
        ]);

        (new PruneAvailabilityData)->handle();

        expect(AvailabilitySnapshot::query()->count())->toBe(1)
            ->and(AvailabilityDailySummary::query()->count())->toBe(1)
            ->and(AvailabilityEvent::query()->count())->toBe(1);
    });

    it('is idempotent — a second run with nothing stale removes nothing more', function () {
        $product = Product::factory()->bulk()->create();
        AvailabilitySnapshot::query()->create([
            'product_id' => $product->id, 'store_id' => $this->store->id,
            'slot_start' => Carbon::now('UTC')->addDays(2),
            'total_stock' => 1, 'total_demanded' => 0, 'available' => 1,
            'demand_breakdown' => [], 'calculated_at' => Carbon::now('UTC'),
        ]);

        (new PruneAvailabilityData)->handle();
        (new PruneAvailabilityData)->handle();

        expect(AvailabilitySnapshot::query()->count())->toBe(1);
    });
});

describe('VerifySnapshotIntegrity', function () {
    it('repairs and logs drift between stored and recomputed snapshots', function () {
        Queue::fake();

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        // Plant a deliberately wrong snapshot inside the horizon (stored 99,
        // correct value with no demands is 10).
        AvailabilitySnapshot::query()->create([
            'product_id' => $product->id, 'store_id' => $this->store->id,
            'slot_start' => Carbon::now('UTC')->addDay()->startOfDay(),
            'total_stock' => 10, 'total_demanded' => 0, 'available' => 99,
            'demand_breakdown' => [], 'calculated_at' => Carbon::now('UTC'),
        ]);

        $spy = Log::spy();

        (new VerifySnapshotIntegrity(sampleSize: 5))->handle(app(RecalculationPipeline::class));

        $repaired = AvailabilitySnapshot::query()
            ->where('product_id', $product->id)
            ->where('slot_start', Carbon::now('UTC')->addDay()->startOfDay())
            ->firstOrFail();

        expect($repaired->available)->toBe(10);
        $spy->shouldHaveReceived('warning')->once();
    });

    it('does nothing when there are no snapshots', function () {
        Queue::fake();

        expect(fn () => (new VerifySnapshotIntegrity)->handle(app(RecalculationPipeline::class)))
            ->not->toThrow(Exception::class);
    });
});

describe('ExpirePutAsides', function () {
    it('is a safe no-op while the put_aside source is deferred', function () {
        Queue::fake();

        expect(fn () => (new ExpirePutAsides)->handle())->not->toThrow(Exception::class);
    });
});
