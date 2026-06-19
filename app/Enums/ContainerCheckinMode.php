<?php

namespace App\Enums;

/**
 * How a kit/hybrid container is scanned at check-in
 * (serialised-containers.md §"Check-In Scanning Modes"). Configured per
 * containerable product. Null for transport-mode products (they dissolve on
 * dispatch and are returned per-item).
 *
 *  - **Parent** (default for kit mode) — scan the container barcode only; all
 *    contents return as a unit.
 *  - **Individual** — scan each component individually; the container is marked
 *    returned only when all contents have been scanned.
 *  - **ParentThenVerify** — scan the container to initiate the return, then scan
 *    individual items for condition assessment.
 *
 * Check-in scanning is a Phase-4 operational concern; this enum exists so the
 * product-level configuration column can be typed in M5-3b.
 */
enum ContainerCheckinMode: string
{
    case Parent = 'parent';
    case Individual = 'individual';
    case ParentThenVerify = 'parent_then_verify';

    public function label(): string
    {
        return match ($this) {
            self::Parent => 'Parent',
            self::Individual => 'Individual',
            self::ParentThenVerify => 'Parent then verify',
        };
    }
}
