<?php

use App\Actions\CustomFields\CreateCustomField;
use App\Actions\CustomFields\UpdateCustomField;
use App\Data\CustomFields\CreateCustomFieldData;
use App\Data\CustomFields\UpdateCustomFieldData;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Models\ListName;
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
        }
    }

    public function updatedFieldType(): void
    {
        if (! in_array($this->fieldType, [CustomFieldType::ListOfValues->value, CustomFieldType::MultiListOfValues->value])) {
            $this->listNameId = null;
        }
    }

    public function save(): void
    {
        $listNameId = in_array($this->fieldType, [CustomFieldType::ListOfValues->value, CustomFieldType::MultiListOfValues->value])
            ? $this->listNameId
            : null;

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
                'default_value' => $this->defaultValue ?: null,
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
                'default_value' => $this->defaultValue ?: null,
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
            'moduleTypes' => ['Member', 'Opportunity', 'Product', 'Invoice', 'Store'],
            'showListNameField' => in_array($this->fieldType, [CustomFieldType::ListOfValues->value, CustomFieldType::MultiListOfValues->value]),
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
                        @foreach ($moduleTypes as $module)
                            <flux:select.option value="{{ $module }}">{{ $module }}</flux:select.option>
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

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Custom Field' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.custom-fields') }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
