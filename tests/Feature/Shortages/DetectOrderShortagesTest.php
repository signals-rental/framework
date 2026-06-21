<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityEventType;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Events\Availability\AvailabilityChanged;
use App\Listeners\Availability\DetectOrderShortages;
use App\Models\AvailabilityEvent;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->listener = app(DetectOrderShortages::class);
});

/**
 * A confirmed-order line item with its own active demand on the product/store, so
 * the proactive listener can re-check it when the product/store changes.
 */
function confirmedOrderItem(Store $store, Product $product, int $quantity): OpportunityItem
{
    $opportunity = Opportunity::factory()->order()->create([
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
    ]);

    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'name' => $product->name,
        'item_type' => Product::class,
        'item_id' => $product->id,
        'quantity' => $quantity,
    ]);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => $quantity,
            'source_type' => 'opportunity_item',
            'source_id' => $item->id,
            'metadata' => [],
        ]);

    return $item;
}

it('emits shortage.detected at opportunity-item scope when a confirmed order becomes short', function () {
    $product = Product::factory()->rental()->bulk()->create();

    // Only 2 units of stock; the confirmed order needs 4.
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 2,
    ]);

    $item = confirmedOrderItem($this->store, $product, 4);

    $this->listener->handle(new AvailabilityChanged($product->id, $this->store->id));

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageDetected->value)
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->exists())->toBeTrue();
});

it('does not re-emit for a standing shortage already open in the log', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 2,
    ]);
    $item = confirmedOrderItem($this->store, $product, 4);

    $this->listener->handle(new AvailabilityChanged($product->id, $this->store->id));
    $this->listener->handle(new AvailabilityChanged($product->id, $this->store->id));

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageDetected->value)
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->count())->toBe(1);
});

it('ignores quote-state opportunities (only confirmed orders are proactively monitored)', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 2,
    ]);

    $opportunity = Opportunity::factory()->quotation()->create([
        'store_id' => $this->store->id,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
    ]);
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'name' => $product->name,
        'item_type' => Product::class,
        'item_id' => $product->id,
        'quantity' => 4,
    ]);
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => $item->id,
            'metadata' => [],
        ]);

    $this->listener->handle(new AvailabilityChanged($product->id, $this->store->id));

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageDetected->value)
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->exists())->toBeFalse();
});

it('excludes a non-Order opportunity item at the DB level even when it shares the product/store with an Order', function () {
    $product = Product::factory()->rental()->bulk()->create();
    // Only 2 units of stock; both lines below want 4, so each would be short.
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 2,
    ]);

    // An Order line — this one MUST be detected.
    $orderItem = confirmedOrderItem($this->store, $product, 4);

    // A Quotation line on the SAME product/store — this one must be excluded by
    // the DB-level state filter, never reaching the per-item shortage check.
    $quote = Opportunity::factory()->quotation()->create([
        'store_id' => $this->store->id,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
    ]);
    $quoteItem = OpportunityItem::factory()->create([
        'opportunity_id' => $quote->id,
        'name' => $product->name,
        'item_type' => Product::class,
        'item_id' => $product->id,
        'quantity' => 4,
    ]);
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => $quoteItem->id,
            'metadata' => [],
        ]);

    $this->listener->handle(new AvailabilityChanged($product->id, $this->store->id));

    // The Order line is detected.
    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageDetected->value)
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $orderItem->id)
        ->exists())->toBeTrue()
        // The quote line is NOT — excluded by the DB state filter.
        ->and(AvailabilityEvent::query()
            ->where('event_type', AvailabilityEventType::ShortageDetected->value)
            ->where('source_type', 'opportunity_item')
            ->where('source_id', $quoteItem->id)
            ->exists())->toBeFalse();
});

it('emits nothing when no demands reference the changed product/store', function () {
    $product = Product::factory()->rental()->bulk()->create();

    $this->listener->handle(new AvailabilityChanged($product->id, $this->store->id));

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageDetected->value)
        ->where('source_type', 'opportunity_item')
        ->exists())->toBeFalse();
});
