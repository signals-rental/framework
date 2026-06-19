<?php

namespace App\Enums;

/**
 * When an opportunity-item demand releases its claim on stock
 * (availability-engine.md §"Configurable release point").
 *
 * The release point governs which line-item operational state maps to the Closed
 * phase (and so frees the unit, subject to turnaround):
 *
 *  - {@see OffHired} (moderate) — release when the client requests return, before
 *    the item is physically back.
 *  - {@see Returned} (conservative, default) — release on physical return.
 *  - {@see Checked} (strict) — release only after inspection clears the item.
 */
enum ReleasePoint: string
{
    case OffHired = 'off_hired';
    case Returned = 'returned';
    case Checked = 'checked';

    public static function default(): self
    {
        return self::Returned;
    }
}
