<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityStatus;
use App\Models\AvailabilityEvent;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Verbs\Events\Opportunities\OpportunityConvertedToOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

/**
 * Create a Draft opportunity (with dates) carrying a single bulk-product line
 * item, returning [opportunity, item]. The item event seeds Draft-phase demands.
 *
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function makeOpportunityWithLineItem(Store $store): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Resync',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    $product = Product::factory()->rental()->bulk()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '2',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    return [$opportunity->refresh(), $opportunity->items()->firstOrFail()];
}

/**
 * Fetch the single demand row for a line item.
 */
function itemDemand(int $itemId): Demand
{
    return Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $itemId)
        ->sole();
}

it('activates inactive item demands when a quotation is converted to an order', function () {
    [$opportunity, $item] = makeOpportunityWithLineItem($this->store);

    // Draft → Quotation/Provisional: still an inactive Draft-phase demand.
    (new ConvertToQuotation)($opportunity->refresh());

    $demand = itemDemand($item->id);
    expect($demand->phase)->toBe(DemandPhase::Draft)
        ->and($demand->is_active)->toBeFalse();

    // Quotation → Order/Active: the resync must promote the demand to Committed
    // (active), so the order now blocks stock.
    (new ConvertToOrder)($opportunity->refresh());

    $demand = itemDemand($item->id);
    expect($demand->phase)->toBe(DemandPhase::Committed)
        ->and($demand->is_active)->toBeTrue();
});

it('voids active item demands when an order is cancelled', function () {
    [$opportunity, $item] = makeOpportunityWithLineItem($this->store);

    (new ConvertToQuotation)($opportunity->refresh());
    (new ConvertToOrder)($opportunity->refresh());

    expect(itemDemand($item->id)->is_active)->toBeTrue();

    // Order/Active → Order/Cancelled (a terminal status): the resync must void
    // the demand so a dead order no longer blocks stock.
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderCancelled);

    $demand = itemDemand($item->id);
    expect($demand->phase)->toBe(DemandPhase::Void)
        ->and($demand->is_active)->toBeFalse();
});

it('voids active item demands when a quotation is marked lost', function () {
    [$opportunity, $item] = makeOpportunityWithLineItem($this->store);

    // Promote to an active (Reserved/Committed) quotation first.
    (new ConvertToQuotation)($opportunity->refresh());
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationReserved);

    expect(itemDemand($item->id)->is_active)->toBeTrue();

    // Quotation/Reserved → Quotation/Lost (terminal): demands void.
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationLost);

    $demand = itemDemand($item->id);
    expect($demand->phase)->toBe(DemandPhase::Void)
        ->and($demand->is_active)->toBeFalse();
});

it('promotes Draft demands when a non-terminal active status change occurs', function () {
    [$opportunity, $item] = makeOpportunityWithLineItem($this->store);

    // Quotation/Provisional → still a Draft-phase (inactive) demand.
    (new ConvertToQuotation)($opportunity->refresh());
    expect(itemDemand($item->id)->phase)->toBe(DemandPhase::Draft);

    // Quotation/Provisional → Quotation/Reserved (within state, non-terminal):
    // the resync promotes the demand to Committed (active).
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationReserved);

    $demand = itemDemand($item->id);
    expect($demand->phase)->toBe(DemandPhase::Committed)
        ->and($demand->is_active)->toBeTrue();
});

it('leaves demand rows, phases and counts unchanged on replay', function () {
    [$opportunity, $item] = makeOpportunityWithLineItem($this->store);

    (new ConvertToQuotation)($opportunity->refresh());
    (new ConvertToOrder)($opportunity->refresh());

    $demandsBefore = Demand::query()->where('source_id', $item->id)->count();
    $phaseBefore = itemDemand($item->id)->phase;
    $activeBefore = itemDemand($item->id)->is_active;
    $availabilityEventsBefore = AvailabilityEvent::query()->count();

    expect($demandsBefore)->toBe(1)
        ->and($phaseBefore)->toBe(DemandPhase::Committed);

    // Replay re-runs every transition handle(), but the resync is wrapped in
    // Verbs::unlessReplaying(): demand rows, phases and availability_events must
    // be untouched.
    Verbs::replay();

    expect(Demand::query()->where('source_id', $item->id)->count())->toBe($demandsBefore)
        ->and(itemDemand($item->id)->phase)->toBe($phaseBefore)
        ->and(itemDemand($item->id)->is_active)->toBe($activeBefore)
        ->and(AvailabilityEvent::query()->count())->toBe($availabilityEventsBefore);
});

it('rejects converting a closed quotation to an order via the generic guard', function () {
    [$opportunity, $item] = makeOpportunityWithLineItem($this->store);

    (new ConvertToQuotation)($opportunity->refresh());
    // Drive the quotation into a terminal status (Lost) through the generic
    // status-change path.
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationLost);

    // The convert event's generic isClosed() guard must reject this — fired
    // directly so the assertion targets the event guard, not the action's
    // shortage gate.
    expect(fn () => OpportunityConvertedToOrder::fire(
        opportunity_id: $opportunity->refresh()->state_id,
    ))->toThrow(EventNotValid::class);
});
