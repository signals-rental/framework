<?php

namespace App\Data\CustomFields;

use App\Models\CustomField;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class CustomFieldData extends Data
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @param  array<string, mixed>|null  $validation_rules
     * @param  array<string, mixed>|null  $visibility_rules
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $display_name,
        public ?string $description,
        public string $module_type,
        public int $field_type,
        public ?int $custom_field_group_id,
        public ?int $list_name_id,
        public int $sort_order,
        public bool $is_required,
        public bool $is_searchable,
        public ?array $settings,
        public ?array $validation_rules,
        public ?array $visibility_rules,
        public ?string $default_value,
        public ?string $plugin_name,
        public ?string $document_layout_name,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(CustomField $field): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $field->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $field->updated_at;

        return new self(
            id: $field->id,
            name: $field->name,
            display_name: $field->display_name,
            description: $field->description,
            module_type: $field->module_type,
            field_type: (int) $field->getRawOriginal('field_type'),
            custom_field_group_id: $field->custom_field_group_id,
            list_name_id: $field->list_name_id,
            sort_order: $field->sort_order,
            is_required: $field->is_required,
            is_searchable: $field->is_searchable,
            settings: $field->getAttribute('settings'),
            validation_rules: $field->getAttribute('validation_rules'),
            visibility_rules: $field->getAttribute('visibility_rules'),
            default_value: $field->default_value,
            plugin_name: $field->plugin_name,
            document_layout_name: $field->document_layout_name,
            is_active: $field->is_active,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
