<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Availability Matrix')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  AVAILABILITY MATRIX TOKENS                                       */
  /* ================================================================ */

  :root {
    --av-hover: rgba(0, 0, 0, 0.03);
    --av-subtle: var(--base);
    --av-border-sub: #ecedf1;
    --av-faint: #b0b4c3;
    --av-accent-light: rgba(5, 150, 105, 0.04);
    --av-green-bg: #ecfdf3; --av-green-bdr: #bbf7d0;
    --av-amber-bg: #fffbeb; --av-amber-bdr: #fde68a;
    --av-red-bg: #fef2f2; --av-red-bdr: #fecaca;
    --av-blue-bg: #eff6ff; --av-blue-bdr: #bfdbfe;
    --av-shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.1);
  }

  .dark {
    --av-hover: rgba(255, 255, 255, 0.05);
    --av-subtle: rgba(255, 255, 255, 0.04);
    --av-border-sub: #283040;
    --av-faint: #475569;
    --av-accent-light: rgba(5, 150, 105, 0.06);
    --av-green-bg: rgba(22, 163, 74, 0.15); --av-green-bdr: rgba(22, 163, 74, 0.3);
    --av-amber-bg: rgba(217, 119, 6, 0.15); --av-amber-bdr: rgba(217, 119, 6, 0.3);
    --av-red-bg: rgba(220, 38, 38, 0.15); --av-red-bdr: rgba(220, 38, 38, 0.3);
    --av-blue-bg: rgba(37, 99, 235, 0.15); --av-blue-bdr: rgba(37, 99, 235, 0.3);
    --av-shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.4);
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */

  .av-page {
    display: flex;
    flex-direction: column;
    flex: 1 1 0;
    height: 0;
    min-height: 0;
    overflow: hidden;
    font-family: var(--font-mono);
    font-size: 12px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
  }

  /* ================================================================ */
  /*  PAGE HEADER                                                      */
  /* ================================================================ */

  .av-ph {
    padding: 18px 24px 0;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-shrink: 0;
  }

  .av-bc {
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .av-bc a { color: var(--text-muted); text-decoration: none; }
  .av-bc a:hover { color: var(--text-secondary); }

  .av-ph-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .av-ph-meta {
    font-size: 11px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 3px;
  }

  .av-ph-meta span { display: flex; align-items: center; gap: 4px; }
  .av-ph-act { display: flex; gap: 8px; align-items: center; }

  /* ================================================================ */
  /*  BADGES & BUTTONS                                                 */
  /* ================================================================ */

  .av-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 3px 10px;
  }

  .av-badge-g { background: var(--av-green-bg); color: #16a34a; border: 1px solid var(--av-green-bdr); }
  .av-badge-a { background: var(--av-amber-bg); color: var(--amber); border: 1px solid var(--av-amber-bdr); }
  .av-badge-r { background: var(--av-red-bg); color: var(--red); border: 1px solid var(--av-red-bdr); }
  .av-bdot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

  .av-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    border: 1px solid var(--card-border);
    background: var(--card-bg);
    color: var(--text-secondary);
    transition: all 0.15s;
    white-space: nowrap;
    box-shadow: var(--shadow-card);
  }

  .av-btn:hover { background: var(--av-hover); color: var(--text-primary); border-color: var(--navy-light); }
  .av-btn svg { width: 14px; height: 14px; }

  /* ================================================================ */
  /*  TOOLBAR                                                          */
  /* ================================================================ */

  .av-tb {
    display: flex;
    align-items: center;
    padding: 10px 24px;
    gap: 8px;
    flex-shrink: 0;
    border-bottom: 1px solid var(--av-border-sub);
  }

  .av-fc {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border: 1px solid var(--card-border);
    color: var(--text-secondary);
    cursor: pointer;
    background: var(--card-bg);
    transition: all 0.15s;
  }

  .av-fc:hover { border-color: var(--navy-light); color: var(--text-primary); }
  .av-fc.on { border-color: var(--green); color: var(--green); background: var(--green-muted); }
  .av-fc svg { width: 12px; height: 12px; }

  .av-tbsep { width: 1px; height: 20px; background: var(--card-border); margin: 0 4px; }
  .av-tbr { margin-left: auto; display: flex; align-items: center; gap: 8px; }

  .av-search {
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid var(--card-border);
    padding: 4px 10px;
    background: var(--card-bg);
    min-width: 180px;
  }

  .av-search svg { width: 13px; height: 13px; color: var(--av-faint); flex-shrink: 0; }

  .av-search input {
    background: none;
    border: none;
    color: var(--text-primary);
    font-family: var(--font-mono);
    font-size: 11px;
    outline: none;
    width: 100%;
  }

  .av-search input::placeholder { color: var(--av-faint); }

  /* ================================================================ */
  /*  LEGEND                                                           */
  /* ================================================================ */

  .av-key {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 8px 24px;
    font-size: 9px;
    font-family: var(--font-mono);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--av-faint);
    flex-shrink: 0;
    border-top: 1px solid var(--card-border);
    box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.10);
  }

  .av-page ~ .app-footer {
    padding: 6px 24px;
  }

  .av-page ~ .app-footer .footer-mark { font-size: 8px; }
  .av-page ~ .app-footer .footer-annotation { font-size: 9px; }

  .av-key-label {
    font-family: var(--font-display);
    font-weight: 600;
    font-size: 9px;
    color: var(--text-muted);
    margin-right: 4px;
  }

  .av-key-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  .av-key-swatch {
    width: 16px;
    height: 10px;
  }

  .av-key-ok { background: rgba(22, 163, 74, 0.12); border: 1px solid rgba(22, 163, 74, 0.25); }
  .av-key-lo { background: rgba(217, 119, 6, 0.12); border: 1px solid rgba(217, 119, 6, 0.25); }
  .av-key-out { background: rgba(220, 38, 38, 0.12); border: 1px solid rgba(220, 38, 38, 0.25); }
  .av-key-na { background: var(--av-subtle); border: 1px solid var(--av-border-sub); }

  /* ================================================================ */
  /*  MATRIX TABLE                                                     */
  /* ================================================================ */

  .av-mw {
    flex: 1 1 0;
    height: 0;
    min-height: 0;
    overflow: auto;
    padding: 0 24px 16px;
  }

  .av-mt {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    overflow: hidden;
    box-shadow: var(--shadow-card);
    table-layout: fixed;
  }

  .av-mt thead { position: sticky; top: 0; z-index: 10; }

  .av-mt th {
    background: var(--av-subtle);
    padding: 0;
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    text-align: center;
    border-bottom: 1px solid var(--card-border);
    border-right: 1px solid var(--av-border-sub);
    white-space: nowrap;
    user-select: none;
  }

  .av-mt th:last-child { border-right: none; }

  .av-th-prod {
    text-align: left !important;
    padding: 6px 12px !important;
    min-width: 240px;
    width: 240px;
    position: sticky;
    left: 0;
    z-index: 11;
    background: var(--av-subtle) !important;
  }

  .av-th-stock {
    width: 52px;
    min-width: 52px;
  }

  .av-th-day {
    width: 38px;
    min-width: 38px;
    padding: 4px 2px !important;
  }

  .av-th-day-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    line-height: 1.2;
  }

  .av-th-dow { font-size: 8px; color: var(--av-faint); }
  .av-th-dom { font-size: 10px; font-weight: 600; color: var(--text-secondary); }
  .av-th-month { font-size: 7px; color: var(--av-faint); }

  .av-th-today .av-th-dom {
    color: var(--green);
    font-weight: 700;
  }

  .av-th-weekend {
    background: var(--av-hover) !important;
  }

  /* ================================================================ */
  /*  TABLE BODY                                                       */
  /* ================================================================ */

  .av-mt tbody tr { transition: background 0.08s; }
  .av-mt tbody tr:hover { background: var(--av-accent-light); }

  .av-mt td {
    padding: 0;
    border-bottom: 1px solid var(--av-border-sub);
    border-right: 1px solid var(--av-border-sub);
    height: 36px;
    vertical-align: middle;
    text-align: center;
  }

  .av-mt td:last-child { border-right: none; }

  /* Product name column */
  .av-td-prod {
    text-align: left !important;
    padding: 6px 12px !important;
    position: sticky;
    left: 0;
    z-index: 5;
    background: var(--card-bg);
  }

  .av-mt tbody tr:hover .av-td-prod { background: rgba(5, 150, 105, 0.03); }

  .av-prod {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .av-prod-icon {
    width: 26px;
    height: 26px;
    background: var(--av-subtle);
    border: 1px solid var(--av-border-sub);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
  }

  .av-prod-name {
    font-weight: 500;
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-primary);
  }

  .av-prod-sku {
    font-size: 9px;
    color: var(--text-muted);
  }

  /* Stock column */
  .av-td-stock {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    background: var(--av-subtle);
  }

  /* Group header row */
  .av-group-row td {
    background: var(--av-subtle) !important;
    border-bottom: 1px solid var(--card-border);
    height: 30px;
  }

  .av-group-label {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 12px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    cursor: pointer;
  }

  .av-group-label svg { width: 10px; height: 10px; transition: transform 0.15s; }
  .av-group-label.collapsed svg { transform: rotate(-90deg); }

  .av-group-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .av-group-count {
    font-family: var(--font-mono);
    font-size: 9px;
    color: var(--av-faint);
    margin-left: 2px;
  }

  /* ================================================================ */
  /*  AVAILABILITY CELLS                                               */
  /* ================================================================ */

  .av-cell {
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 500;
    cursor: default;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 35px;
    transition: all 0.1s;
  }

  .av-cell:hover { filter: brightness(0.95); }

  .av-ok {
    color: #16a34a;
    background: rgba(22, 163, 74, 0.06);
  }

  .av-lo {
    color: var(--amber);
    background: rgba(217, 119, 6, 0.06);
    font-weight: 600;
  }

  .av-out {
    color: var(--red);
    background: rgba(220, 38, 38, 0.08);
    font-weight: 700;
  }

  .av-full {
    color: var(--text-muted);
    background: transparent;
  }

  .av-weekend-cell {
    background-color: rgba(0, 0, 0, 0.015);
  }

  .dark .av-weekend-cell {
    background-color: rgba(255, 255, 255, 0.015);
  }

  /* Shortage indicator */
  .av-shortage {
    position: relative;
  }

  .av-shortage::after {
    content: '';
    position: absolute;
    top: 2px;
    right: 2px;
    width: 4px;
    height: 4px;
    background: var(--red);
    border-radius: 50%;
  }

  /* ================================================================ */
  /*  TOOLTIP                                                          */
  /* ================================================================ */

  .av-tip {
    position: fixed;
    background: var(--navy);
    color: #e2e8f0;
    padding: 10px 14px;
    font-family: var(--font-mono);
    font-size: 10px;
    line-height: 1.6;
    z-index: 200;
    pointer-events: none;
    box-shadow: var(--av-shadow-lg);
    max-width: 220px;
    opacity: 0;
    transition: opacity 0.12s;
  }

  .av-tip.vis { opacity: 1; }
  .av-tip-title { font-family: var(--font-display); font-weight: 600; font-size: 11px; color: #fff; margin-bottom: 4px; }

  .av-tip-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
  }

  .av-tip-label { color: var(--grey-light); }
  .av-tip-val { font-weight: 500; color: #fff; }
  .av-tip-val.av-tip-neg { color: #fca5a5; }
  .av-tip-val.av-tip-warn { color: #fcd34d; }

  .av-tip-sep {
    border: none;
    border-top: 1px solid #334155;
    margin: 5px 0;
  }

  /* ================================================================ */
  /*  SUMMARY BAR                                                      */
  /* ================================================================ */

  .av-summary {
    flex-shrink: 0;
    height: 32px;
    background: var(--card-bg);
    border-top: 1px solid var(--card-border);
    display: flex;
    align-items: center;
    padding: 0 24px;
    gap: 20px;
    font-size: 9px;
    font-family: var(--font-mono);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--av-faint);
  }

  .av-summary span { display: flex; align-items: center; gap: 4px; }
  .av-summary-val { font-weight: 600; color: var(--text-secondary); }
  .av-summary-alert { color: var(--red); font-weight: 600; }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */

  @keyframes av-fadeInUp {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .av-mt tbody tr { animation: av-fadeInUp 0.15s ease both; }
  .av-mt tbody tr:nth-child(1) { animation-delay: 0.02s; }
  .av-mt tbody tr:nth-child(2) { animation-delay: 0.03s; }
  .av-mt tbody tr:nth-child(3) { animation-delay: 0.04s; }
  .av-mt tbody tr:nth-child(4) { animation-delay: 0.05s; }
  .av-mt tbody tr:nth-child(5) { animation-delay: 0.06s; }
  .av-mt tbody tr:nth-child(6) { animation-delay: 0.07s; }
  .av-mt tbody tr:nth-child(7) { animation-delay: 0.08s; }
  .av-mt tbody tr:nth-child(8) { animation-delay: 0.09s; }
  .av-mt tbody tr:nth-child(9) { animation-delay: 0.10s; }
  .av-mt tbody tr:nth-child(10) { animation-delay: 0.11s; }
</style>

<div class="av-page"
     x-data="availabilityMatrix()"
     @mouseleave="hideTooltip()">

  {{-- ============================================================ --}}
  {{--  SUBNAV                                                       --}}
  {{-- ============================================================ --}}
  <nav class="app-subnav">
    <div class="flex h-full items-center gap-0">
      <a href="#" class="subnav-link active">Availability</a>
      <a href="#" class="subnav-link">Stock Levels</a>
      <a href="#" class="subnav-link">Transfers</a>
      <a href="#" class="subnav-link">Quarantine</a>
    </div>
    <span class="ml-auto font-mono text-[9px] uppercase tracking-[0.06em] text-[var(--text-muted)]">
      Daily Resolution
    </span>
  </nav>

  {{-- ============================================================ --}}
  {{--  PAGE HEADER                                                  --}}
  {{-- ============================================================ --}}
  <div class="av-ph">
    <div>
      <div class="av-bc">
        <a href="#">Inventory</a>
        <span style="color:var(--av-faint);font-size:10px">&rsaquo;</span>
        <span style="color:var(--text-primary);font-weight:500">Availability</span>
      </div>
      <div class="av-ph-title">
        Availability Matrix
        <span class="av-badge av-badge-g"><span class="av-bdot"></span> Live</span>
      </div>
      <div class="av-ph-meta">
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          Main Warehouse
        </span>
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <span x-text="dateRangeLabel"></span>
        </span>
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 001 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
          <span x-text="totalProducts + ' products'"></span>
        </span>
      </div>
    </div>
    <div class="av-ph-act">
      <button class="av-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export
      </button>
      <button class="av-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
        Refresh
      </button>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{--  TOOLBAR                                                      --}}
  {{-- ============================================================ --}}
  <div class="av-tb">
    <template x-for="(group, gi) in groups" :key="gi">
      <div class="av-fc"
           :class="{ 'on': activeGroup === group.id }"
           @click="activeGroup = activeGroup === group.id ? 'all' : group.id">
        <span class="av-group-dot" :style="'background:' + group.color"></span>
        <span x-text="group.name"></span>
      </div>
    </template>

    <div class="av-tbsep"></div>

    <div class="av-fc" :class="{ 'on': showShortagesOnly }" @click="showShortagesOnly = !showShortagesOnly">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Shortages Only
    </div>

    <div class="av-tbr">
      <div class="av-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Search products..." x-model="search" />
      </div>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{--  MATRIX                                                       --}}
  {{-- ============================================================ --}}
  <div class="av-mw">
    <table class="av-mt">
      <thead>
        <tr>
          <th class="av-th-prod">
            <div style="display:flex;align-items:center;gap:4px">Product</div>
          </th>
          <th class="av-th-stock">Stock</th>
          <template x-for="(day, di) in days" :key="di">
            <th class="av-th-day"
                :class="{ 'av-th-today': day.isToday, 'av-th-weekend': day.isWeekend }">
              <div class="av-th-day-inner">
                <span class="av-th-dow" x-text="day.dow"></span>
                <span class="av-th-dom" x-text="day.dom"></span>
                <span class="av-th-month" x-show="day.dom === 1 || di === 0" x-text="day.month"></span>
              </div>
            </th>
          </template>
        </tr>
      </thead>

      <tbody>
        <template x-for="(row, ri) in filteredRows" :key="row.id">
          <tr :class="{ 'av-group-row': row.type === 'group' }">
            {{-- Group header --}}
            <template x-if="row.type === 'group'">
              <td :colspan="days.length + 2" style="text-align:left">
                <div class="av-group-label" :class="{ 'collapsed': collapsedGroups[row.id] }" @click="toggleGroup(row.id)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                  <span class="av-group-dot" :style="'background:' + row.color"></span>
                  <span x-text="row.name"></span>
                  <span class="av-group-count" x-text="'(' + row.count + ')'"></span>
                </div>
              </td>
            </template>
            {{-- Product row --}}
            <template x-if="row.type === 'product'">
              <td class="av-td-prod">
                <div class="av-prod">
                  <div class="av-prod-icon" x-text="row.icon"></div>
                  <div>
                    <div class="av-prod-name" x-text="row.name"></div>
                    <div class="av-prod-sku" x-text="row.sku"></div>
                  </div>
                </div>
              </td>
            </template>
            <template x-if="row.type === 'product'">
              <td class="av-td-stock" x-text="row.stock"></td>
            </template>
            <template x-if="row.type === 'product'">
              <template x-for="(day, di) in days" :key="'c' + di">
                <td :class="day.isWeekend ? 'av-weekend-cell' : ''"
                    @mouseenter="showTooltip($event, row, di)"
                    @mouseleave="hideTooltip()">
                  <div class="av-cell"
                       :class="cellClass(row.availability[di], row.stock)">
                    <span x-text="row.availability[di]"></span>
                  </div>
                </td>
              </template>
            </template>
          </tr>
        </template>
      </tbody>
    </table>
  </div>

  {{-- ============================================================ --}}
  {{--  KEY                                                          --}}
  {{-- ============================================================ --}}
  <div class="av-key">
    <span class="av-key-label">Key:</span>
    <span class="av-key-item"><span class="av-key-swatch av-key-ok"></span> Available</span>
    <span class="av-key-item"><span class="av-key-swatch av-key-lo"></span> Low Stock</span>
    <span class="av-key-item"><span class="av-key-swatch av-key-out"></span> Shortage</span>
    <span class="av-key-item"><span class="av-key-swatch av-key-na"></span> Full Stock</span>
    <span style="margin-left:auto">Hover cell for demand breakdown</span>
  </div>

  {{-- ============================================================ --}}
  {{--  TOOLTIP                                                      --}}
  {{-- ============================================================ --}}
  <div class="av-tip"
       :class="{ 'vis': tooltip.show }"
       :style="'top:' + tooltip.y + 'px;left:' + tooltip.x + 'px'">
    <div class="av-tip-title" x-text="tooltip.product"></div>
    <div style="font-size:9px;color:var(--grey-light);margin-bottom:6px" x-text="tooltip.date"></div>
    <hr class="av-tip-sep">
    <div class="av-tip-row">
      <span class="av-tip-label">Total Stock</span>
      <span class="av-tip-val" x-text="tooltip.stock"></span>
    </div>
    <div class="av-tip-row">
      <span class="av-tip-label">Bookings</span>
      <span class="av-tip-val" x-text="tooltip.bookings"></span>
    </div>
    <div class="av-tip-row">
      <span class="av-tip-label">Quarantine</span>
      <span class="av-tip-val" x-text="tooltip.quarantine"></span>
    </div>
    <div class="av-tip-row">
      <span class="av-tip-label">Transfers</span>
      <span class="av-tip-val" x-text="tooltip.transfers"></span>
    </div>
    <hr class="av-tip-sep">
    <div class="av-tip-row" style="font-weight:600">
      <span class="av-tip-label" style="color:#e2e8f0">Available</span>
      <span class="av-tip-val"
            :class="{ 'av-tip-neg': tooltip.available < 0, 'av-tip-warn': tooltip.available > 0 && tooltip.available <= 2 }"
            x-text="tooltip.available"></span>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{--  SUMMARY BAR                                                  --}}
  {{-- ============================================================ --}}
  <div class="av-summary">
    <span>Products: <span class="av-summary-val" x-text="totalProducts"></span></span>
    <span>Total Stock: <span class="av-summary-val" x-text="totalStock"></span></span>
    <span>Shortages: <span class="av-summary-alert" x-text="shortageCount"></span></span>
    <span style="margin-left:auto">
      Updated <span class="av-summary-val">2 min ago</span>
    </span>
  </div>
