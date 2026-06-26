<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\Resolvers\DateShiftResolver;
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

    $this->resolver = app(DateShiftResolver::class);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function dateShiftShortage(
    Store $store,
    Carbon $start,
    Carbon $end,
    int $requested = 3,
    int $available = 1,
): Shortage {
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 10,
    ]);
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
        requestedQuantity: $requested,
        availableQuantity: $available,
        trackingType: StockMethod::Bulk,
        startsAt: $start,
        endsAt: $end,
        isCritical: false,
    );
}

it('is not auto-executable and exposes resolver metadata', function () {
    expect($this->resolver->key())->toBe('date_shift')
        ->and($this->resolver->name())->toBe('Shift dates')
        ->and($this->resolver->priority())->toBe(40)
        ->and($this->resolver->isAutoExecutable())->toBeFalse();
});

it('refuses indefinite windows that cannot be shifted', function () {
    $shortage = dateShiftShortage(
        $this->store,
        Carbon::parse('2026-07-01T09:00:00Z'),
        Demand::sentinel(),
    );

    expect($this->resolver->canResolve($shortage))->toBeFalse()
        ->and($this->resolver->getOptions($shortage))->toBe([]);
});

it('skips candidate windows that cannot satisfy the full requested quantity', function () {
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 2,
    ]);
    $item = OpportunityItem::factory()->create([
        'item_type' => 'product',
        'itemable_id' => $product->id,
    ]);

    $start = Carbon::parse('2026-07-10T09:00:00Z');
    $end = Carbon::parse('2026-07-12T17:00:00Z');

    // Block every shifted window by consuming stock on the original dates only.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($start, $end)
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 2,
            'source_type' => 'opportunity_item',
            'source_id' => 777002,
        ]);

    $shortage = Shortage::make(
        opportunityItemId: $item->id,
        opportunityId: $item->opportunity_id,
        productId: $product->id,
        productName: $product->name,
        storeId: $this->store->id,
        requestedQuantity: 3,
        availableQuantity: 0,
        trackingType: StockMethod::Bulk,
        startsAt: $start,
        endsAt: $end,
        isCritical: false,
    );

    expect($this->resolver->getOptions($shortage))->toBe([]);
});

it('offers forward and backward shift options when stock is free', function () {
    $start = Carbon::parse('2026-07-10T09:00:00Z');
    $end = Carbon::parse('2026-07-12T17:00:00Z');
    $shortage = dateShiftShortage($this->store, $start, $end);

    $options = $this->resolver->getOptions($shortage);

    expect($options)->not->toBeEmpty();

    $offsets = array_map(static fn ($option): int => (int) $option->metadata['offset_days'], $options);

    expect($offsets)->toContain(1)
        ->and($offsets)->toContain(-1);

    foreach ($options as $option) {
        expect($option->type)->toBe(ShortageResolutionType::DateShift)
            ->and($option->quantityResolved)->toBe($shortage->requestedQuantity)
            ->and($option->isPartial)->toBeFalse()
            ->and($option->autoExecutable)->toBeFalse()
            ->and($option->metadata)->toHaveKeys(['shifted_starts_at', 'shifted_ends_at', 'offset_days']);
    }
});

it('records a confirmed date-shift proposal with original and shifted windows', function () {
    $start = Carbon::parse('2026-07-10T09:00:00Z');
    $end = Carbon::parse('2026-07-12T17:00:00Z');
    $shortage = dateShiftShortage($this->store, $start, $end);

    $option = collect($this->resolver->getOptions($shortage))
        ->first(fn ($candidate) => (int) $candidate->metadata['offset_days'] === 1);

    expect($option)->not->toBeNull();

    $result = $this->resolver->apply($shortage, $option);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(ShortageResolutionStatus::Confirmed)
        ->and($result->resolution->resolution_type)->toBe(ShortageResolutionType::DateShift)
        ->and($result->resolution->quantity_resolved)->toBe($shortage->requestedQuantity)
        ->and($result->resolution->metadata['original_starts_at'])->toBe($start->utc()->toIso8601String())
        ->and($result->resolution->metadata['original_ends_at'])->toBe($end->utc()->toIso8601String())
        ->and($result->resolution->metadata['shifted_starts_at'])->toBe($option->metadata['shifted_starts_at']);

    $this->assertDatabaseHas('shortage_resolutions', [
        'id' => $result->resolution->id,
        'resolver_key' => 'date_shift',
        'status' => ShortageResolutionStatus::Confirmed->value,
    ]);
});
