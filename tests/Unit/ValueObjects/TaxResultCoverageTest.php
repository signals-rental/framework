<?php

use App\ValueObjects\TaxResult;

function taxResult(string $currency, int $net, int $tax, int $gross): TaxResult
{
    return new TaxResult(
        taxRateName: 'Standard',
        ratePercentage: '20.0',
        netAmount: $net,
        taxAmount: $tax,
        grossAmount: $gross,
        currencyCode: $currency,
    );
}

describe('TaxResult decimal formatting — known currencies (brick path)', function () {
    it('renders a 2dp currency from minor units', function () {
        $result = taxResult('GBP', 12550, 2510, 15060);

        expect($result->netAmountDecimal())->toBe('125.50')
            ->and($result->taxAmountDecimal())->toBe('25.10')
            ->and($result->grossAmountDecimal())->toBe('150.60');
    });

    it('renders a zero-decimal currency (JPY) with no fraction', function () {
        expect(taxResult('JPY', 1000, 200, 1200)->netAmountDecimal())->toBe('1000');
    });
});

describe('TaxResult decimal formatting — unknown currency fallback', function () {
    it('falls back to the local exponent table for a brick-unknown 3dp code', function () {
        // A made-up 3-decimal code brick does not know: the UnknownCurrencyException
        // path resolves the exponent from the local CURRENCY_EXPONENTS table.
        // We use a real-but-uncommon code present in the table to keep it meaningful:
        // BHD has 3 minor-unit digits (1234 minor => "1.234").
        $result = taxResult('BHD', 1234, 0, 1234);

        expect($result->netAmountDecimal())->toBe('1.234');
    });

    it('defaults to 2dp for a code absent from the exponent table', function () {
        // 'ZZZ' is not a real ISO code and not in the exponent table → default 2dp.
        $result = taxResult('ZZZ', 12550, 0, 12550);

        expect($result->netAmountDecimal())->toBe('125.50');
    });

    it('uppercases the currency code before resolution', function () {
        $result = taxResult('zzz', 100, 0, 100);

        expect($result->netAmountDecimal())->toBe('1.00');
    });
});

it('serialises every monetary field to its raw minor-unit array form', function () {
    $array = taxResult('GBP', 12550, 2510, 15060)->toArray();

    expect($array['net_amount'])->toBe(12550)
        ->and($array['tax_amount'])->toBe(2510)
        ->and($array['gross_amount'])->toBe(15060)
        ->and($array['currency_code'])->toBe('GBP');
});
