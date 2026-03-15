<?php

namespace App\Services;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\ListValue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomFieldValidator
{
    /**
     * Build validation rules for custom field input on a given module type.
     *
     * @param  array<string, mixed>  $customFieldInput
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $moduleType, array $customFieldInput): array
    {
        $definitions = CustomField::query()
            ->forModule($moduleType)
            ->active()
            ->get()
            ->keyBy('name');

        $rules = [];

        foreach ($customFieldInput as $fieldName => $value) {
            $field = $definitions->get($fieldName);

            if (! $field) {
                continue;
            }

            $fieldRules = $this->buildFieldRules($field);

            if ($fieldRules !== null) {
                $rules[$fieldName] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Validate custom field input, returning validated data.
     *
     * @param  array<string, mixed>  $customFieldInput
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(string $moduleType, array $customFieldInput): array
    {
        $rules = $this->rules($moduleType, $customFieldInput);

        // Filter input to only known fields that have rules
        $filteredInput = array_intersect_key($customFieldInput, $rules);

        $validator = Validator::make($filteredInput, $rules);

        return $validator->validate();
    }

    /**
     * Build validation rules for a single custom field.
     *
     * @return array<int, mixed>|null
     */
    private function buildFieldRules(CustomField $field): ?array
    {
        /** @var array<string, mixed> $validationRules */
        $validationRules = $field->validation_rules ?? [];

        /** @var CustomFieldType $fieldType */
        $fieldType = $field->field_type;

        // Skip read-only / unsupported field types
        if ($fieldType === CustomFieldType::AutoNumber || $fieldType === CustomFieldType::FileImage) {
            return null;
        }

        $rules = [];

        // Required or nullable
        $rules[] = $field->is_required ? 'required' : 'nullable';

        // Type-specific rules
        match ($fieldType) {
            CustomFieldType::String => $this->applyStringRules($rules, $validationRules),
            CustomFieldType::Text, CustomFieldType::RichText => $this->applyTextRules($rules, $validationRules),
            CustomFieldType::Number, CustomFieldType::Currency, CustomFieldType::Percentage => $this->applyNumericRules($rules, $validationRules),
            CustomFieldType::Boolean => $rules[] = 'boolean',
            CustomFieldType::DateTime => $rules[] = 'date',
            CustomFieldType::Date => $rules[] = 'date_format:Y-m-d',
            CustomFieldType::Time => $rules[] = 'date_format:H:i:s',
            CustomFieldType::Email => $rules[] = 'email',
            CustomFieldType::Website => $rules[] = 'url',
            CustomFieldType::Telephone => $rules[] = 'string',
            CustomFieldType::Colour => $this->applyColourRules($rules, $validationRules),
            CustomFieldType::JsonKeyValue => $rules[] = 'array',
            CustomFieldType::ListOfValues => $this->applyListOfValuesRules($rules, $field),
            CustomFieldType::MultiListOfValues => $this->applyMultiListOfValuesRules($rules, $field),
        };

        return $rules;
    }

    /**
     * @param  array<int, mixed>  $rules
     * @param  array<string, mixed>  $validationRules
     */
    private function applyStringRules(array &$rules, array $validationRules): void
    {
        $rules[] = 'string';

        if (isset($validationRules['min_length'])) {
            $rules[] = 'min:'.$validationRules['min_length'];
        }

        if (isset($validationRules['max_length'])) {
            $rules[] = 'max:'.$validationRules['max_length'];
        }

        if (isset($validationRules['pattern'])) {
            $rules[] = 'regex:'.$validationRules['pattern'];
        }
    }

    /**
     * @param  array<int, mixed>  $rules
     * @param  array<string, mixed>  $validationRules
     */
    private function applyTextRules(array &$rules, array $validationRules): void
    {
        $rules[] = 'string';

        if (isset($validationRules['max_length'])) {
            $rules[] = 'max:'.$validationRules['max_length'];
        }
    }

    /**
     * @param  array<int, mixed>  $rules
     * @param  array<string, mixed>  $validationRules
     */
    private function applyNumericRules(array &$rules, array $validationRules): void
    {
        $rules[] = 'numeric';

        if (isset($validationRules['min'])) {
            $rules[] = 'min:'.$validationRules['min'];
        }

        if (isset($validationRules['max'])) {
            $rules[] = 'max:'.$validationRules['max'];
        }
    }

    /**
     * @param  array<int, mixed>  $rules
     * @param  array<string, mixed>  $validationRules
     */
    private function applyColourRules(array &$rules, array $validationRules): void
    {
        $rules[] = 'string';

        if (isset($validationRules['pattern'])) {
            $rules[] = 'regex:'.$validationRules['pattern'];
        }
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function applyListOfValuesRules(array &$rules, CustomField $field): void
    {
        if (! $field->list_name_id) {
            return;
        }

        $validValues = ListValue::query()
            ->where('list_name_id', $field->list_name_id)
            ->active()
            ->get();

        $validIds = $validValues->pluck('id')->toArray();
        $validNames = $validValues->pluck('name')->toArray();
        $allowed = array_merge($validIds, $validNames);

        $rules[] = 'in:'.implode(',', $allowed);
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function applyMultiListOfValuesRules(array &$rules, CustomField $field): void
    {
        $rules[] = 'array';

        if (! $field->list_name_id) {
            return;
        }

        $validValues = ListValue::query()
            ->where('list_name_id', $field->list_name_id)
            ->active()
            ->get();

        $validIds = $validValues->pluck('id')->toArray();
        $validNames = $validValues->pluck('name')->toArray();
        $allowed = array_merge($validIds, $validNames);

        $rules[] = function (string $attribute, mixed $value, \Closure $fail) use ($allowed): void {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as $item) {
                if (! in_array($item, $allowed, false)) {
                    $fail("The selected {$attribute} contains an invalid value: {$item}.");
                }
            }
        };
    }
}
