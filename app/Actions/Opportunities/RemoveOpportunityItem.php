<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Enums\OpportunityItemType;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemRemoved;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a line item from its opportunity via the ItemRemoved event.
 *
 * Removing a group row cascades its entire subtree: every descendant is removed
 * deepest-first inside one atomic commitVerbs boundary (mirroring
 * {@see MergeOpportunityItems}'s multi-fire pattern). Existing asset/dispatch
 * guards on {@see ItemRemoved} still apply per row.
 */
class RemoveOpportunityItem
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $removalOrder = $this->removalOrder($item);

        $this->commitVerbs(function () use ($removalOrder): void {
            $items = OpportunityItem::query()
                ->whereIn('id', $removalOrder)
                ->get()
                ->keyBy('id');

            foreach ($removalOrder as $id) {
                /** @var OpportunityItem $row */
                $row = $items->get($id);

                ItemRemoved::fire(opportunity_item_id: $row->state_id);
            }
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }

    /**
     * The projection PKs to remove, deepest-first, with the target row last.
     *
     * @return list<int>
     */
    private function removalOrder(OpportunityItem $item): array
    {
        if ($item->item_type !== OpportunityItemType::Group) {
            return [$item->id];
        }

        $descendants = OpportunityItem::query()
            ->where('opportunity_id', $item->opportunity_id)
            ->when(
                $item->version_id !== null,
                fn ($query) => $query->where('version_id', $item->version_id),
                fn ($query) => $query->whereNull('version_id'),
            )
            ->where('path', 'like', $item->path.'%')
            ->whereRaw('LENGTH(path) > ?', [strlen((string) $item->path)])
            ->orderByRaw('LENGTH(path) DESC')
            ->orderByDesc('path')
            ->pluck('id')
            ->all();

        return [...$descendants, $item->id];
    }
}
