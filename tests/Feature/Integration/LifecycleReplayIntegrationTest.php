<?php

use App\Actions\Opportunities\AcceptVersion;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\CheckAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\ReturnAsset;
use App\Actions\Opportunities\SendVersion;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CheckAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\ReturnAssetData;
use App\Enums\AssetCondition;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityStatus;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

/*
|--------------------------------------------------------------------------
| M6 — Full-lifecycle REPLAY integration (opportunity-lifecycle.md §18.4)
|--------------------------------------------------------------------------
|
| The headline replay test: run a COMPLETE lifecycle end-to-end through the
| real actions, snapshot every projection + opportunity status, truncate all
| projection tables, Verbs::replay(), then assert every projection and the
| status reconstruct IDENTICALLY — and that the always-replay audit bridge
| dedups via verb_event_id (no duplicate action_logs).
|
*/

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

it('reconstructs every projection and status identically after a full lifecycle replay', function () {
    $from = Carbon::parse('2026-09-01T09:00:00Z');
    $to = Carbon::parse('2026-09-05T17:00:00Z');
    $product = Product::factory()->rental()->serialised()->create();
    $assetA = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $this->store->id]);
    $assetB = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $this->store->id]);

    // --- Drive a FULL lifecycle through the real actions ------------------
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Replay slice',
        'store_id' => $this->store->id,
        'starts_at' => $from->toIso8601String(),
        'ends_at' => $to->toIso8601String(),
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '2',
        'unit_price' => 7500,
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    $item = $opportunity->refresh()->items()->firstOrFail();

    (new ConvertToQuotation)($opportunity->refresh());
    $version = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['label' => 'Final']));
    (new SendVersion)(OpportunityVersion::query()->whereKey($version->id)->firstOrFail());
    (new AcceptVersion)(OpportunityVersion::query()->whereKey($version->id)->firstOrFail());

    $item = $opportunity->refresh()->items()->firstOrFail();
    (new ConvertToOrder)($opportunity->refresh());

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $assetA->id]));
    (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $assetB->id]));
    $rowA = OpportunityItemAsset::query()->where('stock_level_id', $assetA->id)->sole();
    $rowB = OpportunityItemAsset::query()->where('stock_level_id', $assetB->id)->sole();

    (new DispatchAsset)($rowA, DispatchAssetData::from([]));
    (new DispatchAsset)($rowB->refresh(), DispatchAssetData::from([]));
    (new ReturnAsset)($rowA->refresh(), ReturnAssetData::from([]));
    (new ReturnAsset)($rowB->refresh(), ReturnAssetData::from([]));
    (new CheckAsset)($rowA->refresh(), CheckAssetData::from(['condition' => AssetCondition::Good->value]));
    (new CheckAsset)($rowB->refresh(), CheckAssetData::from(['condition' => AssetCondition::Good->value]));
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderComplete);

    // --- Snapshot every projection + status BEFORE replay -----------------
    $opportunityBefore = Opportunity::query()->whereKey($opportunity->id)->firstOrFail()
        ->only(['state', 'status', 'active_version_id', 'version_count', 'charge_total', 'charge_excluding_tax_total']);
    $statusBefore = $opportunity->refresh()->statusEnum();
    $itemsBefore = OpportunityItem::query()->orderBy('id')->get(['id', 'version_id', 'quantity', 'total'])->toArray();
    $assetsBefore = OpportunityItemAsset::query()->orderBy('id')->get(['id', 'opportunity_item_id', 'stock_level_id', 'status'])->toArray();
    $versionsBefore = OpportunityVersion::query()->orderBy('id')->get(['id', 'version_number', 'status', 'is_active'])->toArray();
    $costsBefore = OpportunityCost::query()->orderBy('id')->get(['id', 'opportunity_id'])->toArray();
    $auditCountBefore = ActionLog::query()->count();

    // --- Truncate ALL projections, then replay the whole stream -----------
    OpportunityItemAsset::query()->delete();
    OpportunityItem::query()->forceDelete();
    OpportunityVersion::query()->delete();
    OpportunityCost::query()->forceDelete();
    Opportunity::query()->forceDelete();
    StockLevel::query()->update(['quantity_allocated' => 0]);

    Verbs::replay();

    // --- Every projection + status reconstructs IDENTICALLY ---------------
    expect(Opportunity::query()->whereKey($opportunity->id)->firstOrFail()
        ->only(['state', 'status', 'active_version_id', 'version_count', 'charge_total', 'charge_excluding_tax_total']))
        ->toBe($opportunityBefore)
        ->and(Opportunity::query()->whereKey($opportunity->id)->firstOrFail()->statusEnum())->toBe($statusBefore)
        ->and(OpportunityItem::query()->orderBy('id')->get(['id', 'version_id', 'quantity', 'total'])->toArray())->toBe($itemsBefore)
        ->and(OpportunityItemAsset::query()->orderBy('id')->get(['id', 'opportunity_item_id', 'stock_level_id', 'status'])->toArray())->toBe($assetsBefore)
        ->and(OpportunityVersion::query()->orderBy('id')->get(['id', 'version_number', 'status', 'is_active'])->toArray())->toBe($versionsBefore)
        ->and(OpportunityCost::query()->orderBy('id')->get(['id', 'opportunity_id'])->toArray())->toBe($costsBefore);

    // Allocation re-derived from zero by replay.
    expect((float) $assetA->refresh()->quantity_allocated)->toBe(0.0)
        ->and((float) $assetB->refresh()->quantity_allocated)->toBe(0.0);

    // --- Audit dedups via verb_event_id: no duplicate action_logs ---------
    expect(ActionLog::query()->count())->toBe($auditCountBefore);
});
