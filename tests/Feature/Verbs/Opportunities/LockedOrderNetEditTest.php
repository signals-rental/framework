<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Guards\Opportunities\Rules\FxTaxLockRule;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Opportunities\OpportunityItemChargeBounds;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Regression guard for the keystone line-item editor bug (UAT): manual rate /
 * discount / quantity edits MUST move the line total, the parent net charge_total
 * AND (where present) the active version snapshot — on BOTH an unlocked quotation
 * and a confirmed (FX/tax-locked) order.
 *
 * The previous behaviour wrongly let {@see FxTaxLockRule}
 * reject these manual net edits on a locked order (the action declared
 * `changes_rate: true`), so an override returned a 422 the Livewire editor
 * swallowed: the projection stayed at 0 while the request looked like a 200.
 * Per {@see OpportunityTotalsCalculator} the lock freezes
 * only later FX-rate / tax-rule re-pricing — structural/price edits still recompute
 * the net (tax stays frozen on a locked order).
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

/**
 * Wire a 20% tax rule plus a member and product that resolve against it.
 *
 * @return array{member: Member, product: Product}
 */
function wireNetEditTax(): array
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

    return [
        'member' => Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]),
        'product' => Product::factory()->rental()->create(['tax_class_id' => $productClass->id]),
    ];
}

/**
 * Build a Quotation carrying a single manual-priced line (2 x 5000 = 10000 net).
 *
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function netEditQuotation(Store $store): array
{
    ['member' => $member, 'product' => $product] = wireNetEditTax();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Net edit fixture',
        'member_id' => $member->id,
        'store_id' => $store->id,
        'starts_at' => '2026-10-01T09:00:00Z',
        'ends_at' => '2026-10-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '2',
        'unit_price' => 5000,
    ]));

    (new ConvertToQuotation)($opportunity->refresh());

    return [$opportunity->refresh(), $opportunity->allItems()->firstOrFail()];
}

it('authorizes OverrideItemPrice BEFORE the charge-bounds check (403 not a 422 leak)', function () {
    [$opportunity, $item] = netEditQuotation($this->store);

    // A viewer lacks opportunities.edit. An out-of-bounds unit price would
    // throw a 422 if the bounds check ran first — the gate must win.
    $viewer = User::factory()->create();
    $viewer->assignRole('Read Only');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($viewer);

    // A unit price that is individually in-bounds but whose projected line
    // total (× qty × chargeable days) overflows MAX_MINOR. The action-level
    // bounds check would throw a 422 if it ran before the gate.
    $highUnitPrice = OpportunityItemChargeBounds::MAX_MINOR;

    expect(fn () => (new OverrideItemPrice)(
        $item->refresh(),
        OverrideItemPriceData::from(['unit_price' => $highUnitPrice]),
    ))->toThrow(AuthorizationException::class);
});

it('authorizes SetDealPrice BEFORE the lock check (403 not a 422 leak)', function () {
    [$opportunity, $item] = netEditQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh()); // applies FX/tax locks

    $viewer = User::factory()->create();
    $viewer->assignRole('Read Only');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($viewer);

    // On a locked order, hasLocks() would throw a 422 "unlock price" if it ran
    // first — the gate must win and produce a 403.
    expect(fn () => (new SetDealPrice)(
        $opportunity->refresh(),
        SetDealPriceData::from(['deal_total' => '10.00']),
    ))->toThrow(AuthorizationException::class);
});

it('moves the line total and parent net when overriding the rate on an unlocked quotation', function () {
    [$opportunity, $item] = netEditQuotation($this->store);

    expect((int) $opportunity->charge_total)->toBe(40000); // 2 x 5000 x 4 chargeable days

    (new OverrideItemPrice)($item->refresh(), OverrideItemPriceData::from(['unit_price' => 7500]));

    expect((int) $item->refresh()->total)->toBe(60000) // 7500 x 2 x 4
        ->and((int) $opportunity->refresh()->charge_total)->toBe(60000);
});

it('moves the line total and parent net when overriding the rate on a LOCKED order, with tax frozen', function () {
    [$opportunity, $item] = netEditQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());
    $opportunity->refresh();

    expect($opportunity->exchange_rate_locked)->toBeTrue()
        ->and($opportunity->tax_locked)->toBeTrue();

    $lockedTax = (int) $opportunity->tax_total;

    expect($lockedTax)->toBeGreaterThan(0);

    (new OverrideItemPrice)($item->refresh(), OverrideItemPriceData::from(['unit_price' => 7500]));

    expect((int) $item->refresh()->total)->toBe(60000)
        ->and((int) $opportunity->refresh()->charge_total)->toBe(60000)
        // The tax lock is honoured: the frozen tax figure does not move.
        ->and((int) $opportunity->refresh()->tax_total)->toBe($lockedTax);
});

it('applies a discount to the net on a locked order', function () {
    [$opportunity, $item] = netEditQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());

    (new SetItemDiscount)($item->refresh(), SetItemDiscountData::from(['discount_percent' => '10']));

    // 40000 net less 10% = 36000.
    expect((int) $item->refresh()->total)->toBe(36000)
        ->and((int) $opportunity->refresh()->charge_total)->toBe(36000);
});

it('changes the quantity and recomputes the net on a locked order', function () {
    [$opportunity, $item] = netEditQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());

    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '4']));

    // 5000 x 4 qty x 4 chargeable days = 80000.
    expect((int) $item->refresh()->total)->toBe(80000)
        ->and((int) $opportunity->refresh()->charge_total)->toBe(80000);
});
