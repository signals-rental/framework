<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityEventType;
use App\Enums\AvailabilityResolution;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Enums\StockMethod;
use App\Enums\WaitlistMonitorStatus;
use App\Events\Availability\AvailabilityChanged;
use App\Jobs\ExpireWaitlistMonitors;
use App\Listeners\Availability\MatchWaitlistMonitors;
use App\Models\AvailabilityEvent;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ShortageWaitlistMonitor;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\Resolvers\WaitlistResolver;
use App\Services\Shortages\ShortageEventRecorder;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\Shortage;
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
});

/**
 * A bulk shortage VO backed by real product/store/item rows.
 */
function waitlistShortage(Store $store, Product $product, int $shortfall = 2): Shortage
{
    $item = OpportunityItem::factory()->create([
        'item_type' => 'product',
        'itemable_id' => $product->id,
    ]);

    return Shortage::make(
        opportunityItemId: $item->id,
        opportunityId: $item->opportunity_id,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: $shortfall,
        availableQuantity: 0,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );
}

it('creates a monitor row and fires shortage.waitlist.created when applied', function () {
    $product = Product::factory()->rental()->bulk()->create();
    $shortage = waitlistShortage($this->store, $product, 2);

    $resolver = app(WaitlistResolver::class);
    $result = $resolver->apply($shortage, new ResolutionOption(
        resolverKey: 'waitlist',
        type: ShortageResolutionType::Waitlist,
        label: 'Add to waitlist',
        description: 'Monitor',
        quantityResolved: 2,
        isPartial: false,
        autoExecutable: false,
    ));

    $monitor = ShortageWaitlistMonitor::query()->first();

    expect($result->success)->toBeTrue()
        ->and($monitor)->not->toBeNull()
        ->and($monitor->status)->toBe(WaitlistMonitorStatus::Active)
        ->and($monitor->quantity_needed)->toBe(2)
        ->and($monitor->resolution->status)->toBe(ShortageResolutionStatus::Monitoring)
        ->and(AvailabilityEvent::query()
            ->where('event_type', AvailabilityEventType::WaitlistCreated->value)
            ->where('source_id', $monitor->id)
            ->exists())->toBeTrue();
});

it('flips an active monitor to matched and fires shortage.waitlist.matched when stock frees up', function () {
    $product = Product::factory()->rental()->bulk()->create();

    // Plenty of free stock so the availability check satisfies the monitor.
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    $monitor = ShortageWaitlistMonitor::factory()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_needed' => 2,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
        'status' => WaitlistMonitorStatus::Active->value,
    ]);

    app(MatchWaitlistMonitors::class)->handle(
        new AvailabilityChanged($product->id, $this->store->id)
    );

    $monitor->refresh();

    expect($monitor->status)->toBe(WaitlistMonitorStatus::Matched)
        ->and($monitor->matched_at)->not->toBeNull()
        ->and(AvailabilityEvent::query()
            ->where('event_type', AvailabilityEventType::WaitlistMatched->value)
            ->where('source_id', $monitor->id)
            ->exists())->toBeTrue();
});

it('does not match a monitor when there is still no free stock', function () {
    $product = Product::factory()->rental()->bulk()->create();

    // No stock at all.
    $monitor = ShortageWaitlistMonitor::factory()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_needed' => 5,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
        'status' => WaitlistMonitorStatus::Active->value,
    ]);

    app(MatchWaitlistMonitors::class)->handle(
        new AvailabilityChanged($product->id, $this->store->id)
    );

    expect($monitor->fresh()->status)->toBe(WaitlistMonitorStatus::Active);
});

it('expires an active monitor past its expiry and fires shortage.waitlist.expired', function () {
    $product = Product::factory()->rental()->bulk()->create();

    $monitor = ShortageWaitlistMonitor::factory()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'status' => WaitlistMonitorStatus::Active->value,
        'expires_at' => Carbon::now()->subDay(),
    ]);

    app(ExpireWaitlistMonitors::class)->handle(app(ShortageEventRecorder::class));

    expect($monitor->fresh()->status)->toBe(WaitlistMonitorStatus::Expired)
        ->and(AvailabilityEvent::query()
            ->where('event_type', AvailabilityEventType::WaitlistExpired->value)
            ->where('source_id', $monitor->id)
            ->exists())->toBeTrue();
});

it('leaves a not-yet-expired monitor untouched', function () {
    $product = Product::factory()->rental()->bulk()->create();

    $monitor = ShortageWaitlistMonitor::factory()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'status' => WaitlistMonitorStatus::Active->value,
        'expires_at' => Carbon::now()->addDays(5),
    ]);

    app(ExpireWaitlistMonitors::class)->handle(app(ShortageEventRecorder::class));

    expect($monitor->fresh()->status)->toBe(WaitlistMonitorStatus::Active);
});
