<?php

namespace App\Data\ListValues;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateListValueData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        #[Required]
        public int $list_name_id,
        #[Required, Max(255)]
        public string $name,
        public ?int $parent_id = null,
        public int $sort_order = 0,
        public bool $is_system = false,
        public bool $is_active = true,
        public ?array $metadata = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'list_name_id' => ['required', 'integer', 'exists:list_names,id'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
            'sort_order' => ['sometimes', 'integer'],
            'is_system' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
