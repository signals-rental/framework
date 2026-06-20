<?php

namespace App\Data\Opportunities;

use App\Actions\Opportunities\ReorderOpportunitySections;
use Spatie\LaravelData\Data;

/**
 * Input DTO for reordering an opportunity's custom line-item groupings.
 *
 * `section_ids` is the desired ordering of section ids; the
 * {@see ReorderOpportunitySections} action writes each
 * section's `sort_order` to its index in this list (0-based). Ids must all belong
 * to the target opportunity (enforced by the action).
 */
class ReorderOpportunitySectionsData extends Data
{
    /**
     * @param  list<int>  $section_ids
     */
    public function __construct(
        public array $section_ids,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'section_ids' => ['required', 'array'],
            'section_ids.*' => ['integer'],
        ];
    }
}
