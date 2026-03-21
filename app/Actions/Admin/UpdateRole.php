<?php

namespace App\Actions\Admin;

use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use App\Services\PermissionRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

        $oldValues = [
            'name' => $role->name,
            'description' => $role->getAttribute('description'),
            'permissions' => $role->permissions->pluck('name')->all(),
        ];

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
            app(PermissionRegistry::class)->validate($data['permissions']);
            $role->syncPermissions($data['permissions']);
        }

        /** @var \Spatie\Permission\Models\Role $freshRole */
        $freshRole = $role->fresh();

        app(WebhookService::class)->dispatch('role.updated', [
            'role' => ['id' => $freshRole->id, 'name' => $freshRole->name],
        ]);

        event(new AuditableEvent($freshRole, 'updated', $oldValues, [
            'name' => $freshRole->name,
            'description' => $freshRole->getAttribute('description'),
            'permissions' => $freshRole->permissions->pluck('name')->all(),
        ]));

        return $freshRole;
    }
}
