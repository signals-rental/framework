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
use App\Services\Shortages\Resolvers\WarehouseTransferResolver;
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

    $this->resolver = app(WarehouseTransferResolver::class);
});

function transferShortage(
    Store $homeStore,
    Product $product,
    int $shortfall = 2,
    int $availableAtHome = 1,
): Shortage {
    $item = OpportunityItem::factory()->create([
        'item_type' => 'product',
        'itemable_id' => $product->id,
    ]);

    return Shortage::make(
        opportunityItemId: $item->id,
        opportunityId: $item->opportunity_id,
        productId: $product->id,
        productName: $product->name,
        storeId: $homeStore->id,
        requestedQuantity: $availableAtHome + $shortfall,
        availableQuantity: $availableAtHome,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );
}

it('is not auto-executable and identifies itself as the transfer resolver', function () {
    expect($this->resolver->key())->toBe('transfer')
        ->and($this->resolver->name())->toBe('Warehouse transfer')
        ->and($this->resolver->priority())->toBe(30)
        ->and($this->resolver->isAutoExecutable())->toBeFalse();
});

it('returns false from canResolve when no shortfall remains', function () {
    Store::factory()->count(2)->create();
    $store = Store::query()->first();
    $product = Product::factory()->rental()->bulk()->create();

    $shortage = transferShortage($store, $product, shortfall: 0, availableAtHome: 5);

    expect($shortage->remainingShortfall())->toBe(0)
        ->and($this->resolver->canResolve($shortage))->toBeFalse()
        ->and($this->resolver->getOptions($shortage))->toBe([]);
});

it('returns no options in a single-warehouse store', function () {
    $store = Store::factory()->create();
    $product = Product::factory()->rental()->bulk()->create();
    $shortage = transferShortage($store, $product);

    expect($this->resolver->canResolve($shortage))->toBeFalse()
        ->and($this->resolver->getOptions($shortage))->toBe([]);
});

it('offers transfer options from warehouses with free stock, skipping empty ones', function () {
    $home = Store::factory()->create(['name' => 'London', 'timezone' => 'UTC']);
    $withStock = Store::factory()->create(['name' => 'Manchester', 'timezone' => 'UTC']);
    $empty = Store::factory()->create(['name' => 'Birmingham', 'timezone' => 'UTC']);

    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $home->id,
        'quantity_held' => 1,
    ]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $withStock->id,
        'quantity_held' => 5,
    ]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $empty->id,
        'quantity_held' => 2,
    ]);

    // Consume all stock at Birmingham for the shortage window.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $empty->id,
            'quantity' => 2,
            'source_type' => 'opportunity_item',
            'source_id' => 888001,
        ]);

    $shortage = transferShortage($home, $product, shortfall: 2, availableAtHome: 1);

    $options = $this->resolver->getOptions($shortage);

    expect($options)->toHaveCount(1)
        ->and($options[0]->resolverKey)->toBe('transfer')
        ->and($options[0]->type)->toBe(ShortageResolutionType::Transfer)
        ->and($options[0]->quantityResolved)->toBe(2)
        ->and($options[0]->isPartial)->toBeFalse()
        ->and($options[0]->autoExecutable)->toBeFalse()
        ->and($options[0]->metadata['source_store_id'])->toBe($withStock->id)
        ->and($options[0]->label)->toContain('Manchester');
});

it('marks a partial transfer when the remote store cannot cover the full shortfall', function () {
    $home = Store::factory()->create(['timezone' => 'UTC']);
    $remote = Store::factory()->create(['name' => 'Leeds', 'timezone' => 'UTC']);

    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $home->id,
        'quantity_held' => 1,
    ]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $remote->id,
        'quantity_held' => 1,
    ]);

    $shortage = transferShortage($home, $product, shortfall: 3, availableAtHome: 0);

    $options = $this->resolver->getOptions($shortage);

    expect($options)->toHaveCount(1)
        ->and($options[0]->quantityResolved)->toBe(1)
        ->and($options[0]->isPartial)->toBeTrue();
});

it('records transfer intent as pending with the source store metadata', function () {
    $home = Store::factory()->create(['timezone' => 'UTC']);
    $remote = Store::factory()->create(['timezone' => 'UTC']);

    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $remote->id,
        'quantity_held' => 4,
    ]);

    $shortage = transferShortage($home, $product);

    $option = $this->resolver->getOptions($shortage)[0];
    $result = $this->resolver->apply($shortage, $option);

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe(ShortageResolutionStatus::Pending)
        ->and($result->requiresFollowup)->toBeTrue()
        ->and($result->followupType)->toBe('delivery')
        ->and($result->resolution->metadata['pending_dependency'])->toBe('store_transfer')
        ->and($result->resolution->metadata['source_store_id'])->toBe($remote->id)
        ->and($result->resolution->resolution_type)->toBe(ShortageResolutionType::Transfer);

    $this->assertDatabaseHas('shortage_resolutions', [
        'id' => $result->resolution->id,
        'resolver_key' => 'transfer',
        'status' => ShortageResolutionStatus::Pending->value,
    ]);
});

it('memoises the multi-warehouse check within one resolver instance', function () {
    Store::factory()->count(2)->create();
    $store = Store::query()->first();
    $product = Product::factory()->rental()->bulk()->create();
    $shortage = transferShortage($store, $product);

    expect($this->resolver->canResolve($shortage))->toBeTrue()
        ->and($this->resolver->canResolve($shortage))->toBeTrue();

    Store::factory()->create();

    // Still memoised as multi-warehouse from the first check on this instance.
    expect($this->resolver->canResolve($shortage))->toBeTrue();
});
