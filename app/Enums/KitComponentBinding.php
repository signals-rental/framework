<?php

namespace App\Enums;

use App\Services\Availability\KitAvailabilityCalculator;

/**
 * How a kit component is sourced when the kit is dispatched.
 *
 *  - **Pool** (default) — drawn from general stock per job. Pool components
 *    explode into standard `opportunity_item` demands at book time and feed the
 *    {@see KitAvailabilityCalculator} MIN formula at
 *    read time. This is the only binding the catalogue-kit chunk (M5-3a) treats
 *    fully.
 *  - **Fixed** — permanently assigned to a physical container. Fixed components
 *    are checked through container demands (sentinel-dated) rather than exploded
 *    per job. Container modelling lands in M5-3b; until then a fixed binding is a
 *    seam, not behaviour.
 */
enum KitComponentBinding: string
{
    case Fixed = 'fixed';
    case Pool = 'pool';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::Pool => 'Pool',
        };
    }
}
