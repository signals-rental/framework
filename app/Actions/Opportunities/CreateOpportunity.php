<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\OpportunityCreated;
use Illuminate\Support\Facades\Gate;

/**
 * Creates a new opportunity as a Draft via the OpportunityCreated event,
 * committing the event and its projection atomically.
 */
class CreateOpportunity
{
    use CommitsVerbsEvents;

    public function __invoke(CreateOpportunityData $data): OpportunityData
    {
        Gate::authorize('opportunities.create');

        $opportunityId = $this->commitVerbs(function () use ($data): int {
            // Allocate the replay-stable small PK and bake it into the event so a
            // truncate + Verbs::replay() rebuild reproduces the identical id.
            // Allocation lives only here — replay re-applies the stored event with
            // its baked-in opportunity_id and never calls this action.
            $opportunityId = app(SequenceAllocator::class)->next('opportunities');

            OpportunityCreated::fire(
                opportunity_id: $opportunityId,
                subject: $data->subject,
                member_id: $data->member_id,
                store_id: $data->store_id,
                owned_by: $data->owned_by,
                venue_id: $data->venue_id,
                reference: $data->reference,
                description: $data->description,
                external_description: $data->external_description,
                starts_at: $data->starts_at,
                ends_at: $data->ends_at,
                charge_total: $data->charge_total,
            );

            return $opportunityId;
        });

        return OpportunityData::fromModel(
            Opportunity::query()->whereKey($opportunityId)->firstOrFail(),
        );
    }
}
