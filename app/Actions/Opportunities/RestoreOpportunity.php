<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityRestored;
use Illuminate\Support\Facades\Gate;

/**
 * Restores (un-archives) a soft-deleted opportunity via the OpportunityRestored
 * event, committing the event and its projection restoration atomically.
 *
 * Event-sourcing-consistent: the restoration is recorded as an event so history
 * and replay are preserved; the projection row's soft-delete is reversed in the
 * event's handle(). Reuses the `opportunities.delete` permission — the same
 * authority that archived the record un-archives it.
 */
class RestoreOpportunity
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.delete');

        $this->commitVerbs(function () use ($opportunity): void {
            OpportunityRestored::fire(opportunity_id: $opportunity->state_id);
        });
    }
}
