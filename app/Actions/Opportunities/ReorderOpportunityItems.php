<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemData;
use App\Data\Opportunities\ReorderOpportunityItemsData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemAdded;
use App\Verbs\Events\Opportunities\ItemSortOrderChanged;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Reorders an opportunity's line items.
 *
 * Each item's `sort_order` is set to its 0-based index in the supplied id list by
 * firing one event-sourced {@see ItemSortOrderChanged} per item (the per-item
 * aggregate model — one event targets one item state). Because `sort_order` lives
 * in the item event state, the new order survives a Verbs replay; a plain update
 * would be reverted by {@see ItemAdded} on replay.
 *
 * All ids must belong to the target opportunity; an unknown or foreign id throws a
 * validation error before any event fires. The last fired event is the audit
 * anchor, so the reorder records a single `opportunity.items_reordered` row.
 *
 * @return array<int, OpportunityItemData> the reordered items, in new order
 */
class ReorderOpportunityItems
{
    use CommitsVerbsEvents;

    /**
     * @return array<int, OpportunityItemData>
     */
    public function __invoke(Opportunity $opportunity, ReorderOpportunityItemsData $data): array
    {
        Gate::authorize('opportunities.edit');

        /** @var Collection<int, OpportunityItem> $items */
        $items = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->get()
            ->keyBy('id');

        foreach ($data->item_ids as $itemId) {
            if (! $items->has($itemId)) {
                throw ValidationException::withMessages([
                    'item_ids' => 'One or more line items do not belong to this opportunity.',
                ]);
            }
        }

        $orderedIds = $data->item_ids;
        $lastIndex = count($orderedIds) - 1;

        $this->commitVerbs(function () use ($items, $orderedIds, $lastIndex): void {
            foreach ($orderedIds as $index => $itemId) {
                /** @var OpportunityItem $item */
                $item = $items->get($itemId);

                $isAnchor = $index === $lastIndex;

                ItemSortOrderChanged::fire(
                    opportunity_item_id: $item->state_id,
                    sort_order: $index,
                    emit_audit: $isAnchor,
                    ordered_item_ids: $isAnchor ? $orderedIds : null,
                );
            }
        });

        return $opportunity->fresh(['items'])->items
            ->map(fn (OpportunityItem $item): OpportunityItemData => OpportunityItemData::fromModel($item))
            ->all();
    }
}
