<?php

use App\Enums\AvailabilityEventType;
use App\Enums\DemandPhase;
use App\Jobs\RecalculateAvailabilityJob;
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
    // Isolate the command: the demand observer's async recompute job is faked so
    // the only dispatches asserted are those the command itself enqueues.
    Queue::fake();

    Carbon::setTestNow(Carbon::parse('2026-06-18T12:00:00Z'));

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->product = Product::factory()->bulk()->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Create a demand with the given phase and window, suppressing the period column
 * concerns handled by the factory.
 */
function overdueDemand(int $productId, int $storeId, DemandPhase $phase, Carbon $startsAt, Carbon $endsAt): Demand
{
    return Demand::factory()
        ->phase($phase)
        ->window($startsAt, $endsAt)
        ->create([
            'product_id' => $productId,
            'store_id' => $storeId,
            'quantity' => 2,
        ]);
}

it('extends an overdue active demand to the sentinel and dispatches a recalc', function () {
    $demand = overdueDemand(
        $this->product->id,
        $this->store->id,
        DemandPhase::Operational,
        Carbon::parse('2026-06-10T08:00:00Z'),
        Carbon::parse('2026-06-15T17:00:00Z'), // ends 3 days before "now"
    );

    Queue::fake(); // reset the fake so we only count the command's dispatch

    $this->artisan('availability:detect-overdue-demands')->assertSuccessful();

    $demand->refresh();

    expect($demand->ends_at->equalTo(Demand::sentinel()))->toBeTrue()
        ->and($demand->is_active)->toBeTrue()
        ->and($demand->phase)->toBe(DemandPhase::Operational);

    // The overdue extension is logged.
    $event = AvailabilityEvent::query()
        ->ofType(AvailabilityEventType::DemandOverdue)
        ->where('demand_id', $demand->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload['extended_to'])->toBe(Demand::SENTINEL_DATE);

    // And a recompute is queued for the affected product/store.
    Queue::assertPushed(RecalculateAvailabilityJob::class, function (RecalculateAvailabilityJob $job) {
        return $job->productId === $this->product->id && $job->storeId === $this->store->id;
    });
});

it('moves the buffered window to the sentinel so an overdue unit stays UNAVAILABLE for future slots', function () {
    // Regression (R2 FIX 1): extending an overdue demand must also push
    // buffered_ends_at to the sentinel. Otherwise per-slot attribution gates on
    // COALESCE(buffered_ends_at, ends_at) and frees the still-out unit for future
    // bookings.
    StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 1,
    ]);

    // A buffered demand: raw ends 2026-06-15, buffered (turnaround) ends one day
    // later — BOTH before "now" (2026-06-18), so it is overdue and unreturned.
    $demand = Demand::factory()
        ->phase(DemandPhase::Operational)
        ->buffered(
            Carbon::parse('2026-06-10T08:00:00Z'),
            Carbon::parse('2026-06-15T17:00:00Z'),
            Carbon::parse('2026-06-10T08:00:00Z'),
            Carbon::parse('2026-06-16T17:00:00Z'),
        )
        ->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
        ]);

    Queue::fake();

    $this->artisan('availability:detect-overdue-demands')->assertSuccessful();

    $demand->refresh();

    // (a) Both the raw AND buffered end are now the sentinel.
    expect($demand->ends_at->equalTo(Demand::sentinel()))->toBeTrue()
        ->and($demand->buffered_ends_at->equalTo(Demand::sentinel()))->toBeTrue();

    // (b) A point read for a future slot (a week out) sees the unit still occupied,
    // so availability is zero — the held-over unit is NOT freed.
    $future = Carbon::parse('2026-06-25T12:00:00Z');
    $point = app(AvailabilityService::class)->getAvailability($this->product->id, $this->store->id, $future);

    expect($point->total_stock)->toBe(1)
        ->and($point->total_demanded)->toBe(1)
        ->and($point->available)->toBe(0);

    // (c) And the recalculation writes a future-slot snapshot showing 0 free.
    app(RecalculationPipeline::class)->recalculate(
        $this->product->id,
        $this->store->id,
        $future->copy()->startOfDay(),
        $future->copy()->addDay()->startOfDay(),
    );

    $snapshot = AvailabilitySnapshot::query()
        ->where('product_id', $this->product->id)
        ->where('store_id', $this->store->id)
        ->where('slot_start', $future->copy()->startOfDay())
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->available)->toBe(0);
});

