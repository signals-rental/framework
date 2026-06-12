@props(['group' => 'setup'])

@php
    use App\Support\ActiveRoute;
@endphp

<nav class="s-admin-sidebar">
    {{-- Overview: link back to the admin landing page --}}
    <a href="{{ route('admin.index') }}" wire:navigate
       class="s-admin-nav-item s-admin-nav-overview {{ ActiveRoute::is('admin.index') ? 'active' : '' }}">
        <flux:icon.squares-2x2 class="s-admin-nav-icon" />
        Overview
    </a>

    {{-- Setup --}}
    @if($group === 'setup')
        <div class="s-admin-sidebar-title">Setup</div>
        <a href="{{ route('admin.settings.company') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.company') ? 'active' : '' }}">
            <flux:icon.building-office class="s-admin-nav-icon" />
            Company Details
        </a>
        <a href="{{ route('admin.settings.stores') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.stores') ? 'active' : '' }}">
            <flux:icon.building-storefront class="s-admin-nav-icon" />
            Stores
        </a>
        <a href="{{ route('admin.settings.branding') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.branding') ? 'active' : '' }}">
            <flux:icon.paint-brush class="s-admin-nav-icon" />
            Branding
        </a>
    @endif

    {{-- Users & Security --}}
    @if($group === 'users')
        <div class="s-admin-sidebar-title">Users & Security</div>
        <a href="{{ route('admin.settings.users') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.users', 'admin.settings.users.*') ? 'active' : '' }}">
            <flux:icon.users class="s-admin-nav-icon" />
            Users
        </a>
        @can('roles.manage')
            <a href="{{ route('admin.settings.roles') }}" wire:navigate
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.roles', 'admin.settings.roles.*') ? 'active' : '' }}">
                <flux:icon.shield-check class="s-admin-nav-icon" />
                Roles
            </a>
        @endcan
        <a href="{{ route('admin.settings.permissions') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.permissions') ? 'active' : '' }}">
            <flux:icon.key class="s-admin-nav-icon" />
            Permissions Reference
        </a>
        <a href="{{ route('admin.settings.security') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.security') ? 'active' : '' }}">
            <flux:icon.lock-closed class="s-admin-nav-icon" />
            Security
        </a>
        <a href="{{ route('admin.settings.api') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.api') ? 'active' : '' }}">
            <flux:icon.code-bracket class="s-admin-nav-icon" />
            API Tokens
        </a>
    @endif

    {{-- Preferences --}}
    @if($group === 'preferences')
        <div class="s-admin-sidebar-title">Preferences</div>
        <a href="{{ route('admin.settings.preferences') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.preferences') ? 'active' : '' }}">
            <flux:icon.adjustments-horizontal class="s-admin-nav-icon" />
            General
        </a>
        <a href="{{ route('admin.settings.email') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.email') ? 'active' : '' }}">
            <flux:icon.envelope class="s-admin-nav-icon" />
            Email
        </a>
        <a href="{{ route('admin.settings.email-templates') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.email-templates', 'admin.settings.email-templates.*') ? 'active' : '' }}">
            <flux:icon.document-text class="s-admin-nav-icon" />
            Email Templates
        </a>
        <a href="{{ route('admin.settings.notifications') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.notifications') ? 'active' : '' }}">
            <flux:icon.bell class="s-admin-nav-icon" />
            Notifications
        </a>
        <a href="{{ route('admin.settings.scheduling') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.scheduling') ? 'active' : '' }}">
            <flux:icon.calendar-days class="s-admin-nav-icon" />
            Scheduling
        </a>
        <a href="{{ route('admin.settings.integrations') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.integrations') ? 'active' : '' }}">
            <flux:icon.puzzle-piece class="s-admin-nav-icon" />
            Integrations
        </a>
    @endif

    {{-- Data --}}
    @if($group === 'data')
        <div class="s-admin-sidebar-title">Data</div>
        @can('custom-fields.manage')
            <a href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.custom-field-groups*') ? 'active' : '' }}">
                <flux:icon.rectangle-group class="s-admin-nav-icon" />
                Custom Field Groups
            </a>
            <a href="{{ route('admin.settings.custom-fields') }}" wire:navigate
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.custom-fields*') ? 'active' : '' }}">
                <flux:icon.adjustments-vertical class="s-admin-nav-icon" />
                Custom Fields
            </a>
        @endcan
        <a href="{{ route('admin.settings.list-names') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.list-names*', 'admin.settings.lists*', 'admin.settings.list-values*') ? 'active' : '' }}">
            <flux:icon.list-bullet class="s-admin-nav-icon" />
            List Names
        </a>
        <a href="{{ route('admin.settings.countries') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.countries') ? 'active' : '' }}">
            <flux:icon.globe-alt class="s-admin-nav-icon" />
            Countries
        </a>
    @endif

    {{-- Tax --}}
    @if($group === 'tax')
        <div class="s-admin-sidebar-title">Tax</div>
        <a href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.tax.product-tax-classes*') ? 'active' : '' }}">
            <flux:icon.receipt-percent class="s-admin-nav-icon" />
            Product Tax Classes
        </a>
        <a href="{{ route('admin.settings.tax.organisation-tax-classes') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.tax.organisation-tax-classes*') ? 'active' : '' }}">
            <flux:icon.building-office-2 class="s-admin-nav-icon" />
            Organisation Tax Classes
        </a>
        <a href="{{ route('admin.settings.tax.rates') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.tax.rates*') ? 'active' : '' }}">
            <flux:icon.calculator class="s-admin-nav-icon" />
            Tax Rates
        </a>
        <a href="{{ route('admin.settings.tax.rules') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.tax.rules*') ? 'active' : '' }}">
            <flux:icon.table-cells class="s-admin-nav-icon" />
            Tax Rules
        </a>
    @endif

    {{-- Pricing --}}
    @if($group === 'pricing')
        <div class="s-admin-sidebar-title">Pricing</div>
        @can('rates.view')
            <a href="{{ route('admin.settings.rate-definitions') }}" wire:navigate
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.rate-definitions*') ? 'active' : '' }}">
                <flux:icon.calculator class="s-admin-nav-icon" />
                Rate Definitions
            </a>
        @endcan
    @endif

    {{-- System --}}
    @if($group === 'system')
        <div class="s-admin-sidebar-title">System</div>
        @can('action-log.view')
            <a href="{{ route('admin.settings.action-log') }}" wire:navigate
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.action-log') ? 'active' : '' }}">
                <flux:icon.clock class="s-admin-nav-icon" />
                Action Log
            </a>
        @endcan
        <a href="{{ route('admin.settings.system-health') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.system-health') ? 'active' : '' }}">
            <flux:icon.server-stack class="s-admin-nav-icon" />
            System Health
        </a>
        @can('owner')
            <a href="{{ route('admin.settings.infrastructure') }}" wire:navigate
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.infrastructure') ? 'active' : '' }}">
                <flux:icon.wrench-screwdriver class="s-admin-nav-icon" />
                Infrastructure
            </a>
        @endcan
        <a href="{{ route('admin.settings.seeders') }}" wire:navigate
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.seeders') ? 'active' : '' }}">
            <flux:icon.circle-stack class="s-admin-nav-icon" />
            Database Seeders
        </a>
        @can('webhooks.manage')
            <a href="{{ route('admin.settings.webhooks') }}" wire:navigate
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.webhooks') ? 'active' : '' }}">
                <flux:icon.signal class="s-admin-nav-icon" />
                Webhooks
            </a>
        @endcan
    @endif
</nav>
