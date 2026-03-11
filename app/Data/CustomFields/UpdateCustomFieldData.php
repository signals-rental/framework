<?php

namespace App\Data\CustomFields;

use Spatie\LaravelData\Data;

class UpdateCustomFieldData extends Data
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @param  array<string, mixed>|null  $validation_rules
     * @param  array<string, mixed>|null  $visibility_rules
     */
    public function __construct(
        public ?string $name = null,
        public ?string $display_name = null,
        public ?string $description = null,
        public ?int $custom_field_group_id = null,
        public ?int $list_name_id = null,
        public ?int $sort_order = null,
        public ?bool $is_required = null,
        public ?bool $is_searchable = null,
        public ?array $settings = null,
        public ?array $validation_rules = null,
        public ?array $visibility_rules = null,
        public ?string $default_value = null,
        public ?string $plugin_name = null,
        public ?string $document_layout_name = null,
        public ?bool $is_active = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
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
