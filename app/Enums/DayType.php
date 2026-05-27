<?php

namespace App\Enums;

/**
 * How a "day" is interpreted when counting chargeable units.
 *
 * `Clock` counts elapsed wall-clock time; `Business` counts only minutes that
 * fall within the configured business hours on each calendar day.
 */
enum DayType: string
{
    case Clock = 'clock';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Clock => 'Clock',
            self::Business => 'Business Hours',
        };
    }
}
