<?php

namespace App\Services;

use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRule;
use App\ValueObjects\TaxResult;

/**
 * Calculates tax for a given amount using the tax rule matrix.
 *
 * Resolves the applicable tax rule by matching organisation and product tax classes,
 * with fallback to default classes when no exact match exists.
 *
 * Two calculation modes are supported, both sharing the same rule/rate resolution and
 * minor-unit rounding machinery:
 *
 *   - {@see calculate()} — exclusive (tax-additive): given a NET amount, adds tax on top.
 *   - {@see calculateInclusive()} — inclusive (tax-from-gross): given a GROSS amount,
 *     extracts the embedded tax component. This is the exact inverse of the exclusive
 *     path: net + tax always equals the supplied gross, with rounding applied only once
 *     at the currency minor-unit boundary.
 */
class TaxCalculator
{
    /**
     * Calculate tax for a given NET amount in minor units (exclusive / tax-additive mode).
     *
     * The supplied amount is treated as tax-exclusive; tax is computed on top of it and
     * the gross is net + tax.
     */
    public function calculate(
        int $netMinorUnits,
        string $currencyCode,
        ?int $orgTaxClassId = null,
        ?int $productTaxClassId = null,
    ): TaxResult {
        $rule = $this->resolveTaxableRule($orgTaxClassId, $productTaxClassId);

        if (! $rule) {
            return $this->zeroTax($netMinorUnits, $currencyCode);
        }

        $taxRate = $rule->taxRate;
        $rate = (string) $taxRate->rate;

        // Lossless intermediate (10 dp), rounded to the minor-unit boundary at the final step.
        $rawTax = bcmul((string) $netMinorUnits, bcdiv($rate, '100', 10), 10);
        $taxAmount = $this->roundMinorUnits($rawTax);

        return new TaxResult(
            taxRateName: $taxRate->name,
            ratePercentage: $rate,
            netAmount: $netMinorUnits,
            taxAmount: $taxAmount,
            grossAmount: $netMinorUnits + $taxAmount,
            currencyCode: $currencyCode,
            taxRateId: $taxRate->id,
            taxRuleId: $rule->id,
        );
    }

    /**
     * Calculate tax embedded in a GROSS amount in minor units (inclusive mode).
     *
     * The supplied amount is treated as tax-inclusive. The net is derived as
     * round(gross / (1 + rate)) and the tax is the remainder (gross - net). This guarantees
     * net + tax == gross exactly — i.e. the inclusive extraction is the lossless inverse of
     * the exclusive {@see calculate()} addition — and applies rounding only once at the
     * currency minor-unit boundary, using the same round-half-away-from-zero strategy.
     */
    public function calculateInclusive(
        int $grossMinorUnits,
        string $currencyCode,
        ?int $orgTaxClassId = null,
        ?int $productTaxClassId = null,
    ): TaxResult {
        $rule = $this->resolveTaxableRule($orgTaxClassId, $productTaxClassId);

        if (! $rule) {
            return $this->zeroTax($grossMinorUnits, $currencyCode);
        }

        $taxRate = $rule->taxRate;
        $rate = (string) $taxRate->rate;

        // net = gross / (1 + rate/100), lossless intermediate (10 dp), rounded once at the boundary.
        $divisor = bcadd('1', bcdiv($rate, '100', 10), 10);
        $rawNet = bcdiv((string) $grossMinorUnits, $divisor, 10);
        $netAmount = $this->roundMinorUnits($rawNet);

        // Tax is the remainder so net + tax == gross by construction (no double rounding).
        $taxAmount = $grossMinorUnits - $netAmount;

        return new TaxResult(
            taxRateName: $taxRate->name,
            ratePercentage: $rate,
            netAmount: $netAmount,
            taxAmount: $taxAmount,
            grossAmount: $grossMinorUnits,
            currencyCode: $currencyCode,
            taxRateId: $taxRate->id,
            taxRuleId: $rule->id,
        );
    }

    /**
     * Resolve the tax rule that carries an active rate for the given classes, or null when
     * no taxable rule applies.
     *
     * Shared by the exclusive and inclusive calculation paths so resolution logic is never
     * forked. Returns null when either class is missing, no rule matches, or the matched
     * rule's rate is missing or inactive. When a rule is returned, its `taxRate` relation is
     * guaranteed loaded, present, and active.
     */
    private function resolveTaxableRule(?int $orgTaxClassId, ?int $productTaxClassId): ?TaxRule
    {
        if ($orgTaxClassId === null || $productTaxClassId === null) {
            return null;
        }

        $rule = $this->resolveRule($orgTaxClassId, $productTaxClassId);

        if (! $rule) {
            return null;
        }

        $taxRate = $rule->taxRate;

        if (! $taxRate || ! $taxRate->is_active) {
            return null;
        }

        return $rule;
    }

    /**
     * Round a lossless decimal string of minor units to an integer using round-half-away-from-zero.
     *
     * This is the single minor-unit rounding boundary shared by both calculation modes and
     * matches the tenant default "round half up" behaviour.
     */
    private function roundMinorUnits(string $rawMinorUnits): int
    {
        return (int) bcadd($rawMinorUnits, $rawMinorUnits[0] === '-' ? '-0.5' : '0.5', 0);
    }

    /**
     * Resolve the applicable tax rule by org + product class, with fallback to defaults.
     */
    public function resolveRule(?int $orgTaxClassId, ?int $productTaxClassId): ?TaxRule
    {
        $rule = TaxRule::query()
            ->where('organisation_tax_class_id', $orgTaxClassId)
            ->where('product_tax_class_id', $productTaxClassId)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->with('taxRate')
            ->first();

        if ($rule) {
            return $rule;
        }

        $defaultOrgClass = OrganisationTaxClass::query()->where('is_default', true)->first();
        $defaultProductClass = ProductTaxClass::query()->where('is_default', true)->first();

        if (! $defaultOrgClass || ! $defaultProductClass) {
            return null;
        }

        if ($defaultOrgClass->id === $orgTaxClassId && $defaultProductClass->id === $productTaxClassId) {
            return null;
        }

        return TaxRule::query()
            ->where('organisation_tax_class_id', $defaultOrgClass->id)
            ->where('product_tax_class_id', $defaultProductClass->id)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->with('taxRate')
            ->first();
    }

    /**
     * Build a zero-tax result when no rule applies.
     */
    private function zeroTax(int $netMinorUnits, string $currencyCode): TaxResult
    {
        return new TaxResult(
            taxRateName: 'No Tax',
            ratePercentage: '0.0000',
            netAmount: $netMinorUnits,
            taxAmount: 0,
            grossAmount: $netMinorUnits,
            currencyCode: $currencyCode,
        );
    }
}
