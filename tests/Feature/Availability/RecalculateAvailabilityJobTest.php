<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Events\Availability\AvailabilityChanged;
use App\Jobs\RecalculateAvailabilityJob;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    Carbon::setTestNow(Carbon::parse('2026-06-18T00:00:00Z'));

    // Stock recalc is suppressed by default in the suite; re-enable so the
    // stock observer enqueues the job in the stock-dispatch test below.
    config(['availability.suppress_stock_recalc' => false]);

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('dispatch from observers', function () {
    it('is dispatched onto the availability queue when a demand changes', function () {
        Queue::fake();

        $product = Product::factory()->bulk()->create();

        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-06-20T00:00:00Z'), Carbon::parse('2026-06-21T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 2,
            ]);

        Queue::assertPushedOn('availability', RecalculateAvailabilityJob::class, function (RecalculateAvailabilityJob $job) use ($product) {
            return $job->productId === $product->id && $job->storeId === $this->store->id;
        });
    });

    it('is dispatched when stock changes', function () {
        Queue::fake();

        $product = Product::factory()->bulk()->create();

        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 5,
        ]);

        Queue::assertPushed(RecalculateAvailabilityJob::class, function (RecalculateAvailabilityJob $job) use ($product) {
            return $job->productId === $product->id && $job->storeId === $this->store->id;
        });
    });

    it('is NOT dispatched during a Verbs replay', function () {
        Queue::fake();

        $product = Product::factory()->bulk()->create();

        // A real demand write outside replay enqueues exactly one recompute.
        $demand = Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-06-20T00:00:00Z'), Carbon::parse('2026-06-21T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 2,
            ]);

        Queue::assertPushed(RecalculateAvailabilityJob::class, 1);

        // The observer's dispatch path short-circuits while Verbs is replaying,
        // so a demand write performed during a replay enqueues nothing further.
        Verbs::replay(beforeEach: function () use ($demand): void {
            $demand->update(['quantity' => 9]);
        });

        Queue::assertPushed(RecalculateAvailabilityJob::class, 1);
    });
});

describe('debounce / uniqueness', function () {
    it('exposes a per-product/store unique id and a debounce window', function () {
        $job = new RecalculateAvailabilityJob(7, 3);

        expect($job->uniqueId())->toBe('availability:7:3')
            ->and($job->uniqueFor())->toBeGreaterThanOrEqual(1);
    });

    it('coalesces a burst of dispatches for the same product/store into one job', function () {
        Queue::fake();

        // ShouldBeUnique consults the cache lock; the array cache store (suite
        // default) supports it. Two dispatches for the same product/store within
        // the debounce window collapse to a single queued job.
        RecalculateAvailabilityJob::dispatch(101, 5);
        RecalculateAvailabilityJob::dispatch(101, 5);

        Queue::assertPushed(RecalculateAvailabilityJob::class, 1);

        // A different product/store is a distinct unique id and is not coalesced.
        RecalculateAvailabilityJob::dispatch(102, 5);

        Queue::assertPushed(RecalculateAvailabilityJob::class, 2);
    });

    it('serialises concurrent runs with a WithoutOverlapping middleware keyed on product/store', function () {
        $job = new RecalculateAvailabilityJob(7, 3);

        $middleware = collect($job->middleware());

        $overlapping = $middleware->first(
            fn (object $m): bool => $m instanceof WithoutOverlapping,
        );

        expect($overlapping)->not->toBeNull();

        // The lock key embeds the product/store so distinct pairs don't block.
        $reflection = new ReflectionProperty(WithoutOverlapping::class, 'key');
        expect($reflection->getValue($overlapping))->toBe('7:3');
    });
});

