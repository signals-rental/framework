<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Drive the pipeline directly over a narrow window; fake the queue so the
    // demand observer's async whole-horizon recompute does not run.
    Queue::fake();

    app()->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->product = Product::factory()->serialised()->create();
    StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);
});

describe('pending_checkin_quantity snapshot population', function () {
    it('counts closed, returned-not-checked demands without reducing availability', function () {
        $from = Carbon::parse('2026-07-01T00:00:00Z');
        $to = Carbon::parse('2026-07-03T00:00:00Z');

        // A Closed (inactive) demand marked pending check-in: physically returned,
        // not yet inspected. It must NOT reduce availability, but must be counted.
        Demand::factory()
            ->serialised()
            ->phase(DemandPhase::Closed)
            ->window($from, $to)
            ->create([
                'product_id' => $this->product->id,
                'store_id' => $this->store->id,
                'metadata' => ['pending_checkin' => true],
            ]);

        app(RecalculationPipeline::class)->recalculate($this->product->id, $this->store->id, $from, $to);

        $snapshot = AvailabilitySnapshot::query()
            ->where('product_id', $this->product->id)
            ->where('store_id', $this->store->id)
            ->where('slot_start', $from)
            ->firstOrFail();

        expect($snapshot->pending_checkin_quantity)->toBe(1)
            // The closed demand does not consume the unit — still fully available.
            ->and($snapshot->available)->toBe(1)
            ->and($snapshot->total_demanded)->toBe(0)
            ->and($snapshot->demand_breakdown['returned_not_checked'] ?? null)->toBe(1);
    });

    it('leaves pending_checkin_quantity at zero when nothing is in the check-in queue', function () {
        $from = Carbon::parse('2026-07-01T00:00:00Z');
        $to = Carbon::parse('2026-07-02T00:00:00Z');

        Demand::factory()
            ->serialised()
            ->phase(DemandPhase::Committed)
            ->window($from, $to)
            ->create([
                'product_id' => $this->product->id,
                'store_id' => $this->store->id,
            ]);

        app(RecalculationPipeline::class)->recalculate($this->product->id, $this->store->id, $from, $to);

        $snapshot = AvailabilitySnapshot::query()
            ->where('product_id', $this->product->id)
            ->where('store_id', $this->store->id)
            ->where('slot_start', $from)
            ->firstOrFail();

        expect($snapshot->pending_checkin_quantity)->toBe(0)
            ->and($snapshot->demand_breakdown)->not->toHaveKey('returned_not_checked');
    });
});
