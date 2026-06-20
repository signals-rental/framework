<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState as StateAxis;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\Events\Opportunities\Concerns\ResyncsOpportunityDemands;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Reverses an open Quotation back to a Draft (opportunity-lifecycle.md §5.2
 * OpportunityRevertedToDraft, RMS `convert_to_draft`) — the state-axis backward
 * transition, the inverse of {@see OpportunityQuoted} / ConvertToQuotation.
 *
 * Lands on Draft/Open (the Draft state's default status). Used to pull a
 * quotation that was raised prematurely back to a working draft so it can be
 * re-shaped before being re-quoted.
 *
 * Guarded on two generic, phase/state rules (never a named-status matrix, Ben's
 * locked steer):
 *  - the opportunity must be in the Quotation state and in its open/provisional
 *    phase — {@see OpportunityStatus::isRevertibleToDraft()} (the Quotation
 *    state's default, draft-equivalent open status); a Reserved / Lost / Dead /
 *    Postponed quote has already moved on and is not freely reverted; and
 *  - it must not be closed/terminal.
 *
 * Demand effect: a Draft carries no confirmed demand (Draft/Open maps to
 * {@see App\Enums\DemandPhase::Draft}, inactive). Reverting therefore stands the
 * line demands DOWN, which {@see ResyncsOpportunityDemands} performs by
 * re-deriving the line demands from the now-draft status. Replay-safe — the
 * audit dedups on the event id and the resync is idempotent.
 *
 * Replay-safe: apply() flips in-memory state/status, handle() projects them +
 * records the audit (deduped on the event id) + resyncs (idempotent).
 */
class OpportunityRevertedToDraft extends Event
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
            $state->state === StateAxis::Quotation->value,
            'Only a quotation can be reverted to a draft.',
        );

        $this->assert(
            ! $state->isClosed(),
            'A closed quotation cannot be reverted to a draft.',
        );

        // Generic phase rule — only the open/provisional quotation phase reverts;
        // a quote that has progressed (Reserved) or been parked/closed does not.
        $this->assert(
            $state->status()->isRevertibleToDraft(),
            'Only an open, provisional quotation can be reverted to a draft.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        $state->state = StateAxis::Draft->value;
        $state->status = StateAxis::Draft->defaultStatus()->statusValue();
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        // Capture the prior state/status as raw integers BEFORE the projection
        // update (the model casts `state` to an enum, so read raw).
        $oldRow = Opportunity::query()->where('state_id', $state->id)->first();
        $oldValues = $oldRow !== null ? [
            'state' => (int) $oldRow->getRawOriginal('state'),
            'status' => (int) $oldRow->getRawOriginal('status'),
        ] : null;

        Opportunity::query()
            ->where('state_id', $state->id)
            ->update([
                'state' => $state->state,
                'status' => $state->status,
            ]);

        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

        $this->recordAudit(
            $opportunity,
            'opportunity.reverted_to_draft',
            newValues: [
                'state' => $state->state,
                'status' => $state->status,
                'reason' => $this->reason,
            ],
            oldValues: $oldValues,
        );

        $this->resyncOpportunityDemands($opportunity);
    }
}
