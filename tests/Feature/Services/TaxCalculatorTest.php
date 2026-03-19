<?php

use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Services\TaxCalculator;
use App\ValueObjects\TaxResult;

describe('TaxCalculator', function () {
    it('calculates 20% tax on 10000 minor units', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();
        $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => '20.0000']);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $taxRate->id,
        ]);

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', $orgClass->id, $productClass->id);

        expect($result)->toBeInstanceOf(TaxResult::class)
            ->and($result->netAmount)->toBe(10000)
            ->and($result->taxAmount)->toBe(2000)
            ->and($result->grossAmount)->toBe(12000)
            ->and($result->taxRateName)->toBe('Standard')
            ->and($result->ratePercentage)->toBe('20.0000')
            ->and($result->currencyCode)->toBe('GBP');
    });

    it('calculates 5% reduced rate correctly', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();
        $taxRate = TaxRate::factory()->create(['name' => 'Reduced', 'rate' => '5.0000']);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $taxRate->id,
        ]);

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', $orgClass->id, $productClass->id);

        expect($result->taxAmount)->toBe(500)
            ->and($result->grossAmount)->toBe(10500);
    });

    it('calculates 0% zero rate correctly', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();
        $taxRate = TaxRate::factory()->create(['name' => 'Zero Rate', 'rate' => '0.0000']);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $taxRate->id,
        ]);

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', $orgClass->id, $productClass->id);

        expect($result->taxAmount)->toBe(0)
            ->and($result->grossAmount)->toBe(10000)
            ->and($result->taxRateName)->toBe('Zero Rate');
    });

    it('returns zero tax when no rule matches', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', $orgClass->id, $productClass->id);

        expect($result->taxAmount)->toBe(0)
            ->and($result->grossAmount)->toBe(10000)
            ->and($result->taxRateName)->toBe('No Tax')
            ->and($result->ratePercentage)->toBe('0.0000')
            ->and($result->taxRateId)->toBeNull()
            ->and($result->taxRuleId)->toBeNull();
    });

    it('returns zero tax when orgTaxClassId is null', function () {
        $productClass = ProductTaxClass::factory()->create();

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', null, $productClass->id);

        expect($result->taxAmount)->toBe(0)
            ->and($result->grossAmount)->toBe(10000)
            ->and($result->taxRateName)->toBe('No Tax');
    });

    it('returns zero tax when productTaxClassId is null', function () {
        $orgClass = OrganisationTaxClass::factory()->create();

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', $orgClass->id, null);

        expect($result->taxAmount)->toBe(0)
            ->and($result->grossAmount)->toBe(10000)
            ->and($result->taxRateName)->toBe('No Tax');
    });

    it('respects rule priority ordering', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();
        $lowRate = TaxRate::factory()->create(['name' => 'Low', 'rate' => '5.0000']);
        $highRate = TaxRate::factory()->create(['name' => 'High', 'rate' => '20.0000']);

        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $lowRate->id,
            'priority' => 1,
        ]);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $highRate->id,
            'priority' => 10,
        ]);

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', $orgClass->id, $productClass->id);

        expect($result->taxRateName)->toBe('High')
            ->and($result->taxAmount)->toBe(2000);
    });

    it('ignores inactive rules', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();
        $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => '20.0000']);

        TaxRule::factory()->inactive()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $taxRate->id,
        ]);

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', $orgClass->id, $productClass->id);

        expect($result->taxAmount)->toBe(0)
            ->and($result->taxRateName)->toBe('No Tax');
    });

    it('ignores inactive tax rates', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();
        $taxRate = TaxRate::factory()->inactive()->create(['name' => 'Inactive Rate', 'rate' => '20.0000']);

        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $taxRate->id,
        ]);

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', $orgClass->id, $productClass->id);

        expect($result->taxAmount)->toBe(0)
            ->and($result->taxRateName)->toBe('No Tax');
    });

    it('falls back to default tax classes when no exact match', function () {
        $defaultOrgClass = OrganisationTaxClass::factory()->default()->create();
        $defaultProductClass = ProductTaxClass::factory()->default()->create();
        $nonDefaultOrgClass = OrganisationTaxClass::factory()->create();
        $nonDefaultProductClass = ProductTaxClass::factory()->create();
        $taxRate = TaxRate::factory()->create(['name' => 'Default Rate', 'rate' => '20.0000']);

        TaxRule::factory()->create([
            'organisation_tax_class_id' => $defaultOrgClass->id,
            'product_tax_class_id' => $defaultProductClass->id,
            'tax_rate_id' => $taxRate->id,
        ]);

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', $nonDefaultOrgClass->id, $nonDefaultProductClass->id);

        expect($result->taxRateName)->toBe('Default Rate')
            ->and($result->taxAmount)->toBe(2000)
            ->and($result->grossAmount)->toBe(12000);
    });

    it('handles rounding on odd amounts', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();
        $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => '20.0000']);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $taxRate->id,
        ]);

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(333, 'GBP', $orgClass->id, $productClass->id);

        expect($result->netAmount)->toBe(333)
            ->and($result->taxAmount)->toBe(67)
            ->and($result->grossAmount)->toBe(400);
    });

    it('resolveRule returns null when no defaults exist', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();

        $calculator = app(TaxCalculator::class);
        $result = $calculator->resolveRule($orgClass->id, $productClass->id);

        expect($result)->toBeNull();
    });

    it('returns zero tax when default classes match the already-tried classes with no rule', function () {
        $defaultOrgClass = OrganisationTaxClass::factory()->default()->create();
        $defaultProductClass = ProductTaxClass::factory()->default()->create();

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', $defaultOrgClass->id, $defaultProductClass->id);

        expect($result->taxAmount)->toBe(0)
            ->and($result->taxRateName)->toBe('No Tax');
    });

    it('returns zero tax when no default org class exists for fallback', function () {
        $orgClass = OrganisationTaxClass::factory()->create(['is_default' => false]);
        $productClass = ProductTaxClass::factory()->create(['is_default' => false]);

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(10000, 'GBP', $orgClass->id, $productClass->id);

        expect($result->taxAmount)->toBe(0)
            ->and($result->taxRateName)->toBe('No Tax');
    });

    it('rounds negative tax amounts correctly', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();
        $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => '20.0000']);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $taxRate->id,
        ]);

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(-333, 'GBP', $orgClass->id, $productClass->id);

        expect($result->netAmount)->toBe(-333)
            ->and($result->taxAmount)->toBe(-67)
            ->and($result->grossAmount)->toBe(-400);
    });
});

