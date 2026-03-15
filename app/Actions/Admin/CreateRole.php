<?php

namespace App\Actions\Admin;

use App\Events\AuditableEvent;
use App\Services\PermissionRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
            $this->validatePermissions($data['permissions']);
            $role->syncPermissions($data['permissions']);
        }

        app(\App\Services\Api\WebhookService::class)->dispatch('role.created', [
            'role' => ['id' => $role->id, 'name' => $role->name],
        ]);

        event(new AuditableEvent($role, 'created', null, [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->all(),
        ]));

        return $role;
    }

    /**
     * Validate that all permissions exist in the registry.
     *
     * @param  list<string>  $permissions
     *
     * @throws ValidationException
     */
    private function validatePermissions(array $permissions): void
    {
        $registry = app(PermissionRegistry::class);
        $invalid = array_filter($permissions, fn (string $p): bool => ! $registry->has($p));

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'The following permissions are not registered: '.implode(', ', $invalid),
            ]);
        }
    }
}
