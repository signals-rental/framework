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
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Actions\Opportunities\ReturnAsset;
use App\Actions\Opportunities\ReturnBulkQuantity;
use App\Actions\Opportunities\SendVersion;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\BulkReturnData;
use App\Data\Opportunities\CheckAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\ReturnAssetData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Enums\VersionStatus;
use App\Models\ActionLog;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\AvailabilityService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| M6 — Full-lifecycle integration (the vertical slice as one system)
|--------------------------------------------------------------------------
|
| These tests chain the REAL action entry points end-to-end and assert, at
| every transition, that state/status, demand phase + accuracy, audit rows,
| and totals all stay correct as the system composes. They are deliberately
| coarse-grained (one scenario = many steps) — the per-action unit coverage
| lives under tests/Feature/Verbs/Opportunities. M6 proves the composition.
|
| Default lane is SQLite :memory:. The Postgres exclusion-constraint and
| live demand-accuracy proofs live in tests/Pgsql/FullLifecyclePostgresTest.
|
*/

beforeEach(function () {
    // Demand projections must run synchronously so the per-transition demand
    // assertions read reality; only the async recalculation jobs are faked.
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->availability = app(AvailabilityService::class);
});

/**
 * The hire window used across every scenario.
 *
 * @return array{0: Carbon, 1: Carbon}
 */
function lifecycleWindow(): array
{
    return [Carbon::parse('2026-09-01T09:00:00Z'), Carbon::parse('2026-09-05T17:00:00Z')];
}

it('runs the serialised vertical slice: create → quote → version → order → allocate → dispatch → return → check → complete', function () {
    [$from, $to] = lifecycleWindow();
    $product = Product::factory()->rental()->serialised()->create();
    // Two physical assets so we can prove PARTIAL dispatch/return.
    $assetA = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $this->store->id]);
    $assetB = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $this->store->id]);

    // --- Create (Draft) ---------------------------------------------------
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Full slice',
        'store_id' => $this->store->id,
        'starts_at' => $from->toIso8601String(),
        'ends_at' => $to->toIso8601String(),
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    expect($opportunity->statusEnum())->toBe(OpportunityStatus::DraftOpen);

    // Two units of a £75.00 manual line.
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '2',
        'unit_price' => 7500,
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    $item = $opportunity->refresh()->items()->firstOrFail();

    // Totals (NET / ex-tax integer model): 2 × 7500 = 15000.
    expect($opportunity->charge_total)->toBe(15000)
        ->and($opportunity->charge_excluding_tax_total)->toBe(15000)
        ->and($item->total)->toBe(15000);

    // A Draft line raises a draft-phase, quantity-based demand of 2.
    $draftDemand = Demand::query()->where('source_id', $item->id)->sole();
    expect($draftDemand->phase)->toBe(DemandPhase::Draft)
        ->and((int) $draftDemand->quantity)->toBe(2)
        ->and($draftDemand->asset_id)->toBeNull();

    // --- Convert to Quotation --------------------------------------------
    (new ConvertToQuotation)($opportunity->refresh());
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::QuotationProvisional);

    // --- Version: revise, send, accept -----------------------------------
    $version = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['label' => 'Final quote']));
    $opportunity->refresh();
    expect($version->version_number)->toBe(1)
        ->and($opportunity->active_version_id)->toBe($version->id);
    // The active version's totals mirror the opportunity totals.
    $versionItems = OpportunityVersion::query()->whereKey($version->id)->firstOrFail()->items;
    expect($versionItems->sum('total'))->toBe(15000)
        ->and($opportunity->charge_total)->toBe(15000);

    (new SendVersion)(OpportunityVersion::query()->whereKey($version->id)->firstOrFail());
    (new AcceptVersion)(OpportunityVersion::query()->whereKey($version->id)->firstOrFail());
    expect(OpportunityVersion::query()->whereKey($version->id)->firstOrFail()->status)->toBe(VersionStatus::Accepted);

    // The fulfilment line item now lives under the active version.
    $item = $opportunity->refresh()->items()->firstOrFail();

    // --- Convert to Order -------------------------------------------------
    (new ConvertToOrder)($opportunity->refresh());
    $opportunity->refresh();
    expect($opportunity->statusEnum())->toBe(OpportunityStatus::OrderActive)
        ->and($opportunity->state)->toBe(OpportunityState::Order);

    // Order confirmation upgrades the line demand to Committed.
    $committed = Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole();
    expect($committed->phase)->toBe(DemandPhase::Committed)
        ->and((int) $committed->quantity)->toBe(2);

    // --- Allocate both assets --------------------------------------------
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $assetA->id]));
    (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $assetB->id]));

    $rowA = OpportunityItemAsset::query()->where('stock_level_id', $assetA->id)->sole();
    $rowB = OpportunityItemAsset::query()->where('stock_level_id', $assetB->id)->sole();
    expect($rowA->status)->toBe(AssetAssignmentStatus::Allocated)
        ->and($rowB->status)->toBe(AssetAssignmentStatus::Allocated)
        ->and((float) $assetA->refresh()->quantity_allocated)->toBe(1.0);

    // Quantity demand became two per-asset demands, no residual quantity demand.
    $afterAllocation = Demand::query()->where('source_id', $item->id)->get();
    expect($afterAllocation->whereNotNull('asset_id')->count())->toBe(2)
        ->and($afterAllocation->whereNull('asset_id')->count())->toBe(0)
        ->and($afterAllocation->whereNotNull('asset_id')->pluck('phase')->unique()->all())
        ->toBe([DemandPhase::Committed]);

    // Both assets are now consumed: nothing free for a fresh line over the window.
    expect($this->availability->checkAvailability($product->id, $this->store->id, $from, $to, 1))->toBeFalse();

    // --- PARTIAL dispatch: A out, B not → Order auto-promotes to Dispatched
    (new DispatchAsset)($rowA->refresh(), DispatchAssetData::from([]));
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderDispatched);
    expect(Demand::query()->where('source_id', $item->id)->where('asset_id', $assetA->id)->sole()->phase)
        ->toBe(DemandPhase::Operational);

    // --- Dispatch B → all out → On Hire ----------------------------------
    (new DispatchAsset)($rowB->refresh(), DispatchAssetData::from([]));
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire);

    // --- PARTIAL return: A back, B still out → stays On Hire (some still out)
    (new ReturnAsset)($rowA->refresh(), ReturnAssetData::from([]));
    expect($rowA->refresh()->status)->toBe(AssetAssignmentStatus::CheckedIn)
        ->and($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire);
    // A's demand closed + released; A's allocation freed; B still out.
    expect(Demand::query()->where('source_id', $item->id)->where('asset_id', $assetA->id)->sole()->phase)
        ->toBe(DemandPhase::Closed)
        ->and((float) $assetA->refresh()->quantity_allocated)->toBe(0.0)
        ->and((float) $assetB->refresh()->quantity_allocated)->toBe(1.0);

    // --- Return B → all back → Returned ----------------------------------
    (new ReturnAsset)($rowB->refresh(), ReturnAssetData::from([]));
    expect($rowB->refresh()->status)->toBe(AssetAssignmentStatus::CheckedIn)
        ->and($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderReturned)
        ->and((float) $assetB->refresh()->quantity_allocated)->toBe(0.0);

    // --- Check both assets → Checked -------------------------------------
    (new CheckAsset)($rowA->refresh(), CheckAssetData::from(['condition' => AssetCondition::Good->value]));
    (new CheckAsset)($rowB->refresh(), CheckAssetData::from(['condition' => AssetCondition::Good->value]));
    expect($rowA->refresh()->status)->toBe(AssetAssignmentStatus::Finalised)
        ->and($rowB->refresh()->status)->toBe(AssetAssignmentStatus::Finalised)
        ->and($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderChecked);

    // --- Complete ---------------------------------------------------------
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderComplete);
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderComplete)
        ->and($opportunity->statusEnum()->isClosed())->toBeTrue();

    // Totals survived the full lifecycle unchanged.
    expect($opportunity->refresh()->charge_total)->toBe(15000);

    // --- Audit accuracy: key transitions all produced action_log rows -----
    $actions = ActionLog::query()->where('auditable_type', Opportunity::class)
        ->where('auditable_id', $opportunity->id)->pluck('action')->all();
    expect($actions)->toContain('opportunity.created')
        ->and($actions)->toContain('opportunity.quoted')
        ->and($actions)->toContain('opportunity.converted_to_order')
        ->and($actions)->toContain('opportunity.status_changed');

    // Audit rows carry the acting user.
    $createdLog = ActionLog::query()->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.created')->sole();
    expect($createdLog->user_id)->toBe($this->actor->id);
});

