<?php

namespace App\Data\CustomFields;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Partial update for a custom field. Each property is `Optional`, so an omitted
 * key is excluded from `toArray()` and leaves the column untouched, while an
 * explicit `null`/empty on a nullable field (display_name, description,
 * custom_field_group_id, list_name_id, settings, validation_rules,
 * visibility_rules, default_value, plugin_name, document_layout_name) is kept in
 * `toArray()` and clears the column — a distinction the prior all-nullable shape
 * (blanket `array_filter(... !== null)` in the action) could not express, so
 * rules could be set/changed but never cleared (#204).
 */
class UpdateCustomFieldData extends Data
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @param  array<string, mixed>|null  $validation_rules
     * @param  array<string, mixed>|null  $visibility_rules
     */
    public function __construct(
        public string|Optional $name = new Optional,
        public string|null|Optional $display_name = new Optional,
        public string|null|Optional $description = new Optional,
        public int|null|Optional $custom_field_group_id = new Optional,
        public int|null|Optional $list_name_id = new Optional,
        public int|Optional $sort_order = new Optional,
        public bool|Optional $is_required = new Optional,
        public bool|Optional $is_searchable = new Optional,
        public array|null|Optional $settings = new Optional,
        public array|null|Optional $validation_rules = new Optional,
        public array|null|Optional $visibility_rules = new Optional,
        public string|null|Optional $default_value = new Optional,
        public string|null|Optional $plugin_name = new Optional,
        public string|null|Optional $document_layout_name = new Optional,
        public bool|Optional $is_active = new Optional,
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
