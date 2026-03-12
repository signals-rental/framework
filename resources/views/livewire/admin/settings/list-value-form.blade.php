<?php

use App\Actions\ListValues\CreateListValue;
use App\Actions\ListValues\UpdateListValue;
use App\Data\ListValues\CreateListValueData;
use App\Data\ListValues\UpdateListValueData;
use App\Models\ListName;
use App\Models\ListValue;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('List Value')] class extends Component {
    public ListName $listName;
    public ?int $listValueId = null;
    public string $name = '';
    public int $sortOrder = 0;
    public bool $isActive = true;
    public ?int $parentId = null;

    public function mount(ListName $listName, ?ListValue $listValue = null): void
    {
        $this->listName = $listName;

        if ($listValue?->exists) {
            $this->listValueId = $listValue->id;
            $this->name = $listValue->name;
            $this->sortOrder = $listValue->sort_order ?? 0;
            $this->isActive = $listValue->is_active;
            $this->parentId = $listValue->parent_id;
        }
    }

    public function with(): array
    {
        $parentOptions = collect();

        if ($this->listName->is_hierarchical) {
            $query = $this->listName->values()->orderBy('sort_order');

            if ($this->listValueId) {
                $query->where('id', '!=', $this->listValueId);
            }

            $parentOptions = $query->get(['id', 'name']);
        }

        return [
            'isEditing' => $this->listValueId !== null,
            'parentOptions' => $parentOptions,
        ];
    }

    public function save(): void
    {
        $data = [
            'name' => $this->name,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];

        if ($this->listName->is_hierarchical) {
            $data['parent_id'] = $this->parentId;
        }

        if ($this->listValueId) {
            $value = ListValue::findOrFail($this->listValueId);
            (new UpdateListValue)($value, UpdateListValueData::validateAndCreate($data));
        } else {
            $data['list_name_id'] = $this->listName->id;
            (new CreateListValue)(CreateListValueData::validateAndCreate($data));
        }

        $this->redirect(route('admin.settings.lists', $this->listName), navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="data" :title="$isEditing ? 'Edit Value' : 'Add Value'" :description="$isEditing ? 'Update value details.' : 'Add a new value to ' . $listName->name . '.'">
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.lists', $listName) }}" wire:navigate>
                Back to {{ $listName->name }}
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Value Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    <flux:input wire:model="sortOrder" label="Sort Order" type="number" min="0" />
                    <flux:checkbox wire:model="isActive" label="Active" />

                    @if($listName->is_hierarchical)
                        <div>
                            <flux:select wire:model="parentId" label="Parent" placeholder="None (top level)">
                                @foreach($parentOptions as $option)
                                    <flux:select.option value="{{ $option->id }}">{{ $option->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Add Value' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.lists', $listName) }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
