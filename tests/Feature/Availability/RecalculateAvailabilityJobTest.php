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

    it('broadcasts AvailabilityChanged on the per-store private channel after a recompute', function () {
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
            $channels = $event->broadcastOn();

            return $event->productId === $product->id
                && $event->storeId === $this->store->id
                && $event->slots > 0
                && $channels[0]->name === 'private-availability.store.'.$this->store->id;
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
