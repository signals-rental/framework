<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\RunsOpportunityTransition;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityRevertedToDraft;

/**
 * Reverts an open Quotation back to a Draft via the
 * {@see OpportunityRevertedToDraft} event (RMS `convert_to_draft`) — the
 * state-axis backward transition, the inverse of {@see ConvertToQuotation}.
 *
 * Routed through the M7 {@see GuardPipeline} (Permission → Approval → Business
 * Rules → Plugin validators) before firing, so the `opportunity.revert_to_draft`
 * transition is permission-checked and rule-extensible, and shows up in the
 * `available_actions` endpoint via {@see GuardPipeline::check()}. The hard
 * invariants — must be an open/provisional Quotation, not closed — stay in the
 * event's Verbs `validate()`.
 */
class RevertToDraft
{
    use CommitsVerbsEvents, RunsOpportunityTransition;

    /** The transition key this action drives through the guard pipeline. */
    public const string TRANSITION = 'opportunity.revert_to_draft';

    public function __invoke(Opportunity $opportunity, ?string $reason = null): OpportunityData
    {
        return $this->runTransition(self::TRANSITION, $opportunity, OpportunityRevertedToDraft::class, $reason);
    }
}
