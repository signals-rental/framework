{{-- Subnav --}}
<nav class="app-subnav">
    <div class="flex h-full items-center gap-0">
        <a href="{{ route('settings.profile') }}" wire:navigate class="subnav-link {{ request()->routeIs('settings.profile') ? 'active' : '' }}">Profile</a>
        <a href="{{ route('settings.password') }}" wire:navigate class="subnav-link {{ request()->routeIs('settings.password') ? 'active' : '' }}">Password</a>
        <a href="{{ route('settings.appearance') }}" wire:navigate class="subnav-link {{ request()->routeIs('settings.appearance') ? 'active' : '' }}">Appearance</a>
    </div>
</nav>

{{-- Content --}}
<div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
    <h1 class="mb-1 font-display text-base font-bold uppercase tracking-[0.04em] text-[var(--text-primary)]">
        Settings
    </h1>
    <p class="mb-6 text-[13px] text-[var(--text-secondary)]">
        Manage your profile and account settings
    </p>

    <flux:heading>{{ $heading ?? '' }}</flux:heading>
    <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

    <div class="mt-5 w-full max-w-lg">
        {{ $slot }}
    </div>
</div>
