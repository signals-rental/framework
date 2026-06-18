<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityStatusChanged;
use Illuminate\Support\Facades\Gate;

/**
 * Moves an opportunity to a different status within its current state via the
 * OpportunityStatusChanged event.
 */
class ChangeOpportunityStatus
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, OpportunityStatus $status): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($opportunity, $status): void {
            OpportunityStatusChanged::fire(
                opportunity_id: $opportunity->state_id,
                to_status: $status->statusValue(),
            );
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