describe('handle', function () {
    it('recomputes snapshots over the rolling horizon for the product/store', function () {
        Queue::fake();

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

        // Queue::fake() above suppressed the observer's job; run it explicitly.
        expect(AvailabilitySnapshot::query()->where('product_id', $product->id)->count())->toBe(0);

        (new RecalculateAvailabilityJob($product->id, $this->store->id))->handle(
            app(RecalculationPipeline::class)
        );

        $demandedSlot = AvailabilitySnapshot::query()
            ->forProductStore($product->id, $this->store->id)
            ->where('slot_start', Carbon::parse('2026-06-20T00:00:00Z'))
            ->first();

        expect($demandedSlot)->not->toBeNull()
            ->and($demandedSlot->total_stock)->toBe(10)
            ->and($demandedSlot->total_demanded)->toBe(4)
            ->and($demandedSlot->available)->toBe(6);
    });

    it('bounds the recompute window to the snapshot horizon read from settings', function () {
        Queue::fake();

        // Tighten the future horizon to 5 days (default is 365). Now is pinned to
        // 2026-06-18 by the suite's beforeEach.
        settings()->set('availability.snapshot_horizon_future_days', 5, 'integer');
        settings()->set('availability.snapshot_horizon_past_days', 5, 'integer');

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        // A demand 30 days out — inside the default 365-day horizon but OUTSIDE
        // the tightened 5-day window.
        $farSlot = Carbon::parse('2026-07-18T00:00:00Z');
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window($farSlot, $farSlot->copy()->addDay())
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 2,
            ]);

        (new RecalculateAvailabilityJob($product->id, $this->store->id))->handle(
            app(RecalculationPipeline::class)
        );

        // The far slot lies beyond the settings-driven horizon, so no snapshot is
        // materialised for it — proving handle() honours the overridden setting.
        $far = AvailabilitySnapshot::query()
            ->forProductStore($product->id, $this->store->id)
            ->where('slot_start', $farSlot)
            ->first();

        // ...but a slot inside the 5-day window is materialised.
        $nearSlot = Carbon::parse('2026-06-20T00:00:00Z');
        $near = AvailabilitySnapshot::query()
            ->forProductStore($product->id, $this->store->id)
            ->where('slot_start', $nearSlot)
            ->first();

        expect($far)->toBeNull()
            ->and($near)->not->toBeNull();
    });

    it('broadcasts AvailabilityChanged on the product/store, store, and shortages channels after a recompute', function () {
        Queue::fake();
        Event::fake([AvailabilityChanged::class]);

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 3,
        ]);

        (new RecalculateAvailabilityJob($product->id, $this->store->id))->handle(
            app(RecalculationPipeline::class)
        );

        Event::assertDispatched(AvailabilityChanged::class, function (AvailabilityChanged $event) use ($product) {
            $names = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();

            return $event->productId === $product->id
                && $event->storeId === $this->store->id
                && $event->slots > 0
                && $event->hasShortage === false
                && in_array('private-availability.product.'.$product->id.'.store.'.$this->store->id, $names, true)
                && in_array('private-availability.store.'.$this->store->id, $names, true)
                && in_array('private-availability.shortages', $names, true);
        });
    });

    it('flags has_shortage on the broadcast and payload when a slot goes negative', function () {
        Queue::fake();
        Event::fake([AvailabilityChanged::class]);

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 1,
        ]);

        // 4 units demanded against 1 in stock → the slot dips below zero.
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-06-20T00:00:00Z'), Carbon::parse('2026-06-21T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 4,
            ]);

        (new RecalculateAvailabilityJob($product->id, $this->store->id))->handle(
            app(RecalculationPipeline::class)
        );

        Event::assertDispatched(AvailabilityChanged::class, function (AvailabilityChanged $event): bool {
            return $event->hasShortage === true
                && ($event->broadcastWith()['has_shortage'] ?? null) === true;
        });
    });

    it('does not broadcast when the product does not track availability', function () {
        Queue::fake();
        Event::fake([AvailabilityChanged::class]);

        $product = Product::factory()->bulk()->notTracked()->create();

        (new RecalculateAvailabilityJob($product->id, $this->store->id))->handle(
            app(RecalculationPipeline::class)
        );

        Event::assertNotDispatched(AvailabilityChanged::class);
    });
});
