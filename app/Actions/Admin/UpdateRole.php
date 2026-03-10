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

        $updates = [];
        if (isset($data['name'])) {
            $updates['name'] = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }
        if ($updates !== []) {
            $role->update($updates);
        }

        if (isset($data['permissions'])) {
            foreach ($data['permissions'] as $permission) {
                Permission::findOrCreate($permission, 'web');
            }
            $role->syncPermissions($data['permissions']);
        }

        /** @var \Spatie\Permission\Models\Role $freshRole */
        $freshRole = $role->fresh();

        app(\App\Services\Api\WebhookService::class)->dispatch('role.updated', [
            'role' => ['id' => $freshRole->id, 'name' => $freshRole->name],
        ]);

        return $freshRole;
    }
}
