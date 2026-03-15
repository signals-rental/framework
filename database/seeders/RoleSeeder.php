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

        // Clean up old role names from previous seeds
        foreach (['Manager', 'Operator', 'Viewer'] as $oldName) {
            Role::query()->where('name', $oldName)->delete();
        }

        $allPermissions = array_keys(PermissionSeeder::permissions());

        $viewPermissions = array_filter($allPermissions, fn (string $p): bool => str_ends_with($p, '.view') || str_ends_with($p, '.access'));

        $opsManagerPermissions = array_filter($allPermissions, fn (string $p): bool => ! str_starts_with($p, 'settings.')
            && ! str_starts_with($p, 'users.')
            && ! str_starts_with($p, 'roles.')
        );

        $salesPermissions = array_filter($allPermissions, fn (string $p): bool => str_starts_with($p, 'opportunities.')
            || str_starts_with($p, 'invoices.')
            || str_starts_with($p, 'members.')
            || $p === 'products.access'
            || $p === 'products.view'
            || $p === 'reports.access'
            || $p === 'reports.view'
        );

        $warehousePermissions = array_filter($allPermissions, fn (string $p): bool => str_starts_with($p, 'stock.')
            || str_starts_with($p, 'products.')
            || in_array($p, ['members.access', 'members.view', 'reports.access', 'reports.view'])
        );

        // Admin — all permissions, cost visibility on
        $admin = Role::findOrCreate('Admin', 'web');
        $admin->update(['is_system' => true, 'description' => 'Full access to all features and settings.', 'sort_order' => 1, 'cost_visibility' => true]);
        $admin->syncPermissions($allPermissions);

        // Operations Manager — all resource permissions, no settings/users/roles, cost visibility on
        $opsManager = Role::findOrCreate('Operations Manager', 'web');
        $opsManager->update(['is_system' => true, 'description' => 'Manages day-to-day operations without system settings.', 'sort_order' => 2, 'cost_visibility' => true]);
        $opsManager->syncPermissions($opsManagerPermissions);

        // Sales — opportunities, invoices, members, product/report viewing, cost visibility off
        $sales = Role::findOrCreate('Sales', 'web');
        $sales->update(['is_system' => true, 'description' => 'Manages opportunities, invoices, and member relationships.', 'sort_order' => 3, 'cost_visibility' => false]);
        $sales->syncPermissions($salesPermissions);

        // Warehouse — stock and product management, cost visibility off
        $warehouse = Role::findOrCreate('Warehouse', 'web');
        $warehouse->update(['is_system' => true, 'description' => 'Manages stock levels, product inventory, and compliance.', 'sort_order' => 4, 'cost_visibility' => false]);
        $warehouse->syncPermissions($warehousePermissions);

        // Read Only — view-only access to all areas
        $readOnly = Role::findOrCreate('Read Only', 'web');
        $readOnly->update(['is_system' => true, 'description' => 'Read-only access to all resources.', 'sort_order' => 5, 'cost_visibility' => false]);
        $readOnly->syncPermissions($viewPermissions);
    }
}
