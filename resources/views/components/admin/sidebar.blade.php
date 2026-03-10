@props(['group' => 'setup'])

<nav class="s-admin-sidebar">
    {{-- Setup --}}
    @if($group === 'setup')
        <div class="s-admin-sidebar-title">Setup</div>
        <a href="{{ route('admin.settings.company') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.company') ? 'active' : '' }}">
            <flux:icon.building-office class="s-admin-nav-icon" />
            Company Details
        </a>
        <a href="{{ route('admin.settings.stores') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.stores') ? 'active' : '' }}">
            <flux:icon.building-storefront class="s-admin-nav-icon" />
            Stores
        </a>
        <a href="{{ route('admin.settings.branding') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.branding') ? 'active' : '' }}">
            <flux:icon.paint-brush class="s-admin-nav-icon" />
            Branding
        </a>
        <a href="{{ route('admin.settings.modules') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.modules') ? 'active' : '' }}">
            <flux:icon.squares-2x2 class="s-admin-nav-icon" />
            Modules
        </a>
    @endif

    {{-- Users & Security --}}
    @if($group === 'users')
        <div class="s-admin-sidebar-title">Users & Security</div>
        <a href="{{ route('admin.settings.users') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.users') || request()->routeIs('admin.settings.users.*') ? 'active' : '' }}">
            <flux:icon.users class="s-admin-nav-icon" />
            Users
        </a>
        <a href="{{ route('admin.settings.roles') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.roles') || request()->routeIs('admin.settings.roles.*') ? 'active' : '' }}">
            <flux:icon.shield-check class="s-admin-nav-icon" />
            Roles
        </a>
        <a href="{{ route('admin.settings.permissions') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.permissions') ? 'active' : '' }}">
            <flux:icon.key class="s-admin-nav-icon" />
            Permissions Reference
        </a>
        <a href="{{ route('admin.settings.security') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.security') ? 'active' : '' }}">
            <flux:icon.lock-closed class="s-admin-nav-icon" />
            Security
        </a>
        <a href="{{ route('admin.settings.api') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.api') ? 'active' : '' }}">
            <flux:icon.code-bracket class="s-admin-nav-icon" />
            API Tokens
        </a>
    @endif

    {{-- Preferences --}}
    @if($group === 'preferences')
        <div class="s-admin-sidebar-title">Preferences</div>
        <a href="{{ route('admin.settings.preferences') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.preferences') ? 'active' : '' }}">
            <flux:icon.adjustments-horizontal class="s-admin-nav-icon" />
            General
        </a>
        <a href="{{ route('admin.settings.email') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.email') ? 'active' : '' }}">
            <flux:icon.envelope class="s-admin-nav-icon" />
            Email
        </a>
        <a href="{{ route('admin.settings.email-templates') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.email-templates') || request()->routeIs('admin.settings.email-templates.*') ? 'active' : '' }}">
            <flux:icon.document-text class="s-admin-nav-icon" />
            Email Templates
        </a>
        <a href="{{ route('admin.settings.notifications') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.notifications') ? 'active' : '' }}">
            <flux:icon.bell class="s-admin-nav-icon" />
            Notifications
        </a>
        <a href="{{ route('admin.settings.scheduling') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.scheduling') ? 'active' : '' }}">
            <flux:icon.calendar-days class="s-admin-nav-icon" />
            Scheduling
        </a>
    @endif

    {{-- System --}}
    @if($group === 'system')
        <div class="s-admin-sidebar-title">System</div>
        <a href="{{ route('admin.settings.action-log') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.action-log') ? 'active' : '' }}">
            <flux:icon.clock class="s-admin-nav-icon" />
            Action Log
        </a>
        <a href="{{ route('admin.settings.system-health') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.system-health') ? 'active' : '' }}">
            <flux:icon.server-stack class="s-admin-nav-icon" />
            System Health
        </a>
        @can('owner')
            <a href="{{ route('admin.settings.infrastructure') }}" wire:navigate
               class="s-admin-nav-item {{ request()->routeIs('admin.settings.infrastructure') ? 'active' : '' }}">
                <flux:icon.wrench-screwdriver class="s-admin-nav-icon" />
                Infrastructure
            </a>
        @endcan
        <a href="{{ route('admin.settings.seeders') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.seeders') ? 'active' : '' }}">
            <flux:icon.circle-stack class="s-admin-nav-icon" />
            Database Seeders
        </a>
        <a href="{{ route('admin.settings.webhooks') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.webhooks') ? 'active' : '' }}">
            <flux:icon.signal class="s-admin-nav-icon" />
            Webhooks
        </a>
    @endif
</nav>
