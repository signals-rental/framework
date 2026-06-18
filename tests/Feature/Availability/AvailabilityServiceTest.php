<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Pin the resolution to Daily so slot boundaries are deterministic.
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->service = app(AvailabilityService::class);
});

/**
 * Create an active demand without going through the (auto-recalculating) factory
 * defaults, so the test controls product/store/window precisely.
 *
 * @param  array<string, mixed>  $metadata
 */
function makeDemand(int $productId, int $storeId, int $quantity, string $start, string $end, DemandPhase $phase = DemandPhase::Committed, array $metadata = []): Demand
{
    return Demand::factory()
        ->phase($phase)
        ->window(Carbon::parse($start), Carbon::parse($end))
        ->create([
            'product_id' => $productId,
            'store_id' => $storeId,
            'quantity' => $quantity,
            'metadata' => $metadata,
        ]);
}

describe('getAvailability (point, on-the-fly)', function () {
    it('subtracts active demand from bulk stock', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        makeDemand($product->id, $this->store->id, 3, '2026-04-10T09:00:00Z', '2026-04-12T17:00:00Z');

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-11T08:00:00Z'));

        expect($result->total_stock)->toBe(10)
            ->and($result->total_demanded)->toBe(3)
            ->and($result->available)->toBe(7)
            ->and($result->demand_breakdown)->toBe(['opportunity_item' => 3]);
    });

    it('counts serialised stock rows as one unit each', function () {
        $product = Product::factory()->serialised()->create();
        StockLevel::factory()->serialised()->count(4)->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-11T08:00:00Z'));

        expect($result->total_stock)->toBe(4)
            ->and($result->available)->toBe(4);
    });

    it('ignores inactive (Draft/Void) demands', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        makeDemand($product->id, $this->store->id, 4, '2026-04-10T00:00:00Z', '2026-04-12T00:00:00Z', DemandPhase::Draft);
        makeDemand($product->id, $this->store->id, 2, '2026-04-10T00:00:00Z', '2026-04-12T00:00:00Z', DemandPhase::Void);

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-11T08:00:00Z'));

        expect($result->total_demanded)->toBe(0)
            ->and($result->available)->toBe(10);
    });

    it('does not count demand outside the queried slot', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        // Demand on 10-11 April; query 20 April.
        makeDemand($product->id, $this->store->id, 5, '2026-04-10T00:00:00Z', '2026-04-11T00:00:00Z');

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-20T08:00:00Z'));

        expect($result->total_demanded)->toBe(0)
            ->and($result->available)->toBe(10);
    });

    it('nets bulk demand against returned_quantity', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        makeDemand($product->id, $this->store->id, 6, '2026-04-10T00:00:00Z', '2026-04-12T00:00:00Z', DemandPhase::Committed, ['returned_quantity' => 2]);

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-11T08:00:00Z'));

        expect($result->total_demanded)->toBe(4)
            ->and($result->available)->toBe(6);
    });

    it('can report a negative availability (shortage)', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 2,
        ]);

        makeDemand($product->id, $this->store->id, 5, '2026-04-10T00:00:00Z', '2026-04-12T00:00:00Z');

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-11T08:00:00Z'));

        expect($result->available)->toBe(-3);
    });
});

describe('checkAvailability', function () {
    beforeEach(function () {
        $this->product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 5,
        ]);
        makeDemand($this->product->id, $this->store->id, 2, '2026-04-10T00:00:00Z', '2026-04-13T00:00:00Z');
    });

    it('returns true when the requested quantity fits in every slot', function () {
        $ok = $this->service->checkAvailability(
            $this->product->id,
            $this->store->id,
            Carbon::parse('2026-04-10T00:00:00Z'),
            Carbon::parse('2026-04-13T00:00:00Z'),
            3,
        );

        expect($ok)->toBeTrue();
    });

    it('returns false when the requested quantity exceeds availability in a slot', function () {
        $ok = $this->service->checkAvailability(
            $this->product->id,
            $this->store->id,
            Carbon::parse('2026-04-10T00:00:00Z'),
            Carbon::parse('2026-04-13T00:00:00Z'),
            4,
        );

        expect($ok)->toBeFalse();
    });

    it('returns true at the exact availability boundary', function () {
        // 5 stock - 2 demanded = 3 available; requesting exactly 3 must pass.
        $ok = $this->service->checkAvailability(
            $this->product->id,
            $this->store->id,
            Carbon::parse('2026-04-10T00:00:00Z'),
            Carbon::parse('2026-04-13T00:00:00Z'),
            3,
        );

        expect($ok)->toBeTrue();
    });
});

describe('deferred methods', function () {
    it('throws for getShortages (deferred to M3)', function () {
        $this->service->getShortages($this->store->id, Carbon::now(), Carbon::now()->addDay());
    })->throws(BadMethodCallException::class);

    it('throws for getKitAvailability (deferred to M5)', function () {
        $this->service->getKitAvailability(1, $this->store->id, Carbon::now(), Carbon::now()->addDay());
    })->throws(BadMethodCallException::class);
});
