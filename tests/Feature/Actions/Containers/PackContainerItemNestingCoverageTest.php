<?php

use App\Actions\Containers\PackContainerItem;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Containers\PackContainerItemData;
use App\Enums\AvailabilityResolution;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    // Resolve container demands daily so the pack write succeeds.
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * Build an OPEN kit container backed by a fresh containerable housing item.
 */
function covMakeKitContainer(Store $store, ?int $parentId = null, int $maxDepth = 2): Container
{
    $product = Product::factory()
        ->containerable(ContainerAvailabilityMode::Kit)
        ->create(['container_max_nesting_depth' => $maxDepth]);

    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    return Container::factory()->create([
        'product_id' => $product->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
        'status' => ContainerStatus::Open->value,
        'parent_container_id' => $parentId,
    ]);
}

it('packs a nested child container into the root without exceeding depth', function () {
    // Root container (depth 1, ceiling 2). Packing a child container directly into
    // the root yields depth 2 — exactly the ceiling, so it is allowed.
    $root = covMakeKitContainer($this->store, maxDepth: 2);

    // A child container whose housing item we will pack into the root. Its housing
    // backs an ACTIVE container, so guardNestingDepth proceeds past the early return.
    $child = covMakeKitContainer($this->store);

    $result = (new PackContainerItem)($root, PackContainerItemData::from([
        'serialised_item_id' => $child->serialised_item_id,
    ]));

    expect(ContainerItem::query()->whereKey($result->id)->exists())->toBeTrue();
});

it('rejects nesting that exceeds the root container max nesting depth', function () {
    // Root container (ceiling 2) at depth 1.
    $root = covMakeKitContainer($this->store, maxDepth: 2);

    // Target container nested directly under the root → depth 2. Packing a further
    // child into it would be depth 3, exceeding the root's ceiling of 2. Walking the
    // parent chain (guardNestingDepth) and computing the target depth (depthOf) both
    // iterate the parent loop up to the root.
    $target = covMakeKitContainer($this->store, parentId: $root->id);

    // A child container whose housing item we attempt to pack into the target.
    $child = covMakeKitContainer($this->store);

    expect(fn () => (new PackContainerItem)($target, PackContainerItemData::from([
        'serialised_item_id' => $child->serialised_item_id,
    ])))->toThrow(ValidationException::class, 'Nesting this container would exceed the maximum nesting depth.');

    // Nothing was packed — the guard ran before any insert.
    expect(ContainerItem::query()->where('serialised_item_id', $child->serialised_item_id)->count())->toBe(0);
});
