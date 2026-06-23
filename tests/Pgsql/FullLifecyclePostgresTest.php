<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\CheckAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\ReturnAsset;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CheckAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\ReturnAssetData;
use App\Enums\AssetCondition;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityStatus;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\AvailabilityService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\UsesPostgres;
use Thunk\Verbs\Exceptions\EventNotValid;

/*
|--------------------------------------------------------------------------
| M6 — Full-lifecycle end-to-end on real PostgreSQL
|--------------------------------------------------------------------------
|
| Proves the vertical slice's demand accuracy and the serialised-asset
| exclusion constraint hold against a REAL Postgres backend (tstzrange +
| GiST + excl_demands_asset_period), driving the whole lifecycle through the
| real actions and reading availability live. SKIPs when Postgres is
| unreachable.
|
|   php artisan test --compact --group=pgsql tests/Pgsql/FullLifecyclePostgresTest.php
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->availability = app(AvailabilityService::class);
});

it('keeps demand and availability accurate through a full serialised lifecycle on Postgres', function () {
    $from = Carbon::parse('2026-09-01T09:00:00Z');
    $to = Carbon::parse('2026-09-05T17:00:00Z');
    $product = Product::factory()->rental()->serialised()->create();
    $assetA = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $this->store->id]);
    $assetB = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $this->store->id]);

    // Two serialised units → two free assets at the window before any booking.
    expect($this->availability->getAvailableAssets($product->id, $this->store->id, $from, $to))->toHaveCount(2);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG full slice',
        'store_id' => $this->store->id,
        'starts_at' => $from->toIso8601String(),
        'ends_at' => $to->toIso8601String(),
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '2',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($opportunity->refresh());
    $item = $opportunity->items()->firstOrFail();

    // Order committed: quantity demand of 2, Committed phase, real tstzrange row.
    $committed = Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole();
    expect($committed->phase)->toBe(DemandPhase::Committed)
        ->and((int) $committed->quantity)->toBe(2);

    // Allocate both → two asset demands; both assets now consumed for the window.
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $assetA->id]));
    (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $assetB->id]));
    expect($this->availability->getAvailableAssets($product->id, $this->store->id, $from, $to))->toHaveCount(0)
        ->and($this->availability->checkAvailability($product->id, $this->store->id, $from, $to, 1))->toBeFalse();

    $rowA = OpportunityItemAsset::query()->where('stock_level_id', $assetA->id)->sole();
    $rowB = OpportunityItemAsset::query()->where('stock_level_id', $assetB->id)->sole();

    // Dispatch both → Operational demand; On Hire.
    (new DispatchAsset)($rowA, DispatchAssetData::from([]));
    (new DispatchAsset)($rowB->refresh(), DispatchAssetData::from([]));
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire)
        ->and(Demand::query()->where('source_id', $item->id)->where('asset_id', $assetA->id)->sole()->phase)
        ->toBe(DemandPhase::Operational);

    // Return both → Closed demand; assets free again for the window.
    (new ReturnAsset)($rowA->refresh(), ReturnAssetData::from([]));
    (new ReturnAsset)($rowB->refresh(), ReturnAssetData::from([]));
    expect(Demand::query()->where('source_id', $item->id)->where('asset_id', $assetA->id)->sole()->phase)
        ->toBe(DemandPhase::Closed)
        ->and($this->availability->getAvailableAssets($product->id, $this->store->id, $from, $to))->toHaveCount(2);

    // Check + complete.
    (new CheckAsset)($rowA->refresh(), CheckAssetData::from(['condition' => AssetCondition::Good->value]));
    (new CheckAsset)($rowB->refresh(), CheckAssetData::from(['condition' => AssetCondition::Good->value]));
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderComplete);
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderComplete);
});

it('closes a demand at the return time without a backwards tstzrange when an asset is returned before its planned start', function () {
    // Regression: returning an asset BEFORE its planned start once produced a
    // demand window [plannedStart, returnedAt] with lower > upper, which the
    // PostgreSQL tstzrange constructor rejects (SQLSTATE 22000). The resolver now
    // clamps the closed window to the return time.
    $from = Carbon::parse('2026-12-01T09:00:00Z'); // planned window is in the future
    $to = Carbon::parse('2026-12-05T17:00:00Z');
    $product = Product::factory()->rental()->serialised()->create();
    $asset = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $this->store->id]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG early return',
        'store_id' => $this->store->id,
        'starts_at' => $from->toIso8601String(),
        'ends_at' => $to->toIso8601String(),
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name, 'itemable_id' => $product->id, 'itemable_type' => Product::class,
        'quantity' => '1', 'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($opportunity->refresh());
    $item = $opportunity->items()->firstOrFail();
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();

    // Dispatch early, then return early — both before the planned start.
    (new DispatchAsset)($row->refresh(), DispatchAssetData::from(['dispatched_at' => '2026-11-28T09:00:00Z']));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from(['returned_at' => '2026-11-29T09:00:00Z']));

    $demand = Demand::query()->where('source_id', $item->id)->where('asset_id', $asset->id)->sole();
    expect($demand->phase)->toBe(DemandPhase::Closed)
        ->and($demand->ends_at->toIso8601String())->toBe('2026-11-29T09:00:00+00:00')
        // Window did not invert — start is at or before the return.
        ->and($demand->starts_at->lessThanOrEqualTo($demand->ends_at))->toBeTrue();
});

it('blocks double-booking the same physical asset over an overlapping window (exclusion constraint 23P01)', function () {
    $from = Carbon::parse('2026-09-01T09:00:00Z');
    $to = Carbon::parse('2026-09-05T17:00:00Z');
    $product = Product::factory()->rental()->serialised()->create();
    $asset = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $this->store->id]);

    $makeOrderLine = function (string $start, string $end) use ($product): OpportunityItem {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'PG double-book',
            'store_id' => $this->store->id,
            'starts_at' => $start,
            'ends_at' => $end,
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        (new ConvertToQuotation)($opportunity);
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $product->name, 'itemable_id' => $product->id, 'itemable_type' => Product::class,
            'quantity' => '1', 'transaction_type' => LineItemTransactionType::Rental->value,
        ]));
        (new ConvertToOrder)($opportunity->refresh());

        return $opportunity->items()->firstOrFail();
    };

    $itemA = $makeOrderLine($from->toIso8601String(), $to->toIso8601String());
    (new AllocateAsset)($itemA, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    // A second order over the SAME window cannot claim the same asset — the
    // availability guard rejects it before the exclusion constraint would.
    $itemB = $makeOrderLine($from->toIso8601String(), $to->toIso8601String());
    expect(fn () => (new AllocateAsset)($itemB, AllocateAssetData::from(['stock_level_id' => $asset->id])))
        ->toThrow(EventNotValid::class);

    // Exactly one active demand survives; the rejected claim left no projection.
    expect(Demand::query()->where('asset_id', $asset->id)->where('is_active', true)->count())->toBe(1)
        ->and(OpportunityItemAsset::query()->where('opportunity_item_id', $itemB->id)->count())->toBe(0);

    // A non-overlapping later window CAN hold the same asset.
    $itemC = $makeOrderLine('2026-12-01T09:00:00Z', '2026-12-05T17:00:00Z');
    (new AllocateAsset)($itemC, AllocateAssetData::from(['stock_level_id' => $asset->id]));
    expect(Demand::query()->where('asset_id', $asset->id)->where('is_active', true)->count())->toBe(2);
});
