<?php

namespace App\Data\CustomFields;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateCustomFieldGroupData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        public ?string $description = null,
        public int $sort_order = 0,
        public ?string $plugin_name = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer'],
            'plugin_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
