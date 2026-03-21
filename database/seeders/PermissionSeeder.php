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
     * @return array<string, array{label: string, description: string, group: string, sub_group: string|null, layer: string, dependencies: list<string>}>
     */
    public static function permissions(): array
    {
        return [
            // Settings
            'settings.access' => ['label' => 'Access Settings', 'description' => 'Access the settings area', 'group' => 'Settings', 'sub_group' => null, 'layer' => 'area', 'dependencies' => []],
            'settings.view' => ['label' => 'View Settings', 'description' => 'View system settings', 'group' => 'Settings', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['settings.access']],
            'settings.manage' => ['label' => 'Manage Settings', 'description' => 'Create, update, and delete system settings', 'group' => 'Settings', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['settings.view']],

            // Users
            'users.access' => ['label' => 'Access Users', 'description' => 'Access the users area', 'group' => 'Users', 'sub_group' => null, 'layer' => 'area', 'dependencies' => []],
            'users.view' => ['label' => 'View Users', 'description' => 'View user list and details', 'group' => 'Users', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['users.access']],
            'users.invite' => ['label' => 'Invite Users', 'description' => 'Send user invitations', 'group' => 'Users', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['users.view']],
            'users.edit' => ['label' => 'Edit Users', 'description' => 'Edit user details and roles', 'group' => 'Users', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['users.view']],
            'users.deactivate' => ['label' => 'Deactivate Users', 'description' => 'Deactivate user accounts', 'group' => 'Users', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['users.edit']],
            'users.activate' => ['label' => 'Activate Users', 'description' => 'Reactivate user accounts', 'group' => 'Users', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['users.edit']],
            'users.delete' => ['label' => 'Delete Users', 'description' => 'Permanently delete user accounts', 'group' => 'Users', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['users.edit']],
            'users.reset-password' => ['label' => 'Reset Passwords', 'description' => 'Send password reset emails', 'group' => 'Users', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['users.edit']],

            // Roles
            'roles.view' => ['label' => 'View Roles', 'description' => 'View roles and their permissions', 'group' => 'Roles', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['settings.access']],
            'roles.manage' => ['label' => 'Manage Roles', 'description' => 'Create, edit, and delete roles', 'group' => 'Roles', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['roles.view']],

            // Members
            'members.access' => ['label' => 'Access Members', 'description' => 'Access the members area', 'group' => 'Members', 'sub_group' => null, 'layer' => 'area', 'dependencies' => []],
            'members.view' => ['label' => 'View Members', 'description' => 'View member records', 'group' => 'Members', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['members.access']],
            'members.create' => ['label' => 'Create Members', 'description' => 'Create new member records', 'group' => 'Members', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['members.view']],
            'members.edit' => ['label' => 'Edit Members', 'description' => 'Edit member records', 'group' => 'Members', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['members.view']],
            'members.delete' => ['label' => 'Delete Members', 'description' => 'Delete member records', 'group' => 'Members', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['members.edit']],

            // Opportunities
            'opportunities.access' => ['label' => 'Access Opportunities', 'description' => 'Access the opportunities area', 'group' => 'Opportunities', 'sub_group' => null, 'layer' => 'area', 'dependencies' => []],
            'opportunities.view' => ['label' => 'View Opportunities', 'description' => 'View opportunities and quotes', 'group' => 'Opportunities', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['opportunities.access']],
            'opportunities.create' => ['label' => 'Create Opportunities', 'description' => 'Create new opportunities', 'group' => 'Opportunities', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['opportunities.view']],
            'opportunities.edit' => ['label' => 'Edit Opportunities', 'description' => 'Edit existing opportunities', 'group' => 'Opportunities', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['opportunities.view']],
            'opportunities.delete' => ['label' => 'Delete Opportunities', 'description' => 'Delete opportunities', 'group' => 'Opportunities', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['opportunities.edit']],

            // Activities
            'activities.access' => ['label' => 'Access Activities', 'description' => 'Access the activities area', 'group' => 'Activities', 'sub_group' => null, 'layer' => 'area', 'dependencies' => []],
            'activities.view' => ['label' => 'View Activities', 'description' => 'View activity records', 'group' => 'Activities', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['activities.access']],
            'activities.create' => ['label' => 'Create Activities', 'description' => 'Create new activities', 'group' => 'Activities', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['activities.view']],
            'activities.edit' => ['label' => 'Edit Activities', 'description' => 'Edit existing activities', 'group' => 'Activities', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['activities.view']],
            'activities.delete' => ['label' => 'Delete Activities', 'description' => 'Delete activities', 'group' => 'Activities', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['activities.edit']],
            'activities.complete' => ['label' => 'Complete Activities', 'description' => 'Mark activities as completed', 'group' => 'Activities', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['activities.edit']],

            // Invoices
            'invoices.access' => ['label' => 'Access Invoices', 'description' => 'Access the invoices area', 'group' => 'Invoices', 'sub_group' => null, 'layer' => 'area', 'dependencies' => []],
            'invoices.view' => ['label' => 'View Invoices', 'description' => 'View invoices', 'group' => 'Invoices', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['invoices.access']],
            'invoices.create' => ['label' => 'Create Invoices', 'description' => 'Create new invoices', 'group' => 'Invoices', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['invoices.view']],
            'invoices.edit' => ['label' => 'Edit Invoices', 'description' => 'Edit existing invoices', 'group' => 'Invoices', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['invoices.view']],
            'invoices.delete' => ['label' => 'Delete Invoices', 'description' => 'Delete invoices', 'group' => 'Invoices', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['invoices.edit']],

            // Products
            'products.access' => ['label' => 'Access Products', 'description' => 'Access the products area', 'group' => 'Products', 'sub_group' => null, 'layer' => 'area', 'dependencies' => []],
            'products.view' => ['label' => 'View Products', 'description' => 'View product catalogue', 'group' => 'Products', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['products.access']],
            'products.create' => ['label' => 'Create Products', 'description' => 'Create new products', 'group' => 'Products', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['products.view']],
            'products.edit' => ['label' => 'Edit Products', 'description' => 'Edit existing products', 'group' => 'Products', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['products.view']],
            'products.delete' => ['label' => 'Delete Products', 'description' => 'Delete products', 'group' => 'Products', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['products.edit']],

            // Stock
            'stock.access' => ['label' => 'Access Stock', 'description' => 'Access the stock area', 'group' => 'Stock', 'sub_group' => null, 'layer' => 'area', 'dependencies' => []],
            'stock.view' => ['label' => 'View Stock', 'description' => 'View stock levels and movements', 'group' => 'Stock', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['stock.access']],
            'stock.adjust' => ['label' => 'Adjust Stock', 'description' => 'Make stock adjustments', 'group' => 'Stock', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['stock.view']],
            'stock.transfer' => ['label' => 'Transfer Stock', 'description' => 'Transfer stock between stores', 'group' => 'Stock', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['stock.view']],

            // Reports
            'reports.access' => ['label' => 'Access Reports', 'description' => 'Access the reports area', 'group' => 'Reports', 'sub_group' => null, 'layer' => 'area', 'dependencies' => []],
            'reports.view' => ['label' => 'View Reports', 'description' => 'View reports', 'group' => 'Reports', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['reports.access']],
            'reports.export' => ['label' => 'Export Reports', 'description' => 'Export report data', 'group' => 'Reports', 'sub_group' => null, 'layer' => 'action', 'dependencies' => ['reports.view']],

            // System
            'system.read' => ['label' => 'Read System Info', 'description' => 'Access system health and diagnostics via API', 'group' => 'System', 'sub_group' => null, 'layer' => 'action', 'dependencies' => []],
            'action-log.view' => ['label' => 'View Action Log', 'description' => 'View the audit trail', 'group' => 'System', 'sub_group' => 'Audit', 'layer' => 'action', 'dependencies' => ['settings.access']],
            'email-templates.manage' => ['label' => 'Manage Email Templates', 'description' => 'Edit email templates and content', 'group' => 'System', 'sub_group' => 'Communications', 'layer' => 'action', 'dependencies' => ['settings.access']],
            'notifications.manage' => ['label' => 'Manage Notifications', 'description' => 'Configure notification types and channels', 'group' => 'System', 'sub_group' => 'Communications', 'layer' => 'action', 'dependencies' => ['settings.access']],
            'custom-fields.view' => ['label' => 'View Custom Fields', 'description' => 'View custom field definitions', 'group' => 'System', 'sub_group' => 'Customisation', 'layer' => 'action', 'dependencies' => ['settings.access']],
            'custom-fields.manage' => ['label' => 'Manage Custom Fields', 'description' => 'Create and manage custom fields', 'group' => 'System', 'sub_group' => 'Customisation', 'layer' => 'action', 'dependencies' => ['custom-fields.view']],
            'list-values.view' => ['label' => 'View Lists', 'description' => 'View list of values', 'group' => 'System', 'sub_group' => 'Customisation', 'layer' => 'action', 'dependencies' => ['settings.access']],
            'list-values.manage' => ['label' => 'Manage Lists', 'description' => 'Create and manage list of values', 'group' => 'System', 'sub_group' => 'Customisation', 'layer' => 'action', 'dependencies' => ['list-values.view']],
            'tax-classes.view' => ['label' => 'View Tax Classes', 'description' => 'View tax class definitions', 'group' => 'System', 'sub_group' => 'Finance', 'layer' => 'action', 'dependencies' => ['settings.access']],
            'tax-classes.manage' => ['label' => 'Manage Tax Classes', 'description' => 'Create and manage tax classes', 'group' => 'System', 'sub_group' => 'Finance', 'layer' => 'action', 'dependencies' => ['tax-classes.view']],
            'static-data.view' => ['label' => 'View Static Data', 'description' => 'View reference data (countries, etc.)', 'group' => 'System', 'sub_group' => 'Reference Data', 'layer' => 'action', 'dependencies' => ['settings.access']],
            'static-data.manage' => ['label' => 'Manage Static Data', 'description' => 'Manage reference data and lookups', 'group' => 'System', 'sub_group' => 'Reference Data', 'layer' => 'action', 'dependencies' => ['static-data.view']],
            'webhooks.manage' => ['label' => 'Manage Webhooks', 'description' => 'Create and manage webhook subscriptions', 'group' => 'System', 'sub_group' => 'Integrations', 'layer' => 'action', 'dependencies' => ['settings.access']],

            // Global — cost visibility (field-level)
            'costs.view' => ['label' => 'View Costs', 'description' => 'View cost prices, margins, and purchase costs', 'group' => 'Global', 'sub_group' => null, 'layer' => 'field', 'dependencies' => []],
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
