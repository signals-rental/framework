<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Input DTO for packing a single serialised item into an open container.
 */
class PackContainerItemData extends Data
{
    public function __construct(
        public int $serialised_item_id,
        public ?string $position = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'serialised_item_id' => ['required', 'integer', 'exists:stock_levels,id'],
            'position' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
