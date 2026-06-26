<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityVersionData;
use App\Models\OpportunityVersion;
use App\Verbs\Events\Opportunities\VersionDeclined;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Marks a quote version as Declined by the customer (opportunity-lifecycle.md
 * §8.6).
 */
class DeclineVersion
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityVersion $version, ?string $reason = null): OpportunityVersionData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($version, $reason): void {
            VersionDeclined::fire(
                version_id: $version->state_id,
                reason: $reason,
                declined_at: CarbonImmutable::now()->toIso8601String(),
            );
        });

        return OpportunityVersionData::fromModel($version->refresh());
    }
}
