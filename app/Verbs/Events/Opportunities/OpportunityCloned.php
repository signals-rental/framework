<?php

namespace App\Verbs\Events\Opportunities;

use App\Actions\Opportunities\CloneOpportunity;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Audit-trail marker recording that a NEW opportunity was cloned from a SOURCE
 * opportunity.
 *
 * This event carries NO state mutation of its own: the clone is materialised by
 * the {@see CloneOpportunity} action firing a fresh
 * {@see OpportunityCreated} (the new Draft) followed by replaying the source's
 * line items and costs through the existing {@see ItemAdded} / {@see CostAdded}
 * flows, so demands and totals rebuild naturally. This event exists purely to
 * stamp the lineage (`source_opportunity_id` → new id) onto the audit log.
 *
 * `opportunity_id` is the Verbs snowflake StateId of the NEWLY created
 * opportunity's already-existing {@see OpportunityState} (mirroring the other
 * transition events, which take the state_id as their StateId argument), so apply()
 * is a no-op fold over that existing state and handle() resolves the projection row
 * via its `state_id`. `source_opportunity_id` is the small projection PK of the
 * SOURCE opportunity, recorded for lineage only.
 */
class OpportunityCloned extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public int $source_opportunity_id = 0,
    ) {}

    /**
     * No state mutation: the clone's state is fully established by the genesis
     * {@see OpportunityCreated} event plus the replayed item/cost events. This
     * event only records lineage in the audit trail.
     */
    public function apply(OpportunityState $state): void
    {
        // Intentionally empty — see class docblock.
    }

    public function handle(OpportunityState $state): void
    {
        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

        // Persist the clone lineage onto the new row (C3e). The genesis
        // OpportunityCreated has already projected this row; this event runs
        // after it (and after the item/cost replays) within the same atomic
        // clone commit, so the column is written exactly once and replay
        // reproduces it deterministically from the stored source_opportunity_id.
        if ($this->source_opportunity_id !== 0) {
            $opportunity->forceFill(['source_opportunity_id' => $this->source_opportunity_id])->save();
        }

        $this->recordAudit(
            $opportunity,
            'opportunity.cloned',
            newValues: ['source_opportunity_id' => $this->source_opportunity_id],
            oldValues: null,
        );
    }
}
