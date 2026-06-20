<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityReinstated;

/**
 * Reinstates a lost / dead / postponed / cancelled opportunity back to an active
 * status via the {@see OpportunityReinstated} event (the backward-transition
 * complement to the close events).
 *
 * Routed through the M7 {@see GuardPipeline} (Permission → Approval → Business
 * Rules → Plugin validators) before firing, so the permission check and any
 * future business/approval rule attached to the `opportunity.reinstate` transition
 * apply, and the same transition surfaces in the `available_actions` endpoint via
 * {@see GuardPipeline::check()}. The Verbs `validate()` invariant (the status must
 * be reinstatable) remains the final hard guard inside fire().
 */
class ReinstateOpportunity
{
    use CommitsVerbsEvents;

    /** The transition key this action drives through the guard pipeline. */
    public const string TRANSITION = 'opportunity.reinstate';

    public function __invoke(Opportunity $opportunity, ?string $reason = null): OpportunityData
    {
        $this->commitVerbs(function () use ($opportunity, $reason): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: self::TRANSITION,
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            OpportunityReinstated::fire(
                opportunity_id: $opportunity->state_id,
                reason: $reason,
            );
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
