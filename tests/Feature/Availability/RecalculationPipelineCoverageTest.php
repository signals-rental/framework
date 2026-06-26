<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
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

it('returns the configured full horizon window', function () {
    settings()->set('availability.snapshot_horizon_past_days', 30, 'integer');
    settings()->set('availability.snapshot_horizon_future_days', 60, 'integer');

    Carbon::setTestNow(Carbon::parse('2026-06-01T12:00:00Z'));

    [$from, $to] = $this->pipeline->fullHorizon();

    expect($from->toDateString())->toBe('2026-05-02')
        ->and($to->toDateString())->toBe('2026-07-31');

    Carbon::setTestNow();
});

it('skips recalculation when the clamped window lies entirely outside the horizon', function () {
    settings()->set('availability.snapshot_horizon_past_days', 7, 'integer');
    settings()->set('availability.snapshot_horizon_future_days', 7, 'integer');

    Carbon::setTestNow(Carbon::parse('2026-06-01T00:00:00Z'));

    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 4,
    ]);

    $result = $this->pipeline->recalculate(
        $product->id,
        $this->store->id,
        Carbon::parse('2020-01-01T00:00:00Z'),
        Carbon::parse('2020-01-03T00:00:00Z'),
    );

    expect($result->slots)->toBe(0);

    Carbon::setTestNow();
});

it('memoises store timezones within one pipeline instance', function () {
    $this->store->update(['timezone' => 'Europe/London']);

    expect($this->pipeline->storeTimezone($this->store->id))->toBe('Europe/London')
        ->and($this->pipeline->storeTimezone($this->store->id))->toBe('Europe/London');
});

it('sums mixed bulk and serialised stock in totalStock', function () {
    $product = Product::factory()->bulk()->create();

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 4.5,
    ]);
    StockLevel::factory()->serialised()->count(2)->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    expect($this->pipeline->totalStock($product, $this->store->id))->toBe(7);
});

it('ignores zero-quantity demands when summing a slot', function () {
    $slotStart = Carbon::parse('2026-05-01T00:00:00Z');
    $slotEnd = Carbon::parse('2026-05-02T00:00:00Z');

    $active = Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($slotStart, $slotEnd)
        ->create(['quantity' => 2]);

    $returned = Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($slotStart, $slotEnd)
        ->create([
            'quantity' => 5,
            'metadata' => ['returned_quantity' => 5],
        ]);

    [$total, $breakdown] = $this->pipeline->sumDemandForSlot(collect([$active, $returned]), $slotStart, $slotEnd);

    expect($total)->toBe(2)
        ->and($breakdown)->toBe(['opportunity_item' => 2]);
});

it('uses buffered bounds when deciding slot overlap', function () {
    $slotStart = Carbon::parse('2026-05-01T00:00:00Z');
    $slotEnd = Carbon::parse('2026-05-02T00:00:00Z');

    $demand = Demand::factory()->make([
        'quantity' => 1,
        'starts_at' => Carbon::parse('2026-05-02T08:00:00Z'),
        'ends_at' => Carbon::parse('2026-05-04T00:00:00Z'),
        'buffered_starts_at' => Carbon::parse('2026-05-01T20:00:00Z'),
        'buffered_ends_at' => Carbon::parse('2026-05-04T00:00:00Z'),
        'phase' => DemandPhase::Committed->value,
        'is_active' => true,
    ]);

    [$inside] = $this->pipeline->sumDemandForSlot([$demand], $slotStart, $slotEnd);
    [$outside] = $this->pipeline->sumDemandForSlot([$demand], Carbon::parse('2026-04-28T00:00:00Z'), Carbon::parse('2026-04-29T00:00:00Z'));

    expect($inside)->toBe(1)
        ->and($outside)->toBe(0);
});

it('clamps open-ended windows to the rolling horizon', function () {
    settings()->set('availability.snapshot_horizon_past_days', 10, 'integer');
    settings()->set('availability.snapshot_horizon_future_days', 10, 'integer');

    Carbon::setTestNow(Carbon::parse('2026-06-01T00:00:00Z'));

    [$from, $to] = $this->pipeline->clampToHorizon(
        Carbon::parse('2010-01-01T00:00:00Z'),
        Demand::sentinel(),
    );

    expect($from->greaterThanOrEqualTo(Carbon::parse('2026-05-22T00:00:00Z')))->toBeTrue()
        ->and($to->lessThanOrEqualTo(Carbon::parse('2026-06-11T23:59:59Z')))->toBeTrue();

    Carbon::setTestNow();
});
