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

        {{-- Recent Opportunities Table --}}
        <div class="data-table-wrap mb-8">
            <div class="table-header">
                <span class="table-title">Recent Opportunities</span>
                <a class="table-action" href="#">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Subject</th>
                            <th>Member</th>
                            <th>Status</th>
                            <th class="text-right">Value</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><a class="cell-ref" href="#">OPP-001</a></td>
                            <td>Summer Festival 2026 &mdash; Stage &amp; PA</td>
                            <td>Greenfield Events Ltd</td>
                            <td><span class="badge badge-green">Confirmed</span></td>
                            <td class="cell-amount">&pound;12,450.00</td>
                            <td>18 Feb 2026</td>
                        </tr>
                        <tr>
                            <td><a class="cell-ref" href="#">OPP-002</a></td>
                            <td>Corporate Conference &mdash; AV Package</td>
                            <td>Nexus Holdings</td>
                            <td><span class="badge badge-blue">Quotation</span></td>
                            <td class="cell-amount">&pound;3,800.00</td>
                            <td>16 Feb 2026</td>
                        </tr>
                        <tr>
                            <td><a class="cell-ref" href="#">OPP-003</a></td>
                            <td>Wedding Reception &mdash; Lighting Rig</td>
                            <td>Sarah Mitchell</td>
                            <td><span class="badge badge-amber">Draft</span></td>
                            <td class="cell-amount">&pound;1,250.00</td>
                            <td>14 Feb 2026</td>
                        </tr>
                        <tr>
                            <td><a class="cell-ref" href="#">OPP-004</a></td>
                            <td>Warehouse Build &amp; Strike</td>
                            <td>Apex Productions</td>
                            <td><span class="badge badge-blue">Quotation</span></td>
                            <td class="cell-amount">&pound;28,750.00</td>
                            <td>12 Feb 2026</td>
                        </tr>
                        <tr>
                            <td><a class="cell-ref" href="#">OPP-005</a></td>
                            <td>Theatre Sound Dry Hire</td>
                            <td>City Arts Trust</td>
                            <td><span class="badge badge-green">Confirmed</span></td>
                            <td class="cell-amount">&pound;6,100.00</td>
                            <td>10 Feb 2026</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Quick Actions --}}
        <h2 class="section-heading">Quick Actions</h2>
        <div class="quick-actions">
            <a class="quick-action" href="#">
                <flux:icon.plus class="quick-action-icon" />
                New Opportunity
            </a>
            <a class="quick-action" href="#">
                <flux:icon.user-plus class="quick-action-icon" />
                Add Member
            </a>
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
