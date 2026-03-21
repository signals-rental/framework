<?php

namespace App\Services;

use App\Contracts\HasSchema;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\ValueObjects\FieldDefinition;
use Illuminate\Support\Facades\Cache;

/**
 * Singleton registry that resolves the full field schema for any model.
 *
 * Merges core fields (declared via HasSchema) with custom fields (from the
 * custom_fields table). Uses two-tier caching: L1 in-memory for request
 * lifecycle, L2 Redis/tagged for persistence across requests.
 */
class SchemaRegistry
{
    private const CACHE_TAG = 'schema-registry';

    private const CACHE_TTL = 3600; // 1 hour

    /** @var array<string, array<string, FieldDefinition>> */
    private array $memory = [];

    /**
     * Resolve the full schema for a model (core fields + custom fields).
     *
     * @return array<string, FieldDefinition>
     */
    public function resolve(string $modelClass): array
    {
        // L1: in-memory cache
        if (isset($this->memory[$modelClass])) {
            return $this->memory[$modelClass];
        }

        // L2: persistent cache
        $cacheKey = $this->cacheKey($modelClass);
        $cached = $this->persistentGet($cacheKey);

        if ($cached !== null) {
            $this->memory[$modelClass] = $cached;

            return $cached;
        }

        $fields = $this->buildSchema($modelClass);

        $this->memory[$modelClass] = $fields;
        $this->persistentPut($cacheKey, $fields);

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
        unset($this->memory[$modelClass]);
        $this->persistentForget($this->cacheKey($modelClass));
    }

    /**
     * Clear all cached schemas.
     */
    public function invalidateAll(): void
    {
        $this->memory = [];
        $this->persistentFlush();
    }

    /**
     * Build the full schema for a model from core fields + custom fields.
     *
     * @return array<string, FieldDefinition>
     */
    private function buildSchema(string $modelClass): array
    {
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

        return $fields;
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

    private function cacheKey(string $modelClass): string
    {
        return 'schema:'.str_replace('\\', '.', $modelClass);
    }

    /**
     * @return array<string, FieldDefinition>|null
     */
    private function persistentGet(string $key): ?array
    {
        try {
            if (Cache::supportsTags()) {
                return Cache::tags([self::CACHE_TAG])->get($key);
            }

            return Cache::get($key);
        } catch (\Exception $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  array<string, FieldDefinition>  $value
     */
    private function persistentPut(string $key, array $value): void
    {
        try {
            if (Cache::supportsTags()) {
                Cache::tags([self::CACHE_TAG])->put($key, $value, self::CACHE_TTL);

                return;
            }

            Cache::put($key, $value, self::CACHE_TTL);
        } catch (\Exception $e) {
            report($e);
        }
    }

    private function persistentForget(string $key): void
    {
        try {
            if (Cache::supportsTags()) {
                Cache::tags([self::CACHE_TAG])->forget($key);

                return;
            }

            Cache::forget($key);
        } catch (\Exception $e) {
            report($e);
        }
    }

    private function persistentFlush(): void
    {
        try {
            if (Cache::supportsTags()) {
                Cache::tags([self::CACHE_TAG])->flush();

                return;
            }

            // Without tags, forget all known keys by clearing in-memory references.
            // Individual keys will expire via TTL.
            foreach (array_keys($this->memory) as $modelClass) {
                Cache::forget($this->cacheKey($modelClass));
            }
        } catch (\Exception $e) {
            report($e);
        }
    }
}
