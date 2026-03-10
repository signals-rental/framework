<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="flex h-screen flex-col overflow-hidden bg-[var(--content-bg)] text-[13px] leading-normal text-[var(--text-primary)] antialiased"
          x-data="{ mobileNav: false }">

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
                {{ settings('company.name', 'Signals') }}
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
                                <a href="{{ route('admin.settings.company') }}" class="mega-item" wire:navigate>
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
                <a class="sidebar-item {{ request()->is('admin/settings*') ? 'active' : '' }}" href="{{ route('admin.settings.company') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.cog-6-tooth class="!size-[15px]" /> Settings
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
                <div class="sidebar-group-label">Platform</div>
                <a class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.squares-2x2 class="!size-[15px]" /> Dashboard
                </a>

                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                <div class="sidebar-group-label">Operations</div>
                <a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.queue-list class="!size-[15px]" /> Opportunities</a>
                <a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.cube class="!size-[15px]" /> Products</a>
                <a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.archive-box class="!size-[15px]" /> Stock</a>
                <a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.document-text class="!size-[15px]" /> Invoices</a>
                <a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.calendar-days class="!size-[15px]" /> Schedule</a>
                <a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.truck class="!size-[15px]" /> Transport</a>

                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                <div class="sidebar-group-label">CRM</div>
                <a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.user-group class="!size-[15px]" /> Members</a>
                <a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.calendar-days class="!size-[15px]" /> Activities</a>
                <a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.folder class="!size-[15px]" /> Projects</a>

                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                <div class="sidebar-group-label">Insights</div>
                <a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.chart-bar class="!size-[15px]" /> Reports</a>

                <div class="flex-1"></div>
                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                @if(auth()->user()?->hasAdminAccess())
                    <a class="sidebar-item" href="{{ route('admin.settings.company') }}" wire:navigate x-on:click="mobileNav = false">
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
            <aside class="app-sidebar hidden lg:flex">
                @if(request()->is('admin*'))
                    {{-- Admin sidebar --}}
                    <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate>
                        <flux:icon.arrow-left class="!size-[15px]" />
                        Back to app
                    </a>

                    <div class="mx-5 my-2 h-px bg-[var(--sidebar-border)]"></div>
                    <div class="sidebar-group-label">Admin</div>

                    <a class="sidebar-item {{ request()->is('admin/settings*') ? 'active' : '' }}" href="{{ route('admin.settings.company') }}" wire:navigate>
                        <flux:icon.cog-6-tooth class="!size-[15px]" />
                        Settings
                    </a>
                @elseif(request()->routeIs('settings.*'))
                    {{-- Settings sidebar --}}
                    <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate>
                        <flux:icon.arrow-left class="!size-[15px]" />
                        Back to app
                    </a>

                    <div class="mx-5 my-2 h-px bg-[var(--sidebar-border)]"></div>
                    <div class="sidebar-group-label">Settings</div>

                    <a class="sidebar-item {{ request()->routeIs('settings.profile') ? 'active' : '' }}" href="{{ route('settings.profile') }}" wire:navigate>
                        <flux:icon.user class="!size-[15px]" />
                        Profile
                    </a>

                    <a class="sidebar-item {{ request()->routeIs('settings.password') ? 'active' : '' }}" href="{{ route('settings.password') }}" wire:navigate>
                        <flux:icon.key class="!size-[15px]" />
                        Password
                    </a>

                    <a class="sidebar-item {{ request()->routeIs('settings.appearance') ? 'active' : '' }}" href="{{ route('settings.appearance') }}" wire:navigate>
                        <flux:icon.swatch class="!size-[15px]" />
                        Appearance
                    </a>
                @else
                    {{-- Normal sidebar --}}
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

                    <div class="flex-1"></div>
                    @if(auth()->user()?->hasAdminAccess())
                        <a class="sidebar-item" href="{{ route('admin.settings.company') }}" wire:navigate>
                            <flux:icon.wrench-screwdriver class="!size-[15px]" />
                            Admin
                        </a>
                    @endif

                    <div class="h-3 shrink-0"></div>
                @endif
            </aside>

            {{-- Main content area --}}
            <main class="app-main">
                {{ $slot }}
            </main>
        </div>

        @fluxScripts
    </body>
</html>
