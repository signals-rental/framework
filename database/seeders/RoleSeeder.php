<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        // Owner access is handled via is_owner flag, not a Spatie role
        Role::query()->where('name', 'Owner')->delete();

        $allPermissions = array_keys(PermissionSeeder::permissions());

        $viewPermissions = array_filter($allPermissions, fn (string $p): bool => str_ends_with($p, '.view'));

        $resourcePermissions = array_filter($allPermissions, fn (string $p): bool => ! str_starts_with($p, 'settings.')
            && ! str_starts_with($p, 'users.')
            && ! str_starts_with($p, 'roles.')
        );

        $operatorPermissions = array_filter($allPermissions, fn (string $p): bool => str_starts_with($p, 'opportunities.')
            || in_array($p, ['invoices.view', 'invoices.create'])
            || str_starts_with($p, 'stock.')
            || $p === 'products.view'
            || $p === 'members.view'
        );

        // Admin — all permissions
        $admin = Role::findOrCreate('Admin', 'web');
        $admin->update(['is_system' => true, 'description' => 'Full access to all features and settings.', 'sort_order' => 1]);
        $admin->syncPermissions($allPermissions);

        // Manager — all resource permissions, no settings/users/roles
        $manager = Role::findOrCreate('Manager', 'web');
        $manager->update(['is_system' => true, 'description' => 'Manages day-to-day operations without system settings.', 'sort_order' => 2]);
        $manager->syncPermissions($resourcePermissions);

        // Operator — core operational permissions
        $operator = Role::findOrCreate('Operator', 'web');
        $operator->update(['is_system' => true, 'description' => 'Handles opportunities, invoicing, and stock operations.', 'sort_order' => 3]);
        $operator->syncPermissions($operatorPermissions);

        // Viewer — read-only access
        $viewer = Role::findOrCreate('Viewer', 'web');
        $viewer->update(['is_system' => true, 'description' => 'Read-only access to all resources.', 'sort_order' => 4]);
        $viewer->syncPermissions($viewPermissions);
    }
}