describe('TaxResult', function () {
    it('toArray returns correct shape', function () {
        $result = new TaxResult(
            taxRateName: 'Standard',
            ratePercentage: '20.0000',
            netAmount: 10000,
            taxAmount: 2000,
            grossAmount: 12000,
            currencyCode: 'GBP',
            taxRateId: 1,
            taxRuleId: 2,
        );

        expect($result->toArray())->toBe([
            'tax_rate_name' => 'Standard',
            'rate_percentage' => '20.0000',
            'net_amount' => 10000,
            'tax_amount' => 2000,
            'gross_amount' => 12000,
            'currency_code' => 'GBP',
            'tax_rate_id' => 1,
            'tax_rule_id' => 2,
        ]);
    });

    it('decimal accessors produce correct format', function () {
        $result = new TaxResult(
            taxRateName: 'Standard',
            ratePercentage: '20.0000',
            netAmount: 10000,
            taxAmount: 2000,
            grossAmount: 12000,
            currencyCode: 'GBP',
        );

        expect($result->netAmountDecimal())->toBe('100.00')
            ->and($result->taxAmountDecimal())->toBe('20.00')
            ->and($result->grossAmountDecimal())->toBe('120.00');
    });

    it('decimal accessors handle odd amounts', function () {
        $result = new TaxResult(
            taxRateName: 'Standard',
            ratePercentage: '20.0000',
            netAmount: 333,
            taxAmount: 67,
            grossAmount: 400,
            currencyCode: 'GBP',
        );

        expect($result->netAmountDecimal())->toBe('3.33')
            ->and($result->taxAmountDecimal())->toBe('0.67')
            ->and($result->grossAmountDecimal())->toBe('4.00');
    });

    it('decimal accessors return integer strings for zero-exponent currencies like JPY', function () {
        $result = new TaxResult(
            taxRateName: 'Consumption Tax',
            ratePercentage: '10.0000',
            netAmount: 1000,
            taxAmount: 100,
            grossAmount: 1100,
            currencyCode: 'JPY',
        );

        expect($result->netAmountDecimal())->toBe('1000')
            ->and($result->taxAmountDecimal())->toBe('100')
            ->and($result->grossAmountDecimal())->toBe('1100');
    });

    it('decimal accessors handle 3-exponent currencies like KWD', function () {
        $result = new TaxResult(
            taxRateName: 'VAT',
            ratePercentage: '5.0000',
            netAmount: 10000,
            taxAmount: 500,
            grossAmount: 10500,
            currencyCode: 'KWD',
        );

        expect($result->netAmountDecimal())->toBe('10.000')
            ->and($result->taxAmountDecimal())->toBe('0.500')
            ->and($result->grossAmountDecimal())->toBe('10.500');
    });
});
