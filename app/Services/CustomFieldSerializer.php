<?php

namespace App\Services;

use App\Enums\CustomFieldType;
use App\Models\CustomFieldValue;
use App\Models\ListValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Extracts EAV <-> JSON serialisation logic for custom fields into a testable service.
 *
 * All entity parameters must be Eloquent models that use the HasCustomFields trait.
 *
 * @method-note Entities are expected to implement customFieldValues() and customFieldModuleType()
 *              via the App\Models\Traits\HasCustomFields trait.
 */
class CustomFieldSerializer
{
    public function __construct(
        private readonly CustomFieldDefinitionResolver $definitions,
    ) {}

    /**
     * Convert EAV custom field values to a flat JSON-compatible array.
     * Handles ListOfValues (ID->name) and MultiListOfValues (IDs->names) resolution.
     *
     * @template TModel of Model
     *
     * @param  TModel  $entity  A model using HasCustomFields trait
     * @return array<string, mixed>
     */
    public function toArray(Model $entity): array
    {
        $moduleType = $entity->customFieldModuleType(); // @phpstan-ignore method.notFound

        // Start with all active fields for this module defaulting to null (CRMS compatibility)
        $definitions = $this->definitions->resolve($moduleType);

        /** @var array<string, null> $result */
        $result = $definitions->pluck('name')->mapWithKeys(fn (string $name): array => [$name => null])->all();

        /** @var Collection<int, CustomFieldValue> $values */
        $values = $entity->relationLoaded('preloadedCustomFieldValues')
            ? $entity->getRelation('preloadedCustomFieldValues')
            : $entity->customFieldValues()->with('customField')->get(); // @phpstan-ignore method.notFound

        // First pass: collect all list value IDs for batch loading
        $listValueIds = [];

        foreach ($values as $cfv) {
            $field = $cfv->customField;
            if (! $field) {
                continue;
            }

            /** @var CustomFieldType $fieldType */
            $fieldType = $field->field_type;

            if ($fieldType === CustomFieldType::ListOfValues) {
                $rawValue = $cfv->value_integer;
                if (is_int($rawValue)) {
                    $listValueIds[] = $rawValue;
                }
            } elseif ($fieldType === CustomFieldType::MultiListOfValues) {
                /** @var array<int, int>|null $rawValue */
                $rawValue = $cfv->value_json;
                if (is_array($rawValue)) {
                    $listValueIds = array_merge($listValueIds, $rawValue);
                }
            }
        }

        // Batch-load all list values in a single query
        $listValueNames = $listValueIds !== []
            ? ListValue::query()->whereIn('id', array_unique($listValueIds))->pluck('name', 'id')->all()
            : [];

        // Second pass: overlay stored values onto the result (only for active fields)
        foreach ($values as $cfv) {
            $field = $cfv->customField;
            if (! $field || ! array_key_exists($field->name, $result)) {
                continue;
            }

            /** @var CustomFieldType $fieldType */
            $fieldType = $field->field_type;
            $column = $fieldType->valueColumn();

            /** @var mixed $rawValue */
            $rawValue = $cfv->{$column};

            $result[$field->name] = match ($fieldType) {
                CustomFieldType::ListOfValues => is_int($rawValue)
                    ? ($listValueNames[$rawValue] ?? $rawValue)
                    : $rawValue,
                CustomFieldType::MultiListOfValues => is_array($rawValue)
                    ? array_map(fn (int $id): string => $listValueNames[$id] ?? (string) $id, $rawValue)
                    : $rawValue,
                default => $rawValue,
            };
        }

        return $result;
    }

    /**
     * Write flat JSON custom field data to EAV storage.
     * Handles ListOfValues (name->ID) and MultiListOfValues (names->IDs) resolution.
     *
     * @template TModel of Model
     *
     * @param  TModel  $entity  A model using HasCustomFields trait
     * @param  array<string, mixed>  $fields
     */
    public function fromArray(Model $entity, array $fields, bool $applyDefaults = false): void
    {
        $moduleType = $entity->customFieldModuleType(); // @phpstan-ignore method.notFound

        $definitions = $this->definitions->resolve($moduleType)->keyBy('name');

        // Apply default values for fields not provided in input (used on entity creation)
        if ($applyDefaults) {
            foreach ($definitions as $name => $definition) {
                if (! array_key_exists($name, $fields) && $definition->default_value !== null) {
                    /** @var CustomFieldType $fieldType */
                    $fieldType = $definition->field_type;
                    $fields[$name] = $this->coerceDefaultValue($definition->default_value, $fieldType);
                }
            }
        }

        foreach ($fields as $name => $value) {
            $field = $definitions->get($name);

            if (! $field) {
                continue;
            }

            /** @var CustomFieldType $fieldType */
            $fieldType = $field->field_type;
            $column = $fieldType->valueColumn();

            $resolvedValue = match ($fieldType) {
                CustomFieldType::ListOfValues => is_string($value)
                    ? ListValue::query()
                        ->where('list_name_id', $field->list_name_id)
                        ->where('name', $value)
                        ->value('id')
                    : $value,
                CustomFieldType::MultiListOfValues => is_array($value)
                    ? array_map(function (mixed $item) use ($field): mixed {
                        return is_string($item)
                            ? ListValue::query()
                                ->where('list_name_id', $field->list_name_id)
                                ->where('name', $item)
                                ->value('id')
                            : $item;
                    }, $value)
                    : $value,
                default => $value,
            };

            CustomFieldValue::query()->updateOrCreate(
                [
                    'custom_field_id' => $field->id,
                    'entity_type' => $entity->getMorphClass(),
                    'entity_id' => $entity->getKey(),
                ],
                [$column => $resolvedValue],
            );
        }
    }

    /**
     * Batch-load custom field values for a collection of entities to prevent N+1.
     * Stores loaded values on each entity via setRelation().
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $entities
     */
    public function eagerLoad(Collection $entities, string $moduleType): void
    {
        if ($entities->isEmpty()) {
            return;
        }

        $ids = $entities->pluck('id')->all();
        $morphClass = $entities->first()->getMorphClass();

        $allValues = CustomFieldValue::query()
            ->whereIn('entity_id', $ids)
            ->where('entity_type', $morphClass)
            ->with('customField')
            ->get();

        $grouped = $allValues->groupBy('entity_id');

        // Pre-warm the definitions cache so toArray() doesn't query per-entity
        $this->definitions->resolve($moduleType);

        foreach ($entities as $entity) {
            $entity->setRelation('preloadedCustomFieldValues', $grouped->get($entity->getKey(), new \Illuminate\Database\Eloquent\Collection));
        }
    }

    /**
     * Coerce a string default_value to the appropriate PHP type for the field.
     */
    private function coerceDefaultValue(string $value, CustomFieldType $fieldType): mixed
    {
        return match ($fieldType) {
            CustomFieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            CustomFieldType::Number, CustomFieldType::Currency, CustomFieldType::Percentage => is_numeric($value) ? (float) $value : $value,
            CustomFieldType::ListOfValues => is_numeric($value) ? (int) $value : $value,
            default => $value,
        };
    }
}
