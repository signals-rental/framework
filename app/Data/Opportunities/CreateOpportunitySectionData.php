<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for creating a custom line-item grouping (section) on an opportunity.
 *
 * Sections are plain, non-event-sourced rows (M8-3 grouping decision). The parent
 * opportunity is supplied to the action directly, so it is not part of the
 * payload. An optional `parent_id` nests this section under another section on the
 * same opportunity (validated against `opportunity_sections`).
 */
class CreateOpportunitySectionData extends Data
{
    public function __construct(
        public string $name,
        public int $sort_order = 0,
        public ?int $parent_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:opportunity_sections,id'],
        ];
    }
}
