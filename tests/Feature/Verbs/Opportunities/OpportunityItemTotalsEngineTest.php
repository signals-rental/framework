<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\SetItemDiscount;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Enums\LineItemTransactionType;
use App\Enums\RateTransactionType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\ProductTaxClass;
use App\Models\RateDefinition;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\RateEngine\RateCalculator;
use App\ValueObjects\CalculationContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

/**
 * Wire a product with a daily period rate and a 20% tax rule that resolves for
 * the given member + product.
 *
 * @return array{product: Product, member: Member, store: Store, rate: ProductRate}
 */
function wirePricedProduct(int $unitPriceMinor = 10000): array
{
    $store = Store::factory()->create();

    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create();
    $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => '20.0000', 'is_active' => true]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    $member = Member::factory()->create(['sale_tax_class_id' => $orgClass->id]);
    $product = Product::factory()->rental()->create(['tax_class_id' => $productClass->id]);

    $definition = RateDefinition::factory()->create([
        'calculation_strategy' => CalculationStrategyType::Period,
        'base_period' => BasePeriod::Daily,
        'enabled_modifiers' => [],
        'strategy_config' => [],
        'modifier_configs' => [],
    ]);

    $rate = ProductRate::factory()->create([
        'product_id' => $product->id,
        'rate_definition_id' => $definition->id,
        'store_id' => null,
        'transaction_type' => RateTransactionType::Rental,
        'price' => $unitPriceMinor,
        'currency' => 'GBP',
    ]);

    return ['product' => $product, 'member' => $member, 'store' => $store, 'rate' => $rate->load('rateDefinition')];
}

it('prices a rate-backed line via the engine and stores the net total + tax rate', function () {
    ['product' => $product, 'member' => $member, 'store' => $store, 'rate' => $rate] = wirePricedProduct(10000);

    $start = '2026-07-01T09:00:00Z';
    $end = '2026-07-04T09:00:00Z';
    $quantity = 2;

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Priced',
        'member_id' => $member->id,
        'store_id' => $store->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => (string) $quantity,
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->items()->firstOrFail();

    // Compute the reference subtotal with the SAME engine the calculator uses.
    $definition = $rate->rateDefinition;
    $context = new CalculationContext(
        unitPriceMinor: $rate->price,
        currency: 'GBP',
        start: Carbon::parse($start),
        end: Carbon::parse($end),
        quantity: $quantity,
        basePeriod: $definition->base_period,
        strategyConfig: $definition->strategy_config,
        transactionType: RateTransactionType::Rental,
        storeId: $store->id,
    );
    $expectedNet = app(RateCalculator::class)->calculate(
        $context,
        $definition->calculation_strategy->value,
        $definition->enabled_modifiers,
        $definition->modifier_configs,
    )->totalMinor();

    expect($expectedNet)->toBeGreaterThan(0)
        ->and($item->unit_price)->toBe(10000)
        ->and((int) $item->total)->toBe($expectedNet)
        ->and((string) $item->tax_rate)->toBe('20.00');

    $opportunity->refresh();
    $expectedTax = (int) round($expectedNet * 0.20);
    expect($opportunity->charge_excluding_tax_total)->toBe($expectedNet)
        ->and($opportunity->tax_total)->toBe($expectedTax)
        ->and($opportunity->charge_including_tax_total)->toBe($expectedNet + $expectedTax)
        ->and($opportunity->charge_total)->toBe($expectedNet + $expectedTax)
        ->and($opportunity->rental_charge_total)->toBe($expectedNet);
});

it('applies the line discount to the net before computing tax', function () {
    ['product' => $product, 'member' => $member, 'store' => $store] = wirePricedProduct(10000);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Discounted',
        'member_id' => $member->id,
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T09:00:00Z', // single day
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->items()->firstOrFail();
    $netBeforeDiscount = (int) $item->total;
    expect($netBeforeDiscount)->toBeGreaterThan(0);

    (new SetItemDiscount)($item->refresh(), SetItemDiscountData::from(['discount_percent' => '25']));

    $item->refresh();
    $expectedDiscounted = $netBeforeDiscount - (int) round($netBeforeDiscount * 0.25);

    expect((int) $item->total)->toBe($expectedDiscounted);

    $opportunity->refresh();
    $expectedTax = (int) round($expectedDiscounted * 0.20);
    expect($opportunity->charge_excluding_tax_total)->toBe($expectedDiscounted)
        ->and($opportunity->tax_total)->toBe($expectedTax)
        ->and($opportunity->charge_total)->toBe($expectedDiscounted + $expectedTax);
});
