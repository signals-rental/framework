<?php

use App\Enums\AvailabilityEventType;
use App\Enums\DemandPhase;
use App\Enums\WaitlistMonitorStatus;
use App\Events\Availability\AvailabilityChanged;
use App\Listeners\Availability\DetectOrderShortages;
use App\Listeners\Availability\MatchWaitlistMonitors;
use App\Models\AvailabilityEvent;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ShortageWaitlistMonitor;
use App\Models\StockLevel;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Lifecycle\Broker;

/**
 * Force the Verbs broker into its replaying state for the body of the callback,
 * then restore it — so a listener's `Verbs::isReplaying()` guard takes the
 * replay branch deterministically without standing up a full event replay.
 */
function whileReplaying(Closure $callback): void
{
    $broker = app(Broker::class);
    $broker->is_replaying = true;

    try {
        $callback();
    } finally {
        $broker->is_replaying = false;
    }
}

it('DetectOrderShortages short-circuits during a Verbs replay and emits no shortage event', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();

    // Only 2 units of stock against a confirmed order needing 4 — outside replay
    // this would emit a shortage.detected. Inside replay it must be skipped.
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 2,
    ]);

    $opportunity = Opportunity::factory()->order()->create([
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
    ]);
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'name' => $product->name,
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => 4,
    ]);
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => $item->id,
            'metadata' => [],
        ]);

    // The factory writes above may have fanned out a synchronous recompute that
    // emitted its own product/store shortage event; clear the log so we assert
    // strictly on what the listener does (or, under replay, does NOT do).
    AvailabilityEvent::query()->delete();

    whileReplaying(function () use ($product, $store): void {
        app(DetectOrderShortages::class)->handle(new AvailabilityChanged($product->id, $store->id));
    });

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageDetected->value)
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->exists())->toBeFalse();
});

it('MatchWaitlistMonitors short-circuits during a Verbs replay and leaves the monitor active', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->bulk()->create();

    // Plenty of stock so the monitor would normally match and flip to Matched.
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 50,
    ]);

    $monitor = ShortageWaitlistMonitor::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'status' => WaitlistMonitorStatus::Active->value,
        'quantity_needed' => 1,
        'starts_at' => Carbon::parse('2026-07-01T00:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-02T00:00:00Z'),
        'matched_at' => null,
    ]);

    whileReplaying(function () use ($product, $store): void {
        app(MatchWaitlistMonitors::class)->handle(new AvailabilityChanged($product->id, $store->id));
    });

    $monitor->refresh();

    expect($monitor->status->value)->toBe(WaitlistMonitorStatus::Active->value)
        ->and($monitor->matched_at)->toBeNull();
});
