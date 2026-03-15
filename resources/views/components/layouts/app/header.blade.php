@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="flex h-screen flex-col overflow-hidden bg-[var(--content-bg)] text-[13px] leading-normal text-[var(--text-primary)] antialiased"
          x-data="{ mobileNav: false, sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false', sidebarReady: false }"
          x-init="$nextTick(() => sidebarReady = true); $watch('sidebarOpen', v => localStorage.setItem('sidebarOpen', v))">

        {{-- ============================================================ --}}
        {{--  TOP HEADER (always navy)                                     --}}
        {{-- ============================================================ --}}
        <header class="header">
            {{-- Mobile hamburger --}}
            <button class="mr-3 shrink-0 text-[var(--grey-light)] hover:text-white lg:hidden"
                    x-on:click="mobileNav = true"
                    aria-label="{{ __('Toggle sidebar') }}">
                <flux:icon.bars-2 class="!size-5" />
            </button>

            {{-- Brand: customer company name --}}
            <a href="{{ route('dashboard') }}" class="header-brand" wire:navigate>
                <span class="flex h-6 w-6 shrink-0 items-center justify-center bg-[var(--blue)] text-[9px] font-bold text-white">{{ strtoupper(mb_substr(settings('company.name', 'Signals'), 0, 1)) }}</span>
                {{ settings('company.name', 'Signals') }}
            </a>

            {{-- Primary module navigation (desktop) --}}
            <nav class="mr-auto hidden h-full items-center gap-0 lg:flex">
                <a href="{{ route('dashboard') }}"
                   class="header-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                   wire:navigate>
                    Dashboard
                </a>

                {{-- CRM mega dropdown --}}
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item {{ request()->routeIs('members.*') ? 'active' : '' }}" type="button">
                        CRM
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown">
                        <div class="grid grid-cols-2 gap-x-8 gap-y-5">
                            {{-- Column 1: People & Places --}}
                            <div>
                                <div class="mega-group-label">People &amp; Places</div>
                                <a href="{{ route('members.index') }}" class="mega-item" wire:navigate>
                                    <flux:icon.user-group class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Members</span>
                                        <span class="mega-item-desc">All contacts, companies &amp; venues</span>
                                    </div>
                                </a>
                                <a href="{{ route('members.index', ['type' => 'organisation']) }}" class="mega-item" wire:navigate>
                                    <flux:icon.building-office-2 class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Organisations</span>
                                        <span class="mega-item-desc">Companies &amp; businesses</span>
                                    </div>
                                </a>
                                <a href="{{ route('members.index', ['type' => 'venue']) }}" class="mega-item" wire:navigate>
                                    <flux:icon.map-pin class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Venues</span>
                                        <span class="mega-item-desc">Venues &amp; locations</span>
                                    </div>
                                </a>
                                <a href="{{ route('members.index', ['type' => 'contact']) }}" class="mega-item" wire:navigate>
                                    <flux:icon.user class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Contacts</span>
                                        <span class="mega-item-desc">Individual people</span>
                                    </div>
                                </a>
                            </div>
                            {{-- Column 2: Engagement --}}
                            <div>
                                <div class="mega-group-label">Engagement</div>
                                <a href="#" class="mega-item">
                                    <flux:icon.calendar-days class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Activities</span>
                                        <span class="mega-item-desc">Tasks, calls &amp; follow-ups</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.folder class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Projects</span>
                                        <span class="mega-item-desc">Project management</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            {{-- Header actions (right side) --}}
            <div class="header-actions">
                {{-- Search --}}
                <div class="relative hidden lg:block">
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--grey)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6.5" cy="6.5" r="5"/><line x1="10" y1="10" x2="15" y2="15"/></svg>
                    <input type="text"
                           class="h-[30px] w-[200px] border border-[var(--navy-light)] bg-[var(--navy-mid)] pl-[30px] pr-2.5 font-sans text-[11px] text-[#e2e8f0] outline-none transition-colors placeholder:text-[var(--grey)] focus:border-[var(--blue)] focus:bg-[var(--navy)]"
                           placeholder="Search orders, members...">
                </div>

                @auth
                {{-- Notifications --}}
                <button class="header-icon-btn" title="Notifications" aria-label="Notifications">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6a4 4 0 0 1 8 0c0 3 1.5 4.5 2 5H2c.5-.5 2-2 2-5z"/><path d="M6 11v.5a2 2 0 0 0 4 0V11"/></svg>
                    <span class="notification-badge">3</span>
                </button>

                {{-- User dropdown --}}
                <div class="nav-dropdown-wrapper">
                    <button class="ml-1 flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center bg-[var(--blue)] text-[10px] font-semibold tracking-wide text-white" type="button">
                        {{ auth()->user()->initials() }}
                    </button>
                    <div class="mega-dropdown mega-dropdown-end">
                        <div class="flex items-center gap-3 pb-4">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center bg-[var(--blue)] text-[11px] font-semibold tracking-wide text-white">
                                {{ auth()->user()->initials() }}
                            </span>
                            <div class="grid flex-1 text-left leading-tight">
                                <span class="truncate text-[12px] font-semibold text-[var(--text-primary)]">{{ auth()->user()->name }}</span>
                                <span class="truncate text-[11px] text-[var(--text-muted)]">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                        <div class="border-t border-[var(--dropdown-border)] pt-3">
                            <a href="{{ route('settings.profile') }}" class="mega-item" wire:navigate>
                                <flux:icon.cog-6-tooth class="mega-item-icon" />
                                <span class="mega-item-label">Settings</span>
                            </a>
                            @if(auth()->user()?->hasAdminAccess())
                                <a href="{{ route('admin.index') }}" class="mega-item" wire:navigate>
                                    <flux:icon.wrench-screwdriver class="mega-item-icon" />
                                    <span class="mega-item-label">Admin</span>
                                </a>
                            @endif
                            <a href="{{ route('docs.index') }}" class="mega-item" target="_blank">
                                <flux:icon.book-open class="mega-item-icon" />
                                <span class="mega-item-label">Documentation</span>
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="mega-item w-full">
                                    <flux:icon.arrow-right-start-on-rectangle class="mega-item-icon" />
                                    <span class="mega-item-label">{{ __('Log Out') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endauth
            </div>
        </header>

        {{-- ============================================================ --}}
        {{--  MOBILE SIDEBAR (Alpine-managed, hidden on desktop)            --}}
        {{-- ============================================================ --}}

        {{-- Backdrop --}}
        <div x-show="mobileNav" x-on:click="mobileNav = false"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[998] bg-black/50 lg:hidden" style="display:none"></div>

        {{-- Panel --}}
        <aside x-show="mobileNav"
               x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
               x-on:keydown.escape.window="mobileNav = false"
               class="dark fixed inset-y-0 left-0 z-[999] flex w-64 flex-col gap-2 overflow-y-auto border-r border-[var(--navy-light)] bg-[var(--navy)] p-4 text-[#e2e8f0] lg:hidden" style="display:none">

            {{-- Close --}}
            <button x-on:click="mobileNav = false" class="mb-2 self-end text-[var(--grey-light)] hover:text-white">
                <flux:icon.x-mark class="!size-5" />
            </button>

            @if(request()->is('admin*'))
                {{-- Admin mobile sidebar --}}
                <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.arrow-left class="!size-[15px]" /> Back to app
                </a>

                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                <div class="sidebar-group-label">Admin</div>
                <a class="sidebar-item {{ request()->routeIs('admin.settings.company') || request()->routeIs('admin.settings.stores*') || request()->routeIs('admin.settings.branding') || request()->routeIs('admin.settings.modules') ? 'active' : '' }}" href="{{ route('admin.settings.company') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.cog-6-tooth class="!size-[15px]" /> Setup
                </a>
                <a class="sidebar-item {{ request()->routeIs('admin.settings.users*') || request()->routeIs('admin.settings.roles*') || request()->routeIs('admin.settings.permissions') || request()->routeIs('admin.settings.security') ? 'active' : '' }}" href="{{ route('admin.settings.users') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.users class="!size-[15px]" /> Users & Security
                </a>
                <a class="sidebar-item {{ request()->routeIs('admin.settings.preferences') || request()->routeIs('admin.settings.email') || request()->routeIs('admin.settings.email-templates*') || request()->routeIs('admin.settings.notifications') || request()->routeIs('admin.settings.scheduling') ? 'active' : '' }}" href="{{ route('admin.settings.preferences') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.adjustments-horizontal class="!size-[15px]" /> Preferences
                </a>
                <a class="sidebar-item {{ request()->routeIs('admin.settings.custom-field-groups*') || request()->routeIs('admin.settings.custom-fields*') || request()->routeIs('admin.settings.list-names*') || request()->routeIs('admin.settings.lists*') || request()->routeIs('admin.settings.list-values*') || request()->routeIs('admin.settings.countries') ? 'active' : '' }}" href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.rectangle-group class="!size-[15px]" /> Data
                </a>
                <a class="sidebar-item {{ request()->routeIs('admin.settings.tax.*') ? 'active' : '' }}" href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.receipt-percent class="!size-[15px]" /> Tax
                </a>
                <a class="sidebar-item {{ request()->routeIs('admin.settings.action-log') || request()->routeIs('admin.settings.system-health') || request()->routeIs('admin.settings.infrastructure') || request()->routeIs('admin.settings.seeders') ? 'active' : '' }}" href="{{ route('admin.settings.action-log') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.server-stack class="!size-[15px]" /> System
                </a>
            @elseif(request()->routeIs('settings.*'))
                {{-- Settings mobile sidebar --}}
                <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.arrow-left class="!size-[15px]" /> Back to app
                </a>

                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                <div class="sidebar-group-label">Settings</div>
                <a class="sidebar-item {{ request()->routeIs('settings.profile') ? 'active' : '' }}" href="{{ route('settings.profile') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.user class="!size-[15px]" /> Profile
                </a>
                <a class="sidebar-item {{ request()->routeIs('settings.password') ? 'active' : '' }}" href="{{ route('settings.password') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.key class="!size-[15px]" /> Password
                </a>
                <a class="sidebar-item {{ request()->routeIs('settings.appearance') ? 'active' : '' }}" href="{{ route('settings.appearance') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.swatch class="!size-[15px]" /> Appearance
                </a>
            @else
                {{-- Normal mobile sidebar --}}
                <div class="flex-1"></div>
                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                @if(auth()->user()?->hasAdminAccess())
                    <a class="sidebar-item" href="{{ route('admin.index') }}" wire:navigate x-on:click="mobileNav = false">
                        <flux:icon.wrench-screwdriver class="!size-[15px]" /> Admin
                    </a>
                @endif
                <a class="sidebar-item" href="{{ route('settings.profile') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.cog-6-tooth class="!size-[15px]" /> Settings
                </a>
            @endif
        </aside>

        {{-- ============================================================ --}}
        {{--  LAYOUT: SIDEBAR + MAIN CONTENT                              --}}
        {{-- ============================================================ --}}
        <div class="app-layout">
            {{-- Desktop sidebar --}}
            @php
                $hasSidebarContent = request()->is('admin*') || request()->routeIs('settings.*');
            @endphp
            <aside class="app-sidebar hidden lg:flex"
                   :class="{
                       'collapsed': !{{ json_encode($hasSidebarContent) }} || !sidebarOpen,
                       'ready': sidebarReady
                   }">
                @if(request()->is('admin*'))
                    {{-- Admin sidebar --}}
                    <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate>
                        <flux:icon.arrow-left class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Back to app</span>
                    </a>

                    <div class="sidebar-divider"></div>
                    <div class="sidebar-group-label">Admin</div>

                    <a class="sidebar-item {{ request()->routeIs('admin.settings.company') || request()->routeIs('admin.settings.stores*') || request()->routeIs('admin.settings.branding') || request()->routeIs('admin.settings.modules') ? 'active' : '' }}" href="{{ route('admin.settings.company') }}" wire:navigate>
                        <flux:icon.cog-6-tooth class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Setup</span>
                    </a>

                    <a class="sidebar-item {{ request()->routeIs('admin.settings.users*') || request()->routeIs('admin.settings.roles*') || request()->routeIs('admin.settings.permissions') || request()->routeIs('admin.settings.security') ? 'active' : '' }}" href="{{ route('admin.settings.users') }}" wire:navigate>
                        <flux:icon.users class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Users & Security</span>
                    </a>

                    <a class="sidebar-item {{ request()->routeIs('admin.settings.preferences') || request()->routeIs('admin.settings.email') || request()->routeIs('admin.settings.email-templates*') || request()->routeIs('admin.settings.notifications') || request()->routeIs('admin.settings.scheduling') ? 'active' : '' }}" href="{{ route('admin.settings.preferences') }}" wire:navigate>
                        <flux:icon.adjustments-horizontal class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Preferences</span>
                    </a>

                    <a class="sidebar-item {{ request()->routeIs('admin.settings.custom-field-groups*') || request()->routeIs('admin.settings.custom-fields*') || request()->routeIs('admin.settings.list-names*') || request()->routeIs('admin.settings.lists*') || request()->routeIs('admin.settings.list-values*') || request()->routeIs('admin.settings.countries') ? 'active' : '' }}" href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate>
                        <flux:icon.rectangle-group class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Data</span>
                    </a>

                    <a class="sidebar-item {{ request()->routeIs('admin.settings.tax.*') ? 'active' : '' }}" href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate>
                        <flux:icon.receipt-percent class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Tax</span>
                    </a>

                    <a class="sidebar-item {{ request()->routeIs('admin.settings.action-log') || request()->routeIs('admin.settings.system-health') || request()->routeIs('admin.settings.infrastructure') || request()->routeIs('admin.settings.seeders') ? 'active' : '' }}" href="{{ route('admin.settings.action-log') }}" wire:navigate>
                        <flux:icon.server-stack class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>System</span>
                    </a>
                @elseif(request()->routeIs('settings.*'))
                    {{-- Settings sidebar --}}
                    <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate>
                        <flux:icon.arrow-left class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Back to app</span>
                    </a>

                    <div class="sidebar-divider"></div>
                    <div class="sidebar-group-label">Settings</div>

                    <a class="sidebar-item {{ request()->routeIs('settings.profile') ? 'active' : '' }}" href="{{ route('settings.profile') }}" wire:navigate>
                        <flux:icon.user class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Profile</span>
                    </a>

                    <a class="sidebar-item {{ request()->routeIs('settings.password') ? 'active' : '' }}" href="{{ route('settings.password') }}" wire:navigate>
                        <flux:icon.key class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Password</span>
                    </a>

                    <a class="sidebar-item {{ request()->routeIs('settings.appearance') ? 'active' : '' }}" href="{{ route('settings.appearance') }}" wire:navigate>
                        <flux:icon.swatch class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Appearance</span>
                    </a>
                @else
                    {{-- Normal sidebar (no nav items) --}}
                @endif

                {{-- Bottom section: Admin link + toggle (consistent across all pages) --}}
                <div class="flex-1"></div>
                @if(auth()->user()?->hasAdminAccess() && !request()->is('admin*'))
                    <a class="sidebar-item" href="{{ route('admin.index') }}" wire:navigate>
                        <flux:icon.wrench-screwdriver class="!size-[15px]" />
                        <span class="sidebar-label" x-show="{{ $hasSidebarContent ? 'sidebarOpen' : 'false' }}" x-cloak>Admin</span>
                    </a>
                @endif

                <button @if($hasSidebarContent) x-on:click="sidebarOpen = !sidebarOpen" @endif
                        class="sidebar-toggle {{ $hasSidebarContent ? '' : 'disabled' }}"
                        @if(! $hasSidebarContent) disabled @endif
                        :aria-label="sidebarOpen ? '{{ __('Collapse sidebar') }}' : '{{ __('Expand sidebar') }}'">
                    <svg class="sidebar-toggle-icon" :class="{ 'rotate-180': !sidebarOpen }" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M10 3L5 8l5 5"/>
                    </svg>
                </button>
            </aside>

            {{-- Main content area --}}
            <main class="app-main">
                <div class="flex-1">
                    {{ $slot }}
                </div>
                @include('components.layouts.app.footer')
            </main>
        </div>

        @fluxScripts
    </body>
</html>
