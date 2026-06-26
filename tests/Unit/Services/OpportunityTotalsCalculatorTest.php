<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

function totalsCalculator(): OpportunityTotalsCalculator
{
    return app(OpportunityTotalsCalculator::class);
}

function wireTotalsTax(): Member
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

it('uses the deal total as the net headline while keeping component totals from real lines', function () {
    $member = wireTotalsTax();
    $store = Store::factory()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Deal cap',
        'member_id' => $member->id,
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T09:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Line A',
        'quantity' => '1',
        'unit_price' => 10000,
    ]));

    $opportunity->refresh()->forceFill(['deal_total' => 7500])->saveQuietly();

    totalsCalculator()->rollUp($opportunity->refresh());

    $opportunity->refresh();

    expect($opportunity->charge_total)->toBe(7500)
        ->and($opportunity->charge_excluding_tax_total)->toBe(7500)
        ->and($opportunity->tax_total)->toBe(1500)
        ->and($opportunity->charge_including_tax_total)->toBe(9000)
        ->and($opportunity->rental_charge_total)->toBe(10000);
});

it('preserves locked tax totals during rollUp even when line nets change', function () {
    $opportunity = Opportunity::factory()->order()->create([
        'tax_total' => 321,
        'charge_including_tax_total' => 5321,
        'charge_total' => 5000,
        'charge_excluding_tax_total' => 5000,
        'tax_locked' => true,
    ]);

    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quantity' => '1',
        'unit_price' => 9000,
        'total' => 9000,
        'is_optional' => false,
    ]);

    totalsCalculator()->rollUp($opportunity->refresh());

    $opportunity->refresh();

    expect($opportunity->tax_total)->toBe(321)
        ->and($opportunity->charge_including_tax_total)->toBe(9321)
        ->and($opportunity->charge_total)->toBe(9000)
        ->and($item->refresh()->total)->toBe(9000);
});

it('keeps the locked tax frozen even when a deal_total override is set on a locked order', function () {
    $opportunity = Opportunity::factory()->order()->create([
        'tax_total' => 321,
        'charge_including_tax_total' => 5321,
        'charge_total' => 5000,
        'charge_excluding_tax_total' => 5000,
        'tax_locked' => true,
        'deal_total' => 7500,
    ]);

    OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quantity' => '1',
        'unit_price' => 9000,
        'total' => 9000,
        'is_optional' => false,
    ]);

    totalsCalculator()->rollUp($opportunity->refresh());

    $opportunity->refresh();

    // tax_locked wins over the deal_total blended-tax pass: tax stays exactly as
    // locked, and the net headline follows the deal_total override (not the lines).
    expect($opportunity->tax_total)->toBe(321)
        ->and($opportunity->charge_total)->toBe(7500)
        ->and($opportunity->charge_excluding_tax_total)->toBe(7500)
        ->and($opportunity->charge_including_tax_total)->toBe(7821);
});

it('reverts charge_total to the line sum when deal_total is cleared on a locked order, tax still frozen', function () {
    $opportunity = Opportunity::factory()->order()->create([
        'tax_total' => 321,
        'charge_including_tax_total' => 5321,
        'charge_total' => 5000,
        'charge_excluding_tax_total' => 5000,
        'tax_locked' => true,
        'deal_total' => null,
    ]);

    OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quantity' => '1',
        'unit_price' => 9000,
        'total' => 9000,
        'is_optional' => false,
    ]);

    totalsCalculator()->rollUp($opportunity->refresh());

    $opportunity->refresh();

    expect($opportunity->tax_total)->toBe(321)
        ->and($opportunity->charge_total)->toBe(9000)
        ->and($opportunity->charge_including_tax_total)->toBe(9321);
});

it('excludes optional lines from rollUp totals', function () {
    $opportunity = Opportunity::factory()->create([
        'tax_total' => 0,
        'charge_total' => 0,
        'charge_excluding_tax_total' => 0,
        'charge_including_tax_total' => 0,
    ]);

    OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quantity' => '1',
        'unit_price' => 4000,
        'total' => 4000,
        'is_optional' => false,
    ]);
    OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quantity' => '1',
        'unit_price' => 9999,
        'total' => 9999,
        'is_optional' => true,
    ]);

    totalsCalculator()->rollUp($opportunity->refresh());

    expect($opportunity->fresh()->charge_total)->toBe(4000);
});

it('recalculates cost tax rates and mirrors headline totals onto the active version', function () {
    $member = wireTotalsTax();
    $opportunity = Opportunity::factory()->create([
        'member_id' => $member->id,
        'tax_total' => 0,
        'charge_total' => 0,
        'charge_excluding_tax_total' => 0,
        'charge_including_tax_total' => 0,
    ]);
    $version = OpportunityVersion::factory()->create([
        'opportunity_id' => $opportunity->id,
        'version_number' => 2,
        'charge_total' => 0,
        'tax_total' => 0,
        'charge_excluding_tax_total' => 0,
        'charge_including_tax_total' => 0,
    ]);
    $opportunity->forceFill(['active_version_id' => $version->id])->saveQuietly();

    $cost = OpportunityCost::factory()->create([
        'opportunity_id' => $opportunity->id,
        'amount' => 5000,
        'quantity' => '2',
        'is_optional' => false,
    ]);

    totalsCalculator()->recalculateCost($cost->refresh());
    totalsCalculator()->rollUp($opportunity->refresh());

    $cost->refresh();
    $opportunity->refresh();
    $version->refresh();

    expect((string) $cost->tax_rate)->toBe('20.00')
        ->and($opportunity->charge_total)->toBe(10000)
        ->and($opportunity->tax_total)->toBe(2000)
        ->and($version->charge_total)->toBe(10000)
        ->and($version->tax_total)->toBe(2000);
});

it('strips embedded tax from inclusive-priced lines before rollUp', function () {
    $member = wireTotalsTax();
    $productClass = ProductTaxClass::query()->where('is_default', true)->firstOrFail();
    $product = Product::factory()->create(['tax_class_id' => $productClass->id]);
    $opportunity = Opportunity::factory()->create([
        'member_id' => $member->id,
        'prices_include_tax' => true,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T09:00:00Z',
        'tax_total' => 0,
        'charge_total' => 0,
        'charge_excluding_tax_total' => 0,
        'charge_including_tax_total' => 0,
    ]);

    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 12000,
        'total' => 12000,
        'is_optional' => false,
        'starts_at' => $opportunity->starts_at,
        'ends_at' => $opportunity->ends_at,
    ]);

    totalsCalculator()->recalculateItem($item->refresh(), manualUnitPrice: 12000);
    totalsCalculator()->rollUp($opportunity->refresh());

    $item->refresh();
    $opportunity->refresh();

    expect((int) $item->total)->toBe(10000)
        ->and($opportunity->charge_total)->toBe(10000)
        ->and($opportunity->tax_total)->toBe(2000)
        ->and($opportunity->charge_including_tax_total)->toBe(12000);
});
