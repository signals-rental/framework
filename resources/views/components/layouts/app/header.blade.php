<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col overflow-hidden bg-[var(--content-bg)] text-[13px] leading-normal text-[var(--text-primary)] antialiased">

        {{-- ============================================================ --}}
        {{--  TOP HEADER (always navy)                                     --}}
        {{-- ============================================================ --}}
        <header class="header">
            {{-- Mobile hamburger --}}
            <flux:sidebar.toggle class="lg:hidden mr-3 text-[var(--grey-light)]" icon="bars-2" inset="left" />

            {{-- Brand: customer company name --}}
            <a href="{{ route('dashboard') }}" class="header-brand" wire:navigate>
                {{ config('app.name', 'Signals') }}
            </a>

            {{-- Primary module navigation (desktop) --}}
            <nav class="mr-auto hidden h-full items-center gap-0 lg:flex">
                <a href="{{ route('dashboard') }}"
                   class="header-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                   wire:navigate>
                    Dashboard
                </a>

                {{-- Operations mega dropdown --}}
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item" type="button">
                        Operations
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown">
                        <div class="grid grid-cols-2 gap-x-8 gap-y-5">
                            {{-- Column 1: Orders & Sales --}}
                            <div>
                                <div class="mega-group-label">Orders &amp; Sales</div>
                                <a href="#" class="mega-item">
                                    <flux:icon.queue-list class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Opportunities</span>
                                        <span class="mega-item-desc">Quotes, orders &amp; active jobs</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.document-text class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Invoices</span>
                                        <span class="mega-item-desc">Billing, payments &amp; credit notes</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.calendar-days class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Schedule</span>
                                        <span class="mega-item-desc">Delivery, collection &amp; crew calendar</span>
                                    </div>
                                </a>
                            </div>
                            {{-- Column 2: Inventory & Logistics --}}
                            <div>
                                <div class="mega-group-label">Inventory &amp; Logistics</div>
                                <a href="#" class="mega-item">
                                    <flux:icon.cube class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Products</span>
                                        <span class="mega-item-desc">Catalogue, rates &amp; accessories</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.archive-box class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Stock</span>
                                        <span class="mega-item-desc">Availability, check-in &amp; check-out</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.truck class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Transport</span>
                                        <span class="mega-item-desc">Deliveries, collections &amp; routing</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="#" class="header-nav-item">CRM</a>
                <a href="#" class="header-nav-item">Reports</a>
            </nav>

            {{-- Header actions (right side) --}}
            <div class="header-actions">
                {{-- Search --}}
                <div class="relative hidden lg:block">
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--grey)]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6.5" cy="6.5" r="5"/><line x1="10" y1="10" x2="15" y2="15"/></svg>
                    <input type="text"
                           class="h-[30px] w-[200px] border border-[#334155] bg-[#1e293b] pl-[30px] pr-2.5 font-sans text-[11px] text-[#e2e8f0] outline-none transition-colors placeholder:text-[var(--grey)] focus:border-[var(--blue)] focus:bg-[#0f172a]"
                           placeholder="Search orders, members...">
                </div>

                {{-- Notifications --}}
                <button class="header-icon-btn" title="Notifications" aria-label="Notifications">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6a4 4 0 0 1 8 0c0 3 1.5 4.5 2 5H2c.5-.5 2-2 2-5z"/><path d="M6 11v.5a2 2 0 0 0 4 0V11"/></svg>
                    <span class="notification-badge">3</span>
                </button>

                {{-- Theme toggle --}}
                <button class="header-icon-btn"
                        x-data
                        x-on:click="$flux.appearance = $flux.appearance === 'dark' ? 'light' : ($flux.appearance === 'light' ? 'system' : 'dark')"
                        title="Toggle theme"
                        aria-label="Toggle theme">
                    <svg x-show="$flux.appearance === 'dark'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/>
                    </svg>
                    <svg x-show="$flux.appearance !== 'dark'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
                    </svg>
                </button>

                {{-- Settings --}}
                <a href="{{ route('settings.profile') }}"
                   class="header-icon-btn hidden lg:flex {{ request()->routeIs('settings.*') ? '!text-white' : '' }}"
                   wire:navigate
                   title="Settings">
                    <flux:icon.cog-6-tooth class="!size-4" />
                </a>

                {{-- User dropdown --}}
                <flux:dropdown position="top" align="end">
                    <button class="ml-1 flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center bg-[var(--green)] text-[10px] font-semibold tracking-wide text-white">
                        {{ auth()->user()->initials() }}
                    </button>

                    <flux:menu>
                        <flux:menu.radio.group>
                            <div class="p-0 text-sm font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                    <span class="relative flex h-8 w-8 shrink-0 overflow-hidden">
                                        <span class="flex h-full w-full items-center justify-center bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                            {{ auth()->user()->initials() }}
                                        </span>
                                    </span>
                                    <div class="grid flex-1 text-left text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                        <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                    </div>
                                </div>
                            </div>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <flux:menu.radio.group>
                            <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                                {{ __('Log Out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </header>

        {{-- ============================================================ --}}
        {{--  MOBILE SIDEBAR (Flux-managed, hidden on desktop)             --}}
        {{-- ============================================================ --}}
        <flux:sidebar sticky class="lg:hidden bg-[#0f172a]! border-r border-zinc-700">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="ml-1 flex items-center" wire:navigate>
                <span class="font-[var(--font-display)] text-sm font-bold uppercase tracking-[0.06em] text-white">
                    {{ config('app.name', 'Signals') }}
                </span>
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Platform">
                    <flux:navlist.item icon="layout-grid" href="{{ route('dashboard') }}" :current="request()->routeIs('dashboard')" wire:navigate>
                        Dashboard
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Operations" expandable>
                    <flux:navlist.item href="#">Opportunities</flux:navlist.item>
                    <flux:navlist.item href="#">Products</flux:navlist.item>
                    <flux:navlist.item href="#">Stock</flux:navlist.item>
                    <flux:navlist.item href="#">Invoices</flux:navlist.item>
                    <flux:navlist.item href="#">Schedule</flux:navlist.item>
                    <flux:navlist.item href="#">Transport</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="CRM" expandable>
                    <flux:navlist.item href="#">Members</flux:navlist.item>
                    <flux:navlist.item href="#">Activities</flux:navlist.item>
                    <flux:navlist.item href="#">Projects</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Insights">
                    <flux:navlist.item href="#">Reports</flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="cog-6-tooth" href="{{ route('settings.profile') }}" :current="request()->routeIs('settings.*')" wire:navigate>
                    Settings
                </flux:navlist.item>
            </flux:navlist>
        </flux:sidebar>

        {{-- ============================================================ --}}
        {{--  LAYOUT: SIDEBAR + MAIN CONTENT                              --}}
        {{-- ============================================================ --}}
        <div class="app-layout">
            {{-- Desktop sidebar --}}
            <aside class="app-sidebar hidden lg:flex">
                <div class="sidebar-group-label">Platform</div>

                <a class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" wire:navigate>
                    <flux:icon.squares-2x2 class="!size-[15px]" />
                    Dashboard
                </a>

                <a class="sidebar-item" href="#">
                    <flux:icon.bell class="!size-[15px]" />
                    Notifications
                </a>

                <div class="mx-5 my-2 h-px bg-[var(--sidebar-border)]"></div>
                <div class="sidebar-group-label">Operations</div>

                <a class="sidebar-item" href="#">
                    <flux:icon.queue-list class="!size-[15px]" />
                    Opportunities
                </a>

                <a class="sidebar-item" href="#">
                    <flux:icon.cube class="!size-[15px]" />
                    Products
                </a>

                <a class="sidebar-item" href="#">
                    <flux:icon.archive-box class="!size-[15px]" />
                    Stock
                </a>

                <a class="sidebar-item" href="#">
                    <flux:icon.document-text class="!size-[15px]" />
                    Invoices
                </a>

                <div class="mx-5 my-2 h-px bg-[var(--sidebar-border)]"></div>
                <div class="sidebar-group-label">CRM</div>

                <a class="sidebar-item" href="#">
                    <flux:icon.user-group class="!size-[15px]" />
                    Members
                </a>

                <a class="sidebar-item" href="#">
                    <flux:icon.calendar-days class="!size-[15px]" />
                    Activities
                </a>

                <a class="sidebar-item" href="#">
                    <flux:icon.folder class="!size-[15px]" />
                    Projects
                </a>

                {{-- Push settings to bottom --}}
                <div class="flex-1"></div>
                <div class="mx-5 my-2 h-px bg-[var(--sidebar-border)]"></div>

                <a class="sidebar-item {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.profile') }}" wire:navigate>
                    <flux:icon.cog-6-tooth class="!size-[15px]" />
                    Settings
                </a>
                <div class="h-3 shrink-0"></div>
            </aside>

            {{-- Main content area --}}
            <main class="app-main">
                {{ $slot }}
            </main>
        </div>

        @fluxScripts
    </body>
</html>
