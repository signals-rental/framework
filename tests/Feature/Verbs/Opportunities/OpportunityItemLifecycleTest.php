<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemDates;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ClearDealPrice;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\SubstituteItem;
use App\Actions\Opportunities\ToggleItemOptional;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemDatesData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Data\Opportunities\SubstituteItemData;
use App\Data\Opportunities\ToggleItemOptionalData;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Exceptions\EventNotValid;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

/**
 * Create a real event-sourced opportunity (so it has a Verbs state_id usable by
 * the item events) with an ad-hoc manual-priced line.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeOpportunity(array $attributes = []): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(array_merge(['subject' => 'Items'], $attributes)));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function addManualItem(Opportunity $opportunity, array $overrides = []): OpportunityItem
{
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from(array_merge([
        'name' => 'PA Stack',
        'quantity' => '2',
        'unit_price' => 5000, // £50.00 manual price, minor units
    ], $overrides)));

    return $opportunity->items()->latest('id')->firstOrFail();
}

it('adds a line item, projects the row, and rolls totals up to the parent', function () {
    $opportunity = makeOpportunity();

    $item = addManualItem($opportunity);

    expect($item->unit_price)->toBe(5000)
        ->and($item->total)->toBe(10000) // 2 * 5000
        ->and($item->name)->toBe('PA Stack');

    $opportunity->refresh();
    // No tax wired -> excluding == including == charge_total.
    expect($opportunity->charge_excluding_tax_total)->toBe(10000)
        ->and($opportunity->charge_including_tax_total)->toBe(10000)
        ->and($opportunity->rental_charge_total)->toBe(10000)
        ->and($opportunity->charge_total)->toBe(10000);
});

it('recomputes totals when the quantity changes', function () {
    $opportunity = makeOpportunity();
    $item = addManualItem($opportunity);

    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '5']));

    $item->refresh();
    $opportunity->refresh();

    expect($item->total)->toBe(25000) // 5 * 5000
        ->and($opportunity->charge_total)->toBe(25000);
});

it('recomputes totals when the unit price is overridden', function () {
    $opportunity = makeOpportunity();
    $item = addManualItem($opportunity);

    (new OverrideItemPrice)($item->refresh(), OverrideItemPriceData::from(['unit_price' => 8000]));

    $item->refresh();
    expect($item->unit_price)->toBe(8000)
        ->and($item->total)->toBe(16000); // 2 * 8000
});

it('applies a percentage discount before tax', function () {
    $opportunity = makeOpportunity();
    $item = addManualItem($opportunity); // total 10000

    (new SetItemDiscount)($item->refresh(), SetItemDiscountData::from(['discount_percent' => '10']));

    $item->refresh();
    // 10% of 10000 = 1000 discount -> 9000 net
    expect($item->total)->toBe(9000)
        ->and($opportunity->refresh()->charge_total)->toBe(9000);
});

it('recomputes totals when the item dates change', function () {
    $opportunity = makeOpportunity();
    $item = addManualItem($opportunity);

    // Manual-priced lines do not depend on duration, so the total is stable, but
    // the event must run end to end and re-roll the parent.
    (new ChangeItemDates)($item->refresh(), ChangeItemDatesData::from([
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    $item->refresh();
    expect($item->starts_at)->not->toBeNull()
        ->and($item->total)->toBe(10000)
        ->and($opportunity->refresh()->charge_total)->toBe(10000);
});

it('substitutes the catalogue reference and re-prices', function () {
    $opportunity = makeOpportunity();
    $item = addManualItem($opportunity);

    (new SubstituteItem)($item->refresh(), SubstituteItemData::from([
        'name' => 'Lighting Rig',
    ]));

    $item->refresh();
    expect($item->name)->toBe('Lighting Rig')
        // Still a manual-priced ad-hoc line (no product), so price is retained.
        ->and($item->total)->toBe(10000);
});

it('excludes optional items from the parent totals', function () {
    $opportunity = makeOpportunity();
    $item = addManualItem($opportunity); // 10000 counts

    expect($opportunity->refresh()->charge_total)->toBe(10000);

    (new ToggleItemOptional)($item->refresh(), ToggleItemOptionalData::from(['is_optional' => true]));

    expect($opportunity->refresh()->charge_total)->toBe(0)
        ->and($opportunity->charge_excluding_tax_total)->toBe(0);

    (new ToggleItemOptional)($item->refresh(), ToggleItemOptionalData::from(['is_optional' => false]));

    expect($opportunity->refresh()->charge_total)->toBe(10000);
});

it('removes a line item, deletes the row, and rolls totals back down', function () {
    $opportunity = makeOpportunity();
    $item = addManualItem($opportunity);

    expect($opportunity->refresh()->charge_total)->toBe(10000);

    (new RemoveOpportunityItem)($item->refresh());

    expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeFalse()
        ->and($opportunity->refresh()->charge_total)->toBe(0);
});

it('overrides the headline charge_total via a deal price and restores it on clear', function () {
    $opportunity = makeOpportunity();
    addManualItem($opportunity); // computed 10000

    (new SetDealPrice)($opportunity->refresh(), SetDealPriceData::from(['deal_total' => 7500]));

    $opportunity->refresh();
    expect($opportunity->deal_total)->toBe(7500)
        ->and($opportunity->charge_total)->toBe(7500)
        // Per-type / net totals still reflect the lines, not the override.
        ->and($opportunity->charge_excluding_tax_total)->toBe(10000);

    (new ClearDealPrice)($opportunity->refresh());

    $opportunity->refresh();
    expect($opportunity->deal_total)->toBeNull()
        ->and($opportunity->charge_total)->toBe(10000);
});

it('rejects mutating a line item on a closed opportunity', function () {
    $opportunity = makeOpportunity();
    $item = addManualItem($opportunity);

    // Drive the opportunity into a closed/terminal status.
    (new ConvertToQuotation)($opportunity->refresh());
    (new ConvertToOrder)($opportunity->refresh());

    $opportunity->refresh();
    // Move the order into a closed status (e.g. Cancelled).
    $opportunity->update(['status' => OpportunityStatus::OrderCancelled->statusValue()]);

    expect(fn () => (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '9'])))
        ->toThrow(EventNotValid::class);
});
