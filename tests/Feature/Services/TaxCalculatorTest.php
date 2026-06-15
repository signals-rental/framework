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

/**
 * Create an active org class, product class, tax rate, and matching rule, returning the trio.
 *
 * @return array{0: OrganisationTaxClass, 1: ProductTaxClass, 2: TaxRate}
 */
function makeTaxRule(string $rateName, string $rate): array
{
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create();
    $taxRate = TaxRate::factory()->create(['name' => $rateName, 'rate' => $rate]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    return [$orgClass, $productClass, $taxRate];
}

describe('TaxCalculator inclusive (tax-from-gross)', function () {
    it('extracts 20% tax embedded in a 12000 gross amount', function () {
        [$orgClass, $productClass, $taxRate] = makeTaxRule('Standard', '20.0000');

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculateInclusive(12000, 'GBP', $orgClass->id, $productClass->id);

        expect($result)->toBeInstanceOf(TaxResult::class)
            ->and($result->netAmount)->toBe(10000)
            ->and($result->taxAmount)->toBe(2000)
            ->and($result->grossAmount)->toBe(12000)
            ->and($result->taxRateName)->toBe('Standard')
            ->and($result->ratePercentage)->toBe('20.0000')
            ->and($result->currencyCode)->toBe('GBP')
            ->and($result->taxRateId)->toBe($taxRate->id);
    });

    it('extracts 5% reduced rate embedded in gross', function () {
        [$orgClass, $productClass] = makeTaxRule('Reduced', '5.0000');

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculateInclusive(10500, 'GBP', $orgClass->id, $productClass->id);

        expect($result->netAmount)->toBe(10000)
            ->and($result->taxAmount)->toBe(500)
            ->and($result->grossAmount)->toBe(10500);
    });

    it('extracts zero tax for a 0% rate', function () {
        [$orgClass, $productClass] = makeTaxRule('Zero Rate', '0.0000');

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculateInclusive(10000, 'GBP', $orgClass->id, $productClass->id);

        expect($result->netAmount)->toBe(10000)
            ->and($result->taxAmount)->toBe(0)
            ->and($result->grossAmount)->toBe(10000)
            ->and($result->taxRateName)->toBe('Zero Rate');
    });

    it('always preserves net + tax == gross when rounding is required', function () {
        [$orgClass, $productClass] = makeTaxRule('Standard', '20.0000');

        $calculator = app(TaxCalculator::class);
        // 400 / 1.2 = 333.33... -> net rounds to 333, tax is the remainder.
        $result = $calculator->calculateInclusive(400, 'GBP', $orgClass->id, $productClass->id);

        expect($result->netAmount)->toBe(333)
            ->and($result->taxAmount)->toBe(67)
            ->and($result->grossAmount)->toBe(400)
            ->and($result->netAmount + $result->taxAmount)->toBe($result->grossAmount);
    });

    it('returns zero tax when no rule matches', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculateInclusive(10000, 'GBP', $orgClass->id, $productClass->id);

        expect($result->netAmount)->toBe(10000)
            ->and($result->taxAmount)->toBe(0)
            ->and($result->grossAmount)->toBe(10000)
            ->and($result->taxRateName)->toBe('No Tax')
            ->and($result->taxRateId)->toBeNull()
            ->and($result->taxRuleId)->toBeNull();
    });

    it('returns zero tax when orgTaxClassId is null', function () {
        $productClass = ProductTaxClass::factory()->create();

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculateInclusive(10000, 'GBP', null, $productClass->id);

        expect($result->netAmount)->toBe(10000)
            ->and($result->taxAmount)->toBe(0)
            ->and($result->taxRateName)->toBe('No Tax');
    });

    it('returns zero tax when productTaxClassId is null', function () {
        $orgClass = OrganisationTaxClass::factory()->create();

        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculateInclusive(10000, 'GBP', $orgClass->id, null);

        expect($result->netAmount)->toBe(10000)
            ->and($result->taxAmount)->toBe(0)
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
        $result = $calculator->calculateInclusive(12000, 'GBP', $orgClass->id, $productClass->id);

        expect($result->taxAmount)->toBe(0)
            ->and($result->taxRateName)->toBe('No Tax');
    });

    it('shares rule priority resolution with the exclusive path', function () {
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
        $result = $calculator->calculateInclusive(12000, 'GBP', $orgClass->id, $productClass->id);

        // Highest-priority rule (20%) wins, identical to the exclusive resolution.
        expect($result->taxRateName)->toBe('High')
            ->and($result->netAmount)->toBe(10000)
            ->and($result->taxAmount)->toBe(2000);
    });

    it('shares default-class fallback resolution with the exclusive path', function () {
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
        $result = $calculator->calculateInclusive(12000, 'GBP', $nonDefaultOrgClass->id, $nonDefaultProductClass->id);

        expect($result->taxRateName)->toBe('Default Rate')
            ->and($result->netAmount)->toBe(10000)
            ->and($result->taxAmount)->toBe(2000)
            ->and($result->grossAmount)->toBe(12000);
    });

    it('extracts tax losslessly for a 3-decimal currency like KWD', function () {
        [$orgClass, $productClass] = makeTaxRule('VAT', '5.0000');

        $calculator = app(TaxCalculator::class);
        // 10500 minor units (10.500 KWD) inclusive of 5% -> 10000 net, 500 tax.
        $result = $calculator->calculateInclusive(10500, 'KWD', $orgClass->id, $productClass->id);

        expect($result->netAmount)->toBe(10000)
            ->and($result->taxAmount)->toBe(500)
            ->and($result->grossAmount)->toBe(10500)
            ->and($result->netAmount + $result->taxAmount)->toBe($result->grossAmount)
            ->and($result->netAmountDecimal())->toBe('10.000')
            ->and($result->taxAmountDecimal())->toBe('0.500')
            ->and($result->grossAmountDecimal())->toBe('10.500');
    });

    it('extracts tax losslessly for a zero-decimal currency like JPY', function () {
        [$orgClass, $productClass] = makeTaxRule('Consumption Tax', '10.0000');

        $calculator = app(TaxCalculator::class);
        // 1100 JPY inclusive of 10% -> 1000 net, 100 tax.
        $result = $calculator->calculateInclusive(1100, 'JPY', $orgClass->id, $productClass->id);

        expect($result->netAmount)->toBe(1000)
            ->and($result->taxAmount)->toBe(100)
            ->and($result->grossAmount)->toBe(1100)
            ->and($result->netAmount + $result->taxAmount)->toBe($result->grossAmount)
            ->and($result->netAmountDecimal())->toBe('1000')
            ->and($result->taxAmountDecimal())->toBe('100')
            ->and($result->grossAmountDecimal())->toBe('1100');
    });

    it('extracts tax from a negative gross amount (e.g. credit notes)', function () {
        [$orgClass, $productClass] = makeTaxRule('Standard', '20.0000');

        $calculator = app(TaxCalculator::class);
        // -400 / 1.2 = -333.33... -> net rounds away from zero to -333, tax is the remainder.
        $result = $calculator->calculateInclusive(-400, 'GBP', $orgClass->id, $productClass->id);

        expect($result->netAmount)->toBe(-333)
            ->and($result->taxAmount)->toBe(-67)
            ->and($result->grossAmount)->toBe(-400)
            ->and($result->netAmount + $result->taxAmount)->toBe($result->grossAmount);
    });
});

