@props(['group' => 'settings'])

<nav class="s-admin-sidebar">
    <div class="s-admin-sidebar-title">{{ ucfirst($group) }}</div>

    @if($group === 'settings')
        {{-- Account --}}
        <div class="s-admin-nav-group-label">Account</div>
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

        {{-- Users & Security --}}
        <div class="s-admin-nav-group-label">Users & Security</div>
        <a href="{{ route('admin.settings.users') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.users*') ? 'active' : '' }}">
            <flux:icon.users class="s-admin-nav-icon" />
            Users
        </a>
        <a href="{{ route('admin.settings.roles') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.roles*') ? 'active' : '' }}">
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

        {{-- Preferences --}}
        <div class="s-admin-nav-group-label">Preferences</div>
        <a href="{{ route('admin.settings.email') }}" wire:navigate
           class="s-admin-nav-item {{ request()->routeIs('admin.settings.email') ? 'active' : '' }}">
            <flux:icon.envelope class="s-admin-nav-icon" />
            Email
        </a>
    @endif
</nav>
