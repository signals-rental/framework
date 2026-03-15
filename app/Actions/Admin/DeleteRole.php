<?php

namespace App\Actions\Admin;

use App\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class DeleteRole
{
    public function __invoke(Role $role): void
    {
        Gate::authorize('roles.manage');

        if ($role->getAttribute('is_system')) {
            throw ValidationException::withMessages([
                'role' => 'System roles cannot be deleted.',
            ]);
        }

        if ($role->users()->count() > 0) {
            throw ValidationException::withMessages([
                'role' => 'Cannot delete a role that has assigned users.',
            ]);
        }

        $roleData = ['id' => $role->id, 'name' => $role->name];
        $oldValues = [
            'name' => $role->name,
            'description' => $role->getAttribute('description'),
            'permissions' => $role->permissions->pluck('name')->all(),
        ];

        event(new AuditableEvent($role, 'deleted', $oldValues));

        $role->delete();

        app(\App\Services\Api\WebhookService::class)->dispatch('role.deleted', [
            'role' => $roleData,
        ]);
    }
}
