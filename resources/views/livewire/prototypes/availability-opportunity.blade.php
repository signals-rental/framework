<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Opportunity Stock')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  OPPORTUNITY AVAILABILITY TOKENS                                  */
  /* ================================================================ */

  :root {
    --oa-hover: rgba(0, 0, 0, 0.03);
    --oa-subtle: var(--base);
    --oa-border-sub: #ecedf1;
    --oa-faint: #b0b4c3;
    --oa-accent-light: rgba(5, 150, 105, 0.04);
    --oa-green-bg: #ecfdf3; --oa-green-bdr: #bbf7d0;
    --oa-amber-bg: #fffbeb; --oa-amber-bdr: #fde68a;
    --oa-red-bg: #fef2f2; --oa-red-bdr: #fecaca;
    --oa-blue-bg: #eff6ff; --oa-blue-bdr: #bfdbfe;
    --oa-shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.1);
  }

  .dark {
    --oa-hover: rgba(255, 255, 255, 0.05);
    --oa-subtle: rgba(255, 255, 255, 0.04);
    --oa-border-sub: #283040;
    --oa-faint: #475569;
    --oa-accent-light: rgba(5, 150, 105, 0.06);
    --oa-green-bg: rgba(22, 163, 74, 0.15); --oa-green-bdr: rgba(22, 163, 74, 0.3);
    --oa-amber-bg: rgba(217, 119, 6, 0.15); --oa-amber-bdr: rgba(217, 119, 6, 0.3);
    --oa-red-bg: rgba(220, 38, 38, 0.15); --oa-red-bdr: rgba(220, 38, 38, 0.3);
    --oa-blue-bg: rgba(37, 99, 235, 0.15); --oa-blue-bdr: rgba(37, 99, 235, 0.3);
    --oa-shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.4);
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */

  .oa-page {
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

  .oa-ph {
    padding: 18px 24px 0;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-shrink: 0;
  }

  .oa-bc {
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .oa-bc a { color: var(--text-muted); text-decoration: none; }
  .oa-bc a:hover { color: var(--text-secondary); }

  .oa-ph-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .oa-ph-meta {
    font-size: 11px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 3px;
  }

  .oa-ph-meta span { display: flex; align-items: center; gap: 4px; }
  .oa-ph-act { display: flex; gap: 8px; align-items: center; }

  /* ================================================================ */
  /*  BADGES & BUTTONS                                                 */
  /* ================================================================ */

  .oa-badge {
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

  .oa-badge-g { background: var(--oa-green-bg); color: #16a34a; border: 1px solid var(--oa-green-bdr); }
  .oa-badge-a { background: var(--oa-amber-bg); color: var(--amber); border: 1px solid var(--oa-amber-bdr); }
  .oa-badge-r { background: var(--oa-red-bg); color: var(--red); border: 1px solid var(--oa-red-bdr); }
  .oa-badge-b { background: var(--oa-blue-bg); color: #2563eb; border: 1px solid var(--oa-blue-bdr); }
  .oa-bdot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

  .oa-btn {
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

  .oa-btn:hover { background: var(--oa-hover); color: var(--text-primary); border-color: var(--navy-light); }
  .oa-btn svg { width: 14px; height: 14px; }

  /* ================================================================ */
  /*  STATUS FILTERS                                                   */
  /* ================================================================ */

  .oa-filters {
    display: flex;
    align-items: center;
    padding: 10px 24px;
    gap: 10px;
    flex-shrink: 0;
    border-bottom: 1px solid var(--oa-border-sub);
  }

  .oa-ftag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    border: 1px solid var(--card-border);
    background: var(--card-bg);
    color: var(--text-secondary);
    transition: all 0.15s;
  }

  .oa-ftag:hover { border-color: var(--navy-light); color: var(--text-primary); }

  .oa-ftag.on-g {
    border-color: #16a34a;
    color: #16a34a;
    background: var(--oa-green-bg);
  }

  .oa-ftag.on-r {
    border-color: var(--red);
    color: var(--red);
    background: var(--oa-red-bg);
  }

  .oa-ftag-count {
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 700;
    min-width: 16px;
    text-align: center;
    padding: 1px 4px;
    background: rgba(0, 0, 0, 0.06);
  }

  .dark .oa-ftag-count { background: rgba(255, 255, 255, 0.08); }

  .oa-fsep { width: 1px; height: 20px; background: var(--card-border); margin: 0 4px; }
  .oa-fright { margin-left: auto; display: flex; align-items: center; gap: 8px; }

  .oa-search {
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid var(--card-border);
    padding: 4px 10px;
    background: var(--card-bg);
    min-width: 180px;
  }

  .oa-search svg { width: 13px; height: 13px; color: var(--oa-faint); flex-shrink: 0; }

  .oa-search input {
    background: none;
    border: none;
    color: var(--text-primary);
    font-family: var(--font-mono);
    font-size: 11px;
    outline: none;
    width: 100%;
  }

  .oa-search input::placeholder { color: var(--oa-faint); }

  /* ================================================================ */
  /*  MATRIX TABLE                                                     */
  /* ================================================================ */

  .oa-mw {
    flex: 1 1 0;
    height: 0;
    min-height: 0;
    overflow: auto;
    padding: 0 24px 16px;
  }

  .oa-mt {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    overflow: hidden;
    box-shadow: var(--shadow-card);
    table-layout: fixed;
  }

  .oa-mt thead { position: sticky; top: 0; z-index: 10; }

  .oa-mt th {
    background: var(--oa-subtle);
    padding: 0;
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    text-align: center;
    border-bottom: 1px solid var(--card-border);
    border-right: 1px solid var(--oa-border-sub);
    white-space: nowrap;
    user-select: none;
  }

  .oa-mt th:last-child { border-right: none; }

  .oa-th-prod {
    text-align: left !important;
    padding: 6px 12px !important;
    min-width: 240px;
    width: 240px;
    position: sticky;
    left: 0;
    z-index: 11;
    background: var(--oa-subtle) !important;
  }

  .oa-th-qty {
    width: 52px;
    min-width: 52px;
    padding: 4px 2px !important;
  }

  .oa-th-day {
    width: 38px;
    min-width: 38px;
    padding: 4px 2px !important;
  }

  .oa-th-day-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    line-height: 1.2;
  }

  .oa-th-dow { font-size: 8px; color: var(--oa-faint); }
  .oa-th-dom { font-size: 10px; font-weight: 600; color: var(--text-secondary); }
  .oa-th-month { font-size: 7px; color: var(--oa-faint); }

  .oa-th-weekend { background: var(--oa-hover) !important; }

  /* ================================================================ */
  /*  TABLE BODY                                                       */
  /* ================================================================ */

  .oa-mt tbody tr { transition: background 0.08s; }
  .oa-mt tbody tr:hover { background: var(--oa-accent-light); }

  .oa-mt td {
    padding: 0;
    border-bottom: 1px solid var(--oa-border-sub);
    border-right: 1px solid var(--oa-border-sub);
    height: 36px;
    vertical-align: middle;
    text-align: center;
  }

  .oa-mt td:last-child { border-right: none; }

  /* Product name column */
  .oa-td-prod {
    text-align: left !important;
    padding: 6px 12px !important;
    position: sticky;
    left: 0;
    z-index: 5;
    background: var(--card-bg);
    cursor: pointer;
  }

  .oa-mt tbody tr:hover .oa-td-prod { background: rgba(5, 150, 105, 0.03); }

  .oa-prod {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .oa-prod-icon {
    width: 26px;
    height: 26px;
    background: var(--oa-subtle);
    border: 1px solid var(--oa-border-sub);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
  }

  .oa-prod-name {
    font-weight: 500;
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-primary);
  }

  .oa-prod-sku {
    font-size: 9px;
    color: var(--text-muted);
  }

  .oa-prod-expand {
    margin-left: auto;
    width: 14px;
    height: 14px;
    color: var(--oa-faint);
    transition: transform 0.15s, color 0.15s;
  }

  .oa-prod-expand.open { transform: rotate(180deg); color: var(--text-secondary); }

  /* Quantity columns */
  .oa-td-qty {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    background: var(--oa-subtle);
  }

  /* Group header row */
  .oa-group-row td {
    background: var(--oa-subtle) !important;
    border-bottom: 1px solid var(--card-border);
    height: 30px;
  }

  .oa-group-label {
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

  .oa-group-label svg { width: 10px; height: 10px; transition: transform 0.15s; }
  .oa-group-label.collapsed svg { transform: rotate(-90deg); }

  .oa-group-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .oa-group-count {
    font-family: var(--font-mono);
    font-size: 9px;
    color: var(--oa-faint);
    margin-left: 2px;
  }

  /* ================================================================ */
  /*  AVAILABILITY CELLS                                               */
  /* ================================================================ */

  .oa-cell {
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

  .oa-cell:hover { filter: brightness(0.95); }

  /* Available >= qty needed: green */
  .oa-ok {
    color: #16a34a;
    background: rgba(22, 163, 74, 0.12);
  }

  /* Available > 0 but < qty needed: amber */
  .oa-partial {
    color: var(--amber);
    background: rgba(217, 119, 6, 0.14);
    font-weight: 600;
  }

  /* Available <= 0: red */
  .oa-shortage {
    color: var(--red);
    background: rgba(220, 38, 38, 0.20);
    font-weight: 700;
  }

  .oa-weekend-cell {
    background-color: rgba(0, 0, 0, 0.015);
  }

  .dark .oa-weekend-cell {
    background-color: rgba(255, 255, 255, 0.015);
  }

  /* ================================================================ */
  /*  DEMAND DETAIL PANEL                                              */
  /* ================================================================ */

  .oa-detail-row td {
    background: var(--oa-subtle) !important;
    border-bottom: 1px solid var(--oa-border-sub);
    height: auto !important;
    padding: 0 !important;
  }

  .oa-detail {
    padding: 10px 12px 14px;
  }

  .oa-detail-title {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    margin-bottom: 8px;
  }

  .oa-detail-booking {
    display: flex;
    align-items: center;
    padding: 4px 0;
    gap: 8px;
  }

  .oa-detail-booking + .oa-detail-booking {
    border-top: 1px solid var(--oa-border-sub);
    padding-top: 6px;
  }

  .oa-booking-name {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 500;
    color: var(--green);
    min-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
  }

  .oa-booking-name:hover { text-decoration: underline; }

  .oa-booking-qty {
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 600;
    color: var(--text-secondary);
    min-width: 30px;
    text-align: center;
  }

  .oa-booking-cells {
    display: flex;
    gap: 1px;
    flex: 1;
  }

  .oa-booking-cell {
    flex: 1;
    height: 18px;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-mono);
    font-size: 8px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
    position: relative;
  }

  /* Phase colors */
  .oa-phase-draft      { background: #94a3b8; }
  .oa-phase-committed  { background: #3b82f6; }
  .oa-phase-prep       { background: #8b5cf6; }
  .oa-phase-dispatch   { background: #2563eb; }
  .oa-phase-active     { background: #0d9488; }
  .oa-phase-collection { background: #2563eb; }
  .oa-phase-deprep     { background: #a855f7; }
  .oa-phase-turnaround { background: #d97706; }
  .oa-phase-quarantine { background: #dc2626; }
  .oa-phase-none       { background: transparent; }

  /* ================================================================ */
  /*  PHASE KEY                                                        */
  /* ================================================================ */

  .oa-key {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 8px 24px;
    font-size: 9px;
    font-family: var(--font-mono);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--oa-faint);
    flex-shrink: 0;
    border-top: 1px solid var(--card-border);
    box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.10);
  }

  .oa-page ~ .app-footer {
    padding: 6px 24px;
  }

  .oa-page ~ .app-footer .footer-mark { font-size: 8px; }
  .oa-page ~ .app-footer .footer-annotation { font-size: 9px; }

  .oa-key-label {
    font-family: var(--font-display);
    font-weight: 600;
    font-size: 9px;
    color: var(--text-muted);
    margin-right: 4px;
  }

  .oa-key-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  .oa-key-swatch {
    width: 16px;
    height: 10px;
  }

  .oa-key-ok { background: rgba(22, 163, 74, 0.12); border: 1px solid rgba(22, 163, 74, 0.25); }
  .oa-key-lo { background: rgba(217, 119, 6, 0.12); border: 1px solid rgba(217, 119, 6, 0.25); }
  .oa-key-out { background: rgba(220, 38, 38, 0.12); border: 1px solid rgba(220, 38, 38, 0.25); }
  .oa-key-na { background: var(--oa-subtle); border: 1px solid var(--oa-border-sub); }

  /* ================================================================ */
  /*  TOOLTIP                                                          */
  /* ================================================================ */

  .oa-tip {
    position: fixed;
    background: var(--navy);
    color: #e2e8f0;
    padding: 10px 14px;
    font-family: var(--font-mono);
    font-size: 10px;
    line-height: 1.6;
    z-index: 200;
    pointer-events: none;
    box-shadow: var(--oa-shadow-lg);
    max-width: 240px;
    opacity: 0;
    transition: opacity 0.12s;
  }

  .oa-tip.vis { opacity: 1; }
  .oa-tip-title { font-family: var(--font-display); font-weight: 600; font-size: 11px; color: #fff; margin-bottom: 4px; }

  .oa-tip-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
  }

  .oa-tip-label { color: var(--grey-light); }
  .oa-tip-val { font-weight: 500; color: #fff; }
  .oa-tip-val.oa-tip-neg { color: #fca5a5; }
  .oa-tip-val.oa-tip-warn { color: #fcd34d; }

  .oa-tip-sep {
    border: none;
    border-top: 1px solid #334155;
    margin: 5px 0;
  }

  /* ================================================================ */
  /*  SUMMARY BAR                                                      */
  /* ================================================================ */

  .oa-summary {
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
    color: var(--oa-faint);
  }

  .oa-summary span { display: flex; align-items: center; gap: 4px; }
  .oa-summary-val { font-weight: 600; color: var(--text-secondary); }
  .oa-summary-alert { color: var(--red); font-weight: 600; }
  .oa-summary-ok { color: #16a34a; font-weight: 600; }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */

  @keyframes oa-fadeInUp {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .oa-mt tbody tr { animation: oa-fadeInUp 0.15s ease both; }
  .oa-mt tbody tr:nth-child(1) { animation-delay: 0.02s; }
  .oa-mt tbody tr:nth-child(2) { animation-delay: 0.03s; }
  .oa-mt tbody tr:nth-child(3) { animation-delay: 0.04s; }
  .oa-mt tbody tr:nth-child(4) { animation-delay: 0.05s; }
  .oa-mt tbody tr:nth-child(5) { animation-delay: 0.06s; }
  .oa-mt tbody tr:nth-child(6) { animation-delay: 0.07s; }
  .oa-mt tbody tr:nth-child(7) { animation-delay: 0.08s; }
  .oa-mt tbody tr:nth-child(8) { animation-delay: 0.09s; }
  .oa-mt tbody tr:nth-child(9) { animation-delay: 0.10s; }
  .oa-mt tbody tr:nth-child(10) { animation-delay: 0.11s; }
</style>

<div class="oa-page"
     x-data="opportunityAvailability()"
     @mouseleave="hideTooltip()">

  {{-- ============================================================ --}}
  {{--  SUBNAV                                                       --}}
  {{-- ============================================================ --}}
  <nav class="app-subnav">
    <div class="flex h-full items-center gap-0">
      <a href="#" class="subnav-link">Details</a>
      <a href="#" class="subnav-link">Items</a>
      <a href="#" class="subnav-link active">Availability</a>
      <a href="#" class="subnav-link">Documents</a>
      <a href="#" class="subnav-link">Activity</a>
    </div>
    <span class="ml-auto font-mono text-[9px] uppercase tracking-[0.06em] text-[var(--text-muted)]"
          x-text="opportunity.status">
    </span>
  </nav>

  {{-- ============================================================ --}}
  {{--  PAGE HEADER                                                  --}}
  {{-- ============================================================ --}}
  <div class="oa-ph">
    <div>
      <div class="oa-bc">
        <a href="#">Opportunities</a>
        <span style="color:var(--oa-faint);font-size:10px">&rsaquo;</span>
        <a href="#" x-text="opportunity.number"></a>
        <span style="color:var(--oa-faint);font-size:10px">&rsaquo;</span>
        <span style="color:var(--text-primary);font-weight:500">Availability</span>
      </div>
      <div class="oa-ph-title">
        Opportunity Stock
        <span class="oa-badge oa-badge-b"><span class="oa-bdot"></span> <span x-text="opportunity.status"></span></span>
      </div>
      <div class="oa-ph-meta">
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span x-text="opportunity.subject"></span>
        </span>
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          <span x-text="opportunity.store"></span>
        </span>
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <span x-text="dateRangeLabel"></span>
        </span>
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 001 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
          <span x-text="items.length + ' items'"></span>
        </span>
      </div>
    </div>
    <div class="oa-ph-act">
      <button class="oa-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export
      </button>
      <button class="oa-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
        Refresh
      </button>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{--  STATUS FILTERS                                               --}}
  {{-- ============================================================ --}}
  <div class="oa-filters">
    <div class="oa-ftag"
         :class="{ 'on-g': filter === 'available' }"
         @click="filter = filter === 'available' ? 'all' : 'available'">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      Available
      <span class="oa-ftag-count" x-text="availableCount"></span>
    </div>
    <div class="oa-ftag"
         :class="{ 'on-r': filter === 'overbooked' }"
         @click="filter = filter === 'overbooked' ? 'all' : 'overbooked'">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Overbooked
      <span class="oa-ftag-count" x-text="overbookedCount"></span>
    </div>

    <div class="oa-fsep"></div>

    <div class="oa-fright">
      <div class="oa-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Search items..." x-model="search" />
      </div>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{--  MATRIX                                                       --}}
  {{-- ============================================================ --}}
  <div class="oa-mw">
    <table class="oa-mt">
      <thead>
        <tr>
          <th class="oa-th-prod">
            <div style="display:flex;align-items:center;gap:4px">Item</div>
          </th>
          <th class="oa-th-qty">Qty</th>
          <th class="oa-th-qty">Sub</th>
          <template x-for="(day, di) in days" :key="di">
            <th class="oa-th-day"
                :class="{ 'oa-th-weekend': day.isWeekend }">
              <div class="oa-th-day-inner">
                <span class="oa-th-dow" x-text="day.dow"></span>
                <span class="oa-th-dom" x-text="day.dom"></span>
                <span class="oa-th-month" x-show="day.dom === 1 || di === 0" x-text="day.month"></span>
              </div>
            </th>
          </template>
        </tr>
      </thead>

      <tbody>
        <template x-for="(row, ri) in filteredRows" :key="row.id">
          <template x-if="true">
            <tr :class="{
              'oa-group-row': row.type === 'group',
              'oa-detail-row': row.type === 'detail'
            }">
              {{-- Group header --}}
              <template x-if="row.type === 'group'">
                <td :colspan="days.length + 3" style="text-align:left">
                  <div class="oa-group-label" :class="{ 'collapsed': collapsedGroups[row.id] }" @click="toggleGroup(row.id)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    <span class="oa-group-dot" :style="'background:' + row.color"></span>
                    <span x-text="row.name"></span>
                    <span class="oa-group-count" x-text="'(' + row.count + ')'"></span>
                  </div>
                </td>
              </template>

              {{-- Product item row --}}
              <template x-if="row.type === 'item'">
                <td class="oa-td-prod" @click="toggleDetail(row.sku)">
                  <div class="oa-prod">
                    <div class="oa-prod-icon" x-text="row.icon"></div>
                    <div>
                      <div class="oa-prod-name" x-text="row.name"></div>
                      <div class="oa-prod-sku" x-text="row.sku"></div>
                    </div>
                    <svg class="oa-prod-expand" :class="{ 'open': expandedItems[row.sku] }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                  </div>
                </td>
              </template>
              <template x-if="row.type === 'item'">
                <td class="oa-td-qty" x-text="row.qty"></td>
              </template>
              <template x-if="row.type === 'item'">
                <td class="oa-td-qty" x-text="row.subRent || '—'"></td>
              </template>
              <template x-if="row.type === 'item'">
                <template x-for="(day, di) in days" :key="'c' + di">
                  <td :class="day.isWeekend ? 'oa-weekend-cell' : ''"
                      @mouseenter="showTooltip($event, row, di)"
                      @mouseleave="hideTooltip()">
                    <div class="oa-cell"
                         :class="itemCellClass(row.availability[di], row.qty)">
                      <span x-text="row.availability[di]"></span>
                    </div>
                  </td>
                </template>
              </template>

              {{-- Demand detail panel --}}
              <template x-if="row.type === 'detail'">
                <td :colspan="days.length + 3" style="text-align:left">
                  <div class="oa-detail">
                    <div class="oa-detail-title" x-text="'Bookings for ' + row.productName + ' during this period:'"></div>
                    <template x-for="(booking, bi) in row.bookings" :key="bi">
                      <div class="oa-detail-booking">
                        <span class="oa-booking-name" x-text="booking.name + ' (' + booking.number + ')'"></span>
                        <span class="oa-booking-qty" x-text="booking.qty"></span>
                        <div class="oa-booking-cells">
                          <template x-for="(phase, pi) in booking.phases" :key="pi">
                            <div class="oa-booking-cell"
                                 :class="'oa-phase-' + phase"
                                 :title="phase !== 'none' ? phase : ''">
                            </div>
                          </template>
                        </div>
                      </div>
                    </template>
                    <template x-if="row.bookings.length === 0">
                      <div style="color:var(--oa-faint);font-size:10px;padding:4px 0">
                        No other bookings during this period.
                      </div>
                    </template>
                  </div>
                </td>
              </template>
            </tr>
          </template>
        </template>
      </tbody>
    </table>
  </div>

  {{-- ============================================================ --}}
  {{--  TOOLTIP                                                      --}}
  {{-- ============================================================ --}}
  <div class="oa-tip"
       :class="{ 'vis': tooltip.show }"
       :style="'top:' + tooltip.y + 'px;left:' + tooltip.x + 'px'">
    <div class="oa-tip-title" x-text="tooltip.product"></div>
    <div style="font-size:9px;color:var(--grey-light);margin-bottom:6px" x-text="tooltip.date"></div>
    <hr class="oa-tip-sep">
    <div class="oa-tip-row">
      <span class="oa-tip-label">Total Stock</span>
      <span class="oa-tip-val" x-text="tooltip.stock"></span>
    </div>
    <div class="oa-tip-row">
      <span class="oa-tip-label">Other Demands</span>
      <span class="oa-tip-val" x-text="tooltip.otherDemands"></span>
    </div>
    <div class="oa-tip-row">
      <span class="oa-tip-label">Available</span>
      <span class="oa-tip-val"
            :class="{ 'oa-tip-neg': tooltip.available < tooltip.qtyNeeded, 'oa-tip-warn': tooltip.available >= tooltip.qtyNeeded && tooltip.available <= tooltip.qtyNeeded + 2 }"
            x-text="tooltip.available"></span>
    </div>
    <hr class="oa-tip-sep">
    <div class="oa-tip-row">
      <span class="oa-tip-label">Qty Needed</span>
      <span class="oa-tip-val" x-text="tooltip.qtyNeeded"></span>
    </div>
    <div class="oa-tip-row" style="font-weight:600">
      <span class="oa-tip-label" style="color:#e2e8f0">Surplus / Deficit</span>
      <span class="oa-tip-val"
            :class="{ 'oa-tip-neg': tooltip.surplus < 0, 'oa-tip-warn': tooltip.surplus === 0 }"
            x-text="(tooltip.surplus >= 0 ? '+' : '') + tooltip.surplus"></span>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{--  PHASE KEY                                                    --}}
  {{-- ============================================================ --}}
  <div class="oa-key">
    <span class="oa-key-label">Key:</span>
    <span class="oa-key-item"><span class="oa-key-swatch oa-key-ok"></span> Available</span>
    <span class="oa-key-item"><span class="oa-key-swatch oa-key-lo"></span> Low Stock</span>
    <span class="oa-key-item"><span class="oa-key-swatch oa-key-out"></span> Shortage</span>
    <span class="oa-key-item"><span class="oa-key-swatch oa-key-na"></span> Full Stock</span>
    <span style="margin-left:auto">Hover cell for demand breakdown</span>
  </div>

  {{-- ============================================================ --}}
  {{--  SUMMARY BAR                                                  --}}
  {{-- ============================================================ --}}
  <div class="oa-summary">
    <span>Items: <span class="oa-summary-val" x-text="items.length"></span></span>
    <span>Available: <span class="oa-summary-ok" x-text="availableCount"></span></span>
    <span>Overbooked: <span class="oa-summary-alert" x-text="overbookedCount"></span></span>
    <span style="margin-left:auto">
      <span x-text="opportunity.number"></span> &middot; <span x-text="opportunity.subject"></span>
    </span>
  </div>
</div>

<script>
function opportunityAvailability() {
  const dows = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  // Opportunity context
  const opportunity = {
    number: 'OPP-2026-0847',
    subject: 'Henley Summer Festival 2026',
    member: 'Festival Partners Ltd',
    store: 'Main Warehouse',
    status: 'Confirmed',
    startDate: new Date(2026, 2, 5),  // Mar 5
    endDate: new Date(2026, 2, 19),   // Mar 19
  };

  // Generate dates for the opportunity period
  const days = [];
  const dayCount = Math.ceil((opportunity.endDate - opportunity.startDate) / (1000 * 60 * 60 * 24)) + 1;
  for (let i = 0; i < dayCount; i++) {
    const d = new Date(opportunity.startDate);
    d.setDate(d.getDate() + i);
    days.push({
      date: new Date(d),
      dow: dows[d.getDay()].toUpperCase(),
      dom: d.getDate(),
      month: monthNames[d.getMonth()].toUpperCase(),
      isWeekend: d.getDay() === 0 || d.getDay() === 6,
      full: d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
    });
  }

  // Group definitions
  const groupDefs = {
    'Audio':    { color: '#8b5cf6' },
    'Lighting': { color: '#f59e0b' },
    'Video':    { color: '#3b82f6' },
    'Rigging':  { color: '#6b7280' },
    'Power':    { color: '#ef4444' },
  };

  // Items on this opportunity
  const items = [
    { group: 'Audio', name: 'L-Acoustics K2', sku: 'AUD-K2-001', icon: '\u{1F50A}', qty: 8, subRent: 0, stock: 24 },
    { group: 'Audio', name: 'L-Acoustics KS28 Sub', sku: 'AUD-KS28-001', icon: '\u{1F50A}', qty: 4, subRent: 0, stock: 16 },
    { group: 'Audio', name: 'DiGiCo SD12 Console', sku: 'AUD-SD12-001', icon: '\u{1F39B}', qty: 1, subRent: 0, stock: 4 },
    { group: 'Audio', name: 'Shure ULXD Wireless Mic', sku: 'AUD-ULXD-001', icon: '\u{1F3A4}', qty: 6, subRent: 0, stock: 32 },
    { group: 'Lighting', name: 'Robe MegaPointe', sku: 'LGT-MEGA-001', icon: '\u{1F4A1}', qty: 12, subRent: 0, stock: 20 },
    { group: 'Lighting', name: 'MA3 Lighting Console', sku: 'LGT-MA3-001', icon: '\u{1F39B}', qty: 1, subRent: 0, stock: 3 },
    { group: 'Video', name: 'ROE CB5 LED Panel', sku: 'VID-CB5-001', icon: '\u{1F4FA}', qty: 40, subRent: 0, stock: 80 },
    { group: 'Video', name: 'Barco E2 Processor', sku: 'VID-E2-001', icon: '\u{1F5A5}', qty: 1, subRent: 0, stock: 2 },
    { group: 'Rigging', name: 'CM Lodestar 1T Motor', sku: 'RIG-LD1T-001', icon: '\u{2699}', qty: 8, subRent: 0, stock: 30 },
    { group: 'Rigging', name: 'Prolyte H30V Truss 3m', sku: 'RIG-H30-001', icon: '\u{1F529}', qty: 12, subRent: 0, stock: 40 },
    { group: 'Power', name: 'Generator 100kVA', sku: 'PWR-100K-001', icon: '\u{26A1}', qty: 2, subRent: 0, stock: 4 },
    { group: 'Power', name: 'Distro 63A 3ph', sku: 'PWR-D63-001', icon: '\u{26A1}', qty: 4, subRent: 0, stock: 12 },
  ];

  // Competing opportunities (other demands during our period)
  const competitors = [
    {
      name: 'Corporate Awards Gala',
      number: 'OPP-2026-0851',
      phase: 'committed',
      start: new Date(2026, 2, 8),   // Mar 8
      end: new Date(2026, 2, 12),    // Mar 12
      demands: {
        'LGT-MEGA-001': 8,
        'LGT-MA3-001': 1,
        'VID-CB5-001': 20,
        'RIG-LD1T-001': 4,
        'PWR-100K-001': 1,
      }
    },
    {
      name: 'Product Launch Event',
      number: 'OPP-2026-0862',
      phase: 'draft',
      start: new Date(2026, 2, 10),  // Mar 10
      end: new Date(2026, 2, 16),    // Mar 16
      demands: {
        'AUD-SD12-001': 2,
        'VID-E2-001': 1,
        'LGT-MEGA-001': 6,
        'AUD-ULXD-001': 8,
      }
    },
    {
      name: 'Spring Music Weekend',
      number: 'OPP-2026-0870',
      phase: 'committed',
      start: new Date(2026, 2, 14),  // Mar 14
      end: new Date(2026, 2, 19),    // Mar 19
      demands: {
        'AUD-K2-001': 16,
        'AUD-KS28-001': 8,
        'AUD-SD12-001': 2,
        'LGT-MEGA-001': 10,
        'PWR-100K-001': 2,
        'PWR-D63-001': 6,
      }
    }
  ];

  // Calculate availability per item per day (stock - other demands, NOT including this opp)
  function calcAvailability(sku, stock) {
    const avail = [];
    for (let di = 0; di < days.length; di++) {
      const dayDate = days[di].date;
      let otherDemand = 0;
      competitors.forEach(c => {
        if (c.demands[sku] && dayDate >= c.start && dayDate <= c.end) {
          otherDemand += c.demands[sku];
        }
      });
      avail.push(stock - otherDemand);
    }
    return avail;
  }

  // Calculate other demand for a specific day (for tooltip)
  function calcOtherDemand(sku, dayIndex) {
    const dayDate = days[dayIndex].date;
    let total = 0;
    competitors.forEach(c => {
      if (c.demands[sku] && dayDate >= c.start && dayDate <= c.end) {
        total += c.demands[sku];
      }
    });
    return total;
  }

  // Build phase timeline for a competitor booking relative to our date range
  function buildPhaseTimeline(comp, sku) {
    const phases = [];
    const bookingDays = Math.ceil((comp.end - comp.start) / (1000 * 60 * 60 * 24)) + 1;

    for (let di = 0; di < days.length; di++) {
      const dayDate = days[di].date;
      if (dayDate < comp.start || dayDate > comp.end || !comp.demands[sku]) {
        phases.push('none');
      } else {
        // Calculate relative position in the booking
        const dayInBooking = Math.ceil((dayDate - comp.start) / (1000 * 60 * 60 * 24));
        const ratio = dayInBooking / (bookingDays - 1 || 1);

        if (ratio <= 0.1) {
          phases.push('prep');
        } else if (ratio <= 0.15) {
          phases.push('dispatch');
        } else if (ratio <= 0.8) {
          phases.push(comp.phase === 'draft' ? 'draft' : 'active');
        } else if (ratio <= 0.9) {
          phases.push('collection');
        } else {
          phases.push('deprep');
        }
      }
    }
    return phases;
  }

  // Build item rows with availability
  const itemRows = items.map(item => ({
    ...item,
    type: 'item',
    id: item.sku,
    availability: calcAvailability(item.sku, item.stock),
    get isOverbooked() {
      return this.availability.some(a => a < this.qty);
    }
  }));

  // Get detail bookings for a product
  function getDetailBookings(sku) {
    return competitors
      .filter(c => c.demands[sku])
      .map(c => ({
        name: c.name,
        number: c.number,
        qty: c.demands[sku],
        phases: buildPhaseTimeline(c, sku),
      }));
  }

  return {
    days,
    opportunity,
    items: itemRows,
    search: '',
    filter: 'all',
    expandedItems: {},
    collapsedGroups: {},
    tooltip: { show: false, x: 0, y: 0, product: '', date: '', stock: 0, otherDemands: 0, available: 0, qtyNeeded: 0, surplus: 0 },

    get dateRangeLabel() {
      const opts = { day: 'numeric', month: 'short' };
      const optsYear = { day: 'numeric', month: 'short', year: 'numeric' };
      return opportunity.startDate.toLocaleDateString('en-GB', opts) + ' \u2014 ' + opportunity.endDate.toLocaleDateString('en-GB', optsYear);
    },

    get availableCount() {
      return this.items.filter(i => !i.isOverbooked).length;
    },

    get overbookedCount() {
      return this.items.filter(i => i.isOverbooked).length;
    },

    get filteredRows() {
      let filteredItems = [...this.items];

      if (this.search) {
        const q = this.search.toLowerCase();
        filteredItems = filteredItems.filter(i => i.name.toLowerCase().includes(q) || i.sku.toLowerCase().includes(q));
      }

      if (this.filter === 'available') {
        filteredItems = filteredItems.filter(i => !i.isOverbooked);
      } else if (this.filter === 'overbooked') {
        filteredItems = filteredItems.filter(i => i.isOverbooked);
      }

      // Group items
      const rows = [];
      const grouped = {};
      filteredItems.forEach(i => {
        if (!grouped[i.group]) grouped[i.group] = [];
        grouped[i.group].push(i);
      });

      Object.keys(groupDefs).forEach(groupName => {
        if (!grouped[groupName]) return;
        const g = groupDefs[groupName];
        const groupId = 'g-' + groupName;
        rows.push({ type: 'group', id: groupId, name: groupName, color: g.color, count: grouped[groupName].length });
        if (!this.collapsedGroups[groupId]) {
          grouped[groupName].forEach(item => {
            rows.push(item);
            // If expanded, add detail row
            if (this.expandedItems[item.sku]) {
              rows.push({
                type: 'detail',
                id: 'detail-' + item.sku,
                productName: item.name,
                bookings: getDetailBookings(item.sku),
              });
            }
          });
        }
      });

      return rows;
    },

    toggleGroup(id) {
      this.collapsedGroups[id] = !this.collapsedGroups[id];
    },

    toggleDetail(sku) {
      this.expandedItems[sku] = !this.expandedItems[sku];
    },

    itemCellClass(available, qtyNeeded) {
      if (available <= 0) return 'oa-shortage';
      if (available < qtyNeeded) return 'oa-partial';
      return 'oa-ok';
    },

    showTooltip(event, row, dayIndex) {
      const rect = event.target.getBoundingClientRect();
      const available = row.availability[dayIndex];
      const otherDemands = calcOtherDemand(row.sku, dayIndex);

      this.tooltip = {
        show: true,
        x: Math.min(rect.left, window.innerWidth - 260),
        y: rect.bottom + 8,
        product: row.name,
        date: this.days[dayIndex].full,
        stock: row.stock,
        otherDemands,
        available,
        qtyNeeded: row.qty,
        surplus: available - row.qty,
      };
    },

    hideTooltip() {
      this.tooltip.show = false;
    }
  };
}
</script>
