<?php

use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\UnpackContainerItem;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\UnpackContainerItemData;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerItemUnpackReason;
use App\Enums\ContainerStatus;
use App\Enums\DemandPhase;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Demand;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Availability\ContainerDemandResolver;
use App\Services\AvailabilityService;
use App\Services\DemandSourceRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    // Authenticate and allow packing — authorisation is exercised separately
    // below. The actor also populates `packed_by_user_id`.
    $this->actingAs(User::factory()->create());
    Gate::define('containers.pack', fn (): bool => true);

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->resolver = app(ContainerDemandResolver::class);
});

/**
 * Build a serialised stock level (one packable unit) at the test store.
 */
function packableItem(Store $store, ?Product $product = null): StockLevel
{
    $product ??= Product::factory()->serialised()->create();

    return StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
}

/**
 * Pack an item into a container via the action, returning the membership row.
 */
function packItem(Container $container, StockLevel $item): ContainerItem
{
    $data = PackContainerItemData::from(['serialised_item_id' => $item->id]);
    $result = (new PackContainerItem)($container, $data);

    return ContainerItem::query()->findOrFail($result->id);
}

it('registers the container demand source in the registry', function () {
    $registry = app(DemandSourceRegistry::class);

    expect($registry->has('container'))->toBeTrue()
        ->and($registry->resolve('container'))->toBeInstanceOf(ContainerDemandResolver::class)
        ->and($this->resolver->sourceType())->toBe('container');
});

it('creates an indefinite container demand when packing into a kit container', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $item = packableItem($this->store);

    $membership = packItem($container, $item);

    $demand = Demand::query()
        ->where('source_type', 'container')
        ->where('source_id', $membership->id)
        ->first();

    expect($demand)->not->toBeNull()
        ->and($demand->asset_id)->toBe($item->id)
        ->and($demand->quantity)->toBe(1)
        ->and($demand->product_id)->toBe($item->product_id)
        ->and($demand->store_id)->toBe($this->store->id)
        ->and($demand->phase)->toBe(DemandPhase::Committed)
        ->and($demand->is_active)->toBeTrue()
        ->and($demand->ends_at->toIso8601String())->toBe(Demand::sentinel()->toIso8601String());
});

it('removes a packed item from individual availability across windows', function () {
    $product = Product::factory()->serialised()->create();
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $item = packableItem($this->store, $product);

    $service = app(AvailabilityService::class);

    // Before packing: the single serialised unit is available.
    $before = $service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-09-01T09:00:00Z'));
    expect($before->available)->toBe(1);

    packItem($container, $item);

    // After packing: the indefinite container demand removes it — and stays
    // removed for a window months later (sentinel-dated, never expires).
    foreach (['2026-09-01T09:00:00Z', '2027-03-15T09:00:00Z'] as $date) {
        $after = $service->getAvailability($product->id, $this->store->id, Carbon::parse($date));
        expect($after->available)->toBe(0);
    }
});

it('releases the container demand on unpack, returning the item to availability', function () {
    $product = Product::factory()->serialised()->create();
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $item = packableItem($this->store, $product);

    $membership = packItem($container, $item);

    (new UnpackContainerItem)($container, UnpackContainerItemData::from([
        'serialised_item_id' => $item->id,
        'reason' => ContainerItemUnpackReason::Manual->value,
    ]));

    $demand = Demand::query()
        ->where('source_type', 'container')
        ->where('source_id', $membership->id)
        ->first();

    expect($demand)->not->toBeNull()
        ->and($demand->phase)->toBe(DemandPhase::Void)
        ->and($demand->is_active)->toBeFalse();

    $service = app(AvailabilityService::class);
    $after = $service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-09-01T09:00:00Z'));
    expect($after->available)->toBe(1);
});

it('creates NO container demand for a transport container', function () {
    $container = Container::factory()->transport()->create(['store_id' => $this->store->id]);
    $item = packableItem($this->store);

    $membership = packItem($container, $item);

    expect(Demand::query()->where('source_type', 'container')->where('source_id', $membership->id)->exists())
        ->toBeFalse();
});

