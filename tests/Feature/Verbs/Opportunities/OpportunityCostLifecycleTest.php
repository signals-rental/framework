<?php

use App\Actions\Opportunities\AddOpportunityCost;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RemoveOpportunityCost;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\UpdateOpportunityCost;
use App\Data\Opportunities\AddOpportunityCostData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\UpdateOpportunityCostData;
use App\Enums\OpportunityCostType;
use App\Enums\OpportunityStatus;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

/**
 * Wire a 20% tax rule keyed to a member tax class + the DEFAULT product tax class
 * (costs have no product, so they tax against the default class).
 */
function wireCostTax(): Member
{
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => '20.0000', 'is_active' => true]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    return Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeCostOpportunity(array $attributes = []): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(array_merge(['subject' => 'Costs'], $attributes)));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('adds a cost, projects the row, and rolls the net into the opportunity totals', function () {
    $opportunity = makeCostOpportunity();

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Crew',
        'cost_type' => OpportunityCostType::Labour->value,
        'amount' => 5000, // £50.00 minor units
        'quantity' => '2',
    ]));

    $cost = $opportunity->costs()->firstOrFail();

    expect($cost->amount)->toBe(5000)
        ->and((string) $cost->quantity)->toBe('2.00')
        ->and($cost->cost_type)->toBe(OpportunityCostType::Labour);

    $opportunity->refresh();
    // No tax wired here → net == gross == 2 * 5000.
    expect($opportunity->charge_excluding_tax_total)->toBe(10000)
        ->and($opportunity->tax_total)->toBe(0)
        ->and($opportunity->charge_total)->toBe(10000)
        ->and($opportunity->service_charge_total)->toBe(10000)
        ->and($opportunity->transit_charge_total)->toBe(0)
        ->and($opportunity->loss_damage_charge_total)->toBe(0);
});

it('taxes a cost on top in exclusive mode and rolls it into the totals', function () {
    $member = wireCostTax();
    $opportunity = makeCostOpportunity(['member_id' => $member->id]);

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Insurance',
        'cost_type' => OpportunityCostType::Insurance->value,
        'amount' => 10000, // £100.00
        'quantity' => '1',
    ]));

    $cost = $opportunity->costs()->firstOrFail();
    expect((string) $cost->tax_rate)->toBe('20.00');

    $opportunity->refresh();
    expect($opportunity->charge_excluding_tax_total)->toBe(10000)
        ->and($opportunity->tax_total)->toBe(2000)
        ->and($opportunity->charge_including_tax_total)->toBe(12000)
        ->and($opportunity->charge_total)->toBe(10000)
        ->and($opportunity->service_charge_total)->toBe(10000);
});

it('routes delivery costs to the transit total and loss-damage to its own total', function () {
    $opportunity = makeCostOpportunity();

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Van', 'cost_type' => OpportunityCostType::Delivery->value, 'amount' => 3000, 'quantity' => '1',
    ]));
    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Waiver', 'cost_type' => OpportunityCostType::LossDamage->value, 'amount' => 1500, 'quantity' => '1',
    ]));

    $opportunity->refresh();

    expect($opportunity->transit_charge_total)->toBe(3000)
        ->and($opportunity->loss_damage_charge_total)->toBe(1500)
        ->and($opportunity->service_charge_total)->toBe(0)
        ->and($opportunity->sub_rental_charge_total)->toBe(0)
        ->and($opportunity->charge_total)->toBe(4500);
});

it('updates a cost and recomputes the totals', function () {
    $opportunity = makeCostOpportunity();

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Crew', 'amount' => 5000, 'quantity' => '1',
    ]));
    $cost = $opportunity->costs()->firstOrFail();

    (new UpdateOpportunityCost)($cost->refresh(), UpdateOpportunityCostData::from([
        'amount' => 8000,
        'quantity' => '3',
    ]));

    $cost->refresh();
    expect($cost->amount)->toBe(8000)
        ->and((string) $cost->quantity)->toBe('3.00');

    $opportunity->refresh();
    expect($opportunity->charge_total)->toBe(24000) // 3 * 8000
        ->and($opportunity->service_charge_total)->toBe(24000);
});

