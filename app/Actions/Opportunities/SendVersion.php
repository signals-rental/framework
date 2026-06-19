<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityVersionData;
use App\Models\OpportunityVersion;
use App\Verbs\Events\Opportunities\VersionSent;
use Illuminate\Support\Facades\Gate;

/**
 * Marks a quote version as Sent to the customer (opportunity-lifecycle.md §8.6).
 */
class SendVersion
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityVersion $version): OpportunityVersionData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($version): void {
            VersionSent::fire(version_id: $version->state_id);
        });

        return OpportunityVersionData::fromModel($version->refresh());
    }
}
