<?php

namespace App\Services\RateEngine\Strategies;

use App\Contracts\CalculationStrategy;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Services\RateEngine\Strategies\Concerns\CountsChargeableUnits;
use App\ValueObjects\CalculationContext;
use App\ValueObjects\RateBreakdown;
use App\ValueObjects\RateLineItem;
use RuntimeException;

/**
 * Charges a flat fee covering an initial block of base-period units, then the
 * context unit price for every unit beyond that block. Maps to Current RMS's
 * "Fixed Rate and Subs Days Engine".
 *
 * Config (in {@see CalculationContext::$strategyConfig}):
 *  - fixed_charge (int): flat charge, in minor units, for the initial block
 *  - fixed_period_units (int): how many base-period units the fixed charge covers
 *
 * The context's `unitPriceMinor` is the per-unit price for subsequent units.
 * The fixed block and the subsequent block are both subject to the time options
 * (leeway, cut-offs, day type) via the shared unit count. Multipliers and
 * factors do not apply.
 */
class HybridStrategy implements CalculationStrategy
{
    use CountsChargeableUnits;

    public function identifier(): string
    {
        return CalculationStrategyType::Hybrid->value;
    }

    public function label(): string
    {
        return CalculationStrategyType::Hybrid->label();
    }

    /**
     * @return array<int, BasePeriod>
     */
    public function allowedBasePeriods(): array
    {
        return CalculationStrategyType::Hybrid->allowedBasePeriods();
    }

    public function supportsMultiplier(): bool
    {
        return false;
    }

    public function supportsFactor(): bool
    {
        return false;
    }

    public function calculate(CalculationContext $context): RateBreakdown
    {
        $period = $context->basePeriod;

        if (! $period instanceof BasePeriod) {
            throw new RuntimeException('The hybrid strategy requires a base period.');
        }

        $fixedCharge = (int) ($context->strategyConfig['fixed_charge'] ?? 0);
        $fixedPeriodUnits = max(0, (int) ($context->strategyConfig['fixed_period_units'] ?? 0));

        $units = $this->chargeableUnits($context, $period);
        $unitLabel = $this->unitLabel($period);

        $subsequentUnits = max(0, $units - $fixedPeriodUnits);
        $subsequentSubtotalMinor = $subsequentUnits * $context->unitPriceMinor;
        $perUnitSubtotalMinor = $fixedCharge + $subsequentSubtotalMinor;

        $lineItems = [];

        // Only show the fixed block when it actually covers an initial period; an
        // unconfigured hybrid (no fixed period) bills every unit at the unit price.
        if ($fixedPeriodUnits > 0) {
            $lineItems[] = new RateLineItem(
                periodFrom: 1,
                periodTo: min($units, $fixedPeriodUnits),
                label: sprintf('Fixed charge (first %d %s) × %d', $fixedPeriodUnits, $unitLabel, $fixedCharge),
                multiplier: '1.0',
                unitPriceMinor: $fixedCharge,
                lineTotalMinor: $fixedCharge,
            );
        }

        if ($subsequentUnits > 0) {
            $lineItems[] = new RateLineItem(
                periodFrom: $fixedPeriodUnits + 1,
                periodTo: $units,
                label: sprintf('%d subsequent %s × %d', $subsequentUnits, $unitLabel, $context->unitPriceMinor),
                multiplier: '1.0',
                unitPriceMinor: $context->unitPriceMinor,
                lineTotalMinor: $subsequentSubtotalMinor,
            );
        }

        return new RateBreakdown(
            unitPriceMinor: $context->unitPriceMinor,
            currency: $context->currency,
            units: $units,
            unitLabel: $unitLabel,
            perUnitSubtotalMinor: $perUnitSubtotalMinor,
            quantity: $context->quantity,
            lineItems: $lineItems,
            appliedModifiers: [],
        );
    }
}
