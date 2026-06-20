<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityReopened;

/**
 * Re-opens a terminally COMPLETE order back to an active status via the
 * {@see OpportunityReopened} event (RMS `re_open`) — the backward-transition
 * complement to the terminal "complete" close, distinct from
 * {@see ReinstateOpportunity} (which handles Void/Held closes).
 *
 * Routed through the M7 {@see GuardPipeline} (Permission → Approval → Business
 * Rules → Plugin validators) before firing, so the permission check and any
 * future business/approval rule attached to the `opportunity.reopen` transition
 * apply, and the transition surfaces in the `available_actions` endpoint via
 * {@see GuardPipeline::check()}. The Verbs `validate()` invariant (the status must
 * be terminally complete) remains the final hard guard inside fire().
 */
class ReopenOpportunity
{
    use CommitsVerbsEvents;

    /** The transition key this action drives through the guard pipeline. */
    public const string TRANSITION = 'opportunity.reopen';

    public function __invoke(Opportunity $opportunity, ?string $reason = null): OpportunityData
    {
        $this->commitVerbs(function () use ($opportunity, $reason): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: self::TRANSITION,
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            OpportunityReopened::fire(
                opportunity_id: $opportunity->state_id,
                reason: $reason,
            );
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
