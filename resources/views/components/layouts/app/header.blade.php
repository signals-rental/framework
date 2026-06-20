@props(['title' => null])

@php
    use App\Support\ActiveRoute;

    // Stable, request-independent flags for which sidebar to render. Derived from
    // the page's real route (via Livewire's persisted original URL) so they remain
    // correct during Livewire updates, not just on full page loads.
    $isAdminArea = ActiveRoute::is('admin.*');
    $isSettingsArea = ActiveRoute::is('settings.*');
    $hasSidebarContent = $isAdminArea || $isSettingsArea;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="flex h-screen flex-col overflow-hidden bg-[var(--content-bg)] text-[13px] leading-normal text-[var(--text-primary)] antialiased"
          x-data="{ mobileNav: false, sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false', sidebarReady: false, notificationsOpen: false, notificationsReady: false }"
          x-init="$nextTick(() => { sidebarReady = true; notificationsReady = true; }); $watch('sidebarOpen', v => localStorage.setItem('sidebarOpen', v))">

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

            {{-- Primary module navigation (desktop) --}}
            <nav class="mr-auto hidden h-full items-center gap-0 lg:flex">
                <a href="{{ route('dashboard') }}"
                   class="header-nav-item header-nav-item-icon {{ ActiveRoute::is('dashboard') ? 'active' : '' }}"
                   aria-label="{{ __('Dashboard') }}"
                   wire:navigate>
                    <flux:icon.home class="header-nav-icon-glyph" />
                </a>

                {{-- CRM mega dropdown (members and/or activities) --}}
                @canany(['members.access', 'activities.access'])
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item {{ ActiveRoute::is('members.*') ? 'active' : '' }}" type="button">
                        CRM
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown mega-dropdown-cols-2">
                        <div class="mega-grid grid gap-x-8 gap-y-5">
                            {{-- People & Places --}}
                            @can('members.access')
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
                            @endcan
                            {{-- Engagement --}}
                            @can('activities.access')
                            <div>
                                <div class="mega-group-label">Engagement</div>
                                <a href="{{ route('activities.index') }}" class="mega-item {{ ActiveRoute::is('activities.*') ? 'active' : '' }}" wire:navigate>
                                    <flux:icon.chat-bubble-left-right class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Activities</span>
                                        <span class="mega-item-desc">Calls, emails, meetings &amp; tasks</span>
                                    </div>
                                </a>
                            </div>
                            @endcan
                        </div>
                    </div>
                </div>
                @endcanany

                {{-- Job Planning mega dropdown.
                     Opportunities is live and gated on opportunities.access; the
                     remaining links are ungated placeholders that gain their
                     opportunities.* / projects.* gates when those modules ship. --}}
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item {{ ActiveRoute::is('opportunities.*', 'availability.*', 'planner') ? 'active' : '' }}" type="button">
                        Job Planning
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown mega-dropdown-cols-2">
                        <div class="mega-grid grid gap-x-8 gap-y-5">
                            <div>
                                <div class="mega-group-label">Opportunities</div>
                                @can('opportunities.access')
                                <a href="{{ route('opportunities.index') }}" class="mega-item {{ ActiveRoute::is('opportunities.*') ? 'active' : '' }}" wire:navigate>
                                    <flux:icon.briefcase class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Opportunities</span>
                                        <span class="mega-item-desc">Quotes, orders &amp; hires</span>
                                    </div>
                                </a>
                                @endcan
                                @can('opportunities.access')
                                <a href="{{ route('planner') }}" class="mega-item {{ ActiveRoute::is('planner') ? 'active' : '' }}" wire:navigate>
                                    <flux:icon.calendar-days class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Planner</span>
                                        <span class="mega-item-desc">Schedule &amp; resource timeline</span>
                                    </div>
                                </a>
                                @endcan
                                @can('availability.view')
                                <a href="{{ route('availability.index') }}" class="mega-item {{ ActiveRoute::is('availability.*') ? 'active' : '' }}" wire:navigate>
                                    <flux:icon.chart-bar class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Equipment Availability</span>
                                        <span class="mega-item-desc">Stock availability &amp; conflicts</span>
                                    </div>
                                </a>
                                @endcan
                            </div>
                            <div>
                                <div class="mega-group-label">Projects</div>
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

                {{-- Resources mega dropdown --}}
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item {{ ActiveRoute::is('products.*', 'product-groups.*', 'stock-levels.*') ? 'active' : '' }}" type="button">
                        Resources
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown mega-dropdown-cols-2">
                        <div class="mega-grid grid gap-x-8 gap-y-5">
                            @canany(['products.access', 'stock.access'])
                            <div>
                                <div class="mega-group-label">Catalogue</div>
                                @can('products.access')
                                <a href="{{ route('products.index') }}" class="mega-item" wire:navigate>
                                    <flux:icon.cube class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Products</span>
                                        <span class="mega-item-desc">Equipment, services &amp; consumables</span>
                                    </div>
                                </a>
                                <a href="{{ route('product-groups.index') }}" class="mega-item" wire:navigate>
                                    <flux:icon.folder class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Product Groups</span>
                                        <span class="mega-item-desc">Categories &amp; hierarchy</span>
                                    </div>
                                </a>
                                @endcan
                                @can('stock.access')
                                <a href="{{ route('stock-levels.index') }}" class="mega-item" wire:navigate>
                                    <flux:icon.archive-box class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Stock Levels</span>
                                        <span class="mega-item-desc">Inventory &amp; asset tracking</span>
                                    </div>
                                </a>
                                @endcan
                            </div>
                            @endcanany
                            <div>
                                <div class="mega-group-label">Services</div>
                                <a href="#" class="mega-item">
                                    <flux:icon.users class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Crew</span>
                                        <span class="mega-item-desc">People &amp; team scheduling</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.wrench-screwdriver class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Labour</span>
                                        <span class="mega-item-desc">Time &amp; labour charges</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.truck class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Vehicles</span>
                                        <span class="mega-item-desc">Fleet &amp; transport scheduling</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Finance mega dropdown.
                     Placeholder links are ungated for now; they gain
                     invoices.* / payments.* gates when those modules ship. --}}
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item" type="button">
                        Finance
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown mega-dropdown-cols-2">
                        <div class="mega-grid grid gap-x-8 gap-y-5">
                            <div>
                                <div class="mega-group-label">Billing</div>
                                <a href="#" class="mega-item">
                                    <flux:icon.document-currency-pound class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Invoices</span>
                                        <span class="mega-item-desc">Issue &amp; track invoices</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.receipt-refund class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Credit Notes</span>
                                        <span class="mega-item-desc">Refunds &amp; adjustments</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.banknotes class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Payments</span>
                                        <span class="mega-item-desc">Receipts &amp; reconciliation</span>
                                    </div>
                                </a>
                            </div>
                            <div>
                                <div class="mega-group-label">Purchasing</div>
                                <a href="#" class="mega-item">
                                    <flux:icon.shopping-cart class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Purchase Orders</span>
                                        <span class="mega-item-desc">Supplier orders &amp; procurement</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Operations mega dropdown.
                     Placeholder links are ungated for now; they gain
                     inspections.* gates when that module ships. --}}
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item" type="button">
                        Operations
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown mega-dropdown-cols-2">
                        <div class="mega-grid grid gap-x-8 gap-y-5">
                            <div>
                                <div class="mega-group-label">Warehouse</div>
                                <a href="#" class="mega-item">
                                    <flux:icon.arrow-path class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Processing</span>
                                        <span class="mega-item-desc">Pick, pack &amp; dispatch</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.squares-2x2 class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Prep Bays</span>
                                        <span class="mega-item-desc">Staging &amp; preparation areas</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.rectangle-stack class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Shelf</span>
                                        <span class="mega-item-desc">Storage locations &amp; bins</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.qr-code class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Global Check-in</span>
                                        <span class="mega-item-desc">Scan &amp; receive returns</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.cube-transparent class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Containers</span>
                                        <span class="mega-item-desc">Cases, flightcases &amp; kits</span>
                                    </div>
                                </a>
                            </div>
                            <div>
                                <div class="mega-group-label">Inspections</div>
                                <a href="#" class="mega-item">
                                    <flux:icon.clipboard-document-check class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Testing</span>
                                        <span class="mega-item-desc">Inspections &amp; safety checks</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.shield-check class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Certificates</span>
                                        <span class="mega-item-desc">Certifications &amp; compliance</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Reports (top-level placeholder; gains reports.* gate when the module ships). --}}
                <a href="#" class="header-nav-item">
                    Reports
                </a>
            </nav>

            {{-- Header actions (right side) --}}
            <div class="header-actions">
                {{-- Search: compact icon button. Dispatches the same
                     `open-command-palette` event the old search input did;
                     the '/' shortcut is handled globally inside the palette. --}}
                <button class="header-icon-btn" type="button" title="{{ __('Search') }}" aria-label="{{ __('Search') }}" x-on:click="$dispatch('open-command-palette')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>

                @auth
                {{-- Extensions / expandability menu.
                     Placeholder links are ungated for now. --}}
                <div class="nav-dropdown-wrapper">
                    <button class="header-icon-btn" type="button" title="{{ __('Extensions') }}" aria-label="{{ __('Extensions') }}">
                        <flux:icon.puzzle-piece />
                    </button>
                    <div class="mega-dropdown mega-dropdown-end">
                        <div class="grid grid-cols-1 gap-y-1">
                            <a href="#" class="mega-item">
                                <flux:icon.bolt class="mega-item-icon" />
                                <span class="mega-item-label">Workflows</span>
                            </a>
                            <a href="#" class="mega-item">
                                <flux:icon.puzzle-piece class="mega-item-icon" />
                                <span class="mega-item-label">Plugin</span>
                            </a>
                            <a href="#" class="mega-item">
                                <flux:icon.code-bracket class="mega-item-icon" />
                                <span class="mega-item-label">API</span>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Calendar (quick access) --}}
                @can('activities.access')
                <a href="{{ route('calendar.index') }}"
                   class="header-icon-btn {{ ActiveRoute::is('calendar.*') ? 'active' : '' }}"
                   title="{{ __('Calendar') }}" aria-label="{{ __('Calendar') }}"
                   wire:navigate>
                    <flux:icon.calendar-days />
                </a>
                @endcan

                {{-- Notifications --}}
                <button class="header-icon-btn" title="Notifications" aria-label="Notifications" x-on:click="notificationsOpen = !notificationsOpen" :class="{ 'active': notificationsOpen }">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6a4 4 0 0 1 8 0c0 3 1.5 4.5 2 5H2c.5-.5 2-2 2-5z"/><path d="M6 11v.5a2 2 0 0 0 4 0V11"/></svg>
                    <span class="notification-badge">12</span>
                </button>

                {{-- User dropdown --}}
                @php
                    $userAvatarSrc = null;
                    $userThumbPath = auth()->user()?->member?->icon_thumb_url;
                    if ($userThumbPath) {
                        try {
                            $userAvatarSrc = app(\App\Services\FileService::class)->signedUrl($userThumbPath);
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                    $userAvatarInitialsClass = $userAvatarSrc ? '' : 'bg-[var(--blue)] text-[var(--brand-on-accent,#ffffff)]';
                @endphp
                <div class="nav-dropdown-wrapper">
                    <button class="header-avatar-btn" type="button" aria-label="{{ __('User menu') }}">
                        <x-signals.avatar :src="$userAvatarSrc" :initials="auth()->user()->initials()" size="sm" :class="'h-7 w-7 '.$userAvatarInitialsClass" />
                    </button>
                    <div class="mega-dropdown mega-dropdown-end">
                        <div class="flex items-center gap-3 pb-4">
                            <x-signals.avatar :src="$userAvatarSrc" :initials="auth()->user()->initials()" size="md" :class="$userAvatarInitialsClass" />
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

            @if($isAdminArea)
                {{-- Admin mobile sidebar --}}
                <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.arrow-left class="!size-[15px]" /> Back to app
                </a>

                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                <div class="sidebar-group-label">Admin</div>
                <a class="sidebar-item {{ ActiveRoute::is('admin.settings.company', 'admin.settings.stores*', 'admin.settings.branding') ? 'active' : '' }}" href="{{ route('admin.settings.company') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.cog-6-tooth class="!size-[15px]" /> Setup
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('admin.settings.users*', 'admin.settings.roles*', 'admin.settings.permissions', 'admin.settings.security', 'admin.settings.api') ? 'active' : '' }}" href="{{ route('admin.settings.users') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.users class="!size-[15px]" /> Users & Security
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('admin.settings.preferences', 'admin.settings.email', 'admin.settings.email-templates*', 'admin.settings.notifications', 'admin.settings.scheduling', 'admin.settings.integrations') ? 'active' : '' }}" href="{{ route('admin.settings.preferences') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.adjustments-horizontal class="!size-[15px]" /> Preferences
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('admin.settings.custom-field-groups*', 'admin.settings.custom-fields*', 'admin.settings.list-names*', 'admin.settings.lists*', 'admin.settings.list-values*', 'admin.settings.countries') ? 'active' : '' }}" href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.rectangle-group class="!size-[15px]" /> Data
                </a>
                @can('tax-classes.view')
                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.tax.*') ? 'active' : '' }}" href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate x-on:click="mobileNav = false">
                        <flux:icon.receipt-percent class="!size-[15px]" /> Tax
                    </a>
                @endcan
                @can('rates.view')
                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.rate-definitions*') ? 'active' : '' }}" href="{{ route('admin.settings.rate-definitions') }}" wire:navigate x-on:click="mobileNav = false">
                        <flux:icon.calculator class="!size-[15px]" /> Pricing
                    </a>
                @endcan
                <a class="sidebar-item {{ ActiveRoute::is('admin.settings.action-log', 'admin.settings.system-health', 'admin.settings.infrastructure', 'admin.settings.seeders', 'admin.settings.webhooks') ? 'active' : '' }}" href="{{ route('admin.settings.action-log') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.server-stack class="!size-[15px]" /> System
                </a>
            @elseif($isSettingsArea)
                {{-- Settings mobile sidebar --}}
                <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.arrow-left class="!size-[15px]" /> Back to app
                </a>

                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                <div class="sidebar-group-label">Settings</div>
                <a class="sidebar-item {{ ActiveRoute::is('settings.profile') ? 'active' : '' }}" href="{{ route('settings.profile') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.user class="!size-[15px]" /> Profile
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('settings.password') ? 'active' : '' }}" href="{{ route('settings.password') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.key class="!size-[15px]" /> Password
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('settings.appearance') ? 'active' : '' }}" href="{{ route('settings.appearance') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.swatch class="!size-[15px]" /> Appearance
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('settings.calendar') ? 'active' : '' }}" href="{{ route('settings.calendar') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.calendar-days class="!size-[15px]" /> Calendar
                </a>
            @else
                {{-- Normal mobile sidebar --}}
                <a class="sidebar-item {{ ActiveRoute::is('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" wire:navigate x-on:click="mobileNav = false">
                    <flux:icon.home class="!size-[15px]" /> Dashboard
                </a>

                @can('members.access')
                    <div class="sidebar-group-label">People &amp; Places</div>
                    <a class="sidebar-item {{ ActiveRoute::is('members.*') ? 'active' : '' }}" href="{{ route('members.index') }}" wire:navigate x-on:click="mobileNav = false">
                        <flux:icon.user-group class="!size-[15px]" /> Members
                    </a>
                @endcan
                @can('activities.access')
                    <a class="sidebar-item {{ ActiveRoute::is('activities.*') ? 'active' : '' }}" href="{{ route('activities.index') }}" wire:navigate x-on:click="mobileNav = false">
                        <flux:icon.chat-bubble-left-right class="!size-[15px]" /> Activities
                    </a>
                @endcan

                {{-- Job Planning: Opportunities is live (gated on opportunities.access);
                     the rest are ungated placeholders until those modules ship. --}}
                <div class="sidebar-group-label">Job Planning</div>
                @can('opportunities.access')
                    <a class="sidebar-item {{ ActiveRoute::is('opportunities.*') ? 'active' : '' }}" href="{{ route('opportunities.index') }}" wire:navigate x-on:click="mobileNav = false">
                        <flux:icon.briefcase class="!size-[15px]" /> Opportunities
                    </a>
                @endcan
                @can('opportunities.access')
                    <a class="sidebar-item {{ ActiveRoute::is('planner') ? 'active' : '' }}" href="{{ route('planner') }}" wire:navigate x-on:click="mobileNav = false">
                        <flux:icon.calendar-days class="!size-[15px]" /> Planner
                    </a>
                @endcan
                @can('availability.view')
                    <a class="sidebar-item {{ ActiveRoute::is('availability.*') ? 'active' : '' }}" href="{{ route('availability.index') }}" wire:navigate x-on:click="mobileNav = false">
                        <flux:icon.chart-bar class="!size-[15px]" /> Equipment Availability
                    </a>
                @endcan
                <a class="sidebar-item" href="#">
                    <flux:icon.folder class="!size-[15px]" /> Projects
                </a>

                @canany(['products.access', 'stock.access'])
                    <div class="sidebar-group-label">Catalogue</div>
                    @can('products.access')
                        <a class="sidebar-item {{ ActiveRoute::is('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}" wire:navigate x-on:click="mobileNav = false">
                            <flux:icon.cube class="!size-[15px]" /> Products
                        </a>
                        <a class="sidebar-item {{ ActiveRoute::is('product-groups.*') ? 'active' : '' }}" href="{{ route('product-groups.index') }}" wire:navigate x-on:click="mobileNav = false">
                            <flux:icon.folder class="!size-[15px]" /> Product Groups
                        </a>
                    @endcan
                    @can('stock.access')
                        <a class="sidebar-item {{ ActiveRoute::is('stock-levels.*') ? 'active' : '' }}" href="{{ route('stock-levels.index') }}" wire:navigate x-on:click="mobileNav = false">
                            <flux:icon.archive-box class="!size-[15px]" /> Stock Levels
                        </a>
                    @endcan
                @endcanany

                {{-- Services / Finance / Operations placeholders (ungated until modules ship). --}}
                <div class="sidebar-group-label">Services</div>
                <a class="sidebar-item" href="#">
                    <flux:icon.users class="!size-[15px]" /> Crew
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.wrench-screwdriver class="!size-[15px]" /> Labour
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.truck class="!size-[15px]" /> Vehicles
                </a>

                <div class="sidebar-group-label">Finance</div>
                <a class="sidebar-item" href="#">
                    <flux:icon.document-currency-pound class="!size-[15px]" /> Invoices
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.receipt-refund class="!size-[15px]" /> Credit Notes
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.banknotes class="!size-[15px]" /> Payments
                </a>

                <div class="sidebar-group-label">Purchasing</div>
                <a class="sidebar-item" href="#">
                    <flux:icon.shopping-cart class="!size-[15px]" /> Purchase Orders
                </a>

                <div class="sidebar-group-label">Warehouse</div>
                <a class="sidebar-item" href="#">
                    <flux:icon.arrow-path class="!size-[15px]" /> Processing
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.squares-2x2 class="!size-[15px]" /> Prep Bays
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.rectangle-stack class="!size-[15px]" /> Shelf
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.qr-code class="!size-[15px]" /> Global Check-in
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.cube-transparent class="!size-[15px]" /> Containers
                </a>

                <div class="sidebar-group-label">Operations</div>
                <a class="sidebar-item" href="#">
                    <flux:icon.clipboard-document-check class="!size-[15px]" /> Testing
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.shield-check class="!size-[15px]" /> Certificates
                </a>

                <div class="sidebar-group-label">Reports</div>
                <a class="sidebar-item" href="#">
                    <flux:icon.chart-pie class="!size-[15px]" /> Reports
                </a>

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
            <aside class="app-sidebar hidden lg:flex"
                   :class="{
                       'collapsed': !{{ json_encode($hasSidebarContent) }} || !sidebarOpen,
                       'ready': sidebarReady
                   }">
                @if($isAdminArea)
                    {{-- Admin sidebar --}}
                    <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate>
                        <flux:icon.arrow-left class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Back to app</span>
                    </a>

                    <div class="sidebar-divider"></div>
                    <div class="sidebar-group-label">Admin</div>

                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.company', 'admin.settings.stores*', 'admin.settings.branding') ? 'active' : '' }}" href="{{ route('admin.settings.company') }}" wire:navigate>
                        <flux:icon.cog-6-tooth class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Setup</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.users*', 'admin.settings.roles*', 'admin.settings.permissions', 'admin.settings.security', 'admin.settings.api') ? 'active' : '' }}" href="{{ route('admin.settings.users') }}" wire:navigate>
                        <flux:icon.users class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Users & Security</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.preferences', 'admin.settings.email', 'admin.settings.email-templates*', 'admin.settings.notifications', 'admin.settings.scheduling', 'admin.settings.integrations') ? 'active' : '' }}" href="{{ route('admin.settings.preferences') }}" wire:navigate>
                        <flux:icon.adjustments-horizontal class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Preferences</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.custom-field-groups*', 'admin.settings.custom-fields*', 'admin.settings.list-names*', 'admin.settings.lists*', 'admin.settings.list-values*', 'admin.settings.countries') ? 'active' : '' }}" href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate>
                        <flux:icon.rectangle-group class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Data</span>
                    </a>

                    @can('tax-classes.view')
                        <a class="sidebar-item {{ ActiveRoute::is('admin.settings.tax.*') ? 'active' : '' }}" href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate>
                            <flux:icon.receipt-percent class="!size-[15px]" />
                            <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Tax</span>
                        </a>
                    @endcan

                    @can('rates.view')
                        <a class="sidebar-item {{ ActiveRoute::is('admin.settings.rate-definitions*') ? 'active' : '' }}" href="{{ route('admin.settings.rate-definitions') }}" wire:navigate>
                            <flux:icon.calculator class="!size-[15px]" />
                            <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Pricing</span>
                        </a>
                    @endcan

                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.action-log', 'admin.settings.system-health', 'admin.settings.infrastructure', 'admin.settings.seeders', 'admin.settings.webhooks') ? 'active' : '' }}" href="{{ route('admin.settings.action-log') }}" wire:navigate>
                        <flux:icon.server-stack class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>System</span>
                    </a>
                @elseif($isSettingsArea)
                    {{-- Settings sidebar --}}
                    <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate>
                        <flux:icon.arrow-left class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Back to app</span>
                    </a>

                    <div class="sidebar-divider"></div>
                    <div class="sidebar-group-label">Settings</div>

                    <a class="sidebar-item {{ ActiveRoute::is('settings.profile') ? 'active' : '' }}" href="{{ route('settings.profile') }}" wire:navigate>
                        <flux:icon.user class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Profile</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('settings.password') ? 'active' : '' }}" href="{{ route('settings.password') }}" wire:navigate>
                        <flux:icon.key class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Password</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('settings.appearance') ? 'active' : '' }}" href="{{ route('settings.appearance') }}" wire:navigate>
                        <flux:icon.swatch class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Appearance</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('settings.calendar') ? 'active' : '' }}" href="{{ route('settings.calendar') }}" wire:navigate>
                        <flux:icon.calendar-days class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Calendar</span>
                    </a>
                @else
                    {{-- Normal sidebar (no nav items) --}}
                @endif

                {{-- Bottom section: Admin link + toggle (consistent across all pages) --}}
                <div class="flex-1"></div>
                @if(auth()->user()?->hasAdminAccess() && ! $isAdminArea)
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

            {{-- Notifications pane (right side) --}}
            <aside class="app-notifications-pane"
                   :class="{ 'open': notificationsOpen, 'ready': notificationsReady }">
                <div class="app-notifications-header">
                    <span class="app-notifications-title">Notifications</span>
                    <button class="text-[10px] text-[var(--text-muted)] hover:text-[var(--text-primary)]" style="font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em;">Mark all read</button>
                </div>
                <div class="app-notifications-body">
                    <x-signals.event-row name="OpportunityCreated" actor="Sarah Chen" time="2 min ago" border="create">
                        <x-slot:payload>OPP-2026-0891 "Summer Festival Main Stage" created — £12,450.00</x-slot:payload>
                    </x-signals.event-row>
                    <x-signals.event-row name="PaymentReceived" actor="System" time="15 min ago" border="create">
                        <x-slot:payload>INV-2026-1284 paid in full — £12,450.00</x-slot:payload>
                    </x-signals.event-row>
                    <x-signals.event-row name="StatusChanged" actor="System" time="28 min ago" border="status">
                        <x-slot:payload><span class="s-es-payload-key">from:</span> Quotation <span class="s-es-payload-key">to:</span> Confirmed</x-slot:payload>
                    </x-signals.event-row>
                    <x-signals.event-row name="QuoteExpiring" actor="System" time="1 hour ago" border="status">
                        <x-slot:payload>QUO-2026-0456 expires in 48 hours — £32,000.00</x-slot:payload>
                    </x-signals.event-row>
                    <x-signals.event-row name="ItemAdded" actor="Mike Ross" time="1 hour ago" border="update">
                        <x-slot:payload>OPP-2026-0891: Added 6x JBL EON615 Speaker</x-slot:payload>
                    </x-signals.event-row>
                    <x-signals.event-row name="StockAlert" actor="System" time="2 hours ago" border="status">
                        <x-slot:payload>Martin D-28 Guitar below minimum level (2 remaining)</x-slot:payload>
                    </x-signals.event-row>
                    <x-signals.event-row name="InvoiceIssued" actor="Sarah Chen" time="3 hours ago" border="create">
                        <x-slot:payload>INV-2026-1285 issued for OPP-2026-0834 — £8,200.00</x-slot:payload>
                    </x-signals.event-row>
                    <x-signals.event-row name="MemberCreated" actor="Jane Cooper" time="4 hours ago" border="create">
                        <x-slot:payload>New organisation: Festival Hire Co</x-slot:payload>
                    </x-signals.event-row>
                    <x-signals.event-row name="OrderDispatched" actor="Warehouse" time="5 hours ago" border="create">
                        <x-slot:payload>OPP-2026-0834 dispatched — 18 items to ExCeL London</x-slot:payload>
                    </x-signals.event-row>
                    <x-signals.event-row name="DamageReported" actor="Dave S" time="6 hours ago" border="status">
                        <x-slot:payload>JBL VTX speaker cabinet — dent on rear panel. Quarantined.</x-slot:payload>
                    </x-signals.event-row>
                    <x-signals.event-row name="SubHireConfirmed" actor="System" time="Yesterday" border="update">
                        <x-slot:payload>20x moving head lights confirmed by Stage Solutions Ltd</x-slot:payload>
                    </x-signals.event-row>
                    <x-signals.event-row name="CreditCheckPassed" actor="System" time="Yesterday" border="create">
                        <x-slot:payload>ExCeL Events Ltd approved for £75,000 credit limit</x-slot:payload>
                    </x-signals.event-row>
                </div>
                <button class="notifications-toggle" x-on:click="notificationsOpen = !notificationsOpen"
                        :aria-label="notificationsOpen ? 'Collapse notifications' : 'Expand notifications'">
                    <svg class="sidebar-toggle-icon" :class="{ 'rotate-180': notificationsOpen }" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M6 3l5 5-5 5"/>
                    </svg>
                </button>
            </aside>
        </div>

        <x-signals.command-palette x-on:open-command-palette.window="toggle()" />

        @fluxScripts
    </body>
</html>
