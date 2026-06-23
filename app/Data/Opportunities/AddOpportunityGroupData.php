<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for adding a structural group row to an opportunity's line-item tree.
 *
 * A group is a pure container — it carries no catalogue reference, no price, and
 * no demand. `parent_path` nests the group beneath an existing group; null adds
 * it at the top level. The {@see App\Actions\Opportunities\AddOpportunityGroup}
 * action allocates the concrete path within this scope via
 * {@see App\Services\Opportunities\ItemTreeService}.
 */
class AddOpportunityGroupData extends Data
{
    public function __construct(
        public string $name,
        /**
         * The existing group path under which to nest this group (e.g. '0001').
         * Null adds the group at the top level.
         */
        public ?string $parent_path = null,
        /**
         * Internal: the quote version scope the new group belongs to. When null the
         * action resolves it from the opportunity's active version.
         */
        public ?int $version_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_path' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
