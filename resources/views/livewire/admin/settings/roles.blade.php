<?php

use App\Actions\Admin\DeleteRole;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('components.layouts.app')] class extends Component {
    public ?int $confirmingDeletion = null;

    public function with(): array
    {
        return [
            'roles' => Role::query()
                ->withCount('users')
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    public function deleteRole(int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        (new DeleteRole)($role);
        $this->confirmingDeletion = null;
        $this->dispatch('role-deleted');
    }
}; ?>

<section class="w-full">
    <x-admin.layout title="Roles" description="Manage roles and their permissions.">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('admin.settings.roles.create') }}" wire:navigate>Create Role</flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Description</th>
                        <th>Users</th>
                        <th>Type</th>
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr wire:key="role-{{ $role->id }}">
                            <td class="font-medium">{{ $role->name }}</td>
                            <td class="text-sm text-zinc-500">{{ $role->description ?? '-' }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td>
                                @if($role->is_system)
                                    <span class="s-badge s-badge-blue">System</span>
                                @else
                                    <span class="s-badge">Custom</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.settings.roles.edit', $role) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm">
                                        Edit
                                    </a>
                                    @if(! $role->is_system)
                                        <button class="s-btn s-btn-ghost s-btn-sm text-red-600" wire:click="$set('confirmingDeletion', {{ $role->id }})">
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-action-message on="role-deleted">Role deleted.</x-action-message>

        {{-- Delete Confirmation --}}
        @if($confirmingDeletion)
            <flux:modal wire:model.self="confirmingDeletion">
                <div class="space-y-4">
                    <flux:heading size="lg">Delete Role</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Are you sure you want to delete this role? This action cannot be undone.
                    </p>
                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('confirmingDeletion', null)">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="deleteRole({{ $confirmingDeletion }})">Delete Role</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </x-admin.layout>
</section>
