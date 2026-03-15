<?php

namespace App\Services;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
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

        // Second pass: build the result array
        $result = [];

        foreach ($values as $cfv) {
            $field = $cfv->customField;
            if (! $field) {
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
    public function fromArray(Model $entity, array $fields): void
    {
        $moduleType = $entity->customFieldModuleType(); // @phpstan-ignore method.notFound

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

        foreach ($entities as $entity) {
            $entity->setRelation('preloadedCustomFieldValues', $grouped->get($entity->getKey(), new \Illuminate\Database\Eloquent\Collection));
        }
    }
}