</div>

<script>
function availabilityMatrix() {
  // Generate 31 days from today
  const today = new Date();
  const days = [];
  const dows = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  for (let i = 0; i < 31; i++) {
    const d = new Date(today);
    d.setDate(d.getDate() + i);
    days.push({
      dow: dows[d.getDay()].toUpperCase(),
      dom: d.getDate(),
      month: months[d.getMonth()].toUpperCase(),
      isToday: i === 0,
      isWeekend: d.getDay() === 0 || d.getDay() === 6,
      full: d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
    });
  }

  const groups = [
    { id: 'audio', name: 'Audio', color: '#8b5cf6' },
    { id: 'lighting', name: 'Lighting', color: '#f59e0b' },
    { id: 'video', name: 'Video', color: '#3b82f6' },
    { id: 'rigging', name: 'Rigging', color: '#6b7280' },
    { id: 'power', name: 'Power', color: '#ef4444' },
    { id: 'staging', name: 'Staging', color: '#14b8a6' },
  ];

  // Helper: generate realistic availability pattern
  function genAvail(stock, busyWeeks, baseLoad) {
    const avail = [];
    for (let i = 0; i < 31; i++) {
      const d = new Date(today);
      d.setDate(d.getDate() + i);
      const weekNum = Math.floor(i / 7);
      const isWeekend = d.getDay() === 0 || d.getDay() === 6;

      let demand = baseLoad;
      if (busyWeeks.includes(weekNum)) demand += Math.floor(stock * 0.4) + Math.floor(Math.random() * 3);
      if (isWeekend && busyWeeks.includes(weekNum)) demand += Math.floor(stock * 0.2);
      if (Math.random() < 0.08) demand += Math.floor(stock * 0.3);

      avail.push(Math.max(stock - demand, -Math.floor(Math.random() * 3)));
    }
    return avail;
  }

  const products = [
    // Audio
    { id: 'a1', group: 'audio', name: 'L-Acoustics K2', sku: 'AUD-K2-001', icon: '🔊', stock: 24, busyWeeks: [1, 2], baseLoad: 8 },
    { id: 'a2', group: 'audio', name: 'L-Acoustics KS28 Sub', sku: 'AUD-KS28-001', icon: '🔊', stock: 16, busyWeeks: [1, 2], baseLoad: 5 },
    { id: 'a3', group: 'audio', name: 'DiGiCo SD12 Console', sku: 'AUD-SD12-001', icon: '🎛️', stock: 4, busyWeeks: [0, 1, 2, 3], baseLoad: 2 },
    { id: 'a4', group: 'audio', name: 'Shure ULXD Wireless Mic', sku: 'AUD-ULXD-001', icon: '🎤', stock: 32, busyWeeks: [1, 2], baseLoad: 12 },
    { id: 'a5', group: 'audio', name: 'Shure SM58', sku: 'AUD-SM58-001', icon: '🎤', stock: 48, busyWeeks: [1], baseLoad: 15 },
    { id: 'a6', group: 'audio', name: 'DI Box (Radial J48)', sku: 'AUD-DI-001', icon: '📦', stock: 40, busyWeeks: [], baseLoad: 8 },
    // Lighting
    { id: 'l1', group: 'lighting', name: 'Robe MegaPointe', sku: 'LGT-MEGA-001', icon: '💡', stock: 20, busyWeeks: [1, 2, 3], baseLoad: 6 },
    { id: 'l2', group: 'lighting', name: 'Ayrton Perseo Profile', sku: 'LGT-PER-001', icon: '💡', stock: 12, busyWeeks: [2, 3], baseLoad: 4 },
    { id: 'l3', group: 'lighting', name: 'ETC Source Four', sku: 'LGT-S4-001', icon: '💡', stock: 36, busyWeeks: [1], baseLoad: 10 },
    { id: 'l4', group: 'lighting', name: 'MA3 Lighting Console', sku: 'LGT-MA3-001', icon: '🎛️', stock: 3, busyWeeks: [0, 1, 2, 3], baseLoad: 1 },
    { id: 'l5', group: 'lighting', name: 'LED Festoon 10m', sku: 'LGT-FEST-001', icon: '✨', stock: 60, busyWeeks: [1, 2], baseLoad: 18 },
    // Video
    { id: 'v1', group: 'video', name: 'ROE CB5 LED Panel', sku: 'VID-CB5-001', icon: '📺', stock: 80, busyWeeks: [2], baseLoad: 20 },
    { id: 'v2', group: 'video', name: 'Barco E2 Processor', sku: 'VID-E2-001', icon: '🖥️', stock: 2, busyWeeks: [1, 2], baseLoad: 1 },
    { id: 'v3', group: 'video', name: 'Sony PTZ Camera', sku: 'VID-PTZ-001', icon: '📷', stock: 8, busyWeeks: [1, 2, 3], baseLoad: 3 },
    // Rigging
    { id: 'r1', group: 'rigging', name: 'CM Lodestar 1T Motor', sku: 'RIG-LD1T-001', icon: '⚙️', stock: 30, busyWeeks: [1, 2], baseLoad: 10 },
    { id: 'r2', group: 'rigging', name: 'Prolyte H30V Truss 3m', sku: 'RIG-H30-001', icon: '🔩', stock: 40, busyWeeks: [1, 2], baseLoad: 12 },
    { id: 'r3', group: 'rigging', name: 'Steeldeck 8x4', sku: 'RIG-SD84-001', icon: '🔩', stock: 24, busyWeeks: [2], baseLoad: 6 },
    // Power
    { id: 'p1', group: 'power', name: 'Generator 100kVA', sku: 'PWR-100K-001', icon: '⚡', stock: 4, busyWeeks: [1, 2, 3], baseLoad: 2 },
    { id: 'p2', group: 'power', name: 'Distro 63A 3ph', sku: 'PWR-D63-001', icon: '⚡', stock: 12, busyWeeks: [1, 2], baseLoad: 4 },
    { id: 'p3', group: 'power', name: 'Cable 63A 25m', sku: 'PWR-C63-001', icon: '🔌', stock: 20, busyWeeks: [1], baseLoad: 5 },
    // Staging
    { id: 's1', group: 'staging', name: 'Layher Allround Bay', sku: 'STG-LAY-001', icon: '🏗️', stock: 50, busyWeeks: [2, 3], baseLoad: 12 },
    { id: 's2', group: 'staging', name: 'Stage Barrier 2.2m', sku: 'STG-BAR-001', icon: '🚧', stock: 100, busyWeeks: [2], baseLoad: 25 },
  ];

  // Build availability data
  const productRows = products.map(p => ({
    ...p,
    type: 'product',
    availability: genAvail(p.stock, p.busyWeeks, p.baseLoad)
  }));

  return {
    days,
    groups,
    search: '',
    activeGroup: 'all',
    showShortagesOnly: false,
    collapsedGroups: {},
    tooltip: { show: false, x: 0, y: 0, product: '', date: '', stock: 0, bookings: 0, quarantine: 0, transfers: 0, available: 0 },
    products: productRows,

    get dateRangeLabel() {
      const start = new Date(today);
      const end = new Date(today);
      end.setDate(end.getDate() + 30);
      return start.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) + ' — ' + end.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    get totalProducts() {
      return this.products.length;
    },

    get totalStock() {
      return this.products.reduce((sum, p) => sum + p.stock, 0);
    },

    get shortageCount() {
      let count = 0;
      this.products.forEach(p => {
        p.availability.forEach(a => { if (a < 0) count++; });
      });
      return count;
    },

    get filteredRows() {
      let prods = this.products;

      if (this.search) {
        const q = this.search.toLowerCase();
        prods = prods.filter(p => p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q));
      }

      if (this.activeGroup !== 'all') {
        prods = prods.filter(p => p.group === this.activeGroup);
      }

      if (this.showShortagesOnly) {
        prods = prods.filter(p => p.availability.some(a => a < 0));
      }

      // Group products
      const rows = [];
      const grouped = {};
      prods.forEach(p => {
        if (!grouped[p.group]) grouped[p.group] = [];
        grouped[p.group].push(p);
      });

      this.groups.forEach(g => {
        if (!grouped[g.id]) return;
        rows.push({ type: 'group', id: g.id, name: g.name, color: g.color, count: grouped[g.id].length });
        if (!this.collapsedGroups[g.id]) {
          grouped[g.id].forEach(p => rows.push(p));
        }
      });

      return rows;
    },

    toggleGroup(id) {
      this.collapsedGroups[id] = !this.collapsedGroups[id];
    },

    cellClass(available, stock) {
      if (available < 0) return 'av-out av-shortage';
      if (available === 0) return 'av-out';
      if (available <= stock * 0.2) return 'av-lo';
      if (available === stock) return 'av-full';
      return 'av-ok';
    },

    showTooltip(event, row, dayIndex) {
      const rect = event.target.getBoundingClientRect();
      const available = row.availability[dayIndex];
      const totalDemand = row.stock - available;
      const bookings = Math.max(0, Math.floor(totalDemand * 0.7));
      const quarantine = Math.max(0, Math.floor(totalDemand * 0.15));
      const transfers = Math.max(0, totalDemand - bookings - quarantine);

      this.tooltip = {
        show: true,
        x: Math.min(rect.left, window.innerWidth - 240),
        y: rect.bottom + 8,
        product: row.name,
        date: this.days[dayIndex].full,
        stock: row.stock,
        bookings,
        quarantine,
        transfers,
        available
      };
    },

    hideTooltip() {
      this.tooltip.show = false;
    }
  };
}
</script>