describe('TaxCalculator inclusive/exclusive inverse property', function () {
    it('calculateInclusive is the exact inverse of calculate for representative cases', function (
        string $rate,
        string $currency,
        int $net,
    ) {
        [$orgClass, $productClass] = makeTaxRule('Standard', $rate);

        $calculator = app(TaxCalculator::class);

        // Exclusive: net -> gross.
        $exclusive = $calculator->calculate($net, $currency, $orgClass->id, $productClass->id);

        // Inclusive of the resulting gross should reproduce the original net + tax.
        $inclusive = $calculator->calculateInclusive(
            $exclusive->grossAmount,
            $currency,
            $orgClass->id,
            $productClass->id,
        );

        expect($inclusive->grossAmount)->toBe($exclusive->grossAmount)
            ->and($inclusive->netAmount)->toBe($exclusive->netAmount)
            ->and($inclusive->taxAmount)->toBe($exclusive->taxAmount)
            // net + tax always reconciles back to the gross with no double-rounding drift.
            ->and($inclusive->netAmount + $inclusive->taxAmount)->toBe($inclusive->grossAmount);
    })->with([
        '20% GBP clean' => ['20.0000', 'GBP', 10000],
        '20% GBP rounding' => ['20.0000', 'GBP', 333],
        '5% GBP' => ['5.0000', 'GBP', 10000],
        '0% GBP' => ['0.0000', 'GBP', 10000],
        '5% KWD (3dp)' => ['5.0000', 'KWD', 10000],
        '10% JPY (0dp)' => ['10.0000', 'JPY', 1000],
        '20% JPY rounding' => ['20.0000', 'JPY', 1003],
    ]);

    it('inclusive net + tax reconciles to gross across a sweep of gross amounts', function () {
        [$orgClass, $productClass] = makeTaxRule('Standard', '20.0000');

        $calculator = app(TaxCalculator::class);

        foreach (range(1, 250) as $gross) {
            $result = $calculator->calculateInclusive($gross, 'GBP', $orgClass->id, $productClass->id);

            expect($result->netAmount + $result->taxAmount)->toBe($gross)
                ->and($result->grossAmount)->toBe($gross);
        }
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
