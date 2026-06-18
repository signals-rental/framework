<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityDeleted;
use Illuminate\Support\Facades\Gate;

/**
 * Soft-deletes (archives) an opportunity via the OpportunityDeleted event,
 * committing the event and its projection deletion atomically.
 *
 * Event-sourcing-consistent: the deletion is recorded as an event so history and
 * replay are preserved; the projection row is soft-deleted in the event's
 * handle().
 */
class DeleteOpportunity
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.delete');

        $this->commitVerbs(function () use ($opportunity): void {
            OpportunityDeleted::fire(opportunity_id: $opportunity->state_id);
        });
    }
}