it('runs the bulk vertical slice with partial dispatch/return and effective_quantity demand', function () {
    [$from, $to] = lifecycleWindow();
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 100,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Bulk slice',
        'store_id' => $this->store->id,
        'starts_at' => $from->toIso8601String(),
        'ends_at' => $to->toIso8601String(),
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '100',
        'unit_price' => 250,
    ]));
    $item = $opportunity->refresh()->items()->firstOrFail();
    expect($item->total)->toBe(25000); // 100 × 250

    (new ConvertToOrder)($opportunity->refresh());
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderActive);

    // 60 of 100 units free for another line while ordered (100 held − 100 committed = 0,
    // excluding this item's own demand it is the full 100 free → check a fresh probe).
    $committed = Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole();
    expect((int) $committed->quantity)->toBe(100)
        ->and($committed->phase)->toBe(DemandPhase::Committed);

    // --- Partial dispatch 60 → On Hire -----------------------------------
    (new DispatchBulkQuantity)($item, BulkDispatchData::from(['quantity' => '60']));
    expect((float) $item->refresh()->dispatched_quantity)->toBe(60.0)
        ->and($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire);

    // --- Partial return 40 → effective demand = 100 − 40 = 60 ------------
    (new ReturnBulkQuantity)($item->refresh(), BulkReturnData::from(['quantity' => '40']));
    expect((float) $item->refresh()->returned_quantity)->toBe(40.0);
    $demand = Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole();
    expect((int) $demand->quantity)->toBe(60);
});
