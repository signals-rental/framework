<?php

namespace App\Data\Opportunities;

use App\Actions\Opportunities\ReorderOpportunityItems;
use Spatie\LaravelData\Data;

/**
 * Input DTO for reordering an opportunity's line items.
 *
 * `item_ids` is the desired ordering of opportunity_item ids; the
 * {@see ReorderOpportunityItems} action fires one event-sourced
 * `ItemSortOrderChanged` per id, writing each item's `sort_order` to its index in
 * this list (0-based). All ids must belong to the target opportunity (enforced by
 * the action).
 */
class ReorderOpportunityItemsData extends Data
{
    /**
     * @param  list<int>  $item_ids
     */
    public function __construct(
        public array $item_ids,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['integer'],
        ];
    }
}
