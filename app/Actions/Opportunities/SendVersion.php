<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityVersionData;
use App\Models\OpportunityVersion;
use App\Verbs\Events\Opportunities\VersionSent;
use Illuminate\Support\Facades\Gate;

/**
 * Marks a quote version as Sent to the customer (opportunity-lifecycle.md §8.6),
 * recording WHO it was sent to (`sentTo`, a member) and HOW (`sentVia`, e.g.
 * email/portal/manual) on the event stream.
 */
class SendVersion
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityVersion $version, ?int $sentTo = null, ?string $sentVia = null): OpportunityVersionData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($version, $sentTo, $sentVia): void {
            VersionSent::fire(
                version_id: $version->state_id,
                sent_to: $sentTo,
                sent_via: $sentVia,
            );
        });

        return OpportunityVersionData::fromModel($version->refresh());
    }
}
