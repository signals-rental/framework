<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityQuoted;
use Illuminate\Support\Facades\Gate;

/**
 * Converts a Draft opportunity into a Quotation via the OpportunityQuoted event.
 */
class ConvertToQuotation
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($opportunity): void {
            OpportunityQuoted::fire(opportunity_id: $opportunity->state_id);
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
