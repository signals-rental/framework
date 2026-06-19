<?php

namespace App\Enums;

/**
 * Why a container_items membership was closed (serialised-containers.md
 * Container Items table, `unpacked_reason`).
 *
 *  - **Dissolved** — released when the container dissolved (Phase-4 dispatch /
 *    manual dissolve).
 *  - **Manual** — an operator unpacked the item from the open container.
 *  - **Transferred** — moved in a single step to another container. Phase-4.
 */
enum ContainerItemUnpackReason: string
{
    case Dissolved = 'dissolved';
    case Manual = 'manual';
    case Transferred = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::Dissolved => 'Dissolved',
            self::Manual => 'Manual',
            self::Transferred => 'Transferred',
        };
    }
}
