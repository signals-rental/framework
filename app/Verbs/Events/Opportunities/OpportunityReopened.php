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
 * Re-opens a terminally COMPLETE order back to an active status
 * (opportunity-lifecycle.md §5.2 OpportunityReopened, RMS `re_open`) — the
 * backward-transition complement to the terminal "complete" close.
 *
 * This is the path that {@see OpportunityReinstated} deliberately excludes: a
 * Complete order is finalised (all assets returned/checked), so it is not a
 * Void/Held reinstatement. Re-opening returns it to the Order state's default
 * ACTIVE status so the deal can be amended after closure (e.g. a late
 * adjustment, a re-hire of the same booking).
 *
 * Applies within the Order document STATE — it does not reverse a state-axis
 * move (that is {@see OpportunityRevertedToQuotation} / {@see OpportunityRevertedToDraft}).
 * The target is the Order state's {@see OpportunityState::defaultStatus()} active
 * status, so the rule is config-driven by phase, never a hardcoded named-status
 * matrix (Ben's locked steer).
 *
 * Guarded generically on {@see OpportunityStatus::isTerminalComplete()} — the
 * status must be the Order state's terminal complete close (a non-Void closed
 * Order status). A Cancelled/Lost/Dead/Postponed deal is handled by Reinstate,
 * and an already-active deal has nothing to reopen — both are rejected here.
 *
 * Demand effect: re-opening re-activates the opportunity's demand (a re-opened
 * order reserves stock again), so {@see ResyncsOpportunityDemands} re-derives the
 * line demands from the now-active status. Replay-safe — the audit dedups on the
 * event id and the resync is idempotent.
 */
class OpportunityReopened extends Event
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
            $state->status()->isTerminalComplete(),
            'Only a completed order can be re-opened.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        // Return to the Order state's default ACTIVE status. Derived from the
        // state, not name-matched, so configurable statuses inherit the behaviour.
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
            'opportunity.reopened',
            newValues: ['status' => $state->status, 'reason' => $this->reason],
            oldValues: $oldValues,
        );

        $this->resyncOpportunityDemands($opportunity);
    }
}
