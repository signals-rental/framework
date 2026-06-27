<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\MarkAssetOnHire;
use App\Actions\Opportunities\PrepareAsset;
use App\Actions\Opportunities\ReturnAsset;
use App\Actions\Opportunities\RevertAssetStatus;
use App\Actions\Opportunities\SetAssetContainer;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\ReturnAssetData;
use App\Data\Opportunities\RevertAssetStatusData;
use App\Data\Opportunities\SetAssetContainerData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\LineItemTransactionType;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Verbs\Events\Opportunities\AssetChecked;
use App\Verbs\Events\Opportunities\AssetContainerCleared;
use App\Verbs\Events\Opportunities\AssetContainerSet;
use App\Verbs\Events\Opportunities\AssetDeallocated;
use App\Verbs\Events\Opportunities\AssetDispatched;
use App\Verbs\Events\Opportunities\AssetOnHire;
use App\Verbs\Events\Opportunities\AssetPreparationReverted;
use App\Verbs\Events\Opportunities\AssetPrepared;
use App\Verbs\Events\Opportunities\AssetReturned;
use App\Verbs\Events\Opportunities\AssetSubstituted;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

/**
 * Covers the FIRST defensive guard in each asset event's handle() —
 * `if ($asset === null) { return; }`, keyed on the `opportunity_item_assets`
 * projection row (and AssetChecked's equivalent). The guard fires when the asset
 * projection row was hard-deleted out from under a later event in the same Verbs
 * stream.
 *
 * Every asset event's validate() reads the Verbs assignment state plus the
 * `opportunity_items` row (via assertAssignmentMutable / assertStatusIn) — NOT the
 * asset projection row itself — so deleting only the asset row leaves validation
 * intact and exercises the handle() guard.
 *
 * Each dataset entry drives the assignment into the status the target event
 * requires, returns the live asset row, then the test deletes that row and fires
 * the event once. The second, item-level guard further inside these handlers
 * (`if ($item === null) { return; }`) is a race-only branch — validate() requires
 * the item row, so it cannot be reached deterministically; see the report.
 */
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
 * Build an Order with a single serialised line and one allocated asset.
 */
function allocatedOrderAsset(Store $store, Product $product): OpportunityItemAsset
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Asset guard', 'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z', 'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name, 'itemable_id' => $product->id, 'itemable_type' => Product::class,
        'quantity' => '1', 'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($opportunity->refresh());
    $item = $opportunity->items()->firstOrFail();

    $stock = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $store->id]);
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $stock->id]));

    return OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
}

it('asset handle() guards a missing projection row', function (callable $drive, callable $fire) {
    // The dataset wraps each closure in `fn () => …` (so Pest does not eagerly
    // invoke it as a lazy provider); unwrap once to get the real callables.
    $drive = $drive();
    $fire = $fire();

    /** @var OpportunityItemAsset $asset */
    $asset = $drive(allocatedOrderAsset($this->store, $this->product));

    expect($asset->status)->not->toBe(AssetAssignmentStatus::Finalised);

    $stateId = $asset->state_id;
    OpportunityItemAsset::query()->whereKey($asset->id)->delete();

    DB::transaction(function () use ($stateId, $fire) {
        $fire($stateId);
        Verbs::commit();
    });

    expect(OpportunityItemAsset::query()->whereKey($asset->id)->exists())->toBeFalse();
})->with([
    // status Allocated → deallocate / dispatch / prepare / container set.
    'deallocated' => [
        fn () => fn (OpportunityItemAsset $a) => $a,
        fn () => fn (int $id) => AssetDeallocated::fire(state_id: $id, reason: 'wrong asset'),
    ],
    'dispatched' => [
        fn () => fn (OpportunityItemAsset $a) => $a,
        fn () => fn (int $id) => AssetDispatched::fire(state_id: $id),
    ],
    'prepared' => [
        fn () => fn (OpportunityItemAsset $a) => $a,
        fn () => fn (int $id) => AssetPrepared::fire(state_id: $id),
    ],
    'container set' => [
        fn () => fn (OpportunityItemAsset $a) => $a,
        fn () => fn (int $id) => AssetContainerSet::fire(
            state_id: $id,
            container_stock_level_id: StockLevel::factory()->serialised()->create()->id,
        ),
    ],
    // status Prepared → revert preparation.
    'preparation reverted' => [
        function () {
            return function (OpportunityItemAsset $a) {
                (new PrepareAsset)($a);

                return $a->refresh();
            };
        },
        fn () => fn (int $id) => AssetPreparationReverted::fire(state_id: $id),
    ],
    // status Dispatched → mark on hire / revert status.
    'on hire' => [
        function () {
            return function (OpportunityItemAsset $a) {
                (new DispatchAsset)($a, DispatchAssetData::from([]));

                return $a->refresh();
            };
        },
        fn () => fn (int $id) => AssetOnHire::fire(state_id: $id),
    ],
    // status OnHire → return.
    'returned' => [
        function () {
            return function (OpportunityItemAsset $a) {
                (new DispatchAsset)($a, DispatchAssetData::from([]));
                (new MarkAssetOnHire)($a->refresh());

                return $a->refresh();
            };
        },
        fn () => fn (int $id) => AssetReturned::fire(state_id: $id),
    ],
    // status CheckedIn → check.
    'checked' => [
        function () {
            return function (OpportunityItemAsset $a) {
                (new DispatchAsset)($a, DispatchAssetData::from([]));
                (new MarkAssetOnHire)($a->refresh());
                (new ReturnAsset)($a->refresh(), ReturnAssetData::from([]));

                return $a->refresh();
            };
        },
        fn () => fn (int $id) => AssetChecked::fire(state_id: $id, condition: 1),
    ],
]);

