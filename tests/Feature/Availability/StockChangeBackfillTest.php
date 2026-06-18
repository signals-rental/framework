<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityEventType;
use App\Enums\AvailabilityResolution;
use App\Models\AvailabilityEvent;
use App\Models\AvailabilitySnapshot;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Stock-change recalculation is suppressed by default in the suite (see
    // phpunit.xml). Re-enable it here so the StockLevelObserver actually runs the
    // synchronous backfill these tests exercise.
    config(['availability.suppress_stock_recalc' => false]);

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    Carbon::setTestNow(Carbon::parse('2026-06-18T00:00:00Z'));

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->service = app(AvailabilityService::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('materialises snapshots for a stocked-but-un-demanded product on stock create', function () {
    $product = Product::factory()->bulk()->create();

    // Creating stock alone (no demand) must now seed snapshots — closing the
    // M2-review gap where getAvailabilityRange returned empty for such products.
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 12,
    ]);

    $range = $this->service->getAvailabilityRange(
        $product->id,
        $this->store->id,
        Carbon::parse('2026-06-18T00:00:00Z'),
        Carbon::parse('2026-06-21T00:00:00Z'),
    );

    expect($range->slots)->not->toBeEmpty();
    expect($range->slots[0]->total_stock)->toBe(12)
        ->and($range->slots[0]->available)->toBe(12);
});

it('logs a stock_changed event on stock create', function () {
    $product = Product::factory()->bulk()->create();

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    $event = AvailabilityEvent::query()
        ->ofType(AvailabilityEventType::StockChanged)
        ->where('product_id', $product->id)
        ->latest('id')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload['change'])->toBe('created')
        ->and((float) $event->payload['quantity_held'])->toBe(5.0);
});

it('refreshes snapshots when on-hand quantity changes', function () {
    $product = Product::factory()->bulk()->create();
    $stock = StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    $stock->update(['quantity_held' => 3]);

    $snapshot = AvailabilitySnapshot::query()
        ->forProductStore($product->id, $this->store->id)
        ->orderBy('slot_start')
        ->first();

    expect($snapshot->total_stock)->toBe(3)
        ->and($snapshot->available)->toBe(3);
});

it('does not recalculate when a non-quantity field changes', function () {
    $product = Product::factory()->bulk()->create();
    $stock = StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    $eventsBefore = AvailabilityEvent::query()->ofType(AvailabilityEventType::StockChanged)->count();

    // A purely cosmetic change must not trigger a recalc nor a stock_changed event.
    $stock->update(['location' => 'Bay 7']);

    expect(AvailabilityEvent::query()->ofType(AvailabilityEventType::StockChanged)->count())
        ->toBe($eventsBefore);
});

it('skips recalculation for products that do not track availability but still logs the event', function () {
    $product = Product::factory()->bulk()->notTracked()->create();

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 8,
    ]);

    expect(AvailabilitySnapshot::query()->where('product_id', $product->id)->count())->toBe(0);

    // The audit event is still recorded even though no snapshots were refreshed.
    expect(AvailabilityEvent::query()
        ->ofType(AvailabilityEventType::StockChanged)
        ->where('product_id', $product->id)
        ->count())->toBe(1);
});

it('refreshes snapshots when stock deactivates a demand basis on delete', function () {
    $product = Product::factory()->bulk()->create();
    $stock = StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 6,
    ]);

    $stock->delete();

    $snapshot = AvailabilitySnapshot::query()
        ->forProductStore($product->id, $this->store->id)
        ->orderBy('slot_start')
        ->first();

    expect($snapshot->total_stock)->toBe(0)
        ->and($snapshot->available)->toBe(0);
});

it('honours the suppress_stock_recalc guard for bulk seeding', function () {
    config(['availability.suppress_stock_recalc' => true]);

    $product = Product::factory()->bulk()->create();

    StockLevel::factory()->bulk()->count(3)->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    // No snapshots materialised while suppressed...
    expect(AvailabilitySnapshot::query()->where('product_id', $product->id)->count())->toBe(0);

    // ...but the stock_changed audit events are still recorded.
    expect(AvailabilityEvent::query()
        ->ofType(AvailabilityEventType::StockChanged)
        ->where('product_id', $product->id)
        ->count())->toBe(3);
});
