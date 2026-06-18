<?php

use App\Enums\AvailabilityEventType;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Enums\StockMethod;
use App\Models\AvailabilityEvent;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\Resolvers\PartialFulfilmentResolver;
use App\Services\Shortages\Resolvers\QuoteReallocationResolver;
use App\Services\Shortages\Resolvers\WaitlistResolver;
use App\Services\Shortages\Resolvers\WarehouseTransferResolver;
use App\Services\Shortages\ShortageResolverRegistry;
use App\ValueObjects\Shortage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->registry = app(ShortageResolverRegistry::class);
});

/**
 * A bulk shortage VO backed by real product/store/item rows so the resolution
 * and availability-event foreign keys are satisfiable.
 */
function bulkShortage(int $shortfall = 2, int $available = 1): Shortage
{
    $store = Store::query()->first() ?? Store::factory()->create();
    $product = Product::factory()->rental()->bulk()->create();
    $item = OpportunityItem::factory()->create([
        'item_type' => 'product',
        'item_id' => $product->id,
    ]);

    return Shortage::make(
        opportunityItemId: $item->id,
        opportunityId: $item->opportunity_id,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: $available + $shortfall,
        availableQuantity: $available,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );
}

it('registers all six built-in resolvers in priority order', function () {
    $keys = array_map(fn ($r): string => $r->key(), $this->registry->all());

    expect($keys)->toBe(['reallocate', 'substitute', 'transfer', 'date_shift', 'partial', 'waitlist']);
});

it('resolves a resolver instance from the container by key', function () {
    expect($this->registry->resolve('partial'))->toBeInstanceOf(PartialFulfilmentResolver::class)
        ->and($this->registry->has('waitlist'))->toBeTrue()
        ->and($this->registry->has('nope'))->toBeFalse();
});

it('marks only the partial resolver auto-executable among the self-contained ones', function () {
    expect(app(PartialFulfilmentResolver::class)->isAutoExecutable())->toBeTrue()
        ->and(app(WaitlistResolver::class)->isAutoExecutable())->toBeFalse()
        ->and(app(QuoteReallocationResolver::class)->isAutoExecutable())->toBeFalse();
});

it('excludes the transfer resolver in a single-warehouse store', function () {
    Store::factory()->create(); // exactly one store

    $applicable = array_map(fn ($r): string => $r->key(), $this->registry->applicableTo(bulkShortage()));

    expect($applicable)->not->toContain('transfer');
});

it('includes the transfer resolver when a second warehouse exists', function () {
    Store::factory()->count(2)->create();

    expect(app(WarehouseTransferResolver::class)->canResolve(bulkShortage()))->toBeTrue();
});

it('applies the partial resolver, recording a confirmed resolution and its item', function () {
    $shortage = bulkShortage(shortfall: 2, available: 4);
    $resolver = app(PartialFulfilmentResolver::class);

    $options = $resolver->getOptions($shortage);
    expect($options)->toHaveCount(1)
        ->and($options[0]->quantityResolved)->toBe(4)
        ->and($options[0]->autoExecutable)->toBeTrue();

    $result = $resolver->apply($shortage, $options[0]);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(ShortageResolutionStatus::Confirmed)
        ->and($result->resolution)->not->toBeNull();

    $this->assertDatabaseHas('shortage_resolutions', [
        'id' => $result->resolution->id,
        'resolver_key' => 'partial',
        'resolution_type' => ShortageResolutionType::Partial->value,
        'status' => ShortageResolutionStatus::Confirmed->value,
        'quantity_resolved' => 4,
    ]);

    $this->assertDatabaseHas('shortage_resolution_items', [
        'shortage_resolution_id' => $result->resolution->id,
        'opportunity_item_id' => $shortage->opportunityItemId,
        'quantity_allocated' => 4,
    ]);
});

it('records the waitlist resolution as monitoring', function () {
    $shortage = bulkShortage();
    $resolver = app(WaitlistResolver::class);

    $result = $resolver->apply($shortage, $resolver->getOptions($shortage)[0]);

    expect($result->status)->toBe(ShortageResolutionStatus::Monitoring)
        ->and($result->requiresFollowup)->toBeTrue();

    $this->assertDatabaseHas('shortage_resolutions', [
        'id' => $result->resolution->id,
        'resolver_key' => 'waitlist',
        'status' => ShortageResolutionStatus::Monitoring->value,
    ]);
});

it('records reallocation as pending intent awaiting the quote-release domain', function () {
    $shortage = bulkShortage();
    $resolver = app(QuoteReallocationResolver::class);

    $result = $resolver->apply($shortage, $resolver->getOptions($shortage)[0]);

    expect($result->status)->toBe(ShortageResolutionStatus::Pending)
        ->and($result->requiresFollowup)->toBeTrue()
        ->and($result->resolution->metadata['pending_dependency'])->toBe('quote_release');
});

it('emits a shortage.resolution.created event when a resolution is applied', function () {
    $shortage = bulkShortage(shortfall: 2, available: 4);
    $resolver = app(PartialFulfilmentResolver::class);

    $resolver->apply($shortage, $resolver->getOptions($shortage)[0]);

    expect(
        AvailabilityEvent::query()
            ->where('event_type', AvailabilityEventType::ShortageResolutionCreated->value)
            ->where('source_type', 'shortage_resolution')
            ->exists()
    )->toBeTrue();
});
