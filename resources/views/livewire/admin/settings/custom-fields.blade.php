<?php

use App\Actions\CustomFields\DeleteCustomField;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Custom Fields')] class extends Component {
    public string $moduleFilter = '';

    public function delete(int $fieldId): void
    {
        $field = CustomField::findOrFail($fieldId);
        (new DeleteCustomField)($field);
    }

    public function with(): array
    {
        return [
            'fields' => CustomField::query()
                ->with('group')
                ->when($this->moduleFilter, fn ($q, $m) => $q->forModule($m))
                ->orderBy('module_type')
                ->orderBy('sort_order')
                ->get(),
            'availableModules' => CustomField::query()
                ->distinct()
                ->pluck('module_type')
                ->sort()
                ->values(),
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="data" title="Custom Fields" description="Manage custom field definitions." :wide="true">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('admin.settings.custom-fields.create') }}" wire:navigate>Add Custom Field</flux:button>
        </x-slot:actions>

        {{-- Module Filter --}}
        <div class="mb-4 max-w-xs">
            <flux:select wire:model.live="moduleFilter">
                <flux:select.option value="">All Modules</flux:select.option>
                @foreach ($availableModules as $module)
                    <flux:select.option value="{{ $module }}">{{ $module }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($fields->isEmpty())
            <div class="s-card p-8 text-center text-zinc-500 dark:text-zinc-400">
                <p>No custom fields found.</p>
            </div>
        @else
            <div class="s-table-wrap">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Display Name</th>
                            <th>Type</th>
                            <th>Module</th>
                            <th>Group</th>
                            <th>Required</th>
                            <th>Active</th>
                            <th class="w-20"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fields as $field)
                            <tr wire:key="field-{{ $field->id }}">
                                <td class="font-medium font-mono text-xs">{{ $field->name }}</td>
                                <td class="text-sm">{{ $field->display_name ?? '-' }}</td>
                                <td>
                                    @php
                                        $badgeClass = match(true) {
                                            in_array($field->field_type, [CustomFieldType::String, CustomFieldType::Text, CustomFieldType::RichText]) => 's-badge-blue',
                                            in_array($field->field_type, [CustomFieldType::Number, CustomFieldType::Currency, CustomFieldType::Percentage]) => 's-badge-purple',
                                            in_array($field->field_type, [CustomFieldType::Date, CustomFieldType::DateTime, CustomFieldType::Time]) => 's-badge-amber',
                                            in_array($field->field_type, [CustomFieldType::ListOfValues, CustomFieldType::MultiListOfValues]) => 's-badge-green',
                                            $field->field_type === CustomFieldType::Boolean => 's-badge-cyan',
                                            default => '',
                                        };
                                    @endphp
                                    <span class="s-badge {{ $badgeClass }}">{{ $field->field_type->label() }}</span>
                                </td>
                                <td class="text-sm">{{ $field->module_type }}</td>
                                <td class="text-sm text-zinc-500">{{ $field->group?->name ?? '-' }}</td>
                                <td>
                                    @if ($field->is_required)
                                        <flux:icon.check class="w-4 h-4 text-green-600" />
                                    @endif
                                </td>
                                <td>
                                    @if ($field->is_active)
                                        <span class="s-badge bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                    @else
                                        <span class="s-badge bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex gap-2">
                                        <a href="{{ route('admin.settings.custom-fields.edit', $field) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                            <flux:icon.pencil-square class="w-4 h-4" />
                                        </a>
                                        <button
                                            wire:click="delete({{ $field->id }})"
                                            wire:confirm="Are you sure you want to delete the '{{ $field->display_name ?? $field->name }}' custom field? Any stored values for this field will be orphaned."
                                            class="s-btn s-btn-ghost s-btn-xs text-red-600"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.layout>
</section>
