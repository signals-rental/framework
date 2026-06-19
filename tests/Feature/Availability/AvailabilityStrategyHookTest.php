<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Contracts\Availability\AvailabilityStrategyContract;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\PassThroughAvailabilityStrategy;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    Queue::fake();

    $this->product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);
});

it('binds a pass-through strategy by default', function () {
    expect(app(AvailabilityStrategyContract::class))
        ->toBeInstanceOf(PassThroughAvailabilityStrategy::class);
});

it('produces identical snapshots with the pass-through strategy as without a hook', function () {
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-05-01T00:00:00Z'), Carbon::parse('2026-05-03T00:00:00Z'))
        ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id, 'quantity' => 4]);

    app(RecalculationPipeline::class)->recalculate(
        $this->product->id,
        $this->store->id,
        Carbon::parse('2026-05-01T00:00:00Z'),
        Carbon::parse('2026-05-04T00:00:00Z'),
    );

    $snapshot = AvailabilitySnapshot::query()
        ->where('product_id', $this->product->id)
        ->where('slot_start', Carbon::parse('2026-05-01T00:00:00Z'))
        ->firstOrFail();

    // Pass-through: available = 10 stock - 4 demand.
    expect($snapshot->total_stock)->toBe(10)
        ->and($snapshot->total_demanded)->toBe(4)
        ->and($snapshot->available)->toBe(6);
});

it('invokes preCalculation so a strategy can drop demands before summing', function () {
    // A strategy that drops every demand: availability should equal full stock.
    $this->app->bind(AvailabilityStrategyContract::class, fn () => new class implements AvailabilityStrategyContract
    {
        public function preCalculation(int $productId, int $storeId, Carbon $rangeStart, Carbon $rangeEnd, Collection $demands): Collection
        {
            return new Collection;
        }

        public function postCalculation(int $productId, int $storeId, Carbon $rangeStart, Carbon $rangeEnd, Collection $slotResults): Collection
        {
            return $slotResults;
        }
    });

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-05-01T00:00:00Z'), Carbon::parse('2026-05-03T00:00:00Z'))
        ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id, 'quantity' => 4]);

    app(RecalculationPipeline::class)->recalculate(
        $this->product->id,
        $this->store->id,
        Carbon::parse('2026-05-01T00:00:00Z'),
        Carbon::parse('2026-05-04T00:00:00Z'),
    );

    $snapshot = AvailabilitySnapshot::query()
        ->where('product_id', $this->product->id)
        ->where('slot_start', Carbon::parse('2026-05-01T00:00:00Z'))
        ->firstOrFail();

    expect($snapshot->total_demanded)->toBe(0)
        ->and($snapshot->available)->toBe(10);
});

it('invokes postCalculation so a strategy can floor the final availability', function () {
    // A strategy that floors `available` at zero (no negative shortages shown).
    $this->app->bind(AvailabilityStrategyContract::class, fn () => new class implements AvailabilityStrategyContract
    {
        public function preCalculation(int $productId, int $storeId, Carbon $rangeStart, Carbon $rangeEnd, Collection $demands): Collection
        {
            return $demands;
        }

        public function postCalculation(int $productId, int $storeId, Carbon $rangeStart, Carbon $rangeEnd, Collection $slotResults): Collection
        {
            return $slotResults->map(static function (array $result): array {
                $result['available'] = max(0, $result['available']);

                return $result;
            });
        }
    });

    // Over-demand: 14 demanded against 10 stock would be -4 without the floor.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-05-01T00:00:00Z'), Carbon::parse('2026-05-03T00:00:00Z'))
        ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id, 'quantity' => 14]);

    app(RecalculationPipeline::class)->recalculate(
        $this->product->id,
        $this->store->id,
        Carbon::parse('2026-05-01T00:00:00Z'),
        Carbon::parse('2026-05-04T00:00:00Z'),
    );

    $snapshot = AvailabilitySnapshot::query()
        ->where('product_id', $this->product->id)
        ->where('slot_start', Carbon::parse('2026-05-01T00:00:00Z'))
        ->firstOrFail();

    // total_demanded is unchanged (14); available is floored to 0 by the hook.
    expect($snapshot->total_demanded)->toBe(14)
        ->and($snapshot->available)->toBe(0);
});