it('holds only FIXED-binding components in a hybrid container, not pool', function () {
    // Hybrid container backed by a product whose composition marks the fixed
    // product as fixed-binding and the pool product as pool-binding.
    $container = Container::factory()->hybrid()->create(['store_id' => $this->store->id]);
    $containerProduct = $container->product;

    $fixedProduct = Product::factory()->serialised()->create();
    $poolProduct = Product::factory()->serialised()->create();

    SerialisedComponent::factory()->fixed()->quantity(1)->create([
        'product_id' => $containerProduct->id,
        'component_product_id' => $fixedProduct->id,
    ]);
    SerialisedComponent::factory()->pool()->quantity(1)->create([
        'product_id' => $containerProduct->id,
        'component_product_id' => $poolProduct->id,
    ]);

    $fixedItem = packableItem($this->store, $fixedProduct);
    $poolItem = packableItem($this->store, $poolProduct);

    $fixedMembership = packItem($container, $fixedItem);
    $poolMembership = packItem($container, $poolItem);

    // Fixed component → held (container demand exists).
    expect(Demand::query()->where('source_type', 'container')->where('source_id', $fixedMembership->id)->exists())
        ->toBeTrue();

    // Pool component → not held (drawn from general stock per dispatch).
    expect(Demand::query()->where('source_type', 'container')->where('source_id', $poolMembership->id)->exists())
        ->toBeFalse();
});

it('rejects packing a non-serialised item', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $bulkProduct = Product::factory()->bulk()->create();
    $bulkItem = StockLevel::factory()->bulk()->create([
        'product_id' => $bulkProduct->id,
        'store_id' => $this->store->id,
    ]);

    expect(fn () => packItem($container, $bulkItem))->toThrow(ValidationException::class);
});

it('rejects packing into a non-open container', function () {
    $container = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);
    $item = packableItem($this->store);

    expect(fn () => packItem($container, $item))->toThrow(ValidationException::class);
});

it('rejects packing an item already in an active container', function () {
    $first = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $second = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $item = packableItem($this->store);

    packItem($first, $item);

    expect(fn () => packItem($second, $item))->toThrow(ValidationException::class);
});

it('rejects packing an item held at a different store', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $otherStore = Store::factory()->create(['timezone' => 'UTC']);
    $item = packableItem($otherStore);

    expect(fn () => packItem($container, $item))->toThrow(ValidationException::class);
});

it('respects the configured nesting depth when packing a container housing', function () {
    // Root container with max nesting depth 1 → no child may be nested.
    $rootProduct = Product::factory()->containerable(ContainerAvailabilityMode::Kit)->create([
        'container_max_nesting_depth' => 1,
    ]);
    $rootHousing = StockLevel::factory()->serialised()->create([
        'product_id' => $rootProduct->id,
        'store_id' => $this->store->id,
    ]);
    $root = Container::factory()->create([
        'product_id' => $rootProduct->id,
        'serialised_item_id' => $rootHousing->id,
        'store_id' => $this->store->id,
        'status' => ContainerStatus::Open->value,
    ]);

    // A child container whose housing item we try to pack into root.
    $childHousing = packableItem($this->store);
    Container::factory()->create([
        'serialised_item_id' => $childHousing->id,
        'store_id' => $this->store->id,
        'status' => ContainerStatus::Open->value,
    ]);

    expect(fn () => packItem($root, $childHousing))->toThrow(ValidationException::class);
});

it('round-trips through the resolver: re-syncing an active membership converges', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $item = packableItem($this->store);
    $membership = packItem($container, $item);

    // Re-sync should not duplicate the demand (purge + rebuild).
    $membership->setRelation('container', $container);
    $this->resolver->syncDemands($membership);

    expect(Demand::query()->where('source_type', 'container')->where('source_id', $membership->id)->count())
        ->toBe(1);
});

it('requires the containers.pack ability to pack', function () {
    Gate::define('containers.pack', fn (): bool => false);

    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $item = packableItem($this->store);

    expect(fn () => packItem($container, $item))
        ->toThrow(AuthorizationException::class);
});
