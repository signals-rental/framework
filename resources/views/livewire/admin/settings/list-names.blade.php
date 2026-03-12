<?php

use App\Actions\ListValues\DeleteListName;
use App\Models\ListName;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('List Names')] class extends Component {
    public function deleteList(int $listNameId): void
    {
        $listName = ListName::findOrFail($listNameId);

        if ($listName->is_system) {
            $this->addError('delete', 'System lists cannot be deleted.');

            return;
        }

        (new DeleteListName)($listName);
        $this->dispatch('list-deleted');
    }

    public function with(): array
    {
        return [
            'listNames' => ListName::query()
                ->withCount('values')
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="data" title="List Names" description="Manage configurable list definitions.">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('admin.settings.list-names.create') }}" wire:navigate>Add List</flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Hierarchical</th>
                        <th>System</th>
                        <th>Values</th>
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($listNames as $listName)
                        <tr wire:key="list-{{ $listName->id }}">
                            <td class="font-medium">{{ $listName->name }}</td>
                            <td class="text-sm text-zinc-500">{{ $listName->description ?? '-' }}</td>
                            <td>
                                @if($listName->is_hierarchical)
                                    <span class="s-badge s-badge-blue">Hierarchical</span>
                                @else
                                    <span class="s-badge s-badge-zinc">Flat</span>
                                @endif
                            </td>
                            <td>
                                @if($listName->is_system)
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon.lock-closed class="w-4 h-4 text-zinc-400" />
                                        <span class="s-badge s-badge-zinc">System</span>
                                    </span>
                                @endif
                            </td>
                            <td>{{ $listName->values_count }}</td>
                            <td>
                                <div class="flex items-center gap-1">
                                    @if(! $listName->is_system)
                                        <a href="{{ route('admin.settings.list-names.edit', $listName) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                            <flux:icon.pencil-square class="w-4 h-4" />
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.settings.lists', $listName) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Manage Values">
                                        Values
                                    </a>
                                    @if(! $listName->is_system)
                                        <button wire:click="deleteList({{ $listName->id }})"
                                                wire:confirm="Are you sure you want to delete this list and all its values?"
                                                class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-[var(--text-muted)]">No lists found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @error('delete')
            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
        @enderror

        <x-action-message on="list-deleted">List deleted.</x-action-message>
    </x-admin.layout>
</section>
