<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Availability\AvailabilityRangeData;
use App\Enums\AvailabilityResolution;
use App\Models\AvailabilitySnapshot;
use App\Models\Product;
use App\Models\Store;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->service = app(AvailabilityService::class);
    $this->product = Product::factory()->bulk()->create();
});

/** Seed a snapshot row directly so the range read path has data to return. */
function seedSnapshot(int $productId, int $storeId, string $slot, int $stock, int $demanded): void
{
    AvailabilitySnapshot::query()->create([
        'product_id' => $productId,
        'store_id' => $storeId,
        'slot_start' => Carbon::parse($slot),
        'total_stock' => $stock,
        'total_demanded' => $demanded,
        'available' => $stock - $demanded,
        'demand_breakdown' => [],
        'calculated_at' => Carbon::parse($slot),
    ]);
}

it('aggregates a product range across the given stores keyed by store id', function () {
    $a = Store::factory()->create(['timezone' => 'UTC']);
    $b = Store::factory()->create(['timezone' => 'UTC']);

    seedSnapshot($this->product->id, $a->id, '2026-05-01T00:00:00Z', 10, 3);
    seedSnapshot($this->product->id, $b->id, '2026-05-01T00:00:00Z', 5, 5);

    $result = $this->service->getAvailabilityAcrossStores(
        $this->product->id,
        [$a->id, $b->id],
        Carbon::parse('2026-05-01T00:00:00Z'),
        Carbon::parse('2026-05-02T00:00:00Z'),
    );

    expect($result)->toHaveKeys([$a->id, $b->id])
        ->and($result->get($a->id))->toBeInstanceOf(AvailabilityRangeData::class)
        ->and($result->get($a->id)->min_available)->toBe(7)
        ->and($result->get($b->id)->min_available)->toBe(0);
});

it('defaults to all default-query stores and excludes those flagged out', function () {
    $included = Store::factory()->create(['timezone' => 'UTC', 'include_in_default_queries' => true]);
    $excluded = Store::factory()->create(['timezone' => 'UTC', 'include_in_default_queries' => false]);

    seedSnapshot($this->product->id, $included->id, '2026-05-01T00:00:00Z', 10, 0);
    seedSnapshot($this->product->id, $excluded->id, '2026-05-01T00:00:00Z', 99, 0);

    $result = $this->service->getAvailabilityAcrossStores(
        $this->product->id,
        [],
        Carbon::parse('2026-05-01T00:00:00Z'),
        Carbon::parse('2026-05-02T00:00:00Z'),
    );

    expect($result->has($included->id))->toBeTrue()
        ->and($result->has($excluded->id))->toBeFalse();
});

it('still serves an excluded store when requested explicitly', function () {
    $excluded = Store::factory()->create(['timezone' => 'UTC', 'include_in_default_queries' => false]);

    seedSnapshot($this->product->id, $excluded->id, '2026-05-01T00:00:00Z', 4, 1);

    $result = $this->service->getAvailabilityAcrossStores(
        $this->product->id,
        [$excluded->id],
        Carbon::parse('2026-05-01T00:00:00Z'),
        Carbon::parse('2026-05-02T00:00:00Z'),
    );

    expect($result->has($excluded->id))->toBeTrue()
        ->and($result->get($excluded->id)->min_available)->toBe(3);
});
