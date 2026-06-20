<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\OpportunityData;
use App\Models\OpportunityItem;
use App\Services\Shortages\ItemShortageProbe;
use App\Verbs\Events\Opportunities\ItemQuantityChanged;
use Illuminate\Support\Facades\Gate;

/**
 * Changes a line item's quantity via the ItemQuantityChanged event.
 *
 * After the event commits, the {@see ItemShortageProbe} rechecks the line for a
 * shortage over its window (shortage-resolution-sub-hires.md §2.4 "On quantity
 * changes"), emitting `shortage.detected`/`shortage.cleared` as appropriate. The
 * probe never blocks the edit and is skipped during replay.
 */
class ChangeItemQuantity
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, ChangeItemQuantityData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data): void {
            ItemQuantityChanged::fire(
                opportunity_item_id: $item->state_id,
                quantity: $data->quantity,
            );
        });

        // Inline re-detection (§2.4) over the line's new quantity.
        app(ItemShortageProbe::class)->probe($item->refresh());

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
