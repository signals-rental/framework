<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\ReturnAsset;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\ReturnAssetData;
use App\Enums\DemandPhase;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL per-asset dispatch/return lane (M5-2)
|--------------------------------------------------------------------------
|
| Proves the actual-date demand recompute writes a real, contracted `tstzrange`
| period on PostgreSQL: an early return shrinks the asset's demand `period` to the
| actual return time + turnaround, so the SAME physical asset becomes free for a
| later window the exclusion constraint (excl_demands_asset_period) previously
| blocked. SKIPs when Postgres is unreachable.
|
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->rental()->serialised()->create();
});

function makePgFulfilmentLine(Store $store, Product $product, string $start, string $end): OpportunityItem
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG fulfilment',
        'store_id' => $store->id,
        'starts_at' => $start,
        'ends_at' => $end,
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

    return $opportunity->items()->firstOrFail();
}

it('contracts the asset demand period on early return against the real tstzrange', function () {
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);

    // Planned Sep 1 - Sep 30; dispatch on time, return early on Sep 5.
    $item = makePgFulfilmentLine($this->store, $this->product, '2026-09-01T09:00:00Z', '2026-09-30T17:00:00Z');
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from(['returned_at' => '2026-09-05T10:00:00Z']));

    // The asset's demand is now Closed (inactive) and ends at the actual return.
    $demand = Demand::query()->where('asset_id', $asset->id)->sole();
    expect($demand->phase)->toBe(DemandPhase::Closed)
        ->and($demand->is_active)->toBeFalse();

    // The persisted PG `period` tstzrange was rewritten to the contracted window:
    // its upper bound moved from the planned Sep 30 end back to (early return +
    // turnaround), well before Sep 30.
    $upper = DB::connection('pgsql_testing')
        ->table('demands')
        ->where('id', $demand->id)
        ->selectRaw('upper(period) as upper_bound')
        ->value('upper_bound');

    expect(Carbon::parse($upper)->lessThan(Carbon::parse('2026-09-10T00:00:00Z')))->toBeTrue();
});

it('frees the asset for a later overlapping booking once it is returned early', function () {
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);

    // First order: planned Sep 1 - Sep 30, returned early Sep 5.
    $itemA = makePgFulfilmentLine($this->store, $this->product, '2026-09-01T09:00:00Z', '2026-09-30T17:00:00Z');
    (new AllocateAsset)($itemA, AllocateAssetData::from(['stock_level_id' => $asset->id]));
    $rowA = OpportunityItemAsset::query()->where('opportunity_item_id', $itemA->id)->sole();
    (new DispatchAsset)($rowA, DispatchAssetData::from([]));
    (new ReturnAsset)($rowA->refresh(), ReturnAssetData::from(['returned_at' => '2026-09-05T10:00:00Z']));

    // A second order over Sep 20 - Sep 25 ORIGINALLY overlapped the planned window,
    // but the early return contracted the first demand — so the same asset is now
    // free and the allocation succeeds (no excl_demands_asset_period violation).
    $itemB = makePgFulfilmentLine($this->store, $this->product, '2026-09-20T09:00:00Z', '2026-09-25T17:00:00Z');
    (new AllocateAsset)($itemB, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    expect(OpportunityItemAsset::query()->where('opportunity_item_id', $itemB->id)->where('stock_level_id', $asset->id)->count())->toBe(1);
});
