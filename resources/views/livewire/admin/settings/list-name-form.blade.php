<?php

use App\Actions\ListValues\CreateListName;
use App\Actions\ListValues\UpdateListName;
use App\Data\ListValues\CreateListNameData;
use App\Data\ListValues\UpdateListNameData;
use App\Models\ListName;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('List Name')] class extends Component {
    public ?int $listNameId = null;
    public string $name = '';
    public string $description = '';
    public bool $isHierarchical = false;
    public bool $hasValues = false;

    public function mount(?ListName $listName = null): void
    {
        if ($listName?->exists) {
            $this->listNameId = $listName->id;
            $this->name = $listName->name;
            $this->description = $listName->description ?? '';
            $this->isHierarchical = $listName->is_hierarchical;
            $this->hasValues = $listName->values()->count() > 0;
        }
    }

    public function with(): array
    {
        return [
            'isEditing' => $this->listNameId !== null,
        ];
    }

    public function save(): void
    {
        if ($this->listNameId) {
            $listName = ListName::findOrFail($this->listNameId);

            $data = [
                'name' => $this->name,
                'description' => $this->description ?: null,
            ];

            if (! $this->hasValues) {
                $data['is_hierarchical'] = $this->isHierarchical;
            }

            (new UpdateListName)($listName, UpdateListNameData::from($data));
        } else {
            (new CreateListName)(CreateListNameData::validateAndCreate([
                'name' => $this->name,
                'description' => $this->description ?: null,
                'is_hierarchical' => $this->isHierarchical,
            ]));
        }

        $this->redirect(route('admin.settings.list-names'), navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="data" :title="$isEditing ? 'Edit List' : 'Create List'" :description="$isEditing ? 'Update list details.' : 'Create a new configurable list.'">
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.list-names') }}" wire:navigate>
                Back to List Names
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="List Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:textarea wire:model="description" label="Description" rows="3" />

                    <div>
                        <flux:checkbox wire:model="isHierarchical" label="Hierarchical"
                                       :disabled="$isEditing && $hasValues" />
                        @if($isEditing && $hasValues)
                            <p class="text-xs text-zinc-500 mt-1">Cannot change hierarchy setting while values exist.</p>
                        @endif
                    </div>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create List' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.list-names') }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
