<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\RunsOpportunityTransition;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
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
    use CommitsVerbsEvents, RunsOpportunityTransition;

    /** The transition key this action drives through the guard pipeline. */
    public const string TRANSITION = 'opportunity.reinstate';

    public function __invoke(Opportunity $opportunity, ?string $reason = null): OpportunityData
    {
        return $this->runTransition(self::TRANSITION, $opportunity, OpportunityReinstated::class, $reason);
    }
}
