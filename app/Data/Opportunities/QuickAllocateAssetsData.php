<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for batch-allocating several serialised assets to line items in one
 * atomic commit (maps to the RMS `quick_allocate` action).
 *
 * Each allocation pairs a line item with a specific stock level. All allocations
 * fire inside a single Verbs commit, so a failure on any one rolls back the whole
 * batch.
 *
 * @property list<array{opportunity_item_id: int, stock_level_id: int}> $allocations
 */
class QuickAllocateAssetsData extends Data
{
    /**
     * @param  list<array{opportunity_item_id: int, stock_level_id: int}>  $allocations
     */
    public function __construct(
        public array $allocations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.opportunity_item_id' => ['required', 'integer', 'exists:opportunity_items,id'],
            'allocations.*.stock_level_id' => ['required', 'integer', 'exists:stock_levels,id'],
        ];
    }
}
