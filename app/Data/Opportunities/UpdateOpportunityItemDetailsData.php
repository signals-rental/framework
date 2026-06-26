<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Input DTO for updating a line item's description and warehouse notes.
 *
 * Both fields are optional — {@see UpdateOpportunityItemDetails} merges only the
 * provided fields over the item's current values. Explicit null clears a field.
 */
class UpdateOpportunityItemDetailsData extends Data
{
    public function __construct(
        public string|null|Optional $description,
        public string|null|Optional $notes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'description' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
