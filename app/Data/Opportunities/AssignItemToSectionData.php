<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for assigning a line item to a custom grouping (section), or clearing
 * its assignment.
 *
 * `section_id = null` CLEARS the assignment (the line falls back to automatic
 * product-group grouping). A non-null id must belong to the same opportunity as
 * the line (enforced by the action). The write targets only the plain,
 * NON-event-sourced `opportunity_items.section_id` column — never the Verbs event
 * stream — so it survives replay.
 */
class AssignItemToSectionData extends Data
{
    public function __construct(
        public ?int $section_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'section_id' => ['nullable', 'integer'],
        ];
    }
}
