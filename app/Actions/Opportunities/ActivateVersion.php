<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityVersionData;
use App\Models\OpportunityVersion;
use App\Verbs\Events\Opportunities\VersionActivated;
use Illuminate\Support\Facades\Gate;

/**
 * Switches the active quote version (opportunity-lifecycle.md §8.6/§8.9).
 *
 * Fires {@see VersionActivated}, which flips the active flags, re-rolls the
 * opportunity totals onto the new active version's items, and performs the demand
 * swap — all atomically.
 */
class ActivateVersion
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityVersion $version): OpportunityVersionData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($version): void {
            VersionActivated::fire(
                version_id: $version->state_id,
                version_pk: $version->id,
                opportunity_id: $version->opportunity->state_id,
            );
        });

        return OpportunityVersionData::fromModel($version->refresh());
    }
}
