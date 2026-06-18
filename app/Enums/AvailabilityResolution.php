<?php

namespace App\Enums;

/**
 * Granularity at which the availability engine snapshots demand and stock.
 *
 * The resolution fixes the time bucket size for demand windows and availability
 * snapshots. It is immutable once availability data exists, because changing it
 * would require migrating every existing snapshot to the new bucket size.
 */
enum AvailabilityResolution: string
{
    case Hourly = 'hourly';
    case HalfDaily = 'half_daily';
    case Daily = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::HalfDaily => 'Half-daily',
            self::Daily => 'Daily',
        };
    }
}
