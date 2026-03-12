<?php

use App\Actions\CustomFields\DeleteCustomFieldGroup;
use App\Models\CustomFieldGroup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Custom Field Groups')] class extends Component {
    public function deleteGroup(int $groupId): void
    {
        $group = CustomFieldGroup::findOrFail($groupId);

        if ($group->customFields()->count() > 0) {
            $this->addError('delete', 'Cannot delete a group that still has custom fields.');

            return;
        }

        (new DeleteCustomFieldGroup)($group);
        $this->dispatch('group-deleted');
    }

    public function with(): array
    {
        return [
            'groups' => CustomFieldGroup::query()
                ->withCount('customFields')
                ->orderBy('sort_order')
                ->get(),
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="data" title="Custom Field Groups" description="Organise custom fields into groups.">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('admin.settings.custom-field-groups.create') }}" wire:navigate>Add Group</flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Sort Order</th>
                        <th>Fields</th>
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $group)
                        <tr wire:key="group-{{ $group->id }}">
                            <td class="font-medium">{{ $group->name }}</td>
                            <td class="text-sm text-zinc-500">{{ $group->description ?? '-' }}</td>
                            <td>{{ $group->sort_order }}</td>
                            <td>{{ $group->custom_fields_count }}</td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('admin.settings.custom-field-groups.edit', $group) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                        <flux:icon.pencil-square class="w-4 h-4" />
                                    </a>
                                    <button wire:click="deleteGroup({{ $group->id }})"
                                            wire:confirm="Are you sure you want to delete this group?"
                                            class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-[var(--text-muted)]">No custom field groups found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @error('delete')
            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
        @enderror

        <x-action-message on="group-deleted">Group deleted.</x-action-message>
    </x-admin.layout>
</section>
