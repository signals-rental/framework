<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\ChangeVersionLabelData;
use App\Data\Opportunities\OpportunityVersionData;
use App\Models\OpportunityVersion;
use App\Verbs\Events\Opportunities\VersionLabelChanged;
use Illuminate\Support\Facades\Gate;

/**
 * Renames a quote version's label (opportunity-lifecycle.md §8.6).
 */
class ChangeVersionLabel
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityVersion $version, ChangeVersionLabelData $data): OpportunityVersionData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($version, $data): void {
            VersionLabelChanged::fire(
                version_id: $version->state_id,
                label: $data->label,
            );
        });

        return OpportunityVersionData::fromModel($version->refresh());
    }
}
