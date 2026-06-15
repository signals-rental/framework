<?php

use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRule;
use App\Services\TaxCalculator;
use Database\Seeders\TaxClassSeeder;
use Database\Seeders\TaxRateSeeder;
use Database\Seeders\TaxRuleSeeder;

beforeEach(function () {
    $this->seed(TaxClassSeeder::class);
    $this->seed(TaxRateSeeder::class);
    $this->seed(TaxRuleSeeder::class);
});

it('seeds a default tax rule linking the default classes to the standard rate', function () {
    $orgClass = OrganisationTaxClass::query()->where('is_default', true)->first();
    $productClass = ProductTaxClass::query()->where('is_default', true)->first();

    $rule = TaxRule::query()
        ->where('organisation_tax_class_id', $orgClass->id)
        ->where('product_tax_class_id', $productClass->id)
        ->first();

    expect($rule)->not->toBeNull();
    expect($rule->is_active)->toBeTrue();
    expect($rule->taxRate->name)->toBe('Standard');
    expect((float) $rule->taxRate->rate)->toBe(20.0);
});

it('is idempotent', function () {
    $this->seed(TaxRuleSeeder::class);

    expect(TaxRule::query()->count())->toBe(1);
});

it('makes TaxCalculator return non-zero standard tax for the default org and product class', function () {
    $orgClass = OrganisationTaxClass::query()->where('is_default', true)->first();
    $productClass = ProductTaxClass::query()->where('is_default', true)->first();

    $result = app(TaxCalculator::class)->calculate(10000, 'GBP', $orgClass->id, $productClass->id);

    expect($result->taxAmount)->toBe(2000);
    expect($result->grossAmount)->toBe(12000);
    expect($result->ratePercentage)->toBe('20.0000');
    expect($result->taxRateName)->toBe('Standard');
});