it('leaves a returned (closed) demand alone', function () {
    $demand = overdueDemand(
        $this->product->id,
        $this->store->id,
        DemandPhase::Closed,
        Carbon::parse('2026-06-10T08:00:00Z'),
        Carbon::parse('2026-06-15T17:00:00Z'),
    );

    Queue::fake();

    $this->artisan('availability:detect-overdue-demands')->assertSuccessful();

    $demand->refresh();

    // A Closed demand is inactive (returned) — it must not be extended.
    expect($demand->ends_at->equalTo(Demand::sentinel()))->toBeFalse()
        ->and($demand->ends_at->toDateString())->toBe('2026-06-15');

    expect(AvailabilityEvent::query()->ofType(AvailabilityEventType::DemandOverdue)->count())->toBe(0);

    Queue::assertNotPushed(RecalculateAvailabilityJob::class);
});

it('leaves a future-dated active demand alone', function () {
    $demand = overdueDemand(
        $this->product->id,
        $this->store->id,
        DemandPhase::Committed,
        Carbon::parse('2026-07-01T08:00:00Z'),
        Carbon::parse('2026-07-05T17:00:00Z'), // ends in the future
    );

    Queue::fake();

    $this->artisan('availability:detect-overdue-demands')->assertSuccessful();

    $demand->refresh();

    expect($demand->ends_at->toDateString())->toBe('2026-07-05');
    expect(AvailabilityEvent::query()->ofType(AvailabilityEventType::DemandOverdue)->count())->toBe(0);
});

it('leaves an already-indefinite (sentinel) demand alone', function () {
    overdueDemand(
        $this->product->id,
        $this->store->id,
        DemandPhase::Operational,
        Carbon::parse('2026-06-10T08:00:00Z'),
        Demand::sentinel(),
    );

    Queue::fake();

    $this->artisan('availability:detect-overdue-demands')->assertSuccessful();

    // No double extension: a sentinel-dated demand is excluded by definite().
    expect(AvailabilityEvent::query()->ofType(AvailabilityEventType::DemandOverdue)->count())->toBe(0);
    Queue::assertNotPushed(RecalculateAvailabilityJob::class);
});

it('is idempotent across repeated runs', function () {
    overdueDemand(
        $this->product->id,
        $this->store->id,
        DemandPhase::Operational,
        Carbon::parse('2026-06-10T08:00:00Z'),
        Carbon::parse('2026-06-15T17:00:00Z'),
    );

    Queue::fake();

    $this->artisan('availability:detect-overdue-demands')->assertSuccessful();
    $this->artisan('availability:detect-overdue-demands')->assertSuccessful();

    // The first run extended it to the sentinel; the second finds nothing to do,
    // so exactly one overdue event was ever written.
    expect(AvailabilityEvent::query()->ofType(AvailabilityEventType::DemandOverdue)->count())->toBe(1);
});

it('reports when there is nothing overdue', function () {
    $this->artisan('availability:detect-overdue-demands')
        ->expectsOutputToContain('No overdue demands found.')
        ->assertSuccessful();
});

it('de-duplicates recalc dispatches per product/store', function () {
    // Two overdue demands for the SAME product/store → a single recalc dispatch.
    overdueDemand($this->product->id, $this->store->id, DemandPhase::Operational, Carbon::parse('2026-06-10T08:00:00Z'), Carbon::parse('2026-06-12T17:00:00Z'));
    overdueDemand($this->product->id, $this->store->id, DemandPhase::Committed, Carbon::parse('2026-06-11T08:00:00Z'), Carbon::parse('2026-06-14T17:00:00Z'));

    Queue::fake();

    $this->artisan('availability:detect-overdue-demands')->assertSuccessful();

    expect(AvailabilityEvent::query()->ofType(AvailabilityEventType::DemandOverdue)->count())->toBe(2);
    Queue::assertPushed(RecalculateAvailabilityJob::class, 1);
});
