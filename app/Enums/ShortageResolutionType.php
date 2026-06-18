<?php

namespace App\Enums;

use App\Contracts\ShortageResolverContract;

/**
 * The kind of resolution a {@see ShortageResolverContract} records
 * against a shortage (shortage-resolution-sub-hires.md §3.3, §8.1).
 *
 * The non-PO resolvers shipped in this milestone use Reallocate, Substitute,
 * Transfer, DateShift, Partial, and Waitlist. Subhire / Purchase / Custom are
 * declared for forward compatibility — they land with virtual stock (Phase 4)
 * and plugin resolvers respectively — so resolution records and event payloads
 * carry a stable, exhaustive type vocabulary from the outset.
 */
enum ShortageResolutionType: string
{
    case Reallocate = 'reallocate';
    case Substitute = 'substitute';
    case Transfer = 'transfer';
    case DateShift = 'date_shift';
    case Partial = 'partial';
    case Waitlist = 'waitlist';
    case Subhire = 'subhire';
    case Purchase = 'purchase';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Reallocate => 'Reallocate from quote',
            self::Substitute => 'Substitute product',
            self::Transfer => 'Warehouse transfer',
            self::DateShift => 'Shift dates',
            self::Partial => 'Partial fulfilment',
            self::Waitlist => 'Waitlist',
            self::Subhire => 'Sub-hire',
            self::Purchase => 'Purchase',
            self::Custom => 'Custom',
        };
    }
}
