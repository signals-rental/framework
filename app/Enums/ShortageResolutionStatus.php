<?php

namespace App\Enums;

/**
 * Lifecycle status of a persisted shortage resolution
 * (shortage-resolution-sub-hires.md §8.3).
 *
 * The non-PO resolvers in this milestone settle into one of three states at
 * creation time:
 *
 *  - Confirmed — a self-contained resolution that took immediate effect
 *    (e.g. Partial fulfilment reduced the line quantity).
 *  - Pending   — recorded intent awaiting an unbuilt domain (e.g. Transfer needs
 *    store transfers, Reallocate needs the quote-release event) or awaiting a
 *    user/supplier action.
 *  - Monitoring — the Waitlist state: watching for availability to free up.
 *
 * The remaining states (InProgress / Fulfilled / PartiallyFulfilled / Cancelled
 * / Failed) are declared for the full lifecycle and used as later milestones and
 * plugins drive resolutions through fulfilment.
 */
enum ShortageResolutionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Monitoring = 'monitoring';
    case InProgress = 'in_progress';
    case Fulfilled = 'fulfilled';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Monitoring => 'Monitoring',
            self::InProgress => 'In progress',
            self::Fulfilled => 'Fulfilled',
            self::PartiallyFulfilled => 'Partially fulfilled',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    /**
     * Whether the resolution still actively contributes to covering a shortage
     * (i.e. its `quantity_resolved` should reduce remaining shortfall). Cancelled
     * and Failed resolutions no longer count.
     */
    public function isActive(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Failed], true);
    }
}
