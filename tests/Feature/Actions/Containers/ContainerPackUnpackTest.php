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
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function covPackableAsset(Store $store, ?Product $product = null): StockLevel
{
    $product ??= Product::factory()->serialised()->create();

    return StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
}

it('mirrors container housing metadata on the packed stock level', function () {
    $housingProduct = Product::factory()->containerable(ContainerAvailabilityMode::Kit)->create();
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $housingProduct->id,
        'store_id' => $this->store->id,
    ]);
    $container = Container::factory()->create([
        'product_id' => $housingProduct->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $this->store->id,
        'status' => ContainerStatus::Open->value,
    ]);
    $asset = covPackableAsset($this->store);

    (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
        'position' => 'Front tray',
        'notes' => 'Packed for test',
    ]));

    $membership = ContainerItem::query()->where('serialised_item_id', $asset->id)->firstOrFail();
    $asset->refresh();

    expect($membership->position)->toBe('Front tray')
        ->and($membership->notes)->toBe('Packed for test')
        ->and($membership->isActive())->toBeTrue()
        ->and($asset->container_stock_level_id)->toBe($housing->id)
        ->and($asset->container_mode)->toBe(ContainerAvailabilityMode::Kit->value);
});

it('rejects unpacking from a sealed container and clears stock-level mirrors on success', function () {
    $openContainer = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $asset = covPackableAsset($this->store);
    (new PackContainerItem)($openContainer, PackContainerItemData::from(['serialised_item_id' => $asset->id]));

    (new UnpackContainerItem)($openContainer, UnpackContainerItemData::from([
        'serialised_item_id' => $asset->id,
        'reason' => ContainerItemUnpackReason::Manual->value,
    ]));

    $membership = ContainerItem::query()->where('serialised_item_id', $asset->id)->firstOrFail();
    $asset->refresh();

    expect($membership->isActive())->toBeFalse()
        ->and($asset->container_stock_level_id)->toBeNull()
        ->and($asset->container_mode)->toBeNull();

    $sealed = Container::factory()->kit()->sealed()->create(['store_id' => $this->store->id]);
    $other = covPackableAsset($this->store);
    ContainerItem::factory()->create([
        'container_id' => $sealed->id,
        'serialised_item_id' => $other->id,
        'product_id' => $other->product_id,
    ]);

    expect(fn () => (new UnpackContainerItem)($sealed, UnpackContainerItemData::from([
        'serialised_item_id' => $other->id,
        'reason' => ContainerItemUnpackReason::Manual->value,
    ])))->toThrow(ValidationException::class);
});

it('rejects unpacking an item that is not packed in the container', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $asset = covPackableAsset($this->store);

    expect(fn () => (new UnpackContainerItem)($container, UnpackContainerItemData::from([
        'serialised_item_id' => $asset->id,
        'reason' => ContainerItemUnpackReason::Manual->value,
    ])))->toThrow(ValidationException::class);
});

it('requires the containers.pack ability to unpack', function () {
    $owner = User::factory()->owner()->create();
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $asset = covPackableAsset($this->store);

    $this->actingAs($owner);
    (new PackContainerItem)($container, PackContainerItemData::from(['serialised_item_id' => $asset->id]));

    $this->actingAs(User::factory()->create());

    expect(fn () => (new UnpackContainerItem)($container, UnpackContainerItemData::from([
        'serialised_item_id' => $asset->id,
        'reason' => ContainerItemUnpackReason::Manual->value,
    ])))->toThrow(AuthorizationException::class);
});
