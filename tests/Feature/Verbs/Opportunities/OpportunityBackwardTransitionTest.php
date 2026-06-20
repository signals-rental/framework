<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\ReinstateOpportunity;
use App\Actions\Opportunities\RevertToQuotation;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * Build a Quotation opportunity carrying a single manual-priced line.
 */
function backwardQuotation(Store $store): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Backward transitions',
        'store_id' => $store->id,
        'starts_at' => '2026-11-01T09:00:00Z',
        'ends_at' => '2026-11-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'PA Stack',
        'quantity' => '2',
        'unit_price' => 5000,
    ]));

    (new ConvertToQuotation)($opportunity->refresh());

    return $opportunity->refresh();
}

// ---------------------------------------------------------------------------
// Reinstate
// ---------------------------------------------------------------------------

it('reinstates a lost quotation back to provisional', function () {
    $opportunity = backwardQuotation($this->store);
    (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::QuotationLost);

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::QuotationLost);

    (new ReinstateOpportunity)($opportunity->refresh(), 'client came back');

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::QuotationProvisional);
});

it('reinstates a postponed quotation back to provisional', function () {
    $opportunity = backwardQuotation($this->store);
    (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::QuotationPostponed);

    (new ReinstateOpportunity)($opportunity->refresh());

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::QuotationProvisional);
});

it('reinstates a cancelled order back to active', function () {
    $opportunity = backwardQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderCancelled);

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::OrderCancelled);

    (new ReinstateOpportunity)($opportunity->refresh());

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::OrderActive);
});

it('rejects reinstating an opportunity that is already active', function () {
    $opportunity = backwardQuotation($this->store);

    (new ReinstateOpportunity)($opportunity->refresh());
})->throws(EventNotValid::class);

it('forbids reinstating without the opportunities.edit permission', function () {
    $opportunity = backwardQuotation($this->store);
    (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::QuotationLost);

    $viewer = User::factory()->create();
    $viewer->assignRole('Read Only');
    $this->actingAs($viewer);

    expect($viewer->can('opportunities.edit'))->toBeFalse();

    (new ReinstateOpportunity)($opportunity->refresh());
})->throws(AuthorizationException::class);

it('records an opportunity.reinstated audit row', function () {
    $opportunity = backwardQuotation($this->store);
    (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::QuotationLost);

    (new ReinstateOpportunity)($opportunity->refresh());

    expect(ActionLog::query()->where('auditable_id', $opportunity->id)->where('action', 'opportunity.reinstated')->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Revert to quotation
// ---------------------------------------------------------------------------

it('reverts an undispatched order back to a provisional quotation and releases the locks', function () {
    $opportunity = backwardQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());
    $opportunity->refresh();

    expect($opportunity->state)->toBe(OpportunityState::Order)
        ->and($opportunity->exchange_rate_locked)->toBeTrue()
        ->and($opportunity->tax_locked)->toBeTrue();

    (new RevertToQuotation)($opportunity->refresh(), 'confirmed too early');
    $opportunity->refresh();

    expect($opportunity->state)->toBe(OpportunityState::Quotation)
        ->and($opportunity->statusEnum())->toBe(OpportunityStatus::QuotationProvisional)
        ->and($opportunity->exchange_rate_locked)->toBeFalse()
        ->and($opportunity->tax_locked)->toBeFalse();
});

it('rejects reverting an order with a dispatched asset', function () {
    $product = Product::factory()->rental()->serialised()->create();
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Dispatched revert',
        'store_id' => $this->store->id,
        'starts_at' => '2026-11-01T09:00:00Z',
        'ends_at' => '2026-11-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '1',
    ]));
    (new ConvertToOrder)($opportunity->refresh());
    $item = $opportunity->items()->firstOrFail();
    $stock = StockLevel::factory()->serialised()->create(['product_id' => $product->id, 'store_id' => $this->store->id]);
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $stock->id]));
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
    (new DispatchAsset)($row, DispatchAssetData::from([]));

    expect(fn () => (new RevertToQuotation)($opportunity->refresh()))->toThrow(EventNotValid::class);

    // Atomic: still an Order.
    expect($opportunity->fresh()->state)->toBe(OpportunityState::Order);
});

it('rejects reverting a quotation (not an order) to a quotation', function () {
    $opportunity = backwardQuotation($this->store);

    (new RevertToQuotation)($opportunity->refresh());
})->throws(EventNotValid::class);

it('replays the reverted-to-quotation event to the same projection', function () {
    $opportunity = backwardQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());
    (new RevertToQuotation)($opportunity->refresh());

    Verbs::commit();
    Opportunity::query()->whereKey($opportunity->id)->update([
        'state' => OpportunityState::Order->value,
        'status' => OpportunityStatus::OrderActive->statusValue(),
        'exchange_rate_locked' => true,
        'tax_locked' => true,
    ]);

    Verbs::replay();

    $replayed = Opportunity::query()->whereKey($opportunity->id)->firstOrFail();
    expect($replayed->state)->toBe(OpportunityState::Quotation)
        ->and($replayed->exchange_rate_locked)->toBeFalse()
        ->and($replayed->tax_locked)->toBeFalse();
});
