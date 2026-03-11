<?php

namespace App\Models\Traits;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        $values = $this->customFieldValues()->with('customField')->get();
        $result = [];

        foreach ($values as $cfv) {
            $field = $cfv->customField;
            if (! $field) {
                continue;
            }

            /** @var CustomFieldType $fieldType */
            $fieldType = $field->field_type;
            $column = $fieldType->valueColumn();
            $result[$field->name] = $cfv->{$column};
        }

        return $result;
    }

    /**
     * Sync custom field values from a flat key-value array.
     *
     * @param  array<string, mixed>  $fields
     */
    public function syncCustomFields(array $fields): void
    {
        $moduleType = $this->customFieldModuleType();

        $definitions = CustomField::query()
            ->forModule($moduleType)
            ->active()
            ->get()
            ->keyBy('name');

        foreach ($fields as $name => $value) {
            $field = $definitions->get($name);

            if (! $field) {
                continue;
            }

            /** @var CustomFieldType $fieldType */
            $fieldType = $field->field_type;
            $column = $fieldType->valueColumn();

            CustomFieldValue::query()->updateOrCreate(
                [
                    'custom_field_id' => $field->id,
                    'entity_type' => $this->getMorphClass(),
                    'entity_id' => $this->getKey(),
                ],
                [$column => $value],
            );
        }
    }
}
