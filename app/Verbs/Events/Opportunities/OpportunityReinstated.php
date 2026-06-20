<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\Events\Opportunities\Concerns\ResyncsOpportunityDemands;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Reinstates a parked/abandoned opportunity back to an active status
 * (opportunity-lifecycle.md §5.2 OpportunityReinstated) — the backward-transition
 * complement to the close events.
 *
 * Applies within the opportunity's current document STATE (it does not reverse a
 * state-axis move — that is {@see OpportunityRevertedToQuotation}): a Lost / Dead
 * / Postponed quotation returns to Quotation/Provisional, a Cancelled order
 * returns to Order/Active. The target is the state's {@see OpportunityState::defaultStatus()}
 * active status, so the rule is config-driven by phase, never a hardcoded
 * named-status matrix (Ben's locked steer).
 *
 * Guarded generically on {@see OpportunityStatus::isReinstatable()} — the status
 * must be Void-phase (Lost/Dead/Cancelled) or Held-phase (Postponed). An active
 * opportunity has nothing to reinstate and is rejected.
 *
 * Demand effect: reinstating re-activates the opportunity's demand (a reinstated
 * order/quote reserves stock again), so {@see ResyncsOpportunityDemands} re-derives
 * the line demands from the now-active status. Replay-safe — the audit dedups on
 * the event id and the resync is idempotent.
 */
class OpportunityReinstated extends Event
{
    use RecordsOpportunityAudit;
    use ResyncsOpportunityDemands;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public ?string $reason = null,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            $state->status()->isReinstatable(),
            'Only a lost, dead, postponed, or cancelled opportunity can be reinstated.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        // Return to the current document state's default ACTIVE status (Quotation →
        // Provisional, Order → Active). Derived from the state, not name-matched, so
        // configurable statuses inherit the behaviour.
        $state->status = $state->stateAxis()->defaultStatus()->statusValue();
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        // Capture the prior status as a raw integer BEFORE the projection update.
        $oldRow = Opportunity::query()->where('state_id', $state->id)->first();
        $oldValues = $oldRow !== null
            ? ['status' => (int) $oldRow->getRawOriginal('status')]
            : null;

        Opportunity::query()
            ->where('state_id', $state->id)
            ->update(['status' => $state->status]);

        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

        $this->recordAudit(
            $opportunity,
            'opportunity.reinstated',
            newValues: ['status' => $state->status, 'reason' => $this->reason],
            oldValues: $oldValues,
        );

        $this->resyncOpportunityDemands($opportunity);
    }
}
