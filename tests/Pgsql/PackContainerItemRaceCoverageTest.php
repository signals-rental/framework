<?php

use App\Actions\Containers\PackContainerItem;
use App\Data\Containers\PackContainerItemData;
use App\Models\Container;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL pack-race translation lane
|--------------------------------------------------------------------------
|
| Proves that when a concurrent pack wins the race on the Postgres partial-unique
| index uq_container_items_active_membership BETWEEN the in-action guard's
| exists() check and the INSERT, the resulting QueryException (23505) is
| translated into a friendly field-scoped ValidationException — not a raw 500.
|
| The race is simulated with DB::listen: a duplicate ACTIVE membership row for the
| same serialised item is inserted directly the moment the guard's membership-
| existence SELECT runs, so the action's own INSERT then violates the partial-
| unique index and is caught.
|
|   php artisan test --compact --group=pgsql
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
});

it('translates a raced partial-unique violation into a 422', function () {
    $product = Product::factory()->serialised()->create();
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $otherContainer = Container::factory()->kit()->create(['store_id' => $this->store->id]);

    // Simulate a concurrent pack: the instant the guard's membership-existence
    // SELECT runs, insert a duplicate ACTIVE membership for the same item via a
    // SEPARATE connection (so it commits independently of the action's transaction
    // and is visible to the partial-unique index when the action's INSERT runs).
    $injected = false;
    DB::listen(function ($query) use (&$injected, $asset, $otherContainer, $product): void {
        if ($injected) {
            return;
        }

        if (str_contains($query->sql, 'container_items')
            && str_contains($query->sql, 'exists')) {
            $injected = true;

            DB::connection($query->connectionName)->table('container_items')->insert([
                'container_id' => $otherContainer->id,
                'serialised_item_id' => $asset->id,
                'product_id' => $product->id,
                'packed_at' => now(),
                'unpacked_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    expect(fn () => (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
    ])))->toThrow(ValidationException::class, 'The item is already packed in an active container.');
});
