<?php

namespace App\ValueObjects;

/**
 * Immutable value object representing the result of a tax calculation.
 *
 * All monetary amounts are stored in minor units (pence, cents, fils).
 */
class TaxResult
{
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
     * Net amount as a decimal string for API responses (e.g. "125.50").
     */
    public function netAmountDecimal(): string
    {
        return number_format($this->netAmount / 100, 2, '.', '');
    }

    /**
     * Tax amount as a decimal string for API responses (e.g. "25.00").
     */
    public function taxAmountDecimal(): string
    {
        return number_format($this->taxAmount / 100, 2, '.', '');
    }

    /**
     * Gross amount as a decimal string for API responses (e.g. "150.50").
     */
    public function grossAmountDecimal(): string
    {
        return number_format($this->grossAmount / 100, 2, '.', '');
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
}
