<?php

namespace App\Verbs\States;

use App\Enums\OpportunityState as StateAxis;
use App\Enums\OpportunityStatus;
use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

/**
 * In-memory event-sourced representation of an opportunity.
 *
 * Verbs folds events onto this object via their apply() methods. It holds only
 * public scalar properties (Verbs serialises public properties only) and carries
 * no business logic — business rules live in events, projections live in their
 * handle() methods.
 *
 * `state` and `status` are stored as the raw RMS integers (mirroring the
 * `opportunities` columns) so serialisation is trivial and the projection
 * mapping is one-to-one. Use {@see status()} / {@see StateAxis()} for the
 * type-safe enum view.
 */
class OpportunityState extends State
{
    /**
     * Application-allocated small projection PK (set by the genesis event).
     * The state's inherent `->id` remains the Verbs snowflake StateId.
     */
    public int $opportunity_id = 0;

    /** Document-type axis (0=Draft, 1=Quotation, 2=Order). */
    public int $state = 0;

    /** Workflow-position axis — per-state integer. */
    public int $status = 0;

    public ?string $subject = null;

    public ?int $member_id = null;

    public ?int $venue_id = null;

    public ?int $store_id = null;

    public ?int $owned_by = null;

    public ?string $reference = null;

    public ?string $description = null;

    public ?string $external_description = null;

    public ?CarbonImmutable $starts_at = null;

    public ?CarbonImmutable $ends_at = null;

    public ?CarbonImmutable $charge_starts_at = null;

    public ?CarbonImmutable $charge_ends_at = null;

    public int $item_count = 0;

    /** Charge total in integer minor units (pence/cents/fils). */
    public int $charge_total = 0;

    public bool $is_invoiced = false;

    /** True once the opportunity has been soft-deleted (archived) via OpportunityDeleted. */
    public bool $is_deleted = false;

    public ?CarbonImmutable $last_event_at = null;

    public function stateAxis(): StateAxis
    {
        return StateAxis::from($this->state);
    }

    public function status(): OpportunityStatus
    {
        return OpportunityStatus::fromStateAndStatus($this->stateAxis(), $this->status);
    }

    /**
     * True once the opportunity has reached a closed/terminal status and can no
     * longer be mutated through the standard lifecycle events.
     */
    public function isClosed(): bool
    {
        return $this->status()->isClosed();
    }
}
