<?php

namespace App\Actions\Admin;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UpdateRole
{
    /**
     * @param  array{name?: string, description?: string, permissions?: list<string>}  $data
     */
    public function __invoke(Role $role, array $data): Role
    {
        Gate::authorize('roles.manage');

        if ($role->getAttribute('is_system') && isset($data['name']) && $data['name'] !== $role->name) {
            throw ValidationException::withMessages([
                'name' => 'System roles cannot be renamed.',
            ]);
        }

        $role->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : null,
        ], fn ($value) => $value !== null));

        if (isset($data['permissions'])) {
            foreach ($data['permissions'] as $permission) {
                Permission::findOrCreate($permission, 'web');
            }
            $role->syncPermissions($data['permissions']);
        }

        return $role->fresh();
    }
}
