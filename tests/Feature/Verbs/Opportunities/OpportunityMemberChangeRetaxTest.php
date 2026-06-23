<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\UpdateOpportunityData;
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
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/**
 * R-A master M12 (B6): a member change shifts the organisation tax class, so the
 * OpportunityUpdated event must recompute the grouped final tax (unless the
 * opportunity's tax is locked).
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

it('recomputes tax_total when the member changes to one with a different tax class', function () {
    $store = Store::factory()->create();

    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => '20.0000', 'is_active' => true]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    // Taxed member (20%) vs an untaxed member (no sale tax class → no rule → 0 tax).
    $taxedMember = Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);
    $untaxedMember = Member::factory()->organisation()->create(['sale_tax_class_id' => null]);

    $product = Product::factory()->rental()->create(['tax_class_id' => $productClass->id]);
    $definition = RateDefinition::factory()->create([
        'calculation_strategy' => CalculationStrategyType::Period,
        'base_period' => BasePeriod::Daily,
        'enabled_modifiers' => [],
        'strategy_config' => [],
        'modifier_configs' => [],
    ]);
    ProductRate::factory()->create([
        'product_id' => $product->id,
        'rate_definition_id' => $definition->id,
        'store_id' => null,
        'transaction_type' => RateTransactionType::Rental,
        'price' => 10000,
        'currency' => 'GBP',
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Retax',
        'member_id' => $taxedMember->id,
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-04T09:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $opportunity->refresh();
    expect($opportunity->tax_total)->toBeGreaterThan(0);

    // Switch to the untaxed member → the update event must re-roll the tax to 0.
    (new UpdateOpportunity)($opportunity, UpdateOpportunityData::from([
        'member_id' => $untaxedMember->id,
    ]));

    $opportunity->refresh();
    expect($opportunity->tax_total)->toBe(0)
        ->and($opportunity->charge_including_tax_total)->toBe($opportunity->charge_total);
});

it('does not move tax on a non-member header update', function () {
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    $member = Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);
    $store = Store::factory()->create();
    $product = Product::factory()->rental()->create(['tax_class_id' => $productClass->id]);
    $definition = RateDefinition::factory()->create([
        'calculation_strategy' => CalculationStrategyType::Period,
        'base_period' => BasePeriod::Daily,
        'enabled_modifiers' => [],
        'strategy_config' => [],
        'modifier_configs' => [],
    ]);
    ProductRate::factory()->create([
        'product_id' => $product->id,
        'rate_definition_id' => $definition->id,
        'store_id' => null,
        'transaction_type' => RateTransactionType::Rental,
        'price' => 10000,
        'currency' => 'GBP',
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Stable Tax',
        'member_id' => $member->id,
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-04T09:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $opportunity->refresh();
    $taxBefore = $opportunity->tax_total;

    (new UpdateOpportunity)($opportunity, UpdateOpportunityData::from(['subject' => 'Renamed only']));

    expect($opportunity->refresh()->tax_total)->toBe($taxBefore);
});
