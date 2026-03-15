<?php

namespace App\Services;

use App\Contracts\HasSchema;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\ValueObjects\FieldDefinition;

/**
 * Singleton registry that resolves the full field schema for any model.
 *
 * Merges core fields (declared via HasSchema) with custom fields (from the
 * custom_fields table), caches the result in memory for the request lifecycle.
 */
class SchemaRegistry
{
    /** @var array<string, array<string, FieldDefinition>> */
    private array $cache = [];

    /**
     * Resolve the full schema for a model (core fields + custom fields).
     *
     * @return array<string, FieldDefinition>
     */
    public function resolve(string $modelClass): array
    {
        if (isset($this->cache[$modelClass])) {
            return $this->cache[$modelClass];
        }

        $fields = [];

        // Resolve core fields from HasSchema implementation
        if (is_subclass_of($modelClass, HasSchema::class)) {
            $builder = new SchemaBuilder;
            $modelClass::defineSchema($builder);
            $fields = $builder->build();
        }

        // Merge custom fields from the database
        $moduleName = class_basename($modelClass);
        $customFields = CustomField::query()
            ->forModule($moduleName)
            ->active()
            ->get();

        foreach ($customFields as $customField) {
            /** @var CustomFieldType $customFieldType */
            $customFieldType = $customField->field_type;
            $fieldType = $this->mapCustomFieldType($customFieldType);

            $fields[$customField->name] = new FieldDefinition(
                name: $customField->name,
                type: $fieldType,
                source: 'custom',
                model: $modelClass,
                plugin: $customField->plugin_name,
                filterable: true,
                sortable: true,
                searchable: (bool) $customField->is_searchable,
                label: $customField->display_name,
                description: $customField->description,
                rules: $customField->validation_rules ?? [],
                required: (bool) $customField->is_required,
            );
        }

        $this->cache[$modelClass] = $fields;

        return $fields;
    }

    /**
     * Alias for resolve().
     *
     * @return array<string, FieldDefinition>
     */
    public function for(string $modelClass): array
    {
        return $this->resolve($modelClass);
    }

    /**
     * Clear cached schema for a specific model.
     */
    public function invalidate(string $modelClass): void
    {
        unset($this->cache[$modelClass]);
    }

    /**
     * Clear all cached schemas.
     */
    public function invalidateAll(): void
    {
        $this->cache = [];
    }

    /**
     * Map a CustomFieldType enum to the schema type string.
     */
    private function mapCustomFieldType(CustomFieldType $fieldType): string
    {
        return match ($fieldType) {
            CustomFieldType::String, CustomFieldType::Email, CustomFieldType::Website,
            CustomFieldType::Telephone, CustomFieldType::AutoNumber, CustomFieldType::Colour => 'string',
            CustomFieldType::Text, CustomFieldType::RichText => 'text',
            CustomFieldType::Number, CustomFieldType::Percentage => 'decimal',
            CustomFieldType::Boolean => 'boolean',
            CustomFieldType::Date => 'date',
            CustomFieldType::DateTime => 'datetime',
            CustomFieldType::Time => 'string',
            CustomFieldType::Currency => 'currency',
            CustomFieldType::ListOfValues => 'enum',
            CustomFieldType::MultiListOfValues, CustomFieldType::FileImage,
            CustomFieldType::JsonKeyValue => 'json',
        };
    }
}
