<?php

namespace App\Data\CustomFields;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class UpdateCustomFieldGroupData extends Data
{
    public function __construct(
        #[Max(255)]
        public ?string $name = null,
        public ?string $description = null,
        public ?int $sort_order = null,
        public ?string $plugin_name = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer'],
            'plugin_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
