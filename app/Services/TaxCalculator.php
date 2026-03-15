<?php

namespace App\Services;

use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRule;
use App\ValueObjects\TaxResult;

/**
 * Calculates tax for a given net amount using the tax rule matrix.
 *
 * Resolves the applicable tax rule by matching organisation and product tax classes,
 * with fallback to default classes when no exact match exists.
 */
class TaxCalculator
{
    /**
     * Calculate tax for a given net amount in minor units.
     */
    public function calculate(
        int $netMinorUnits,
        string $currencyCode,
        ?int $orgTaxClassId = null,
        ?int $productTaxClassId = null,
    ): TaxResult {
        if ($orgTaxClassId === null || $productTaxClassId === null) {
            return $this->zeroTax($netMinorUnits, $currencyCode);
        }

        $rule = $this->resolveRule($orgTaxClassId, $productTaxClassId);

        if (! $rule) {
            return $this->zeroTax($netMinorUnits, $currencyCode);
        }

        $taxRate = $rule->taxRate;

        if (! $taxRate || ! $taxRate->is_active) {
            return $this->zeroTax($netMinorUnits, $currencyCode);
        }

        $rate = (string) $taxRate->rate;
        $rawTax = bcmul((string) $netMinorUnits, bcdiv($rate, '100', 10), 10);
        $taxAmount = (int) bcadd($rawTax, $rawTax[0] === '-' ? '-0.5' : '0.5', 0);

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
