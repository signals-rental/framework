<?php

use App\Actions\ListValues\DeleteListValue;
use App\Actions\ListValues\UpdateListValue;
use App\Data\ListValues\UpdateListValueData;
use App\Models\ListName;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Lists')] class extends Component {
    public ListName $listName;

    public function mount(ListName $listName): void
    {
        $this->listName = $listName;
    }

    public function toggleActive(int $valueId): void
    {
        $value = $this->listName->values()->findOrFail($valueId);
        (new UpdateListValue)($value, UpdateListValueData::validateAndCreate([
            'is_active' => ! $value->is_active,
        ]));
    }

    public function deleteValue(int $valueId): void
    {
        $value = $this->listName->values()->findOrFail($valueId);

        if ($value->is_system) {
            $this->addError('delete', 'System values cannot be deleted.');

            return;
        }

        (new DeleteListValue)($value);
        $this->dispatch('value-deleted');
    }

    public function with(): array
    {
        return [
            'values' => $this->listName->values()->with('parent')->orderBy('sort_order')->get(),
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="data" :title="$listName->name" :description="'Manage values for ' . $listName->name . '.'">
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.list-names') }}" wire:navigate>
                Back to List Names
            </flux:button>
            <flux:button variant="primary" href="{{ route('admin.settings.list-values.create', $listName) }}" wire:navigate>Add Value</flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        @if($listName->is_hierarchical)
                            <th>Parent</th>
                        @endif
                        <th>Sort Order</th>
                        <th>System</th>
                        <th>Active</th>
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($values as $value)
                        <tr wire:key="value-{{ $value->id }}">
                            <td class="font-medium">{{ $value->name }}</td>
                            @if($listName->is_hierarchical)
                                <td class="text-sm text-zinc-500">{{ $value->parent?->name ?? '-' }}</td>
                            @endif
                            <td>{{ $value->sort_order }}</td>
                            <td>
                                @if($value->is_system)
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon.lock-closed class="w-4 h-4 text-zinc-400" />
                                        <span class="s-badge s-badge-zinc">System</span>
                                    </span>
                                @endif
                            </td>
                            <td>
                                <button wire:click="toggleActive({{ $value->id }})"
                                        class="s-btn-ghost s-btn-xs">
                                    @if($value->is_active)
                                        <span class="s-badge s-badge-green">Active</span>
                                    @else
                                        <span class="s-badge s-badge-zinc">Inactive</span>
                                    @endif
                                </button>
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    @if(! $value->is_system)
                                        <a href="{{ route('admin.settings.list-values.edit', [$listName, $value]) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                            <flux:icon.pencil-square class="w-4 h-4" />
                                        </a>
                                        <button wire:click="deleteValue({{ $value->id }})"
                                                wire:confirm="Are you sure you want to delete this value?"
                                                class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $listName->is_hierarchical ? 6 : 5 }}" class="text-center text-[var(--text-muted)]">No values found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @error('delete')
            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
        @enderror

        <x-action-message on="value-deleted">Value deleted.</x-action-message>
    </x-admin.layout>
</section>
