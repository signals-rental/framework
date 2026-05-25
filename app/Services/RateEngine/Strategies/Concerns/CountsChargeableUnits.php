<?php

namespace App\Services\RateEngine\Strategies\Concerns;

use App\Enums\BasePeriod;
use App\Enums\DayType;
use App\ValueObjects\CalculationContext;
use App\ValueObjects\RentalPeriod;

/**
 * Shared helpers for strategies that translate a rental window into a count of
 * chargeable units for a base period, and that label those units for display.
 */
trait CountsChargeableUnits
{
    /**
     * Count the chargeable units in the context's window for the given base
     * period, applying the time options carried in the strategy config.
     */
    protected function chargeableUnits(CalculationContext $context, BasePeriod $period): int
    {
        return (new RentalPeriod($context->start, $context->end))
            ->chargeableUnits($period, $this->timeOptions($context));
    }

    /**
     * Coerce the raw strategy config into the time-option shape
     * {@see RentalPeriod::chargeableUnits()} expects, mapping the `day_type`
     * string onto the {@see DayType} enum.
     *
     * @return array<string, mixed>
     */
    protected function timeOptions(CalculationContext $context): array
    {
        $options = $context->strategyConfig;

        if (isset($options['day_type']) && is_string($options['day_type'])) {
            $options['day_type'] = DayType::tryFrom($options['day_type']) ?? DayType::Clock;
        }

        return $options;
    }

    /**
     * Pluralised, human-readable label for a single base-period unit.
     */
    protected function unitLabel(BasePeriod $period): string
    {
        return match ($period) {
            BasePeriod::HalfHourly => 'half-hours',
            BasePeriod::Hourly => 'hours',
            BasePeriod::Daily => 'days',
            BasePeriod::Weekly => 'weeks',
            BasePeriod::Monthly => 'months',
        };
    }
}
