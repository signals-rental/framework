<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityUpdated;
use Illuminate\Support\Facades\Gate;

/**
 * Updates editable header fields on an existing opportunity via the
 * OpportunityUpdated event.
 */
class UpdateOpportunity
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, UpdateOpportunityData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($opportunity, $data): void {
            OpportunityUpdated::fire(
                opportunity_id: $opportunity->state_id,
                subject: $data->subject,
                member_id: $data->member_id,
                venue_id: $data->venue_id,
                store_id: $data->store_id,
                owned_by: $data->owned_by,
                reference: $data->reference,
                description: $data->description,
                external_description: $data->external_description,
                starts_at: $data->starts_at,
                ends_at: $data->ends_at,
            );
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
