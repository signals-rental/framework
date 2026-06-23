<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for merging duplicate line items into a single surviving line.
 *
 * `duplicate_item_ids` are the `opportunity_items.id` of the lines to fold into the
 * survivor; their quantities are summed onto the survivor and they are removed.
 */
class MergeOpportunityItemsData extends Data
{
    /**
     * @param  array<int, int>  $duplicate_item_ids
     */
    public function __construct(
        public array $duplicate_item_ids = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'duplicate_item_ids' => ['required', 'array', 'min:1'],
            'duplicate_item_ids.*' => ['integer'],
        ];
    }
}
