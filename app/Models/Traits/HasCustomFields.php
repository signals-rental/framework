<?php

namespace App\Models\Traits;

use App\Models\CustomFieldValue;
use App\Services\CustomFieldSerializer;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasCustomFields
{
    /**
     * @return MorphMany<CustomFieldValue, $this>
     */
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'entity');
    }

    /**
     * Get the module type string for custom field resolution.
     * Override in the model if the class name doesn't match the module type.
     */
    public function customFieldModuleType(): string
    {
        return class_basename($this);
    }

    /**
     * Get all custom field values as a flat key-value array.
     *
     * @return array<string, mixed>
     */
    public function getCustomFieldsAttribute(): array
    {
        return app(CustomFieldSerializer::class)->toArray($this);
    }

    /**
     * Sync custom field values from a flat key-value array.
     *
     * @param  array<string, mixed>  $fields
     */
    public function syncCustomFields(array $fields): void
    {
        app(CustomFieldSerializer::class)->fromArray($this, $fields);
    }

    /**
     * Batch-load custom field values for a collection of entities to prevent N+1.
     *
     * @param  Collection<int, static>  $entities
     */
    public static function eagerLoadCustomFields(Collection $entities): void
    {
        if ($entities->isEmpty()) {
            return;
        }

        $moduleType = $entities->first()->customFieldModuleType();
        app(CustomFieldSerializer::class)->eagerLoad($entities, $moduleType);
    }
}
