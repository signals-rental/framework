<?php

use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use Illuminate\Database\QueryException;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL container-membership lane
|--------------------------------------------------------------------------
|
| Validates the PostgreSQL-only partial unique indexes that back the container
| containment invariants (the SQLite default suite degrades to in-action
| guards, mirroring the `demands` lane):
|
|  - container_items: one ACTIVE membership per serialised item
|    (uq_container_items_active_membership, WHERE unpacked_at IS NULL).
|  - containers: one ACTIVE (non-dissolved) container per housing item
|    (uq_containers_active_serialised_item).
|
| Runs against the dedicated `pgsql_testing` connection and SKIPS cleanly when
| Postgres is unreachable.
|
|   php artisan test --compact --group=pgsql
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->serialised()->create();
    $this->item = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);
});

it('rejects a second active membership for the same serialised item', function () {
    $containerA = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $containerB = Container::factory()->kit()->create(['store_id' => $this->store->id]);

    ContainerItem::factory()->create([
        'container_id' => $containerA->id,
        'serialised_item_id' => $this->item->id,
        'product_id' => $this->product->id,
        'unpacked_at' => null,
    ]);

    expect(fn () => ContainerItem::factory()->create([
        'container_id' => $containerB->id,
        'serialised_item_id' => $this->item->id,
        'product_id' => $this->product->id,
        'unpacked_at' => null,
    ]))->toThrow(QueryException::class);
});

it('allows a new membership once the prior one is unpacked', function () {
    $containerA = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $containerB = Container::factory()->kit()->create(['store_id' => $this->store->id]);

    ContainerItem::factory()->create([
        'container_id' => $containerA->id,
        'serialised_item_id' => $this->item->id,
        'product_id' => $this->product->id,
        'unpacked_at' => now(),
    ]);

    $second = ContainerItem::factory()->create([
        'container_id' => $containerB->id,
        'serialised_item_id' => $this->item->id,
        'product_id' => $this->product->id,
        'unpacked_at' => null,
    ]);

    expect($second->exists)->toBeTrue();
});

it('rejects a second active container for the same housing item', function () {
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);

    Container::factory()->create([
        'serialised_item_id' => $housing->id,
        'store_id' => $this->store->id,
        'status' => ContainerStatus::Open->value,
    ]);

    expect(fn () => Container::factory()->create([
        'serialised_item_id' => $housing->id,
        'store_id' => $this->store->id,
        'status' => ContainerStatus::Sealed->value,
    ]))->toThrow(QueryException::class);
});

it('allows a new container for a housing whose prior container is dissolved', function () {
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);

    Container::factory()->create([
        'serialised_item_id' => $housing->id,
        'store_id' => $this->store->id,
        'status' => ContainerStatus::Dissolved->value,
    ]);

    $second = Container::factory()->create([
        'serialised_item_id' => $housing->id,
        'store_id' => $this->store->id,
        'status' => ContainerStatus::Open->value,
    ]);

    expect($second->exists)->toBeTrue();
});

it('enforces the no-self-nest check constraint', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

    expect(fn () => $container->forceFill(['parent_container_id' => $container->id])->save())
        ->toThrow(QueryException::class);
});
