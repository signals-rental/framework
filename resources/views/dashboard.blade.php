<x-layouts.app :title="'Dashboard'">
    @if (config('signals.setup_complete'))
        <livewire:dashboard.getting-started-checklist />
    @endif

    {{-- Subnav --}}
    <nav class="app-subnav">
        <div class="flex h-full items-center gap-0">
            <a href="#" class="subnav-link active">Overview</a>
            <a href="#" class="subnav-link">Opportunities</a>
            <a href="#" class="subnav-link">Schedule</a>
            <a href="#" class="subnav-link">Returns</a>
        </div>
        <span class="ml-auto font-mono text-[9px] uppercase tracking-[0.06em] text-[var(--text-muted)]">
            {{ now()->format('M Y') }}
        </span>
    </nav>

    {{-- Content --}}
    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">

        <h1 class="mb-1 font-display text-base font-bold uppercase tracking-[0.04em] text-[var(--text-primary)]">
            Dashboard
        </h1>
        <p class="mb-4 text-[13px] text-[var(--text-secondary)]">
            Overview of your rental operations
        </p>

        {{-- Date pills --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-2"
             x-data="{ active: 'week' }">
            <div class="flex">
                <button class="date-pill" :class="active === 'today' && 'active'" x-on:click="active = 'today'">Today</button>
                <button class="date-pill" :class="active === 'week' && 'active'" x-on:click="active = 'week'">This Week</button>
                <button class="date-pill" :class="active === 'month' && 'active'" x-on:click="active = 'month'">This Month</button>
                <button class="date-pill" :class="active === 'custom' && 'active'" x-on:click="active = 'custom'">Custom</button>
            </div>
            <span class="font-mono text-[9px] tracking-[0.3px] text-[var(--text-muted)]">
                Last updated 2 min ago
            </span>
        </div>

        {{-- KPI Cards --}}
        <div class="mb-8 grid grid-cols-4 gap-4 max-lg:grid-cols-2 max-sm:grid-cols-1">
            <div class="kpi-card">
                <div class="kpi-label">Active Opportunities</div>
                <div class="kpi-value-row">
                    <span class="kpi-value">47</span>
                    <span class="kpi-change up">+12%</span>
                </div>
                <div class="kpi-footer">18 quotations, 29 orders</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">Revenue</div>
                <div class="kpi-value-row">
                    <span class="kpi-value">&pound;84,250</span>
                    <span class="kpi-change up">+8.3%</span>
                </div>
                <div class="kpi-footer">vs &pound;77,800 last month</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">On Hire</div>
                <div class="kpi-value-row">
                    <span class="kpi-value">312</span>
                    <span class="kpi-change down">-3%</span>
                </div>
                <div class="kpi-footer">Across 23 active jobs</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">Overdue</div>
                <div class="kpi-value-row">
                    <span class="kpi-value">&pound;12,680</span>
                    <span class="kpi-change down">+2</span>
                </div>
                <div class="kpi-footer">6 invoices, avg 14 days overdue</div>
            </div>
        </div>

        {{-- Opportunity pipeline widget --}}
        @can('opportunities.access')
            <livewire:dashboard.opportunity-pipeline />
        @endcan

        {{-- Recent Opportunities Table --}}
        @can('opportunities.access')
            <livewire:dashboard.recent-opportunities />
        @endcan

        {{-- Quick Actions --}}
        <h2 class="section-heading">Quick Actions</h2>
        <div class="quick-actions">
            @can('opportunities.create')
            <a class="quick-action" href="{{ route('opportunities.create') }}" wire:navigate>
                <flux:icon.briefcase class="quick-action-icon" />
                New Opportunity
            </a>
            @endcan
            @can('members.create')
            <a class="quick-action" href="{{ route('members.create') }}" wire:navigate>
                <flux:icon.user-plus class="quick-action-icon" />
                Add Member
            </a>
            @endcan
            <a class="quick-action" href="#">
                <flux:icon.document-plus class="quick-action-icon" />
                Create Invoice
            </a>
            <a class="quick-action" href="#">
                <flux:icon.cube class="quick-action-icon" />
                Add Product
            </a>
        </div>

    </div>
</x-layouts.app>
