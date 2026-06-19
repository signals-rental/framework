<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\CheckAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DeallocateAsset;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\PrepareAsset;
use App\Actions\Opportunities\ReturnAsset;
use App\Actions\Opportunities\RevertAssetStatus;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CheckAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\ReturnAssetData;
use App\Data\Opportunities\RevertAssetStatusData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Enums\ReleasePoint;
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
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Exceptions\EventNotValid;

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->rental()->serialised()->create();
});

/**
 * Build an event-sourced Quotation carrying a single serialised line item,
 * returning [opportunity, item]. Stops at the Quotation state so the caller can
 * decide whether (and with what items) to convert it.
 *
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function makeInvariantQuotation(Store $store, Product $product, string $quantity = '1'): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Invariant',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => $quantity,
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    return [$opportunity->refresh(), $opportunity->items()->firstOrFail()];
}

/**
 * Build an event-sourced Order with a single serialised line item.
 *
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function makeInvariantOrder(Store $store, Product $product, string $quantity = '1'): array
{
    [$opportunity, $item] = makeInvariantQuotation($store, $product, $quantity);
    (new ConvertToOrder)($opportunity->refresh());

    return [$opportunity->refresh(), $item];
}

function makeInvariantAsset(Store $store, Product $product): StockLevel
{
    return StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
}

function allocateInvariant(OpportunityItem $item, StockLevel $asset): OpportunityItemAsset
{
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    return OpportunityItemAsset::query()
        ->where('opportunity_item_id', $item->id)
        ->where('stock_level_id', $asset->id)
        ->sole();
}

// ---------------------------------------------------------------------------
// FIX 1 — generic lifecycle guards (convert / cancel / complete)
// ---------------------------------------------------------------------------

it('rejects converting a quotation with zero items to an order', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Empty quote',
        'store_id' => $this->store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    expect(fn () => (new ConvertToOrder)($opportunity->refresh()))
        ->toThrow(EventNotValid::class);
});

it('allows converting a quotation that has at least one item', function () {
    [$opportunity] = makeInvariantQuotation($this->store, $this->product);

    (new ConvertToOrder)($opportunity->refresh());

    expect($opportunity->refresh()->state)->toBe(OpportunityState::Order)
        ->and($opportunity->statusEnum())->toBe(OpportunityStatus::OrderActive);
});

it('rejects cancelling an order while an asset is still on hire', function () {
    [$opportunity, $item] = makeInvariantOrder($this->store, $this->product);
    $row = allocateInvariant($item, makeInvariantAsset($this->store, $this->product));

    // Asset out with the client → Dispatched/OnHire.
    (new DispatchAsset)($row, DispatchAssetData::from([]));
    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::Dispatched);

    expect(fn () => (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderCancelled))
        ->toThrow(EventNotValid::class);
});

it('allows cancelling an order with no assets out', function () {
    [$opportunity, $item] = makeInvariantOrder($this->store, $this->product);
    // Allocated but never dispatched — nothing is physically out.
    allocateInvariant($item, makeInvariantAsset($this->store, $this->product));

    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderCancelled);

    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderCancelled);
});

it('rejects completing an order with an unreturned asset', function () {
    [$opportunity, $item] = makeInvariantOrder($this->store, $this->product);
    $row = allocateInvariant($item, makeInvariantAsset($this->store, $this->product));

    (new DispatchAsset)($row, DispatchAssetData::from([]));

    expect(fn () => (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderComplete))
        ->toThrow(EventNotValid::class);
});

it('allows completing an order once all assets are finalised', function () {
    [$opportunity, $item] = makeInvariantOrder($this->store, $this->product);
    $row = allocateInvariant($item, makeInvariantAsset($this->store, $this->product));

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([]));
    (new CheckAsset)($row->refresh(), CheckAssetData::from(['condition' => AssetCondition::Good->value]));
    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::Finalised);

    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderComplete);

    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderComplete);
});

// ---------------------------------------------------------------------------
// FIX 2 — postponed demand is HELD (still active), not released
// ---------------------------------------------------------------------------

it('postponing a reserved quote retains its demand as held (still active)', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);
    [$opportunity, $item] = makeInvariantQuotation($this->store, $product, '2');

    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationReserved);
    $reserved = Demand::query()->where('source_id', $item->id)->sole();
    expect($reserved->phase)->toBe(DemandPhase::Committed)
        ->and($reserved->is_active)->toBeTrue();

    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationPostponed);

    $held = Demand::query()->where('source_id', $item->id)->sole();
    expect($held->phase)->toBe(DemandPhase::Held)
        ->and($held->is_active)->toBeTrue();
});

it('reinstating a postponed quote restores the committed demand', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);
    [$opportunity, $item] = makeInvariantQuotation($this->store, $product, '2');

    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationReserved);
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationPostponed);
    expect(Demand::query()->where('source_id', $item->id)->sole()->phase)->toBe(DemandPhase::Held);

    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationReserved);

    $restored = Demand::query()->where('source_id', $item->id)->sole();
    expect($restored->phase)->toBe(DemandPhase::Committed)
        ->and($restored->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// FIX 3 — configurable release_point governs the Operational → Closed boundary
// ---------------------------------------------------------------------------

it('maps the Returned demand phase per release point', function (ReleasePoint $releasePoint, DemandPhase $returnedPhase, DemandPhase $checkedPhase) {
    expect(OpportunityStatus::OrderReturned->phase($releasePoint))->toBe($returnedPhase)
        ->and(OpportunityStatus::OrderChecked->phase($releasePoint))->toBe($checkedPhase);
})->with([
    // Returned (default): a physical return closes the demand.
    'returned' => [ReleasePoint::Returned, DemandPhase::Closed, DemandPhase::Closed],
    // Off-hired: documented to map to the same Returned boundary (no distinct status).
    'off_hired' => [ReleasePoint::OffHired, DemandPhase::Closed, DemandPhase::Closed],
    // Checked (strict): a Returned order still occupies (Operational) until inspection.
    'checked' => [ReleasePoint::Checked, DemandPhase::Operational, DemandPhase::Closed],
]);

it('reads availability.release_point so a checked-policy returned order keeps an active occupying demand', function () {
    settings()->set('availability.release_point', ReleasePoint::Checked->value);

    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);
    [$opportunity, $item] = makeInvariantOrder($this->store, $product, '2');

    // Drive the order's status to Returned via the generic status-change path,
    // which re-syncs demand reading the configured release point.
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderReturned);

    // Under the strict `checked` release point a Returned order still occupies the
    // unit: the line demand stays Operational (active), not Closed.
    $demand = Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole();
    expect($demand->phase)->toBe(DemandPhase::Operational)
        ->and($demand->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// FIX 4 — AssetStatusReverted ghost-state guard
// ---------------------------------------------------------------------------

it('rejects reverting the status of a deallocated asset assignment', function () {
    [, $item] = makeInvariantOrder($this->store, $this->product);
    $row = allocateInvariant($item, makeInvariantAsset($this->store, $this->product));

    // Prepare (status Prepared = 1) so a revert_to Allocated (0) would otherwise be
    // a legitimate backwards step — then deallocate (valid while Prepared), which
    // hard-deletes the projection row but leaves the assignment state "removed".
    (new PrepareAsset)($row);
    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::Prepared);

    // Capture the (stale) projection instance BEFORE deallocating — the row is
    // hard-deleted, so this is the only handle on the removed assignment.
    $removedRow = $row->fresh();
    (new DeallocateAsset)($removedRow, 'wrong asset');
    expect(OpportunityItemAsset::query()->whereKey($removedRow->id)->exists())->toBeFalse();

    // Reverting a removed assignment must be rejected by the ghost-state guard
    // (assertAssignmentNotRemoved), which fires before the revert_to < status check.
    expect(fn () => (new RevertAssetStatus)($removedRow, RevertAssetStatusData::from([
        'revert_to' => AssetAssignmentStatus::Allocated->value,
    ])))->toThrow(EventNotValid::class);
});
