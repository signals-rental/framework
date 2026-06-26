<?php

use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerItemUnpackReason;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use Carbon\CarbonImmutable;

it('casts container enums and booleans', function () {
    $container = Container::factory()->kit()->create([
        'status' => ContainerStatus::Open->value,
        'scan_mode' => ContainerScanMode::Strict->value,
        'is_temporary' => true,
        'sealed_at' => '2026-01-01T10:00:00Z',
    ])->fresh();

    expect($container->status)->toBe(ContainerStatus::Open)
        ->and($container->scan_mode)->toBe(ContainerScanMode::Strict)
        ->and($container->is_temporary)->toBeTrue()
        ->and($container->sealed_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('resolves availability mode and content-holding behaviour from the backing product', function () {
    $kit = Container::factory()->kit()->create()->fresh();
    $transport = Container::factory()->transport()->create()->fresh();
    $temporary = Container::factory()->create([
        'product_id' => null,
        'is_temporary' => true,
    ])->fresh();

    expect($kit->availabilityMode())->toBe(ContainerAvailabilityMode::Kit)
        ->and($kit->holdsContentsFromAvailability())->toBeTrue()
        ->and($transport->availabilityMode())->toBe(ContainerAvailabilityMode::Transport)
        ->and($transport->holdsContentsFromAvailability())->toBeFalse()
        ->and($temporary->availabilityMode())->toBe(ContainerAvailabilityMode::Transport)
        ->and($temporary->holdsContentsFromAvailability())->toBeFalse();
});

it('exposes container relationships and active scopes', function () {
    $store = Store::factory()->create();
    $product = Product::factory()->containerable()->create();
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    $parent = Container::factory()->create([
        'store_id' => $store->id,
        'product_id' => $product->id,
        'serialised_item_id' => $housing->id,
    ]);
    $child = Container::factory()->create([
        'store_id' => $store->id,
        'parent_container_id' => $parent->id,
    ]);
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    $activeItem = ContainerItem::factory()->create([
        'container_id' => $parent->id,
        'serialised_item_id' => $asset->id,
        'product_id' => $product->id,
    ]);
    $closedItem = ContainerItem::factory()->create([
        'container_id' => $parent->id,
        'serialised_item_id' => StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
        ])->id,
        'product_id' => $product->id,
        'unpacked_at' => now(),
        'unpacked_reason' => ContainerItemUnpackReason::Manual,
    ]);

    $parent->load(['serialisedItem', 'product', 'store', 'parent', 'children', 'containerItems', 'activeItems']);

    expect($parent->serialisedItem->is($housing))->toBeTrue()
        ->and($parent->product->is($product))->toBeTrue()
        ->and($parent->store->is($store))->toBeTrue()
        ->and($parent->children->contains(fn (Container $c): bool => $c->is($child)))->toBeTrue()
        ->and($parent->containerItems)->toHaveCount(2)
        ->and($parent->activeItems)->toHaveCount(1)
        ->and($activeItem->fresh()->isActive())->toBeTrue()
        ->and($closedItem->fresh()->isActive())->toBeFalse()
        ->and(Container::query()->active()->whereKey($parent->id)->exists())->toBeTrue()
        ->and(ContainerItem::query()->active()->whereKey($activeItem->id)->exists())->toBeTrue()
        ->and(ContainerItem::query()->active()->whereKey($closedItem->id)->exists())->toBeFalse();
});

it('generates a public uuid separate from the integer primary key', function () {
    $container = Container::factory()->create();

    expect($container->uuid)->not->toBeEmpty()
        ->and($container->uniqueIds())->toBe(['uuid']);
});
