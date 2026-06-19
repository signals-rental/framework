<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityVersionData;
use App\Models\OpportunityVersion;
use App\Verbs\Events\Opportunities\VersionAccepted;
use Illuminate\Support\Facades\Gate;

/**
 * Marks a quote version as Accepted by the customer (opportunity-lifecycle.md
 * §8.6), recording WHO accepted it (`acceptedBy`, a member) on the event stream.
 * An accepted version takes priority at quote → order conversion (§8.8).
 */
class AcceptVersion
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityVersion $version, ?int $acceptedBy = null): OpportunityVersionData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($version, $acceptedBy): void {
            VersionAccepted::fire(
                version_id: $version->state_id,
                accepted_by: $acceptedBy,
            );
        });

        return OpportunityVersionData::fromModel($version->refresh());
    }
}
