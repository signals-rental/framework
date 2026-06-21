<?php

namespace App\Enums;

use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;

/**
 * The aggregate fulfilment position of an Order, derived from the projected state
 * of all its line items (opportunity-lifecycle.md §7.6 — the "lowest common
 * denominator" across every item).
 *
 * This phase axis is the type-safe intermediary for auto-promotion: the
 * {@see PromotesOpportunityStatus} trait tallies every item into one of these
 * phases, then maps the phase to a concrete {@see OpportunityStatus} via
 * {@see toStatus()}. Keeping the derivation phase-keyed (rather than mapping the
 * item tally straight to named statuses) means the named order sub-statuses are
 * never hardcoded into the derivation logic — they live in one place, the
 * phase → status table below.
 *
 * The §7.6 derivation, in precedence order:
 *
 *   nothing dispatched anywhere               → {@see NotStarted}    (no promotion)
 *   any item has allocated/prepared units     → {@see PendingDispatch}
 *   any item has dispatched/on-hire units      → {@see OnHire}
 *   any item has returned-but-unchecked units → {@see Returned}
 *   everything dispatched is checked          → {@see Checked}
 *
 * @see OpportunityStatus
 * @see PromotesOpportunityStatus
 */
enum FulfilmentPhase: string
{
    /**
     * Nothing has been dispatched on any line yet — the order is still Active and
     * no fulfilment promotion is implied. {@see toStatus()} returns null.
     */
    case NotStarted = 'not_started';

    /** At least one item holds allocated/prepared units not yet dispatched. */
    case PendingDispatch = 'pending_dispatch';

    /** Everything is out: dispatched/on-hire units remain unreturned. */
    case OnHire = 'on_hire';

    /** All out units are back, but some returns are not yet condition-checked. */
    case Returned = 'returned';

    /** Everything dispatched has been returned and checked. */
    case Checked = 'checked';

    /**
     * The concrete Order sub-status this fulfilment phase promotes to, or null for
     * {@see NotStarted} (which implies no promotion — the order stays Active).
     *
     * This single mapping is the ONLY place the named order fulfilment statuses are
     * referenced by the auto-promotion derivation.
     */
    public function toStatus(): ?OpportunityStatus
    {
        return match ($this) {
            self::NotStarted => null,
            self::PendingDispatch => OpportunityStatus::OrderDispatched,
            self::OnHire => OpportunityStatus::OrderOnHire,
            self::Returned => OpportunityStatus::OrderReturned,
            self::Checked => OpportunityStatus::OrderChecked,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::PendingDispatch => 'Pending Dispatch',
            self::OnHire => 'On Hire',
            self::Returned => 'Returned',
            self::Checked => 'Checked',
        };
    }
}
