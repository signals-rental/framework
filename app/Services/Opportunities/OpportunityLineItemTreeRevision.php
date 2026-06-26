<?php

namespace App\Services\Opportunities;

use App\Models\OpportunityItem;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Models\VerbStateEvent;

/**
 * Resolves the opportunity line-item tree revision token used by the local-first
 * editor for stale-write detection.
 *
 * The token is the latest Verbs event id touching any item state in the
 * opportunity's active-version scope — a monotonic counter that advances on every
 * committed item mutation (add, field change, restructure, remove).
 */
class OpportunityLineItemTreeRevision
{
    public function current(int $opportunityId): int
    {
        $stateIds = OpportunityItem::query()
            ->where('opportunity_id', $opportunityId)
            ->pluck('state_id');

        if ($stateIds->isEmpty()) {
            return 0;
        }

        if (! Schema::hasTable('verb_state_events')) {
            return 0;
        }

        $max = VerbStateEvent::query()
            ->whereIn('state_id', $stateIds)
            ->max('event_id');

        return (int) ($max ?? 0);
    }
}
