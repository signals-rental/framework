<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RenameOpportunityItem;
use App\Actions\Opportunities\UpdateOpportunityItemDetails;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\RenameOpportunityItemData;
use App\Data\Opportunities\UpdateOpportunityItemDetailsData;
use App\Enums\LineItemTransactionType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Verbs\Events\Opportunities\BulkQuantityAdjusted;
use App\Verbs\Events\Opportunities\ItemDatesChanged;
use App\Verbs\Events\Opportunities\ItemDetailsUpdated;
use App\Verbs\Events\Opportunities\ItemDiscountSet;
use App\Verbs\Events\Opportunities\ItemOptionalToggled;
use App\Verbs\Events\Opportunities\ItemPriceOverridden;
use App\Verbs\Events\Opportunities\ItemQuantityChanged;
use App\Verbs\Events\Opportunities\ItemRemoved;
use App\Verbs\Events\Opportunities\ItemRenamed;
use App\Verbs\Events\Opportunities\ItemsRestructured;
use App\Verbs\Events\Opportunities\ItemSubstituted;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

/**
 * Covers the defensive `if ($item === null) { return; }` guard in each item
 * event's handle(). The guard fires when the line-item projection row was
 * hard-deleted out from under a later event in the same Verbs stream — a real
 * edge case the projection code defends against.
 *
 * Mechanism: drive the line item into the state where the target event is valid,
 * then hard-delete ONLY the `opportunity_items` projection row (the Verbs state
 * survives, so validate() — which reads the state / the parent opportunity row —
 * still passes), then fire the target event once. handle() finds no row and
 * returns; the assertion confirms the no-op (no exception, the row is NOT
 * resurrected, and no errant projection write occurs).
 */
beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

/**
 * Build a draft opportunity with a single manual-priced line item.
 */
function itemGuardLine(): OpportunityItem
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Guarded items']));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'PA Stack', 'quantity' => '2', 'unit_price' => 5000,
    ]));

    return $opportunity->items()->latest('id')->firstOrFail();
}

/**
 * @param  callable(int): mixed  $fire  receives the line item's Verbs state_id
 */
function fireOnMissingItem(OpportunityItem $item, callable $fire): void
{
    $stateId = $item->state_id;
    OpportunityItem::query()->whereKey($item->id)->delete();

    DB::transaction(function () use ($stateId, $fire) {
        $fire($stateId);
        Verbs::commit();
    });

    // No-op: the deleted row is not resurrected and no exception escaped.
    expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeFalse();
}

it('item handle() guards a missing projection row', function (callable $fire) {
    // The dataset wraps each closure in `fn () => …` so Pest does not eagerly
    // invoke it as a lazy provider; unwrap once to get the real fire callable.
    fireOnMissingItem(itemGuardLine(), $fire());
})->with([
    'dates changed' => [fn () => fn (int $id) => ItemDatesChanged::fire(
        opportunity_item_id: $id, starts_at: '2026-07-01T09:00:00Z', ends_at: '2026-07-05T17:00:00Z',
    )],
    'discount set' => [fn () => fn (int $id) => ItemDiscountSet::fire(opportunity_item_id: $id, discount_percent: '5')],
    'optional toggled' => [fn () => fn (int $id) => ItemOptionalToggled::fire(opportunity_item_id: $id, is_optional: true)],
    'price overridden' => [fn () => fn (int $id) => ItemPriceOverridden::fire(opportunity_item_id: $id, unit_price: 6000)],
    'quantity changed' => [fn () => fn (int $id) => ItemQuantityChanged::fire(opportunity_item_id: $id, quantity: '3')],
    'details updated' => [fn () => fn (int $id) => ItemDetailsUpdated::fire(opportunity_item_id: $id, description: 'New desc', notes: 'New notes')],
    'renamed' => [fn () => fn (int $id) => ItemRenamed::fire(opportunity_item_id: $id, name: 'Renamed line')],
    'removed' => [fn () => fn (int $id) => ItemRemoved::fire(opportunity_item_id: $id)],
    'substituted' => [fn () => fn (int $id) => ItemSubstituted::fire(opportunity_item_id: $id, name: 'Lighting Rig')],
    'restructured' => [fn () => fn (int $id) => ItemsRestructured::fire(opportunity_item_id: $id, path: '2')],
]);

it('renames a line item and updates its details on the happy path', function () {
    $item = itemGuardLine();

    (new RenameOpportunityItem)($item, RenameOpportunityItemData::from(['name' => 'Headline Rig']));
    expect($item->refresh()->name)->toBe('Headline Rig');

    (new UpdateOpportunityItemDetails)($item->refresh(), UpdateOpportunityItemDetailsData::from([
        'description' => 'Full PA with subs', 'notes' => 'Deliver Friday',
    ]));

    $item->refresh();
    expect($item->description)->toBe('Full PA with subs')
        ->and($item->notes)->toBe('Deliver Friday');
});

it('bulk quantity adjusted handle() guards a missing projection row', function () {
    $store = Store::factory()->create();
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id, 'store_id' => $store->id, 'quantity_held' => 100,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Bulk', 'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z', 'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name, 'itemable_id' => $product->id, 'itemable_type' => Product::class,
        'quantity' => '50', 'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($opportunity->refresh());
    $item = $opportunity->items()->firstOrFail();

    // BulkQuantityAdjusted::validate reads only the state + parent opportunity row,
    // so deleting the line row leaves validation intact for the guard test.
    fireOnMissingItem($item, fn (int $id) => BulkQuantityAdjusted::fire(
        opportunity_item_id: $id, new_quantity: '40', reason: 'less needed',
    ));
});
