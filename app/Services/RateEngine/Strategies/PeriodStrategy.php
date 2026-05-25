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
 * Charges one unit for every base-period interval (hour, day, week, month) that
 * elapses during the rental window. This is the most common rental pricing
 * model and the basis for the daily/hourly/weekly/monthly presets.
 *
 * The breakdown returned here is the *base* charge: a flat per-unit price across
 * every unit. The multiplier modifier (M2) later splits the single line item
 * into tiers and the factor modifier scales the subtotal.
 */
class PeriodStrategy implements CalculationStrategy
{
    use CountsChargeableUnits;

    public function identifier(): string
    {
        return CalculationStrategyType::Period->value;
    }

    public function label(): string
    {
        return CalculationStrategyType::Period->label();
    }

    /**
     * @return array<int, BasePeriod>
     */
    public function allowedBasePeriods(): array
    {
        return CalculationStrategyType::Period->allowedBasePeriods();
    }

    public function supportsMultiplier(): bool
    {
        return true;
    }

    public function supportsFactor(): bool
    {
        return true;
    }

    public function calculate(CalculationContext $context): RateBreakdown
    {
        $period = $context->basePeriod;

        if (! $period instanceof BasePeriod) {
            throw new RuntimeException('The period strategy requires a base period.');
        }

        $units = $this->chargeableUnits($context, $period);
        $unitLabel = $this->unitLabel($period);
        $perUnitSubtotalMinor = $units * $context->unitPriceMinor;

        $lineItem = new RateLineItem(
            periodFrom: 1,
            periodTo: $units,
            label: sprintf('%d %s × %d', $units, $unitLabel, $context->unitPriceMinor),
            multiplier: '1.0',
            unitPriceMinor: $context->unitPriceMinor,
            lineTotalMinor: $perUnitSubtotalMinor,
        );

        return new RateBreakdown(
            unitPriceMinor: $context->unitPriceMinor,
            currency: $context->currency,
            units: $units,
            unitLabel: $unitLabel,
            perUnitSubtotalMinor: $perUnitSubtotalMinor,
            quantity: $context->quantity,
            lineItems: [$lineItem],
            appliedModifiers: [],
        );
    }
}
