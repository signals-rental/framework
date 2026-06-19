<?php

namespace App\Enums;

/**
 * Lifecycle status of a shortage waitlist monitor
 * (shortage-resolution-sub-hires.md §4.6).
 *
 *  - Active    — watching for availability that satisfies the shortage.
 *  - Matched   — freed-up stock was found; the user decides whether to act.
 *  - Expired   — reached `expires_at` without a match.
 *  - Cancelled — withdrawn before a match (e.g. the resolution was cancelled).
 */
enum WaitlistMonitorStatus: string
{
    case Active = 'active';
    case Matched = 'matched';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Matched => 'Matched',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }
}
