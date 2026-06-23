<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Guards\Opportunities\Rules\FxTaxLockRule;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

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
 * Build a Quotation carrying a single manual-priced line (2 x 5000 = 10000 net).
 *
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function netEditQuotation(Store $store): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Net edit fixture',
        'store_id' => $store->id,
        'starts_at' => '2026-10-01T09:00:00Z',
        'ends_at' => '2026-10-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'PA Stack',
        'quantity' => '2',
        'unit_price' => 5000,
    ]));

    (new ConvertToQuotation)($opportunity->refresh());

    return [$opportunity->refresh(), $opportunity->allItems()->firstOrFail()];
}

it('moves the line total and parent net when overriding the rate on an unlocked quotation', function () {
    [$opportunity, $item] = netEditQuotation($this->store);

    expect((int) $opportunity->charge_total)->toBe(10000);

    (new OverrideItemPrice)($item->refresh(), OverrideItemPriceData::from(['unit_price' => 7500]));

    expect((int) $item->refresh()->total)->toBe(15000) // 7500 x 2
        ->and((int) $opportunity->refresh()->charge_total)->toBe(15000);
});

it('moves the line total and parent net when overriding the rate on a LOCKED order, with tax frozen', function () {
    [$opportunity, $item] = netEditQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());
    $opportunity->refresh();

    expect($opportunity->exchange_rate_locked)->toBeTrue()
        ->and($opportunity->tax_locked)->toBeTrue();

    $lockedTax = (int) $opportunity->tax_total;

    (new OverrideItemPrice)($item->refresh(), OverrideItemPriceData::from(['unit_price' => 7500]));

    expect((int) $item->refresh()->total)->toBe(15000)
        ->and((int) $opportunity->refresh()->charge_total)->toBe(15000)
        // The tax lock is honoured: the frozen tax figure does not move.
        ->and((int) $opportunity->refresh()->tax_total)->toBe($lockedTax);
});

it('applies a discount to the net on a locked order', function () {
    [$opportunity, $item] = netEditQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());

    (new SetItemDiscount)($item->refresh(), SetItemDiscountData::from(['discount_percent' => '10']));

    // 10000 net less 10% = 9000.
    expect((int) $item->refresh()->total)->toBe(9000)
        ->and((int) $opportunity->refresh()->charge_total)->toBe(9000);
});

it('changes the quantity and recomputes the net on a locked order', function () {
    [$opportunity, $item] = netEditQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());

    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '4']));

    // 5000 x 4 = 20000.
    expect((int) $item->refresh()->total)->toBe(20000)
        ->and((int) $opportunity->refresh()->charge_total)->toBe(20000);
});
