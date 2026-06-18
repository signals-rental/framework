<?php

namespace App\Enums;

/**
 * A store's confirmation-gate policy for unresolved shortages when converting a
 * quotation into an order (see shortage-resolution-sub-hires.md §7.1).
 *
 *  - Block — the conversion is rejected while shortages exist, unless the actor
 *    holds `can_ignore_shortages` AND records an acknowledgement (then it
 *    degrades to Warn for that user, per §7.2).
 *  - Warn  — the conversion proceeds but an acknowledgement is recorded.
 *  - Allow — shortages are still computed/visible but never gate the transition.
 *
 * The default is Warn: visible-but-not-blocking is the least-surprising posture
 * for a fresh single-store install.
 */
enum ShortagePolicy: string
{
    case Block = 'block';
    case Warn = 'warn';
    case Allow = 'allow';

    public function label(): string
    {
        return match ($this) {
            self::Block => 'Block',
            self::Warn => 'Warn',
            self::Allow => 'Allow',
        };
    }

    /**
     * The policy a store falls back to when none is configured.
     */
    public static function default(): self
    {
        return self::Warn;
    }

    /**
     * Apply the `can_ignore_shortages` permission override (§7.2): the permission
     * relaxes the gate by one level — Block becomes Warn, Warn becomes Allow,
     * Allow is unchanged. The actor still SEES the shortages; only the hard block
     * is removed.
     */
    public function relaxedByPermission(): self
    {
        return match ($this) {
            self::Block => self::Warn,
            self::Warn => self::Allow,
            self::Allow => self::Allow,
        };
    }
}
