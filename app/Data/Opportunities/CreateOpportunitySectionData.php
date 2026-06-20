<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for creating a custom line-item grouping (section) on an opportunity.
 *
 * Sections are plain, non-event-sourced rows (M8-3 grouping decision). The parent
 * opportunity is supplied to the action directly, so it is not part of the
 * payload.
 */
class CreateOpportunitySectionData extends Data
{
    public function __construct(
        public string $name,
        public int $sort_order = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
