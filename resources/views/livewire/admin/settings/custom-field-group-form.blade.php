<?php

use App\Actions\CustomFields\CreateCustomFieldGroup;
use App\Actions\CustomFields\UpdateCustomFieldGroup;
use App\Data\CustomFields\CreateCustomFieldGroupData;
use App\Data\CustomFields\UpdateCustomFieldGroupData;
use App\Models\CustomFieldGroup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Custom Field Group')] class extends Component {
    public ?int $groupId = null;
    public string $name = '';
    public string $description = '';
    public int $sortOrder = 0;

    public function mount(?CustomFieldGroup $customFieldGroup = null): void
    {
        if ($customFieldGroup?->exists) {
            $this->groupId = $customFieldGroup->id;
            $this->name = $customFieldGroup->name;
            $this->description = $customFieldGroup->description ?? '';
            $this->sortOrder = $customFieldGroup->sort_order ?? 0;
        }
    }

    public function with(): array
    {
        return [
            'isEditing' => $this->groupId !== null,
        ];
    }

    public function save(): void
    {
        if ($this->groupId) {
            $group = CustomFieldGroup::findOrFail($this->groupId);
            (new UpdateCustomFieldGroup)($group, UpdateCustomFieldGroupData::validateAndCreate([
                'name' => $this->name,
                'description' => $this->description ?: null,
                'sort_order' => $this->sortOrder,
            ]));
        } else {
            (new CreateCustomFieldGroup)(CreateCustomFieldGroupData::validateAndCreate([
                'name' => $this->name,
                'description' => $this->description ?: null,
                'sort_order' => $this->sortOrder,
            ]));
        }

        $this->redirect(route('admin.settings.custom-field-groups'), navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="data" :title="$isEditing ? 'Edit Custom Field Group' : 'Create Custom Field Group'" :description="$isEditing ? 'Update group details.' : 'Create a new custom field group.'">
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate>
                Back to Custom Field Groups
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Group Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" rows="3" />
                    <flux:input wire:model="sortOrder" label="Sort Order" type="number" min="0" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Group' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
