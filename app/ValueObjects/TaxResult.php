<?php

namespace App\ValueObjects;

/**
 * Immutable value object representing the result of a tax calculation.
 *
 * The monetary properties `netAmount`, `taxAmount`, and `grossAmount` are stored
 * in minor units (pence, cents, fils). Use the `*Decimal()` accessors for
 * API-formatted strings that respect the currency's minor unit exponent.
 */
class TaxResult
{
    /**
     * ISO 4217 minor unit exponents for currencies that differ from the default of 2.
     *
     * @var array<string, int>
     */
    private const CURRENCY_EXPONENTS = [
        'BHD' => 3, 'BIF' => 0, 'CLF' => 4, 'CLP' => 0,
        'DJF' => 0, 'GNF' => 0, 'IQD' => 3, 'ISK' => 0,
        'JOD' => 3, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0,
        'KWD' => 3, 'LYD' => 3, 'MGA' => 1, 'MRU' => 1,
        'OMR' => 3, 'PYG' => 0, 'RWF' => 0, 'TND' => 3,
        'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0,
        'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
    ];

    public function __construct(
        public readonly string $taxRateName,
        public readonly string $ratePercentage,
        public readonly int $netAmount,
        public readonly int $taxAmount,
        public readonly int $grossAmount,
        public readonly string $currencyCode,
        public readonly ?int $taxRateId = null,
        public readonly ?int $taxRuleId = null,
    ) {}

    /**
     * Net amount as a decimal string for API responses (e.g. "125.50", "1000" for JPY).
     */
    public function netAmountDecimal(): string
    {
        return $this->toDecimalString($this->netAmount);
    }

    /**
     * Tax amount as a decimal string for API responses.
     */
    public function taxAmountDecimal(): string
    {
        return $this->toDecimalString($this->taxAmount);
    }

    /**
     * Gross amount as a decimal string for API responses.
     */
    public function grossAmountDecimal(): string
    {
        return $this->toDecimalString($this->grossAmount);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tax_rate_name' => $this->taxRateName,
            'rate_percentage' => $this->ratePercentage,
            'net_amount' => $this->netAmount,
            'tax_amount' => $this->taxAmount,
            'gross_amount' => $this->grossAmount,
            'currency_code' => $this->currencyCode,
            'tax_rate_id' => $this->taxRateId,
            'tax_rule_id' => $this->taxRuleId,
        ];
    }

    /**
     * Convert a minor-unit integer to a decimal string using the currency's exponent.
     */
    private function toDecimalString(int $minorUnits): string
    {
        $exponent = self::CURRENCY_EXPONENTS[strtoupper($this->currencyCode)] ?? 2;

        if ($exponent === 0) {
            return (string) $minorUnits;
        }

        $divisor = 10 ** $exponent;

        return number_format($minorUnits / $divisor, $exponent, '.', '');
    }
}
