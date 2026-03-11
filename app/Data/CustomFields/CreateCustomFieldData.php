<?php

namespace App\Data\CustomFields;

use App\Enums\CustomFieldType;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateCustomFieldData extends Data
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @param  array<string, mixed>|null  $validation_rules
     * @param  array<string, mixed>|null  $visibility_rules
     */
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required, Max(255)]
        public string $module_type,
        #[Required]
        public CustomFieldType $field_type,
        public ?string $display_name = null,
        public ?string $description = null,
        public ?int $custom_field_group_id = null,
        public ?int $list_name_id = null,
        public int $sort_order = 0,
        public bool $is_required = false,
        public bool $is_searchable = false,
        public ?array $settings = null,
        public ?array $validation_rules = null,
        public ?array $visibility_rules = null,
        public ?string $default_value = null,
        public ?string $plugin_name = null,
        public ?string $document_layout_name = null,
        public bool $is_active = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'module_type' => ['required', 'string', 'max:255'],
            'field_type' => ['required', new Enum(CustomFieldType::class)],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'custom_field_group_id' => ['sometimes', 'nullable', 'integer', 'exists:custom_field_groups,id'],
            'list_name_id' => ['sometimes', 'nullable', 'integer', 'exists:list_names,id'],
            'sort_order' => ['sometimes', 'integer'],
            'is_required' => ['sometimes', 'boolean'],
            'is_searchable' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'validation_rules' => ['sometimes', 'nullable', 'array'],
            'visibility_rules' => ['sometimes', 'nullable', 'array'],
            'default_value' => ['sometimes', 'nullable', 'string'],
            'plugin_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'document_layout_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
