<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for re-parenting a custom line-item grouping (section) — the drag-to-nest
 * path in the editor.
 *
 * A null `parent_id` promotes the section to the top level; a non-null id nests it
 * under another section on the SAME opportunity. The action validates ownership, the
 * 4-level max-depth guard (including the moved section's own subtree), and rejects a
 * cycle (a section may not become its own descendant). `sort_order` places the
 * section among its new siblings.
 */
class AssignSectionParentData extends Data
{
    public function __construct(
        public ?int $parent_id = null,
        public int $sort_order = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:opportunity_sections,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
