<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Concerns\UsesPostgres;
use Thunk\Verbs\Exceptions\EventNotValid;

/*
|--------------------------------------------------------------------------
| PostgreSQL per-asset allocation lane
|--------------------------------------------------------------------------
|
| Proves the serialised-asset over-allocation guard holds against the real
| PostgreSQL exclusion constraint (excl_demands_asset_period): two opportunities
| cannot hold an active demand for the SAME physical asset over an overlapping
| window. The AllocateAsset availability guard rejects the second claim before it
| reaches the constraint; this lane re-verifies the demand the first allocation
| writes is exactly the row the constraint protects. SKIPs when Postgres is
| unreachable.
|
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->rental()->serialised()->create();
});

function makePgOrderLine(Store $store, Product $product): OpportunityItem
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG allocation',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    // The line item must exist before conversion — an order must carry at least
    // one item to be confirmed (opportunity-lifecycle.md §12.1).
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    (new ConvertToOrder)($opportunity->refresh());

    return $opportunity->items()->firstOrFail();
}

it('blocks two opportunities allocating the same asset over an overlapping window', function () {
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);

    $itemA = makePgOrderLine($this->store, $this->product);
    (new AllocateAsset)($itemA, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    // The first allocation wrote one active asset-specific demand — the exact row
    // the exclusion constraint guards.
    expect(Demand::query()->where('asset_id', $asset->id)->where('is_active', true)->count())->toBe(1);

    $itemB = makePgOrderLine($this->store, $this->product);

    // The availability guard rejects the second claim (it would otherwise violate
    // excl_demands_asset_period with 23P01).
    expect(fn () => (new AllocateAsset)($itemB, AllocateAssetData::from(['stock_level_id' => $asset->id])))
        ->toThrow(EventNotValid::class);

    // Still exactly one active demand for the asset; the second allocation rolled
    // back atomically and left no projection row.
    expect(Demand::query()->where('asset_id', $asset->id)->where('is_active', true)->count())->toBe(1)
        ->and(OpportunityItemAsset::query()->where('opportunity_item_id', $itemB->id)->count())->toBe(0);
});

it('allows the same asset on a non-overlapping window', function () {
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);

    $itemA = makePgOrderLine($this->store, $this->product);
    (new AllocateAsset)($itemA, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    // A second order in a clearly later window (no overlap with the first) can hold
    // the same physical asset.
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG later window',
        'store_id' => $this->store->id,
        'starts_at' => '2026-12-01T09:00:00Z',
        'ends_at' => '2026-12-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    // The line item must exist before conversion — an order must carry at least
    // one item to be confirmed (opportunity-lifecycle.md §12.1).
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $this->product->name,
        'item_id' => $this->product->id,
        'item_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($opportunity->refresh());
    $itemB = $opportunity->items()->firstOrFail();

    (new AllocateAsset)($itemB, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    expect(Demand::query()->where('asset_id', $asset->id)->where('is_active', true)->count())->toBe(2);
});