it('leaves omitted fields untouched on a partial update', function () {
    $opportunity = makeCostOpportunity();

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Original', 'cost_type' => OpportunityCostType::Delivery->value, 'amount' => 5000, 'quantity' => '1',
    ]));
    $cost = $opportunity->costs()->firstOrFail();

    // Update only the amount; description + cost_type must persist.
    (new UpdateOpportunityCost)($cost->refresh(), UpdateOpportunityCostData::from(['amount' => 9000]));

    $cost->refresh();
    expect($cost->description)->toBe('Original')
        ->and($cost->cost_type)->toBe(OpportunityCostType::Delivery)
        ->and($cost->amount)->toBe(9000);
});

it('excludes optional costs from the totals', function () {
    $opportunity = makeCostOpportunity();

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Optional extra', 'amount' => 5000, 'quantity' => '1', 'is_optional' => true,
    ]));

    $opportunity->refresh();
    expect($opportunity->charge_total)->toBe(0)
        ->and($opportunity->service_charge_total)->toBe(0);
});

it('removes a cost and rolls the totals back down', function () {
    $opportunity = makeCostOpportunity();

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Crew', 'amount' => 5000, 'quantity' => '2',
    ]));
    $cost = $opportunity->costs()->firstOrFail();

    expect($opportunity->fresh()->charge_total)->toBe(10000);

    (new RemoveOpportunityCost)($cost->refresh());

    expect(OpportunityCost::query()->whereKey($cost->id)->exists())->toBeFalse()
        ->and($opportunity->fresh()->charge_total)->toBe(0);
});

it('rejects mutating a cost on a closed opportunity', function () {
    $opportunity = makeCostOpportunity();
    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Crew', 'amount' => 5000, 'quantity' => '1',
    ]));
    $cost = $opportunity->costs()->firstOrFail();

    (new ConvertToQuotation)($opportunity->refresh());
    // An order must carry at least one line item to be confirmed
    // (opportunity-lifecycle.md §12.1 convert guard).
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Line', 'quantity' => '1', 'unit_price' => 5000,
    ]));
    (new ConvertToOrder)($opportunity->refresh());
    $opportunity->refresh();
    $opportunity->update(['status' => OpportunityStatus::OrderCancelled->statusValue()]);

    expect(fn () => (new UpdateOpportunityCost)($cost->refresh(), UpdateOpportunityCostData::from(['amount' => 9999])))
        ->toThrow(EventNotValid::class);
});

it('combines items and costs into the charge total, and the deal-total override still wins', function () {
    $opportunity = makeCostOpportunity();

    // Manual-priced item: 2 * £50.00 = £100.00 net (no tax wired).
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'PA Stack', 'quantity' => '2', 'unit_price' => 5000,
    ]));
    // Cost: £30.00 delivery.
    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Van', 'cost_type' => OpportunityCostType::Delivery->value, 'amount' => 3000, 'quantity' => '1',
    ]));

    $opportunity->refresh();
    expect($opportunity->charge_excluding_tax_total)->toBe(13000) // 10000 item + 3000 cost
        ->and($opportunity->charge_total)->toBe(13000)
        ->and($opportunity->rental_charge_total)->toBe(10000)
        ->and($opportunity->transit_charge_total)->toBe(3000);

    // A manual deal-total override replaces the computed headline.
    (new SetDealPrice)($opportunity->refresh(), SetDealPriceData::from(['deal_total' => 20000]));

    $opportunity->refresh();
    expect($opportunity->charge_total)->toBe(20000)
        // The deal is a NET override of BOTH headline figures (charge_total AND
        // charge_excluding_tax_total), but the per-type component total still
        // reflects the real lines — proving the override only touches the headline.
        ->and($opportunity->charge_excluding_tax_total)->toBe(20000)
        ->and($opportunity->transit_charge_total)->toBe(3000);
});

it('rebuilds the cost projection and totals idempotently on replay', function () {
    $opportunity = makeCostOpportunity();

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Van', 'cost_type' => OpportunityCostType::Delivery->value, 'amount' => 3000, 'quantity' => '2',
    ]));
    $cost = $opportunity->costs()->firstOrFail();

    $chargeBefore = (int) $opportunity->fresh()->charge_total;
    $transitBefore = (int) $opportunity->fresh()->transit_charge_total;
    expect($chargeBefore)->toBe(6000);

    Verbs::replay();

    $cost->refresh();
    $opportunity->refresh();

    expect(OpportunityCost::query()->where('opportunity_id', $opportunity->id)->count())->toBe(1)
        ->and($cost->amount)->toBe(3000)
        ->and($opportunity->charge_total)->toBe($chargeBefore)
        ->and($opportunity->transit_charge_total)->toBe($transitBefore);
});
