<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityItemChargeBounds;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
});

/**
 * Build a one-line quotation with a fractional quantity at a 4-day window.
 *
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function boundsFixture(Store $store, string $quantity): array
{
    $product = Product::factory()->rental()->bulk()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Bounds fixture',
        'store_id' => $store->id,
        'starts_at' => '2026-10-01T09:00:00Z',
        'ends_at' => '2026-10-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => $quantity,
        'unit_price' => 5000,
    ]));

    return [$opportunity->refresh(), $opportunity->allItems()->firstOrFail()];
}

it('rounds a fractional quantity to whole units, matching the calculator', function () {
    [$opportunity, $item] = boundsFixture($this->store, '1.5');

    $bounds = app(OpportunityItemChargeBounds::class);

    // 1.5 rounds to 2 units (the calculator's manualLineSubtotal uses round()):
    // 7500 × 2 × 4 days = 60000 — NOT 1.5 × 7500 × 4 = 45000.
    expect($bounds->projectedChargeTotalMinor($item, $opportunity, 7500))->toBe(60000);

    // And the calculator agrees once the override is applied.
    (new OverrideItemPrice)($item->refresh(), OverrideItemPriceData::from(['unit_price' => 7500]));

    expect((int) $item->refresh()->total)->toBe(60000);
});

it('applies the percentage discount with the same minor-unit rounding as the calculator', function () {
    [$opportunity, $item] = boundsFixture($this->store, '2');
    $item->forceFill(['discount_percent' => '10'])->save();

    $bounds = app(OpportunityItemChargeBounds::class);

    // 5000 × 2 × 4 = 40000, less 10% = 36000.
    expect($bounds->projectedChargeTotalMinor($item->refresh(), $opportunity, 5000))->toBe(36000);
});
