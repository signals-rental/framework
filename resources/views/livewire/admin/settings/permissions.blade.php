<?php

use App\Services\PermissionRegistry;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('components.layouts.app')] class extends Component {
    public function with(): array
    {
        $registry = app(PermissionRegistry::class);
        $roles = Role::with('permissions')->orderBy('sort_order')->get();

        return [
            'permissionGroups' => $registry->grouped(),
            'roles' => $roles,
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="users" title="Permissions Reference" description="View all available permissions and which roles have them.">
        <div class="space-y-8">
            @foreach($permissionGroups as $group => $permissions)
                <x-signals.form-section :title="$group">
                    <div class="s-table-wrap">
                        <table class="s-table s-table-compact">
                            <thead>
                                <tr>
                                    <th>Permission</th>
                                    <th>Description</th>
                                    <th class="text-center w-20">Owner</th>
                                    @foreach($roles as $role)
                                        <th class="text-center w-20">{{ $role->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($permissions as $key => $meta)
                                    <tr wire:key="perm-{{ $key }}">
                                        <td class="font-medium text-sm">{{ $meta['label'] }}</td>
                                        <td class="text-sm text-zinc-500">{{ $meta['description'] }}</td>
                                        <td class="text-center">
                                            <span class="text-green-600" title="Implicit all-access">*</span>
                                        </td>
                                        @foreach($roles as $role)
                                            <td class="text-center">
                                                @if($role->hasPermissionTo($key))
                                                    <flux:icon.check class="w-4 h-4 text-green-600 mx-auto" />
                                                @else
                                                    <span class="text-zinc-300">-</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-signals.form-section>
            @endforeach
        </div>
    </x-admin.layout>
</section>
