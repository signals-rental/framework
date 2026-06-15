<?php

use App\Actions\CustomFields\CreateCustomField;
use App\Actions\CustomFields\UpdateCustomField;
use App\Data\CustomFields\CreateCustomFieldData;
use App\Data\CustomFields\UpdateCustomFieldData;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Models\ListName;
use App\Services\CustomFieldModuleRegistry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Custom Field')] class extends Component {
    public ?int $fieldId = null;
    public string $name = '';
    public string $displayName = '';
    public string $description = '';
    public string $moduleType = '';
    public ?int $fieldType = null;
    public ?int $customFieldGroupId = null;
    public ?int $listNameId = null;
    public int $sortOrder = 0;
    public bool $isRequired = false;
    public bool $isSearchable = false;
    public string $defaultValue = '';
    public bool $isActive = true;

    /**
     * Structured validation-rule inputs. Only the keys relevant to the selected
     * field type are persisted to the validation_rules JSONB column, in the
     * shape CustomFieldValidator consumes (min_length/max_length/pattern/min/max).
     *
     * @var array<string, string>
     */
    public array $validationRules = [
        'min_length' => '',
        'max_length' => '',
        'pattern' => '',
        'min' => '',
        'max' => '',
    ];

    /**
     * Visibility-rule builder rows. Each row is {field, operator, value} and is
     * persisted to the visibility_rules JSONB column in the exact shape
     * VisibilityRuleEvaluator consumes.
     *
     * @var array<int, array{field: string, operator: string, value: string}>
     */
    public array $visibilityRules = [];

    public function mount(?CustomField $customField = null): void
    {
        if ($customField?->exists) {
            $this->fieldId = $customField->id;
            $this->name = $customField->name;
            $this->displayName = $customField->display_name ?? '';
            $this->description = $customField->description ?? '';
            $this->moduleType = $customField->module_type;
            $this->fieldType = $customField->field_type->value;
            $this->customFieldGroupId = $customField->custom_field_group_id;
            $this->listNameId = $customField->list_name_id;
            $this->sortOrder = $customField->sort_order ?? 0;
            $this->isRequired = $customField->is_required;
            $this->isSearchable = $customField->is_searchable;
            $this->defaultValue = $customField->default_value ?? '';
            $this->isActive = $customField->is_active;

            $this->loadValidationRules($customField->validation_rules ?? []);
            $this->loadVisibilityRules($customField->visibility_rules ?? []);
        }
    }

    /**
     * Populate the structured validation-rule inputs from stored JSONB.
     *
     * @param  array<string, mixed>  $rules
     */
    private function loadValidationRules(array $rules): void
    {
        foreach (array_keys($this->validationRules) as $key) {
            $this->validationRules[$key] = isset($rules[$key]) ? (string) $rules[$key] : '';
        }
    }

    /**
     * Populate the visibility-rule builder rows from stored JSONB.
     *
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function loadVisibilityRules(array $rules): void
    {
        $this->visibilityRules = [];

        foreach ($rules as $rule) {
            $value = $rule['value'] ?? '';

            $this->visibilityRules[] = [
                'field' => (string) ($rule['field'] ?? ''),
                'operator' => (string) ($rule['operator'] ?? 'eq'),
                'value' => is_array($value) ? implode(', ', $value) : (string) $value,
            ];
        }
    }

    public function updatedFieldType(): void
    {
        if (! in_array($this->fieldType, [CustomFieldType::ListOfValues->value, CustomFieldType::MultiListOfValues->value])) {
            $this->listNameId = null;
        }
    }

    public function addVisibilityRule(): void
    {
        $this->visibilityRules[] = ['field' => '', 'operator' => 'eq', 'value' => ''];
    }

    public function removeVisibilityRule(int $index): void
    {
        unset($this->visibilityRules[$index]);
        $this->visibilityRules = array_values($this->visibilityRules);
    }

    /**
     * Build the validation_rules JSONB payload for the selected field type.
     *
     * Only keys appropriate to the field type and with a non-empty value are
     * included, matching the keys CustomFieldValidator reads. Numeric inputs are
     * cast to int/float; pattern stays a string. Returns null when no rules set.
     *
     * @return array<string, mixed>|null
     */
    private function buildValidationRules(): ?array
    {
        $allowed = $this->allowedValidationKeys();
        $rules = [];

        foreach ($allowed as $key) {
            $raw = trim($this->validationRules[$key] ?? '');

            if ($raw === '') {
                continue;
            }

            $rules[$key] = match ($key) {
                'min_length', 'max_length' => (int) $raw,
                'min', 'max' => is_numeric($raw) && ! str_contains($raw, '.') ? (int) $raw : (float) $raw,
                default => $raw,
            };
        }

        return $rules === [] ? null : $rules;
    }

    /**
     * The validation-rule keys applicable to the currently selected field type.
     *
     * @return list<string>
     */
    private function allowedValidationKeys(): array
    {
        return match ($this->fieldType) {
            CustomFieldType::String->value => ['min_length', 'max_length', 'pattern'],
            CustomFieldType::Text->value, CustomFieldType::RichText->value => ['max_length'],
            CustomFieldType::Number->value, CustomFieldType::Currency->value, CustomFieldType::Percentage->value => ['min', 'max'],
            CustomFieldType::Colour->value => ['pattern'],
            default => [],
        };
    }

    /**
     * Build the visibility_rules JSONB payload in the shape VisibilityRuleEvaluator
     * consumes: a list of {field, operator, value} objects. Rows without a field
     * are dropped. present/blank operators omit the value. in/not_in values are
     * split into an array on commas. Returns null when no rules set.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function buildVisibilityRules(): ?array
    {
        $rules = [];

        foreach ($this->visibilityRules as $row) {
            $field = trim($row['field'] ?? '');

            if ($field === '') {
                continue;
            }

            $operator = $row['operator'] ?? 'eq';
            $rule = ['field' => $field, 'operator' => $operator];

            if (! in_array($operator, ['present', 'blank'], true)) {
                $rawValue = $row['value'] ?? '';

                if (in_array($operator, ['in', 'not_in'], true)) {
                    $rule['value'] = array_values(array_filter(
                        array_map('trim', explode(',', $rawValue)),
                        fn (string $v): bool => $v !== '',
                    ));
                } else {
                    $rule['value'] = $rawValue;
                }
            }

            $rules[] = $rule;
        }

        return $rules === [] ? null : $rules;
    }

    public function save(): void
    {
        $listNameId = in_array($this->fieldType, [CustomFieldType::ListOfValues->value, CustomFieldType::MultiListOfValues->value])
            ? $this->listNameId
            : null;

        $validationRules = $this->buildValidationRules();
        $visibilityRules = $this->buildVisibilityRules();

        if ($this->fieldId) {
            $field = CustomField::findOrFail($this->fieldId);
            (new UpdateCustomField)($field, UpdateCustomFieldData::validateAndCreate([
                'name' => $this->name,
                'display_name' => $this->displayName ?: null,
                'description' => $this->description ?: null,
                'custom_field_group_id' => $this->customFieldGroupId,
                'list_name_id' => $listNameId,
                'sort_order' => $this->sortOrder,
                'is_required' => $this->isRequired,
                'is_searchable' => $this->isSearchable,
                'validation_rules' => $validationRules,
                'visibility_rules' => $visibilityRules,
                'default_value' => $this->defaultValue !== '' ? $this->defaultValue : null,
                'is_active' => $this->isActive,
            ]));
        } else {
            (new CreateCustomField)(CreateCustomFieldData::validateAndCreate([
                'name' => $this->name,
                'module_type' => $this->moduleType,
                'field_type' => $this->fieldType,
                'display_name' => $this->displayName ?: null,
                'description' => $this->description ?: null,
                'custom_field_group_id' => $this->customFieldGroupId,
                'list_name_id' => $listNameId,
                'sort_order' => $this->sortOrder,
                'is_required' => $this->isRequired,
                'is_searchable' => $this->isSearchable,
                'validation_rules' => $validationRules,
                'visibility_rules' => $visibilityRules,
                'default_value' => $this->defaultValue !== '' ? $this->defaultValue : null,
                'is_active' => $this->isActive,
            ]));
        }

        $this->redirect(route('admin.settings.custom-fields'), navigate: true);
    }

    public function with(): array
    {
        return [
            'isEditing' => $this->fieldId !== null,
            'groups' => CustomFieldGroup::query()->orderBy('name')->get(),
            'listNames' => ListName::query()->orderBy('name')->get(),
            'fieldTypes' => CustomFieldType::cases(),
            'moduleTypes' => app(CustomFieldModuleRegistry::class)->modules(),
            'showListNameField' => in_array($this->fieldType, [CustomFieldType::ListOfValues->value, CustomFieldType::MultiListOfValues->value]),
            'validationKeys' => $this->allowedValidationKeys(),
            'visibilityOperators' => [
                'eq' => 'Equals',
                'not_eq' => 'Does not equal',
                'in' => 'In list',
                'not_in' => 'Not in list',
                'present' => 'Is present',
                'blank' => 'Is blank',
                'gt' => 'Greater than',
                'gte' => 'Greater than or equal',
                'lt' => 'Less than',
                'lte' => 'Less than or equal',
                'contains' => 'Contains',
            ],
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="data" :title="$isEditing ? 'Edit Custom Field' : 'Create Custom Field'" :description="$isEditing ? 'Update custom field definition.' : 'Define a new custom field.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Custom Fields', 'href' => route('admin.settings.custom-fields')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.custom-fields') }}" wire:navigate>
                Back to Custom Fields
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-8">
            {{-- Field Details --}}
            <x-signals.form-section title="Field Details">
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="name" label="Name" placeholder="po_reference" description="Lowercase identifier with underscores." required />
                    <flux:input wire:model="displayName" label="Display Name" placeholder="PO Reference" />
                </div>

                <div class="mt-4">
                    <flux:textarea wire:model="description" label="Description" rows="2" />
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <flux:select wire:model="moduleType" label="Module" required>
                        <flux:select.option value="">Select module...</flux:select.option>
                        @foreach ($moduleTypes as $moduleValue => $moduleLabel)
                            <flux:select.option value="{{ $moduleValue }}">{{ $moduleLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="fieldType" label="Field Type" required>
                        <flux:select.option value="">Select type...</flux:select.option>
                        @foreach ($fieldTypes as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="mt-4">
                    <flux:select wire:model="customFieldGroupId" label="Group">
                        <flux:select.option value="">No group</flux:select.option>
                        @foreach ($groups as $group)
                            <flux:select.option value="{{ $group->id }}">{{ $group->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </x-signals.form-section>

            {{-- Configuration --}}
            <x-signals.form-section title="Configuration">
                @if ($showListNameField)
                    <div class="mb-4">
                        <flux:select wire:model="listNameId" label="List Name">
                            <flux:select.option value="">Select list...</flux:select.option>
                            @foreach ($listNames as $listName)
                                <flux:select.option value="{{ $listName->id }}">{{ $listName->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="sortOrder" label="Sort Order" type="number" />
                    <flux:input wire:model="defaultValue" label="Default Value" />
                </div>

                <div class="mt-4 space-y-3">
                    <flux:checkbox wire:model="isRequired" label="Required" />
                    <flux:checkbox wire:model="isSearchable" label="Searchable" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            {{-- Validation Rules --}}
            <x-signals.form-section title="Validation Rules">
                @if (count($validationKeys) === 0)
                    <flux:text variant="subtle">
                        @if ($fieldType === null)
                            Select a field type to configure validation rules.
                        @else
                            This field type has no configurable validation rules.
                        @endif
                    </flux:text>
                @else
                    <div class="grid grid-cols-2 gap-4">
                        @if (in_array('min_length', $validationKeys, true))
                            <flux:input wire:model="validationRules.min_length" label="Minimum Length" type="number" min="0" placeholder="No minimum" />
                        @endif
                        @if (in_array('max_length', $validationKeys, true))
                            <flux:input wire:model="validationRules.max_length" label="Maximum Length" type="number" min="0" placeholder="No maximum" />
                        @endif
                        @if (in_array('min', $validationKeys, true))
                            <flux:input wire:model="validationRules.min" label="Minimum Value" type="number" placeholder="No minimum" />
                        @endif
                        @if (in_array('max', $validationKeys, true))
                            <flux:input wire:model="validationRules.max" label="Maximum Value" type="number" placeholder="No maximum" />
                        @endif
                        @if (in_array('pattern', $validationKeys, true))
                            <flux:input wire:model="validationRules.pattern" label="Pattern (regex)" placeholder="/^[A-Z]{3}-\d+$/" description="A regular expression the value must match." class="col-span-2" />
                        @endif
                    </div>
                @endif
            </x-signals.form-section>

            {{-- Visibility Rules --}}
            <x-signals.form-section title="Visibility Rules">
                <x-slot:headerActions>
                    <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addVisibilityRule">
                        Add rule
                    </flux:button>
                </x-slot:headerActions>

                <flux:text variant="subtle" class="mb-4">
                    Show this field only when all rules match other fields on the same record. Leave empty to always show.
                </flux:text>

                @forelse ($visibilityRules as $index => $rule)
                    <div class="grid grid-cols-12 gap-3 items-end mb-3" wire:key="visibility-rule-{{ $index }}">
                        <div class="col-span-4">
                            <flux:input wire:model="visibilityRules.{{ $index }}.field" :label="$index === 0 ? 'Field' : null" placeholder="membership_type" />
                        </div>
                        <div class="col-span-3">
                            <flux:select wire:model.live="visibilityRules.{{ $index }}.operator" :label="$index === 0 ? 'Operator' : null">
                                @foreach ($visibilityOperators as $opValue => $opLabel)
                                    <flux:select.option value="{{ $opValue }}">{{ $opLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="col-span-4">
                            @unless (in_array($rule['operator'] ?? 'eq', ['present', 'blank'], true))
                                <flux:input
                                    wire:model="visibilityRules.{{ $index }}.value"
                                    :label="$index === 0 ? 'Value' : null"
                                    :placeholder="in_array($rule['operator'] ?? 'eq', ['in', 'not_in'], true) ? 'Comma-separated list' : 'Value'"
                                />
                            @endunless
                        </div>
                        <div class="col-span-1">
                            <flux:button type="button" size="sm" variant="subtle" icon="trash" wire:click="removeVisibilityRule({{ $index }})" aria-label="Remove rule" />
                        </div>
                    </div>
                @empty
                    <flux:text variant="subtle">No visibility rules — this field is always shown.</flux:text>
                @endforelse
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Custom Field' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.custom-fields') }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
