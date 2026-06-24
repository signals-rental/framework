<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for substituting the catalogue item a line refers to.
 *
 * `item_id` is the RMS catalogue reference (maps to `itemable_id`). The polymorphic
 * class is supplied via `itemable_type` — distinct from the structural `item_type`
 * role exposed on {@see OpportunityItemData}.
 */
class SubstituteItemData extends Data
{
    public function __construct(
        public ?int $item_id = null,
        public ?string $itemable_type = null,
        public ?string $name = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'item_id' => ['sometimes', 'nullable', 'integer'],
            'itemable_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
