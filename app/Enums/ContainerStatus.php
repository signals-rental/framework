<?php

namespace App\Enums;

/**
 * The lifecycle status of a container (serialised-containers.md §"Container
 * Lifecycle").
 *
 *  - **Open** — being built; items can be packed/unpacked.
 *  - **Sealed** — contents verified and locked; ready for dispatch.
 *  - **Dispatched** — kit/hybrid container checked out to an opportunity as a
 *    unit (persists; does not dissolve). Phase-4 lifecycle.
 *  - **Returned** — kit/hybrid container returned from the opportunity. Phase-4.
 *  - **Dissolved** — transport container broken apart (on dispatch or manually);
 *    contents released and tracked individually. Phase-4.
 *
 * For the M5-3b availability subset only **Open** and **Sealed** are exercised;
 * the remaining states exist as columns/values for the Phase-4 operational
 * lifecycle.
 */
enum ContainerStatus: string
{
    case Open = 'open';
    case Sealed = 'sealed';
    case Dispatched = 'dispatched';
    case Returned = 'returned';
    case Dissolved = 'dissolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Sealed => 'Sealed',
            self::Dispatched => 'Dispatched',
            self::Returned => 'Returned',
            self::Dissolved => 'Dissolved',
        };
    }

    /**
     * Whether the container still actively holds its contents — i.e. its items'
     * container demands remain in force. Every status except Dissolved holds the
     * contents (a dispatched/returned kit keeps its grouping).
     */
    public function holdsContents(): bool
    {
        return $this !== self::Dissolved;
    }

    /**
     * Whether items may be packed into / unpacked from the container in this
     * status. Only an open container accepts pack/unpack mutations.
     */
    public function acceptsPacking(): bool
    {
        return $this === self::Open;
    }
}
