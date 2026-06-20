<?php

namespace App\Enums;

/**
 * A store's dispatch-gate policy for unresolved shortages when an order is
 * dispatched (shortage-resolution-sub-hires.md §7.4).
 *
 * The dispatch gate is SEPARATE from the confirmation gate ({@see ShortagePolicy}):
 * even an order that was knowingly confirmed short still has its own check at
 * dispatch time.
 *
 *  - Block        — dispatch cannot begin while any line in the batch is short.
 *  - WarnPartial  — available items dispatch as a partial shipment; short items
 *    are held back in a pending-dispatch state, with a warning surfaced.
 *  - AllowPartial — available items dispatch silently; short items are held back
 *    without a warning.
 *
 * The default is WarnPartial: dispatching what you can while flagging what you
 * cannot is the least-surprising posture, and (since no dispatch gate enforced a
 * policy before this column existed) it preserves the existing non-blocking
 * dispatch behaviour while making the held-back items visible.
 */
enum ShortageDispatchPolicy: string
{
    case Block = 'block';
    case WarnPartial = 'warn_partial';
    case AllowPartial = 'allow_partial';

    public function label(): string
    {
        return match ($this) {
            self::Block => 'Block',
            self::WarnPartial => 'Warn with partial',
            self::AllowPartial => 'Allow partial',
        };
    }

    /**
     * The policy a store falls back to when none is configured.
     */
    public static function default(): self
    {
        return self::WarnPartial;
    }

    /**
     * Whether this policy hard-blocks dispatch while unresolved shortages remain.
     */
    public function blocksDispatch(): bool
    {
        return $this === self::Block;
    }

    /**
     * Whether this policy surfaces a warning when short items are held back from a
     * partial dispatch (WarnPartial does; AllowPartial holds silently).
     */
    public function warnsOnPartial(): bool
    {
        return $this === self::WarnPartial;
    }
}
