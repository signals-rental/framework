<?php

namespace App\Data\Shortages;

use App\Models\ShortageResolutionItem;
use Spatie\LaravelData\Data;

/**
 * API representation of a resolution-to-opportunity-item allocation
 * (shortage-resolution-sub-hires.md §8.2).
 */
class ShortageResolutionItemData extends Data
{
    public function __construct(
        public int $id,
        public int $opportunity_item_id,
        public int $quantity_allocated,
    ) {}

    public static function fromModel(ShortageResolutionItem $item): self
    {
        return new self(
            id: $item->id,
            opportunity_item_id: $item->opportunity_item_id,
            quantity_allocated: $item->quantity_allocated,
        );
    }
}
