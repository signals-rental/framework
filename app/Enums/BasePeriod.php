<?php

namespace App\Enums;

/**
 * Granularity of a chargeable unit within a calculation strategy.
 *
 * Each period exposes its length in minutes so the rate engine can convert a
 * raw rental duration into a count of chargeable units.
 */
enum BasePeriod: string
{
    case HalfHourly = 'half_hourly';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::HalfHourly => 'Half-Hourly',
            self::Hourly => 'Hourly',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    /**
     * Length of one chargeable unit in minutes.
     *
     * NOTE: `Monthly` is simplified to a fixed 30-day month (30 × 1440 = 43200
     * minutes) in v1. Calendar-month length is a deliberate later refinement.
     * `Weekly` reflects a 7-day week here; callers that honour a configurable
     * `rental_days_per_week` should scale `Daily->minutes()` accordingly rather
     * than rely on this constant.
     */
    public function minutes(): int
    {
        return match ($this) {
            self::HalfHourly => 30,
            self::Hourly => 60,
            self::Daily => 1440,
            self::Weekly => 10080,
            self::Monthly => 43200,
        };
    }

    /**
     * Whether the period is measured against the wall clock (sub-day granularity)
     * rather than as a calendar day or longer.
     */
    public function isClockBased(): bool
    {
        return match ($this) {
            self::HalfHourly, self::Hourly => true,
            self::Daily, self::Weekly, self::Monthly => false,
        };
    }
}
