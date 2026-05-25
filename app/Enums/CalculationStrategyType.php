<?php

namespace App\Enums;

/**
 * How a calculation strategy converts elapsed time into chargeable units.
 *
 * Each strategy declares which base periods it supports; the rate engine uses
 * this to constrain the base period offered when building a rate definition.
 */
enum CalculationStrategyType: string
{
    case Period = 'period';
    case Fixed = 'fixed';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Period => 'Period-based',
            self::Fixed => 'Fixed',
            self::Hybrid => 'Hybrid',
        };
    }

    /**
     * Base periods this strategy permits.
     *
     * @return array<int, BasePeriod>
     */
    public function allowedBasePeriods(): array
    {
        return match ($this) {
            self::Period => [
                BasePeriod::HalfHourly,
                BasePeriod::Hourly,
                BasePeriod::Daily,
                BasePeriod::Weekly,
                BasePeriod::Monthly,
            ],
            self::Hybrid => [
                BasePeriod::Daily,
                BasePeriod::Weekly,
                BasePeriod::Monthly,
            ],
            self::Fixed => [],
        };
    }

    /**
     * Whether a base period must be supplied for this strategy.
     */
    public function requiresBasePeriod(): bool
    {
        return match ($this) {
            self::Period, self::Hybrid => true,
            self::Fixed => false,
        };
    }
}
