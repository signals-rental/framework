<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityEventType;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\ShortagePolicy;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Enums\StockMethod;
use App\Models\ActionLog;
use App\Models\AvailabilityEvent;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ShortageResolution;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\ShortageEventRecorder;
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
});

it('logs shortage.detected when the confirmation gate sees a shortage', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);

    $store = Store::factory()->shortagePolicy(ShortagePolicy::Warn)->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 666001,
            'metadata' => [],
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Event test',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '3',
    ]));
    (new ConvertToQuotation)($opportunity->fresh());

    (new ConvertToOrder)($opportunity->fresh());

    expect(
        AvailabilityEvent::query()
            ->where('event_type', AvailabilityEventType::ShortageDetected->value)
            ->where('product_id', $product->id)
            ->where('store_id', $store->id)
            ->exists()
    )->toBeTrue();

    // The acknowledgement is audit-bridged into the action log.
    expect(ActionLog::query()->where('action', 'shortage.acknowledged')->exists())->toBeTrue();
});

it('logs shortage.cleared with a reason', function () {
    $this->actingAs(User::factory()->owner()->create());

    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();

    $shortage = Shortage::make(
        opportunityItemId: 1,
        opportunityId: 1,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: 3,
        availableQuantity: 1,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );

    app(ShortageEventRecorder::class)->cleared($shortage, 'stock_returned');

    $event = AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageResolved->value)
        ->where('product_id', $product->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload['reason'])->toBe('stock_returned');
});

/**
 * Build a persisted resolution for exercising a status-transition recorder
 * method (§9.2). Resolution status transitions are audit-only — they emit a
 * shortage.* domain event for the audit trail / webhook bus, but do NOT write a
 * product/store `availability_events` delta row (those rows model availability
 * deltas keyed on a real product/store, not resolution lifecycle state).
 */
function lifecycleResolution(ShortageResolutionStatus $status): ShortageResolution
{
    return ShortageResolution::factory()->create([
        'resolver_key' => 'transfer',
        'resolution_type' => ShortageResolutionType::Transfer->value,
        'status' => $status->value,
        'quantity_resolved' => 2,
    ]);
}

it('logs shortage.resolution.in_progress as an audit log without an availability event', function () {
    $this->actingAs(User::factory()->owner()->create());

    $resolution = lifecycleResolution(ShortageResolutionStatus::InProgress);

    app(ShortageEventRecorder::class)->resolutionInProgress($resolution);

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageResolutionInProgress->value)
        ->where('source_id', $resolution->id)
        ->exists())->toBeFalse()
        ->and(ActionLog::query()->where('action', 'shortage.resolution.in_progress')->exists())->toBeTrue();
});

it('logs shortage.resolution.fulfilled as an audit log without an availability event', function () {
    $this->actingAs(User::factory()->owner()->create());

    $resolution = lifecycleResolution(ShortageResolutionStatus::Fulfilled);

    app(ShortageEventRecorder::class)->resolutionFulfilled($resolution);

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageResolutionFulfilled->value)
        ->where('source_id', $resolution->id)
        ->exists())->toBeFalse()
        ->and(ActionLog::query()->where('action', 'shortage.resolution.fulfilled')->exists())->toBeTrue();
});

it('logs shortage.resolution.failed with the failure reason in the audit log', function () {
    $this->actingAs(User::factory()->owner()->create());

    $resolution = lifecycleResolution(ShortageResolutionStatus::Failed);

    app(ShortageEventRecorder::class)->resolutionFailed($resolution, 'supplier_declined');

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageResolutionFailed->value)
        ->where('source_id', $resolution->id)
        ->exists())->toBeFalse()
        ->and(ActionLog::query()
            ->where('action', 'shortage.resolution.failed')
            ->exists())->toBeTrue();

    $log = ActionLog::query()->where('action', 'shortage.resolution.failed')->firstOrFail();
    expect(data_get($log->new_values, 'failure_reason'))->toBe('supplier_declined');
});
