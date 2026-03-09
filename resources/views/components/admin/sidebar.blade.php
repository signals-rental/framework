@props(['group' => 'settings'])

<nav class="s-admin-sidebar">
    <div class="s-admin-sidebar-title">{{ ucfirst($group) }}</div>

    @if($group === 'settings')
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
</nav>
