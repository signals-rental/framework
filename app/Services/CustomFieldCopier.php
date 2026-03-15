<?php

namespace App\Services;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\ValueObjects\CopyResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CustomFieldCopier
{
    public function __construct(
        private readonly CustomFieldValidator $validator,
    ) {}

    /**
     * Copy custom field values from source to target where fields match by name + field_type.
     *
     * Both source and target models must use the HasCustomFields trait.
     */
    public function copy(
        Model $source,
        string $sourceModuleType,
        Model $target,
        string $targetModuleType,
    ): CopyResult {
        $sourceValues = CustomFieldValue::query()
            ->with('customField')
            ->where('entity_type', $source->getMorphClass())
            ->where('entity_id', $source->getKey())
            ->get();

        if ($sourceValues->isEmpty()) {
            return new CopyResult(copied: 0, skipped: 0);
        }

        $targetFields = CustomField::query()
            ->forModule($targetModuleType)
            ->active()
            ->get()
            ->keyBy('name');

        $copied = [];
        $skipped = [];

        foreach ($sourceValues as $sourceValue) {
            $sourceField = $sourceValue->customField;

            if (! $sourceField) {
                continue;
            }

            $targetField = $targetFields->get($sourceField->name);

            if (! $targetField || $targetField->field_type !== $sourceField->field_type) {
                $skipped[] = $sourceField->name;

                continue;
            }

            $fieldType = $sourceField->getAttribute('field_type');
            assert($fieldType instanceof CustomFieldType);
            $column = $fieldType->valueColumn();
            $rawValue = $sourceValue->{$column};

            if ($rawValue === null) {
                $skipped[] = $sourceField->name;

                continue;
            }

            try {
                $this->validator->validate($targetModuleType, [$sourceField->name => $rawValue]);
            } catch (ValidationException) {
                $skipped[] = $sourceField->name;

                continue;
            }

            CustomFieldValue::query()->updateOrCreate(
                [
                    'custom_field_id' => $targetField->id,
                    'entity_type' => $target->getMorphClass(),
                    'entity_id' => $target->getKey(),
                ],
                [$column => $rawValue],
            );

            $copied[] = $sourceField->name;
        }

        return new CopyResult(
            copied: count($copied),
            skipped: count($skipped),
            fieldsCopied: $copied,
            fieldsSkipped: $skipped,
        );
    }
}
