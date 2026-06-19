<?php

namespace App\Verbs\Events\Opportunities\Concerns;

use App\Enums\AssetAssignmentStatus;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\AssetAllocated;
use App\Verbs\Events\Opportunities\AssetDeallocated;
use App\Verbs\States\AssetAssignmentState;
use Thunk\Verbs\Event;

/**
 * Shared validation guards for per-asset assignment events that mutate an
 * existing assignment (everything except the {@see AssetAllocated}
 * genesis event).
 *
 * Reads the parent opportunity from the projection (validate() runs before
 * handle(), so the projection reflects the pre-mutation state).
 *
 * @mixin Event
 */
trait GuardsAssetAssignment
{
    /**
     * Assert the parent opportunity is open (not closed/terminal), so its assets
     * may still be mutated.
     */
    protected function assertAssignmentMutable(AssetAssignmentState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();
        $closed = $item?->opportunity()->first()?->statusEnum()->isClosed() ?? false;

        $this->assert(
            ! $closed,
            'A closed opportunity\'s assets cannot be modified.',
        );
    }

    /**
     * Assert the assignment is currently in one of the allowed statuses.
     *
     * @param  list<AssetAssignmentStatus>  $allowed
     */
    protected function assertStatusIn(AssetAssignmentState $state, array $allowed, string $message): void
    {
        $this->assert(
            in_array($state->statusEnum(), $allowed, strict: true),
            $message,
        );
    }

    /**
     * Assert the assignment has NOT been deallocated/removed.
     *
     * {@see AssetDeallocated} hard-deletes the `opportunity_item_assets`
     * projection row (and parks the state at Finalised). Without this guard an
     * event such as AssetStatusReverted could fire against a deallocated
     * assignment — mutating the Verbs state with no projection row to write to,
     * leaving the event store and projection permanently diverged (and the ghost
     * mutation surviving replay). A removed assignment has no projection row, so
     * its absence is the tombstone.
     */
    protected function assertAssignmentNotRemoved(AssetAssignmentState $state): void
    {
        $this->assert(
            OpportunityItemAsset::query()->whereKey($state->assignment_id)->exists(),
            'A deallocated asset assignment can no longer be modified.',
        );
    }
}
