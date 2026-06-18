<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\AddOpportunityCostData;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\CostAdded;
use Illuminate\Support\Facades\Gate;

/**
 * Adds an ad-hoc cost to an opportunity via the CostAdded genesis event,
 * allocating the replay-stable cost id, firing the event, and committing it with
 * its projection atomically.
 */
class AddOpportunityCost
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, AddOpportunityCostData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($opportunity, $data): void {
            // Allocate the replay-stable small PK and bake it into the event so a
            // truncate + Verbs::replay() rebuild reproduces the identical id.
            $costId = app(SequenceAllocator::class)->next('opportunity_costs');

            CostAdded::fire(
                opportunity_cost_id: $costId,
                opportunity_id: $opportunity->id,
                description: $data->description,
                cost_type: $data->cost_type,
                transaction_type: $data->transaction_type,
                amount: $data->amount,
                quantity: $data->quantity,
                is_optional: $data->is_optional,
                sort_order: $data->sort_order,
                notes: $data->notes,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['costs']));
    }
}
