<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('System Administration')] class extends Component {
    /**
     * @return array<string, array{title: string, description: string, items: array<int, array{label: string, description: string, icon: string, route: string, gate?: string}>}>
     */
    public function getSectionsProperty(): array
    {
        return [
            'setup' => [
                'title' => 'Setup',
                'description' => 'Company details, stores, branding, and modules.',
                'items' => [
                    ['label' => 'Company Details', 'description' => 'Business name, address, and locale', 'icon' => 'building-office', 'route' => 'admin.settings.company'],
                    ['label' => 'Stores', 'description' => 'Manage store locations', 'icon' => 'building-storefront', 'route' => 'admin.settings.stores'],
                    ['label' => 'Branding', 'description' => 'Logo, colours, and appearance', 'icon' => 'paint-brush', 'route' => 'admin.settings.branding'],
                    ['label' => 'Modules', 'description' => 'Enable or disable application modules', 'icon' => 'squares-2x2', 'route' => 'admin.settings.modules'],
                ],
            ],
            'users' => [
                'title' => 'Users & Security',
                'description' => 'User accounts, roles, permissions, and API access.',
                'items' => [
                    ['label' => 'Users', 'description' => 'Manage user accounts', 'icon' => 'users', 'route' => 'admin.settings.users'],
                    ['label' => 'Roles', 'description' => 'Create and assign roles', 'icon' => 'shield-check', 'route' => 'admin.settings.roles'],
                    ['label' => 'Permissions Reference', 'description' => 'View all system permissions', 'icon' => 'key', 'route' => 'admin.settings.permissions'],
                    ['label' => 'Security', 'description' => 'Password policies and two-factor auth', 'icon' => 'lock-closed', 'route' => 'admin.settings.security'],
                    ['label' => 'API Tokens', 'description' => 'Manage API authentication tokens', 'icon' => 'code-bracket', 'route' => 'admin.settings.api'],
                ],
            ],
            'preferences' => [
                'title' => 'Preferences',
                'description' => 'Email, notifications, and scheduling configuration.',
                'items' => [
                    ['label' => 'General', 'description' => 'Default preferences and display options', 'icon' => 'adjustments-horizontal', 'route' => 'admin.settings.preferences'],
                    ['label' => 'Email', 'description' => 'SMTP and email delivery settings', 'icon' => 'envelope', 'route' => 'admin.settings.email'],
                    ['label' => 'Email Templates', 'description' => 'Customise transactional email templates', 'icon' => 'document-text', 'route' => 'admin.settings.email-templates'],
                    ['label' => 'Notifications', 'description' => 'Notification channels and preferences', 'icon' => 'bell', 'route' => 'admin.settings.notifications'],
                    ['label' => 'Scheduling', 'description' => 'Scheduled tasks and automation', 'icon' => 'calendar-days', 'route' => 'admin.settings.scheduling'],
                    ['label' => 'Integrations', 'description' => 'what3words, Google Maps, and other service keys', 'icon' => 'puzzle-piece', 'route' => 'admin.settings.integrations'],
                ],
            ],
            'data' => [
                'title' => 'Data',
                'description' => 'Custom fields, lists, and reference data.',
                'items' => [
                    ['label' => 'Custom Field Groups', 'description' => 'Organise custom fields into groups', 'icon' => 'rectangle-group', 'route' => 'admin.settings.custom-field-groups'],
                    ['label' => 'Custom Fields', 'description' => 'Define custom data fields', 'icon' => 'adjustments-vertical', 'route' => 'admin.settings.custom-fields'],
                    ['label' => 'Lists', 'description' => 'Reference data and dropdown values', 'icon' => 'list-bullet', 'route' => 'admin.settings.list-names'],
                    ['label' => 'Countries', 'description' => 'Country reference data', 'icon' => 'globe-alt', 'route' => 'admin.settings.countries'],
                ],
            ],
            'tax' => [
                'title' => 'Tax',
                'description' => 'Tax classifications for products and organisations.',
                'items' => [
                    ['label' => 'Product Tax Classes', 'description' => 'Tax rates applied to products', 'icon' => 'receipt-percent', 'route' => 'admin.settings.tax.product-tax-classes'],
                    ['label' => 'Organisation Tax Classes', 'description' => 'Tax exemptions for organisations', 'icon' => 'building-office-2', 'route' => 'admin.settings.tax.organisation-tax-classes'],
                    ['label' => 'Tax Rates', 'description' => 'Tax rate percentages', 'icon' => 'calculator', 'route' => 'admin.settings.tax.rates'],
                    ['label' => 'Tax Rules', 'description' => 'Map tax classes to rates', 'icon' => 'table-cells', 'route' => 'admin.settings.tax.rules'],
                ],
            ],
            'system' => [
                'title' => 'System',
                'description' => 'Logs, health checks, webhooks, and infrastructure.',
                'items' => [
                    ['label' => 'Action Log', 'description' => 'Audit trail of system actions', 'icon' => 'clock', 'route' => 'admin.settings.action-log'],
                    ['label' => 'System Health', 'description' => 'Service status and diagnostics', 'icon' => 'server-stack', 'route' => 'admin.settings.system-health'],
                    ['label' => 'Infrastructure', 'description' => 'Server and environment details', 'icon' => 'wrench-screwdriver', 'route' => 'admin.settings.infrastructure', 'gate' => 'owner'],
                    ['label' => 'Database Seeders', 'description' => 'Seed demo and reference data', 'icon' => 'circle-stack', 'route' => 'admin.settings.seeders'],
                    ['label' => 'Webhooks', 'description' => 'Outgoing webhook subscriptions', 'icon' => 'signal', 'route' => 'admin.settings.webhooks'],
                ],
            ],
        ];
    }
}; ?>

<section class="w-full">
    <div class="s-admin-layout" style="display: block;">
        <div class="s-admin-main">
            <div class="s-admin-section-header">
                <h1 class="s-admin-section-title">System Administration</h1>
                <p class="s-admin-section-desc">Manage your application settings, users, and system configuration.</p>
            </div>

            <div class="space-y-8">
                @foreach($this->sections as $key => $section)
                    <div>
                        <div class="mb-3">
                            <h2 class="text-sm font-semibold text-[var(--text-primary)]">{{ $section['title'] }}</h2>
                            <p class="text-xs text-[var(--text-secondary)] mt-0.5">{{ $section['description'] }}</p>
                        </div>
                        <div class="s-admin-landing-grid">
                            @foreach($section['items'] as $item)
                                @if(isset($item['gate']))
                                    @can($item['gate'])
                                        <a href="{{ route($item['route']) }}" wire:navigate class="s-module-card enabled">
                                            <div class="s-module-icon">
                                                <flux:icon :name="$item['icon']" class="!size-5" />
                                            </div>
                                            <div class="s-module-info">
                                                <div class="s-module-name">{{ $item['label'] }}</div>
                                                <div class="s-module-desc">{{ $item['description'] }}</div>
                                            </div>
                                            <flux:icon.chevron-right class="!size-4 text-[var(--text-muted)]" />
                                        </a>
                                    @endcan
                                @else
                                    <a href="{{ route($item['route']) }}" wire:navigate class="s-module-card enabled">
                                        <div class="s-module-icon">
                                            <flux:icon :name="$item['icon']" class="!size-5" />
                                        </div>
                                        <div class="s-module-info">
                                            <div class="s-module-name">{{ $item['label'] }}</div>
                                            <div class="s-module-desc">{{ $item['description'] }}</div>
                                        </div>
                                        <flux:icon.chevron-right class="!size-4 text-[var(--text-muted)]" />
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
