<?php

use App\Actions\Containers\PackContainerItem;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Containers\PackContainerItemData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL container pack-vs-allocation lane
|--------------------------------------------------------------------------
|
| Proves the REVERSE of AssetAllocationPostgresTest: packing a serialised item
| that is ALREADY committed to an opportunity must be rejected as a friendly 422
| (ValidationException) BEFORE it writes an indefinite container demand that would
| trip the GiST exclusion constraint excl_demands_asset_period (23P01 → raw 500).
|
| Runs against the dedicated `pgsql_testing` connection; SKIPs when Postgres is
| unreachable.
|
|   php artisan test --compact --group=pgsql
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->rental()->serialised()->create();
});

function makePgAllocatedOrderLine(Store $store, Product $product, StockLevel $asset): OpportunityItem
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG pack-vs-allocation',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    (new ConvertToOrder)($opportunity->refresh());

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->items()->firstOrFail();
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    return $item;
}

it('rejects packing an asset committed to an opportunity as a 422, not a 500', function () {
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);

    makePgAllocatedOrderLine($this->store, $this->product, $asset);

    // The asset now holds an active asset-specific demand overlapping the indefinite
    // container window. Packing must be rejected up-front as a ValidationException,
    // NOT a raw QueryException (23P01) from the exclusion constraint.
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

    expect(fn () => (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
    ])))->toThrow(ValidationException::class);

    // No membership row and no container demand were written — the guard ran before
    // any insert.
    expect(ContainerItem::query()->where('serialised_item_id', $asset->id)->count())->toBe(0)
        ->and(Demand::query()->where('source_type', 'container')->where('asset_id', $asset->id)->count())->toBe(0);
});

it('allows packing a free asset (no opportunity commitment)', function () {
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);

    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);

    $result = (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
    ]));

    expect(ContainerItem::query()->whereKey($result->id)->exists())->toBeTrue()
        ->and(Demand::query()->where('source_type', 'container')->where('asset_id', $asset->id)->count())->toBe(1);
});
