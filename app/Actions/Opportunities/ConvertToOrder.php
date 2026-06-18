<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityConvertedToOrder;
use Illuminate\Support\Facades\Gate;

/**
 * Converts a Quotation into a confirmed Order via the
 * OpportunityConvertedToOrder event.
 */
class ConvertToOrder
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($opportunity): void {
            OpportunityConvertedToOrder::fire(opportunity_id: $opportunity->state_id);
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
