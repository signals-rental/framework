<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Models\OpportunityVersion;
use App\Verbs\Events\Opportunities\ItemRemoved;
use App\Verbs\Events\Opportunities\VersionDeleted;
use Illuminate\Support\Facades\Gate;

/**
 * Deletes a (non-active, non-only) quote version (opportunity-lifecycle.md §8.6).
 *
 * Removes the version's line items by firing the standard {@see ItemRemoved}
 * events (replay-consistent — demands released, totals rolled down), then fires
 * {@see VersionDeleted} to hard-delete the version row. All atomic.
 */
class DeleteVersion
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityVersion $version, ?string $reason = null): void
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($version, $reason): void {
            foreach ($version->items()->get() as $item) {
                ItemRemoved::fire(opportunity_item_id: $item->state_id);
            }

            VersionDeleted::fire(version_id: $version->state_id, reason: $reason);
        });
    }
}
