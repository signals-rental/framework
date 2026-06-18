<?php

namespace App\Enums;

/**
 * The unit of charge a line item's `unit_price` is quoted against.
 *
 * RMS-aligned integers persisted on `opportunity_items.charge_period`. A `Fixed`
 * period means the `unit_price` is the entire charge regardless of duration.
 *
 * Distinct from the rate engine's string-backed {@see BasePeriod}; this enum
 * mirrors the exact Current RMS line-item charge-period integers.
 */
enum ChargePeriod: int
{
    case Hour = 0;

    case Day = 1;

    case Week = 2;

    case Month = 3;

    case Fixed = 4;

    public function label(): string
    {
        return match ($this) {
            self::Hour => 'Hour',
            self::Day => 'Day',
            self::Week => 'Week',
            self::Month => 'Month',
            self::Fixed => 'Fixed',
        };
    }
}
