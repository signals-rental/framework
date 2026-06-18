<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for substituting the catalogue item a line refers to.
 */
class SubstituteItemData extends Data
{
    public function __construct(
        public ?int $item_id = null,
        public ?string $item_type = null,
        public ?string $name = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'item_id' => ['sometimes', 'nullable', 'integer'],
            'item_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
