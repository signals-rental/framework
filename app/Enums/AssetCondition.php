<?php

namespace App\Enums;

/**
 * The condition an asset is assessed in when it is checked back in.
 *
 * RMS-aligned integers persisted on `opportunity_item_assets.condition_on_return`
 * (nullable until the asset is checked in). Damaged or missing assets feed the
 * loss/damage charge flow downstream.
 */
enum AssetCondition: int
{
    case Good = 0;

    case Damaged = 1;

    case Missing = 2;

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Damaged => 'Damaged',
            self::Missing => 'Missing',
        };
    }
}
