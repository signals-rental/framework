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
use Thunk\Verbs\Lifecycle\Broker;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-18T00:00:00Z'));
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('logs a DemandReleased event and dispatches a recompute when a demand is deleted', function () {
    Queue::fake();

    $product = Product::factory()->bulk()->create();

    $demand = Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-06-20T00:00:00Z'), Carbon::parse('2026-06-21T00:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 2,
        ]);

    // The create above already logged DemandCreated; clear the slate so we assert
    // only what the delete produces.
    AvailabilityEvent::query()->delete();
    $demandId = $demand->id;

    $demand->delete();

    // deleted() appends a DemandReleased event for the now-removed demand...
    $released = AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::DemandReleased->value)
        ->where('demand_id', $demandId)
        ->first();

    expect($released)->not->toBeNull()
        ->and((int) $released->product_id)->toBe($product->id)
        ->and((int) $released->store_id)->toBe($this->store->id);

    // ...and enqueues a recompute for the affected product/store.
    Queue::assertPushed(
        RecalculateAvailabilityJob::class,
        fn (RecalculateAvailabilityJob $job): bool => $job->productId === $product->id
            && $job->storeId === $this->store->id,
    );
});

it('does not enqueue a recompute when a demand is written during a Verbs replay', function () {
    Queue::fake();

    $product = Product::factory()->bulk()->create();

    $broker = app(Broker::class);
    $broker->is_replaying = true;

    try {
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-06-20T00:00:00Z'), Carbon::parse('2026-06-21T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 2,
            ]);
    } finally {
        $broker->is_replaying = false;
    }

    // The observer's dispatch path is Verbs::isReplaying()-guarded, so no job is
    // enqueued for a demand written while the event store is replaying.
    Queue::assertNotPushed(RecalculateAvailabilityJob::class);
});
