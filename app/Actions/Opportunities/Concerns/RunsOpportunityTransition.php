<?php

namespace App\Actions\Opportunities\Concerns;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use Thunk\Verbs\Event;

/**
 * Shared driver for the simple opportunity status/state transition actions
 * ({@see App\Actions\Opportunities\ReinstateOpportunity},
 * {@see App\Actions\Opportunities\ReopenOpportunity},
 * {@see App\Actions\Opportunities\RevertToDraft},
 * {@see App\Actions\Opportunities\RevertToQuotation}).
 *
 * Each runs the M7 {@see GuardPipeline} (Permission → Approval → Business Rules →
 * Plugin validators) then fires exactly ONE transition event carrying the
 * opportunity's snowflake `state_id` and an optional reason, inside one atomic
 * commit. The event's Verbs `validate()` invariant remains the final hard guard.
 *
 * Requires {@see CommitsVerbsEvents} (provided by the using action).
 */
trait RunsOpportunityTransition
{
    /**
     * Run a guarded single-event transition: pipeline-check the transition, fire the
     * event with the opportunity's state_id + reason, and return the refreshed DTO.
     *
     * @param  class-string<Event>  $eventClass  the transition event to fire
     */
    protected function runTransition(
        string $transition,
        Opportunity $opportunity,
        string $eventClass,
        ?string $reason = null,
    ): OpportunityData {
        $this->commitVerbs(function () use ($transition, $opportunity, $eventClass, $reason): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: $transition,
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            $eventClass::fire(
                opportunity_id: $opportunity->state_id,
                reason: $reason,
            );
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