it('reverting a dispatched asset to allocated clears the dispatched_at milestone', function () {
    $asset = allocatedOrderAsset($this->store, $this->product);
    (new DispatchAsset)($asset, DispatchAssetData::from([]));
    expect($asset->refresh()->dispatched_at)->not->toBeNull();

    // Revert below Dispatched → apply() nulls the dispatched_at milestone so a later
    // replay of the forward events repopulates it cleanly.
    (new RevertAssetStatus)($asset->refresh(), RevertAssetStatusData::from([
        'revert_to' => AssetAssignmentStatus::Allocated->value,
        'reason' => 'scanned wrong asset',
    ]));

    expect($asset->refresh()->status)->toBe(AssetAssignmentStatus::Allocated)
        ->and($asset->dispatched_at)->toBeNull();
});

it('records a per-line return-store override when an asset is returned', function () {
    $asset = allocatedOrderAsset($this->store, $this->product);
    (new DispatchAsset)($asset, DispatchAssetData::from([]));
    (new MarkAssetOnHire)($asset->refresh());

    $returnStore = Store::factory()->create();
    (new ReturnAsset)($asset->refresh(), ReturnAssetData::from(['return_store_id' => $returnStore->id]));

    // The override is persisted onto the line item (consumed for return-store routing).
    $item = $asset->refresh()->item()->firstOrFail();
    expect($item->return_store_id)->toBe($returnStore->id)
        ->and($asset->status)->toBe(AssetAssignmentStatus::CheckedIn);
});

it('asset substituted handle() guards a missing projection row', function () {
    $asset = allocatedOrderAsset($this->store, $this->product);
    $substitute = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id, 'store_id' => $this->store->id,
    ]);

    $stateId = $asset->state_id;
    OpportunityItemAsset::query()->whereKey($asset->id)->delete();

    DB::transaction(function () use ($stateId, $substitute) {
        AssetSubstituted::fire(state_id: $stateId, new_stock_level_id: $substitute->id, reason: 'swap');
        Verbs::commit();
    });

    expect(OpportunityItemAsset::query()->whereKey($asset->id)->exists())->toBeFalse();
});

it('asset container cleared handle() guards a missing projection row', function () {
    $asset = allocatedOrderAsset($this->store, $this->product);
    $container = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id, 'store_id' => $this->store->id,
    ]);
    (new SetAssetContainer)($asset, SetAssetContainerData::from(['container_stock_level_id' => $container->id]));
    $asset->refresh();

    $stateId = $asset->state_id;
    OpportunityItemAsset::query()->whereKey($asset->id)->delete();

    DB::transaction(function () use ($stateId) {
        AssetContainerCleared::fire(state_id: $stateId);
        Verbs::commit();
    });

    expect(OpportunityItemAsset::query()->whereKey($asset->id)->exists())->toBeFalse();
});
