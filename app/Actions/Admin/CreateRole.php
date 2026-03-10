<?php

namespace App\Actions\Admin;

use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateRole
{
    /**
     * @param  array{name: string, description?: string, permissions?: list<string>}  $data
     */
    public function __invoke(array $data): Role
    {
        Gate::authorize('roles.manage');

        /** @var Role $role */
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'sort_order' => Role::max('sort_order') + 1,
        ]);

        if (! empty($data['permissions'])) {
            foreach ($data['permissions'] as $permission) {
                Permission::findOrCreate($permission, 'web');
            }
            $role->syncPermissions($data['permissions']);
        }

        return $role;
    }
}
