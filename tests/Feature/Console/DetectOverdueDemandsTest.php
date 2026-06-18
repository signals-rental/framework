<?php

use App\Enums\AvailabilityEventType;
use App\Enums\DemandPhase;
use App\Jobs\RecalculateAvailabilityJob;
use App\Models\AvailabilityEvent;
use App\Models\Demand;
use App\Models\Product;
use App\Models\Store;
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
