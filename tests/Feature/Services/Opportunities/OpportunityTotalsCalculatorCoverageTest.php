<?php

use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

function totalsCalc(): OpportunityTotalsCalculator
{
    return app(OpportunityTotalsCalculator::class);
}

function wireCovTax(): Member
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

it('returns early when recalculating an item with no parent opportunity', function () {
    // Unsaved item with no opportunity_id -> opportunity()->first() is null.
    $item = new OpportunityItem(['quantity' => '1', 'unit_price' => 1000]);

    totalsCalc()->recalculateItem($item);

    // No exception, and nothing priced onto the detached model.
    expect($item->total)->toBeNull();
});

it('returns early when recalculating a cost with no parent opportunity', function () {
    $cost = new OpportunityCost(['amount' => 1000, 'quantity' => '1']);

    totalsCalc()->recalculateCost($cost);

    expect($cost->tax_rate)->toBeNull();
});

it('strips embedded tax from an inclusive-priced cost when rolling up', function () {
    $member = wireCovTax();
    $opportunity = Opportunity::factory()->create([
        'member_id' => $member->id,
        'prices_include_tax' => true,
        'tax_total' => 0,
        'charge_total' => 0,
        'charge_excluding_tax_total' => 0,
        'charge_including_tax_total' => 0,
    ]);

    // 12000 gross inclusive @ 20% -> net 10000, tax 2000 (costNet inclusive branch).
    OpportunityCost::factory()->create([
        'opportunity_id' => $opportunity->id,
        'amount' => 12000,
        'quantity' => '1',
        'is_optional' => false,
    ]);

    totalsCalc()->rollUp($opportunity->refresh());

    $opportunity->refresh();

    expect($opportunity->charge_total)->toBe(10000)
        ->and($opportunity->tax_total)->toBe(2000)
        ->and($opportunity->charge_including_tax_total)->toBe(12000);
});
