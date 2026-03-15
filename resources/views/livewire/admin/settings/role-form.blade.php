<?php

use App\Actions\Admin\CreateRole;
use App\Actions\Admin\UpdateRole;
use App\Models\User;
use App\Services\PermissionRegistry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('components.layouts.app')] #[Title('Role')] class extends Component {
    public ?int $roleId = null;
    public string $roleName = '';
    public string $roleDescription = '';
    /** @var list<string> */
    public array $selectedPermissions = [];
    /** @var list<int> */
    public array $selectedUsers = [];
    public bool $isSystem = false;

    public function mount(?Role $role = null): void
    {
        if ($role?->exists) {
            $this->roleId = $role->id;
            $this->roleName = $role->name;
            $this->roleDescription = $role->description ?? '';
            $this->selectedPermissions = $role->permissions->pluck('name')->all();
            $this->selectedUsers = User::role($role->name)->pluck('id')->all();
            $this->isSystem = (bool) $role->getAttribute('is_system');
        }
    }

    public function with(): array
    {
        $registry = app(PermissionRegistry::class);

        return [
            'permissionGroups' => $registry->grouped(),
            'allUsers' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'is_owner']),
            'isEditing' => $this->roleId !== null,
        ];
    }

    public function save(): void
    {
        $registry = app(PermissionRegistry::class);
        $validPermissions = array_keys($registry->all());

        $this->validate([
            'roleName' => ['required', 'string', 'max:255'],
            'roleDescription' => ['nullable', 'string', 'max:1000'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', \Illuminate\Validation\Rule::in($validPermissions)],
            'selectedUsers' => ['array'],
            'selectedUsers.*' => ['integer', 'exists:users,id'],
        ]);

        if ($this->roleId) {
            $role = Role::findOrFail($this->roleId);
            (new UpdateRole)($role, [
                'name' => $this->roleName,
                'description' => $this->roleDescription,
                'permissions' => $this->selectedPermissions,
            ]);
        } else {
            $role = (new CreateRole)([
                'name' => $this->roleName,
                'description' => $this->roleDescription,
                'permissions' => $this->selectedPermissions,
            ]);
        }

        $this->syncRoleUsers($role);

        $this->redirect(route('admin.settings.roles'), navigate: true);
    }

    public function toggleGroupPermissions(string $group): void
    {
        $registry = app(PermissionRegistry::class);
        $grouped = $registry->grouped();
        $groupPermissions = array_keys($grouped[$group] ?? []);

        $allSelected = count(array_intersect($groupPermissions, $this->selectedPermissions)) === count($groupPermissions);

        if ($allSelected) {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $groupPermissions));
        } else {
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $groupPermissions)));
        }
    }

    private function syncRoleUsers(Role $role): void
    {
        $currentUserIds = User::role($role->name)->pluck('id')->all();
        $newUserIds = $this->selectedUsers;

        $toAssign = array_diff($newUserIds, $currentUserIds);
        $toRemove = array_diff($currentUserIds, $newUserIds);

        foreach ($toAssign as $userId) {
            $user = User::find($userId);
            $user?->assignRole($role);
        }

        foreach ($toRemove as $userId) {
            $user = User::find($userId);
            $user?->removeRole($role);
        }
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="users" :title="$isEditing ? 'Edit Role' : 'Create Role'" :description="$isEditing ? 'Update role details and permissions.' : 'Create a new role with specific permissions.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Roles', 'href' => route('admin.settings.roles')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.roles') }}" wire:navigate>
                Back to Roles
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Role Details">
                <div class="space-y-4">
                    <flux:input wire:model="roleName" label="Role Name" required
                                :disabled="$isSystem" />
                    <flux:textarea wire:model="roleDescription" label="Description" rows="2" />
                </div>
            </x-signals.form-section>

            {{-- Users --}}
            <x-signals.form-section title="Users">
                <p class="text-sm text-zinc-500 mb-3">Select which users should have this role.</p>
                @if($allUsers->isEmpty())
                    <p class="text-sm text-zinc-400">No users available.</p>
                @else
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @foreach($allUsers as $user)
                            <label class="flex items-center gap-2 cursor-pointer" wire:key="user-{{ $user->id }}"
                                   x-data="{ checked: @js(in_array($user->id, $selectedUsers)) }"
                                   x-init="$watch('$wire.selectedUsers', v => checked = v.includes({{ $user->id }}))">
                                <input type="checkbox" wire:model="selectedUsers" value="{{ $user->id }}" class="hidden" x-on:change="checked = $el.checked" />
                                <x-signals.checkbox x-bind:class="checked && 'checked'" />
                                <span class="text-sm">{{ $user->name }}</span>
                                @if($user->is_owner)
                                    <span class="s-status s-status-amber text-xs">Owner</span>
                                @endif
                                <span class="text-xs text-zinc-400">{{ $user->email }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('selectedUsers') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                @error('selectedUsers.*') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </x-signals.form-section>

            <div class="space-y-6">
                @foreach($permissionGroups as $group => $permissions)
                    <x-signals.form-section wire:key="group-{{ $group }}">
                        <x-slot:headerActions>
                            <button type="button" class="s-btn s-btn-ghost s-btn-sm"
                                    wire:click="toggleGroupPermissions('{{ $group }}')">
                                Toggle All
                            </button>
                        </x-slot:headerActions>

                        <div class="s-form-section-title mb-3">{{ $group }}</div>

                        <div class="grid grid-cols-2 gap-2">
                            @foreach($permissions as $key => $meta)
                                <label class="flex items-center gap-2 cursor-pointer" wire:key="perm-{{ $key }}"
                                       x-data="{ checked: @js(in_array($key, $selectedPermissions)) }"
                                       x-init="$watch('$wire.selectedPermissions', v => checked = v.includes('{{ $key }}'))">
                                    <input type="checkbox" wire:model="selectedPermissions" value="{{ $key }}" class="hidden" x-on:change="checked = $el.checked" />
                                    <x-signals.checkbox x-bind:class="checked && 'checked'" />
                                    <span class="text-sm">{{ $meta['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </x-signals.form-section>
                @endforeach
            </div>

            @error('selectedPermissions') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('selectedPermissions.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Role' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.roles') }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
