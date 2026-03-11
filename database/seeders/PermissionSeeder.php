<?php

namespace Database\Seeders;

use App\Services\PermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Core permissions with metadata for the UI.
     *
     * @return array<string, array{label: string, description: string, group: string}>
     */
    public static function permissions(): array
    {
        return [
            // Settings
            'settings.view' => ['label' => 'View Settings', 'description' => 'View system settings', 'group' => 'Settings'],
            'settings.manage' => ['label' => 'Manage Settings', 'description' => 'Create, update, and delete system settings', 'group' => 'Settings'],

            // Users
            'users.view' => ['label' => 'View Users', 'description' => 'View user list and details', 'group' => 'Users'],
            'users.invite' => ['label' => 'Invite Users', 'description' => 'Send user invitations', 'group' => 'Users'],
            'users.edit' => ['label' => 'Edit Users', 'description' => 'Edit user details and roles', 'group' => 'Users'],
            'users.deactivate' => ['label' => 'Deactivate Users', 'description' => 'Deactivate user accounts', 'group' => 'Users'],
            'users.activate' => ['label' => 'Activate Users', 'description' => 'Reactivate user accounts', 'group' => 'Users'],
            'users.reset-password' => ['label' => 'Reset Passwords', 'description' => 'Send password reset emails', 'group' => 'Users'],

            // Roles
            'roles.view' => ['label' => 'View Roles', 'description' => 'View roles and their permissions', 'group' => 'Roles'],
            'roles.manage' => ['label' => 'Manage Roles', 'description' => 'Create, edit, and delete roles', 'group' => 'Roles'],

            // Members
            'members.view' => ['label' => 'View Members', 'description' => 'View member records', 'group' => 'Members'],
            'members.create' => ['label' => 'Create Members', 'description' => 'Create new member records', 'group' => 'Members'],
            'members.edit' => ['label' => 'Edit Members', 'description' => 'Edit member records', 'group' => 'Members'],
            'members.delete' => ['label' => 'Delete Members', 'description' => 'Delete member records', 'group' => 'Members'],

            // Opportunities
            'opportunities.view' => ['label' => 'View Opportunities', 'description' => 'View opportunities and quotes', 'group' => 'Opportunities'],
            'opportunities.create' => ['label' => 'Create Opportunities', 'description' => 'Create new opportunities', 'group' => 'Opportunities'],
            'opportunities.edit' => ['label' => 'Edit Opportunities', 'description' => 'Edit existing opportunities', 'group' => 'Opportunities'],
            'opportunities.delete' => ['label' => 'Delete Opportunities', 'description' => 'Delete opportunities', 'group' => 'Opportunities'],

            // Invoices
            'invoices.view' => ['label' => 'View Invoices', 'description' => 'View invoices', 'group' => 'Invoices'],
            'invoices.create' => ['label' => 'Create Invoices', 'description' => 'Create new invoices', 'group' => 'Invoices'],
            'invoices.edit' => ['label' => 'Edit Invoices', 'description' => 'Edit existing invoices', 'group' => 'Invoices'],
            'invoices.delete' => ['label' => 'Delete Invoices', 'description' => 'Delete invoices', 'group' => 'Invoices'],

            // Products
            'products.view' => ['label' => 'View Products', 'description' => 'View product catalogue', 'group' => 'Products'],
            'products.create' => ['label' => 'Create Products', 'description' => 'Create new products', 'group' => 'Products'],
            'products.edit' => ['label' => 'Edit Products', 'description' => 'Edit existing products', 'group' => 'Products'],
            'products.delete' => ['label' => 'Delete Products', 'description' => 'Delete products', 'group' => 'Products'],

            // Stock
            'stock.view' => ['label' => 'View Stock', 'description' => 'View stock levels and movements', 'group' => 'Stock'],
            'stock.adjust' => ['label' => 'Adjust Stock', 'description' => 'Make stock adjustments', 'group' => 'Stock'],
            'stock.transfer' => ['label' => 'Transfer Stock', 'description' => 'Transfer stock between stores', 'group' => 'Stock'],

            // Reports
            'reports.view' => ['label' => 'View Reports', 'description' => 'View reports', 'group' => 'Reports'],
            'reports.export' => ['label' => 'Export Reports', 'description' => 'Export report data', 'group' => 'Reports'],

            // System
            'system.read' => ['label' => 'Read System Info', 'description' => 'Access system health and diagnostics via API', 'group' => 'System'],
            'action-log.view' => ['label' => 'View Action Log', 'description' => 'View the audit trail', 'group' => 'System'],
            'email-templates.manage' => ['label' => 'Manage Email Templates', 'description' => 'Edit email templates and content', 'group' => 'System'],
            'notifications.manage' => ['label' => 'Manage Notifications', 'description' => 'Configure notification types and channels', 'group' => 'System'],
            'custom-fields.view' => ['label' => 'View Custom Fields', 'description' => 'View custom field definitions', 'group' => 'System'],
            'custom-fields.manage' => ['label' => 'Manage Custom Fields', 'description' => 'Create and manage custom fields', 'group' => 'System'],
            'list-values.view' => ['label' => 'View Lists', 'description' => 'View list of values', 'group' => 'System'],
            'list-values.manage' => ['label' => 'Manage Lists', 'description' => 'Create and manage list of values', 'group' => 'System'],
            'tax-classes.view' => ['label' => 'View Tax Classes', 'description' => 'View tax class definitions', 'group' => 'System'],
            'tax-classes.manage' => ['label' => 'Manage Tax Classes', 'description' => 'Create and manage tax classes', 'group' => 'System'],
            'static-data.view' => ['label' => 'View Static Data', 'description' => 'View reference data (countries, etc.)', 'group' => 'System'],
            'static-data.manage' => ['label' => 'Manage Static Data', 'description' => 'Manage reference data and lookups', 'group' => 'System'],
            'webhooks.manage' => ['label' => 'Manage Webhooks', 'description' => 'Create and manage webhook subscriptions', 'group' => 'System'],
        ];
    }

    public function run(): void
    {
        $permissions = self::permissions();

        // Create Spatie permissions
        foreach (array_keys($permissions) as $key) {
            Permission::findOrCreate($key, 'web');
        }

        // Register metadata in the registry
        $registry = app(PermissionRegistry::class);
        $registry->registerMany($permissions);
    }
}
