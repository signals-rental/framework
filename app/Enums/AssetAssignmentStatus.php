<?php

namespace App\Enums;

/**
 * Per-asset position in the dispatch/return cycle.
 *
 * RMS-aligned integers persisted on `opportunity_item_assets.status`. Each
 * physical asset assigned to a line item advances independently through these
 * stages as it is prepared, dispatched, confirmed on hire, checked in, and
 * finally cleared.
 */
enum AssetAssignmentStatus: int
{
    case Allocated = 0;

    case Prepared = 1;

    case Dispatched = 2;

    case OnHire = 3;

    case CheckedIn = 4;

    case Finalised = 5;

    public function label(): string
    {
        return match ($this) {
            self::Allocated => 'Allocated',
            self::Prepared => 'Prepared',
            self::Dispatched => 'Dispatched',
            self::OnHire => 'On Hire',
            self::CheckedIn => 'Checked In',
            self::Finalised => 'Finalised',
        };
    }
}
