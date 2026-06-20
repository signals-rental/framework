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

    /**
     * Whether this is a terminal status — no further transitions are permitted.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Fulfilled, self::Cancelled, self::Failed], true);
    }

    /**
     * The statuses this status may transition into, per the §8.3 lifecycle matrix:
     *
     *   pending             → confirmed | cancelled | failed
     *   confirmed           → in_progress | cancelled
     *   monitoring          → confirmed | cancelled | failed
     *   in_progress         → fulfilled | partially_fulfilled
     *   partially_fulfilled → fulfilled
     *   fulfilled / cancelled / failed → (terminal)
     *
     * Monitoring (the Waitlist state) is treated as an alias of pending for the
     * forward path: a watched shortage that frees up is confirmed, and a monitor
     * may equally be cancelled or fail (expiry).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled, self::Failed],
            self::Monitoring => [self::Confirmed, self::Cancelled, self::Failed],
            self::Confirmed => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Fulfilled, self::PartiallyFulfilled],
            self::PartiallyFulfilled => [self::Fulfilled],
            self::Fulfilled, self::Cancelled, self::Failed => [],
        };
    }

    /**
     * Whether a transition from this status into $target is permitted (§8.3).
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
