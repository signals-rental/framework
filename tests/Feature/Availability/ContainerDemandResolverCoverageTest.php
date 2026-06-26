<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\DemandPhase;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Demand;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\ContainerDemandResolver;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->resolver = app(ContainerDemandResolver::class);
});

it('rejects the wrong model type', function () {
    expect(fn () => $this->resolver->syncDemands(Product::factory()->create()))
        ->toThrow(InvalidArgumentException::class, 'ContainerItem');
});

it('releases demands for an inactive (unpacked) membership without leaving rows', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $item = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised()->create()->id,
        'store_id' => $this->store->id,
    ]);

    $membership = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $item->id,
        'product_id' => $item->product_id,
        'packed_at' => now()->subHour(),
        'unpacked_at' => now(),
    ]);

    Demand::factory()->create([
        'source_type' => 'container',
        'source_id' => $membership->id,
        'product_id' => $item->product_id,
        'store_id' => $this->store->id,
        'asset_id' => $item->id,
        'quantity' => 1,
    ]);

    $this->resolver->syncDemands($membership);

    expect(Demand::query()->where('source_type', 'container')->where('source_id', $membership->id)->exists())
        ->toBeFalse();
});

it('purges stale container demands when switching from kit to transport mode', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $product = Product::factory()->serialised()->create();
    $item = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $membership = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $item->id,
        'product_id' => $product->id,
        'packed_at' => now()->subHour(),
        'unpacked_at' => null,
    ]);

    $this->resolver->syncDemands($membership);
    expect(Demand::query()->where('source_id', $membership->id)->count())->toBe(1);

    $container->product->update(['container_availability_mode' => ContainerAvailabilityMode::Transport->value]);
    $membership->setRelation('container', $container->fresh(['product']));

    $this->resolver->syncDemands($membership);

    expect(Demand::query()->where('source_type', 'container')->where('source_id', $membership->id)->count())
        ->toBe(0);
});

it('does not write a demand when the serialised stock level is missing', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $item = StockLevel::factory()->serialised()->create(['store_id' => $this->store->id]);

    $membership = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $item->id,
        'product_id' => $item->product_id,
        'packed_at' => now()->subHour(),
        'unpacked_at' => null,
    ]);

    $item->delete();

    $this->resolver->syncDemands($membership);

    expect(Demand::query()->where('source_type', 'container')->where('source_id', $membership->id)->exists())
        ->toBeFalse();
});

it('returns Void phase for inactive memberships and Committed when active', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $item = StockLevel::factory()->serialised()->create(['store_id' => $this->store->id]);

    $active = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $item->id,
        'product_id' => $item->product_id,
        'packed_at' => now()->subHour(),
        'unpacked_at' => null,
    ]);
    $inactive = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $item->id,
        'product_id' => $item->product_id,
        'packed_at' => now()->subDay(),
        'unpacked_at' => now(),
    ]);

    expect($this->resolver->resolvePhase($active))->toBe(DemandPhase::Committed)
        ->and($this->resolver->resolvePhase($inactive))->toBe(DemandPhase::Void);
});

it('builds metadata with container and membership identifiers', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $item = StockLevel::factory()->serialised()->create(['store_id' => $this->store->id]);

    $membership = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $item->id,
        'product_id' => $item->product_id,
        'packed_at' => now()->subHour(),
        'unpacked_at' => null,
    ]);

    expect($this->resolver->buildMetadata($membership))->toBe([
        'container_id' => $container->id,
        'container_item_id' => $membership->id,
        'serialised_item_id' => $item->id,
    ]);
});

it('releaseDemands purges container rows for the membership', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $item = StockLevel::factory()->serialised()->create(['store_id' => $this->store->id]);

    $membership = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $item->id,
        'product_id' => $item->product_id,
        'packed_at' => now()->subHour(),
        'unpacked_at' => null,
    ]);

    Demand::factory()->create([
        'source_type' => 'container',
        'source_id' => $membership->id,
        'asset_id' => $item->id,
        'quantity' => 1,
    ]);

    $this->resolver->releaseDemands($membership);

    expect(Demand::query()->where('source_type', 'container')->where('source_id', $membership->id)->count())
        ->toBe(0);
});

it('accepts only ContainerItem models through the contract type hint', function () {
    $wrong = new class extends Model {};

    expect(fn () => $this->resolver->syncDemands($wrong))
        ->toThrow(InvalidArgumentException::class);
});

it('holds hybrid fixed slots but not pool slots after a mode change on the container product', function () {
    $container = Container::factory()->hybrid()->create(['store_id' => $this->store->id]);
    $fixedProduct = Product::factory()->serialised()->create();
    $poolProduct = Product::factory()->serialised()->create();

    SerialisedComponent::factory()->fixed()->quantity(1)->create([
        'product_id' => $container->product_id,
        'component_product_id' => $fixedProduct->id,
    ]);
    SerialisedComponent::factory()->pool()->quantity(1)->create([
        'product_id' => $container->product_id,
        'component_product_id' => $poolProduct->id,
    ]);

    $fixedItem = StockLevel::factory()->serialised()->create([
        'product_id' => $fixedProduct->id,
        'store_id' => $this->store->id,
    ]);
    $poolItem = StockLevel::factory()->serialised()->create([
        'product_id' => $poolProduct->id,
        'store_id' => $this->store->id,
    ]);

    $fixedMembership = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $fixedItem->id,
        'product_id' => $fixedProduct->id,
        'packed_at' => now()->subHour(),
        'unpacked_at' => null,
    ]);
    $poolMembership = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $poolItem->id,
        'product_id' => $poolProduct->id,
        'packed_at' => now()->subHour(),
        'unpacked_at' => null,
    ]);

    $this->resolver->syncDemands($fixedMembership);
    $this->resolver->syncDemands($poolMembership);

    expect(Demand::query()->where('source_id', $fixedMembership->id)->exists())->toBeTrue()
        ->and(Demand::query()->where('source_id', $poolMembership->id)->exists())->toBeFalse();
});
