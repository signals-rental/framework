<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Opportunity Lifecycle')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  OPPORTUNITY LIFECYCLE TOKENS — maps to brand system in app.css   */
  /* ================================================================ */
  :root {
    --ol-bg: var(--content-bg);
    --ol-panel: var(--card-bg);
    --ol-surface: var(--base);
    --ol-border: var(--card-border);
    --ol-border-subtle: var(--grey-border);
    --ol-text: var(--text-primary);
    --ol-text-secondary: var(--text-secondary);
    --ol-text-muted: var(--text-muted);
    --ol-accent: var(--green);
    --ol-accent-dim: var(--green-muted);
    --ol-hover: rgba(0, 0, 0, 0.03);
    --ol-shadow: var(--shadow-card);
    --ol-draft: var(--grey);
    --ol-draft-bg: rgba(100, 116, 139, 0.08);
    --ol-quote: var(--blue);
    --ol-quote-bg: rgba(37, 99, 235, 0.08);
    --ol-order: var(--green);
    --ol-order-bg: rgba(5, 150, 105, 0.08);
    --ol-red: var(--red);
    --ol-red-bg: rgba(220, 38, 38, 0.08);
    --ol-amber: var(--amber);
    --ol-amber-bg: rgba(217, 119, 6, 0.08);
    --ol-violet: var(--violet);
    --ol-violet-bg: rgba(124, 58, 237, 0.08);
    --ol-cyan: var(--cyan);
    --ol-cyan-bg: rgba(8, 145, 178, 0.08);
    --ol-blue: var(--blue);
    --ol-blue-bg: rgba(37, 99, 235, 0.06);
    --ol-shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.1);
    --ol-row-hover: var(--table-row-hover);
    --ol-table-border: var(--table-border);
    --ol-table-header-bg: var(--table-header-bg);
  }

  .dark {
    --ol-bg: var(--content-bg);
    --ol-panel: var(--card-bg);
    --ol-surface: var(--navy-mid);
    --ol-border: var(--card-border);
    --ol-border-subtle: #283040;
    --ol-text: var(--text-primary);
    --ol-text-secondary: var(--text-secondary);
    --ol-text-muted: var(--text-muted);
    --ol-accent: var(--green);
    --ol-accent-dim: rgba(5, 150, 105, 0.12);
    --ol-hover: rgba(255, 255, 255, 0.06);
    --ol-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --ol-draft-bg: rgba(100, 116, 139, 0.15);
    --ol-quote-bg: rgba(37, 99, 235, 0.12);
    --ol-order-bg: rgba(5, 150, 105, 0.12);
    --ol-red-bg: rgba(220, 38, 38, 0.12);
    --ol-amber-bg: rgba(217, 119, 6, 0.12);
    --ol-violet-bg: rgba(124, 58, 237, 0.12);
    --ol-cyan-bg: rgba(8, 145, 178, 0.12);
    --ol-blue-bg: rgba(37, 99, 235, 0.12);
    --ol-shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.4);
    --ol-row-hover: var(--table-row-hover);
    --ol-table-border: var(--table-border);
    --ol-table-header-bg: var(--table-header-bg);
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */
  .ol-page {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 64px);
    background: var(--ol-bg);
    position: relative;
  }

  .ol-container {
    max-width: 1440px;
    margin: 0 auto;
    padding: 24px 32px 64px;
    width: 100%;
  }

  /* ================================================================ */
  /*  HEADER                                                           */
  /* ================================================================ */
  .ol-header {
    margin-bottom: 24px;
  }

  .ol-header-title {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 700;
    color: var(--ol-text);
    margin: 0 0 4px;
  }

  .ol-header-subtitle {
    font-family: var(--font-display);
    font-size: 13px;
    color: var(--ol-text-muted);
    margin: 0;
  }

  /* ================================================================ */
  /*  TAB BAR                                                          */
  /* ================================================================ */
  .ol-tabs {
    display: flex;
    gap: 2px;
    border-bottom: 1px solid var(--ol-border);
    margin-bottom: 24px;
  }

  .ol-tab {
    padding: 10px 18px;
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 500;
    color: var(--ol-text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.15s ease;
    background: none;
    border-top: none;
    border-left: none;
    border-right: none;
    user-select: none;
  }

  .ol-tab:hover {
    color: var(--ol-text-secondary);
    background: var(--ol-hover);
  }

  .ol-tab-active {
    color: var(--ol-accent);
    border-bottom-color: var(--ol-accent);
    font-weight: 600;
  }

  /* ================================================================ */
  /*  PANELS                                                           */
  /* ================================================================ */
  .ol-panel {
    background: var(--ol-panel);
    border: 1px solid var(--ol-border);
    border-radius: 8px;
    box-shadow: var(--ol-shadow);
    overflow: hidden;
  }

  .ol-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-bottom: 1px solid var(--ol-border);
    background: var(--ol-table-header-bg);
  }

  .ol-panel-title {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    color: var(--ol-text);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .ol-panel-body {
    padding: 20px;
  }

  /* ================================================================ */
  /*  STATE MACHINE — THREE COLUMNS                                    */
  /* ================================================================ */
  .ol-sm-grid {
    display: grid;
    grid-template-columns: 200px 260px 1fr;
    gap: 20px;
    align-items: flex-start;
  }

  .ol-sm-column {
    border: 2px solid var(--ol-border);
    border-radius: 10px;
    overflow: hidden;
    transition: border-color 0.2s ease;
  }

  .ol-sm-column-draft { border-color: var(--ol-draft); }
  .ol-sm-column-quote { border-color: var(--ol-quote); }
  .ol-sm-column-order { border-color: var(--ol-order); }

  .ol-sm-col-header {
    padding: 12px 16px;
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .ol-sm-col-header-draft { background: var(--ol-draft-bg); color: var(--ol-draft); }
  .ol-sm-col-header-quote { background: var(--ol-quote-bg); color: var(--ol-quote); }
  .ol-sm-col-header-order { background: var(--ol-order-bg); color: var(--ol-order); }

  .ol-sm-col-body {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .ol-sm-status-box {
    padding: 10px 14px;
    border: 1px solid var(--ol-border-subtle);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    background: var(--ol-panel);
    position: relative;
  }

  .ol-sm-status-box:hover {
    border-color: var(--ol-text-muted);
    background: var(--ol-hover);
  }

  .ol-sm-status-box-active {
    border-color: var(--ol-accent);
    background: var(--ol-accent-dim);
    box-shadow: 0 0 0 1px var(--ol-accent);
  }

  .ol-sm-status-name {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    color: var(--ol-text);
  }

  .ol-sm-status-value {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ol-text-muted);
    margin-top: 2px;
  }

  .ol-sm-terminal-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--ol-red);
  }

  .ol-sm-pause-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--ol-amber);
  }

  /* ================================================================ */
  /*  STATE MACHINE — TRANSITIONS PANEL                                */
  /* ================================================================ */
  .ol-sm-transitions {
    margin-top: 20px;
  }

  .ol-sm-transition-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    transition: background 0.15s ease;
  }

  .ol-sm-transition-row:hover {
    background: var(--ol-hover);
  }

  .ol-sm-transition-from {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--ol-text-secondary);
    min-width: 140px;
  }

  .ol-sm-transition-arrow {
    font-size: 14px;
    flex-shrink: 0;
  }

  .ol-sm-transition-arrow-forward { color: var(--ol-accent); }
  .ol-sm-transition-arrow-reverse { color: var(--ol-amber); }
  .ol-sm-transition-arrow-terminal { color: var(--ol-red); }
  .ol-sm-transition-arrow-reinstate { color: var(--ol-cyan); }

  .ol-sm-transition-to {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--ol-text-secondary);
    min-width: 140px;
  }

  .ol-sm-transition-label {
    font-family: var(--font-display);
    font-size: 12px;
    color: var(--ol-text-muted);
    flex: 1;
    text-align: right;
  }

  .ol-sm-transition-event {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ol-violet);
    background: var(--ol-violet-bg);
    padding: 2px 8px;
    border-radius: 4px;
  }

  .ol-sm-transition-highlight {
    background: var(--ol-accent-dim);
  }

  /* ================================================================ */
  /*  STATE MACHINE — LEGEND                                           */
  /* ================================================================ */
  .ol-sm-legend {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 20px;
    padding: 14px 20px;
    background: var(--ol-surface);
    border-radius: 8px;
    border: 1px solid var(--ol-border-subtle);
  }

  .ol-sm-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--ol-text-secondary);
    font-family: var(--font-display);
  }

  .ol-sm-legend-swatch {
    width: 24px;
    height: 4px;
    border-radius: 2px;
  }

  .ol-sm-legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .ol-sm-legend-swatch-forward { background: var(--ol-accent); }
  .ol-sm-legend-swatch-reverse { background: var(--ol-amber); border-style: dashed; }
  .ol-sm-legend-swatch-terminal { background: var(--ol-red); }
  .ol-sm-legend-swatch-reinstate { background: var(--ol-cyan); }

  /* ================================================================ */
  /*  OPPORTUNITY DETAIL                                               */
  /* ================================================================ */
  .ol-detail-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
  }

  .ol-detail-opp-number {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--ol-text-muted);
    margin-bottom: 4px;
  }

  .ol-detail-subject {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    color: var(--ol-text);
    margin-bottom: 8px;
  }

  .ol-detail-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
  }

  .ol-detail-meta-item {
    font-size: 13px;
    color: var(--ol-text-secondary);
  }

  .ol-detail-meta-label {
    color: var(--ol-text-muted);
    font-family: var(--font-display);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: block;
    margin-bottom: 2px;
  }

  .ol-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 4px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .ol-badge-draft { background: var(--ol-draft-bg); color: var(--ol-draft); }
  .ol-badge-quote { background: var(--ol-quote-bg); color: var(--ol-quote); }
  .ol-badge-order { background: var(--ol-order-bg); color: var(--ol-order); }
  .ol-badge-red { background: var(--ol-red-bg); color: var(--ol-red); }
  .ol-badge-amber { background: var(--ol-amber-bg); color: var(--ol-amber); }
  .ol-badge-violet { background: var(--ol-violet-bg); color: var(--ol-violet); }
  .ol-badge-cyan { background: var(--ol-cyan-bg); color: var(--ol-cyan); }
  .ol-badge-blue { background: var(--ol-blue-bg); color: var(--ol-blue); }

  .ol-detail-badges {
    display: flex;
    gap: 6px;
    margin-bottom: 12px;
  }

  /* ================================================================ */
  /*  FINANCIAL SUMMARY CARDS                                          */
  /* ================================================================ */
  .ol-fin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
  }

  .ol-fin-card {
    padding: 14px 16px;
    background: var(--ol-surface);
    border: 1px solid var(--ol-border-subtle);
    border-radius: 8px;
    text-align: center;
  }

  .ol-fin-label {
    font-family: var(--font-display);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ol-text-muted);
    margin-bottom: 4px;
  }

  .ol-fin-value {
    font-family: var(--font-mono);
    font-size: 18px;
    font-weight: 700;
    color: var(--ol-text);
  }

  .ol-fin-card-total {
    background: var(--ol-order-bg);
    border-color: var(--ol-order);
  }

  .ol-fin-card-total .ol-fin-value {
    color: var(--ol-order);
  }

  /* ================================================================ */
  /*  TABLE                                                            */
  /* ================================================================ */
  .ol-table-wrap {
    overflow-x: auto;
  }

  .ol-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }

  .ol-table th {
    padding: 10px 14px;
    text-align: left;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ol-text-muted);
    background: var(--ol-table-header-bg);
    border-bottom: 1px solid var(--ol-table-border);
    white-space: nowrap;
  }

  .ol-table th.ol-th-right,
  .ol-table td.ol-td-right {
    text-align: right;
  }

  .ol-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--ol-table-border);
    color: var(--ol-text);
    vertical-align: middle;
  }

  .ol-table tr:hover td {
    background: var(--ol-row-hover);
  }

  .ol-table-mono {
    font-family: var(--font-mono);
    font-size: 12px;
  }

  .ol-table-muted {
    color: var(--ol-text-muted);
  }

  .ol-row-expandable {
    cursor: pointer;
  }

  .ol-row-optional td {
    border-style: dashed;
    opacity: 0.75;
  }

  .ol-expand-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    font-size: 11px;
    color: var(--ol-text-muted);
    transition: transform 0.15s ease;
    margin-right: 6px;
  }

  .ol-expand-icon-open {
    transform: rotate(90deg);
  }

  /* ================================================================ */
  /*  ASSET ASSIGNMENT SUB-TABLE                                       */
  /* ================================================================ */
  .ol-asset-row td {
    background: var(--ol-surface);
    padding: 8px 14px;
    font-size: 12px;
  }

  .ol-asset-row td:first-child {
    padding-left: 44px;
  }

  .ol-status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
    vertical-align: middle;
  }

  .ol-status-dot-allocated { background: var(--ol-blue); }
  .ol-status-dot-dispatched { background: var(--ol-amber); }
  .ol-status-dot-onhire { background: var(--ol-accent); }
  .ol-status-dot-returned { background: var(--ol-cyan); }
  .ol-status-dot-checked { background: var(--ol-violet); }
  .ol-status-dot-virtual { background: var(--ol-amber); border: 1px dashed var(--ol-amber); background: transparent; }

  /* ================================================================ */
  /*  ACTION BUTTONS                                                   */
  /* ================================================================ */
  .ol-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
  }

  .ol-btn {
    padding: 8px 18px;
    border-radius: 6px;
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .ol-btn-primary {
    background: var(--ol-accent);
    color: #fff;
    border-color: var(--ol-accent);
  }

  .ol-btn-primary:hover {
    opacity: 0.9;
    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
  }

  .ol-btn-outline {
    background: transparent;
    color: var(--ol-text-secondary);
    border-color: var(--ol-border);
  }

  .ol-btn-outline:hover {
    background: var(--ol-hover);
    border-color: var(--ol-text-muted);
  }

  .ol-btn-danger {
    background: transparent;
    color: var(--ol-red);
    border-color: var(--ol-red);
  }

  .ol-btn-danger:hover {
    background: var(--ol-red-bg);
  }

  /* ================================================================ */
  /*  VERSION TREE                                                     */
  /* ================================================================ */
  .ol-vt-tree {
    position: relative;
    padding-left: 24px;
  }

  .ol-vt-node {
    position: relative;
    padding: 12px 16px;
    margin-bottom: 8px;
    margin-left: 20px;
    border: 1px solid var(--ol-border);
    border-radius: 8px;
    background: var(--ol-panel);
    cursor: pointer;
    transition: all 0.15s ease;
  }

  .ol-vt-node:hover {
    border-color: var(--ol-text-muted);
    background: var(--ol-hover);
  }

  .ol-vt-node-active {
    border-color: var(--ol-accent);
    box-shadow: 0 0 0 1px var(--ol-accent);
  }

  .ol-vt-node-selected {
    border-color: var(--ol-blue);
    background: var(--ol-blue-bg);
  }

  .ol-vt-node-header {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .ol-vt-version-label {
    font-family: var(--font-mono);
    font-size: 13px;
    font-weight: 700;
    color: var(--ol-text);
  }

  .ol-vt-version-name {
    font-family: var(--font-display);
    font-size: 13px;
    color: var(--ol-text-secondary);
  }

  .ol-vt-version-total {
    font-family: var(--font-mono);
    font-size: 13px;
    font-weight: 600;
    color: var(--ol-text);
    margin-left: auto;
  }

  .ol-vt-version-date {
    font-size: 12px;
    color: var(--ol-text-muted);
  }

  .ol-vt-children {
    margin-left: 24px;
    padding-left: 20px;
    border-left: 2px solid var(--ol-border-subtle);
  }

  .ol-vt-confirmed-marker {
    font-family: var(--font-display);
    font-size: 11px;
    color: var(--ol-accent);
    font-weight: 700;
    letter-spacing: 0.04em;
  }

  /* ================================================================ */
  /*  VERSION COMPARISON                                               */
  /* ================================================================ */
  .ol-vc-panel {
    margin-top: 24px;
  }

  .ol-vc-added td { background: rgba(5, 150, 105, 0.06); }
  .ol-vc-removed td { background: rgba(220, 38, 38, 0.06); text-decoration: line-through; color: var(--ol-text-muted); }
  .ol-vc-changed td { background: rgba(217, 119, 6, 0.06); }

  .ol-vc-indicator {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
    margin-right: 6px;
    flex-shrink: 0;
  }

  .ol-vc-indicator-add { background: var(--ol-order-bg); color: var(--ol-order); }
  .ol-vc-indicator-remove { background: var(--ol-red-bg); color: var(--ol-red); }
  .ol-vc-indicator-change { background: var(--ol-amber-bg); color: var(--ol-amber); }

  /* ================================================================ */
  /*  EVENT STREAM                                                     */
  /* ================================================================ */
  .ol-es-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
  }

  .ol-es-chip {
    padding: 5px 14px;
    border-radius: 20px;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid var(--ol-border);
    background: var(--ol-panel);
    color: var(--ol-text-secondary);
    transition: all 0.15s ease;
    user-select: none;
  }

  .ol-es-chip:hover {
    border-color: var(--ol-text-muted);
    background: var(--ol-hover);
  }

  .ol-es-chip-active {
    border-color: var(--ol-accent);
    background: var(--ol-accent-dim);
    color: var(--ol-accent);
    font-weight: 600;
  }

  .ol-es-search {
    padding: 6px 14px;
    border-radius: 6px;
    border: 1px solid var(--ol-border);
    background: var(--ol-panel);
    color: var(--ol-text);
    font-size: 13px;
    font-family: var(--font-display);
    outline: none;
    width: 220px;
    margin-left: auto;
    transition: border-color 0.15s ease;
  }

  .ol-es-search:focus {
    border-color: var(--ol-accent);
  }

  .ol-es-event-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 16px;
    border-bottom: 1px solid var(--ol-table-border);
    cursor: pointer;
    transition: background 0.1s ease;
    position: relative;
  }

  .ol-es-event-row:hover {
    background: var(--ol-row-hover);
  }

  .ol-es-event-border {
    width: 3px;
    min-height: 36px;
    border-radius: 2px;
    flex-shrink: 0;
    align-self: stretch;
  }

  .ol-es-border-create { background: var(--ol-accent); }
  .ol-es-border-update { background: var(--ol-blue); }
  .ol-es-border-status { background: var(--ol-amber); }
  .ol-es-border-asset { background: var(--ol-violet); }
  .ol-es-border-version { background: var(--ol-cyan); }

  .ol-es-event-body {
    flex: 1;
    min-width: 0;
  }

  .ol-es-event-top {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .ol-es-event-name {
    font-family: var(--font-mono);
    font-size: 13px;
    font-weight: 600;
    color: var(--ol-text);
  }

  .ol-es-event-actor {
    font-size: 12px;
    color: var(--ol-text-secondary);
    font-family: var(--font-display);
  }

  .ol-es-actor-badge {
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-left: 4px;
  }

  .ol-es-actor-user { background: var(--ol-blue-bg); color: var(--ol-blue); }
  .ol-es-actor-system { background: var(--ol-amber-bg); color: var(--ol-amber); }
  .ol-es-actor-api { background: var(--ol-violet-bg); color: var(--ol-violet); }

  .ol-es-event-time {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ol-text-muted);
    margin-left: auto;
    white-space: nowrap;
    flex-shrink: 0;
  }

  .ol-es-event-detail {
    font-size: 12px;
    color: var(--ol-text-muted);
    margin-top: 4px;
    line-height: 1.5;
  }

  .ol-es-event-payload {
    margin-top: 8px;
    padding: 10px 14px;
    background: var(--ol-surface);
    border: 1px solid var(--ol-border-subtle);
    border-radius: 6px;
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ol-text-secondary);
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
  }

  .ol-es-payload-key {
    color: var(--ol-text-muted);
    font-weight: 600;
  }

  .ol-es-correlation {
    margin-top: 6px;
    font-size: 11px;
    color: var(--ol-cyan);
    font-style: italic;
  }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes olFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes olPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
  }

  @keyframes olSlideDown {
    from { opacity: 0; max-height: 0; }
    to { opacity: 1; max-height: 600px; }
  }

  .ol-animate-in {
    animation: olFadeIn 0.2s ease forwards;
  }

  .ol-animate-pulse {
    animation: olPulse 2s ease-in-out infinite;
  }

  .ol-animate-slide {
    animation: olSlideDown 0.25s ease forwards;
    overflow: hidden;
  }

  /* ================================================================ */
  /*  EMPTY / UTILITY                                                  */
  /* ================================================================ */
  .ol-empty {
    padding: 40px 20px;
    text-align: center;
    color: var(--ol-text-muted);
    font-size: 13px;
    font-family: var(--font-display);
  }

  .ol-mono { font-family: var(--font-mono); }
  .ol-muted { color: var(--ol-text-muted); }
  .ol-nowrap { white-space: nowrap; }
</style>

<div class="ol-page" x-data="opportunityLifecycle()" x-cloak>
  <div class="ol-container">

    {{-- ============================================================ --}}
    {{--  HEADER                                                        --}}
    {{-- ============================================================ --}}
    <div class="ol-header">
      <h1 class="ol-header-title">Opportunity Lifecycle</h1>
      <p class="ol-header-subtitle">Two-Axis State Model & Event Sourcing</p>
    </div>

    {{-- ============================================================ --}}
    {{--  TAB BAR                                                       --}}
    {{-- ============================================================ --}}
    <div class="ol-tabs">
      <template x-for="t in tabs" :key="t.id">
        <button class="ol-tab"
                :class="{ 'ol-tab-active': activeTab === t.id }"
                @click="activeTab = t.id"
                x-text="t.label"></button>
      </template>
    </div>

    {{-- ============================================================ --}}
    {{--  STATE MACHINE TAB                                             --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'state-machine'" x-transition class="ol-animate-in">

      {{-- Three-column state diagram --}}
      <div class="ol-panel" style="margin-bottom: 20px;">
        <div class="ol-panel-header">
          <span class="ol-panel-title">State Diagram</span>
          <span style="font-size: 12px; color: var(--ol-text-muted);">Click a status to see available transitions</span>
        </div>
        <div class="ol-panel-body">
          <div class="ol-sm-grid">

            {{-- Draft column --}}
            <div class="ol-sm-column ol-sm-column-draft">
              <div class="ol-sm-col-header ol-sm-col-header-draft">
                <span>&#9679;</span> Draft
              </div>
              <div class="ol-sm-col-body">
                <template x-for="s in stateStatuses.draft" :key="s.key">
                  <div class="ol-sm-status-box"
                       :class="{ 'ol-sm-status-box-active': selectedStatus === s.key }"
                       @click="selectStatus(s.key)">
                    <div class="ol-sm-status-name" x-text="s.name"></div>
                    <div class="ol-sm-status-value" x-text="'state=0, status=' + s.value"></div>
                  </div>
                </template>
              </div>
            </div>

            {{-- Quotation column --}}
            <div class="ol-sm-column ol-sm-column-quote">
              <div class="ol-sm-col-header ol-sm-col-header-quote">
                <span>&#9679;</span> Quotation
              </div>
              <div class="ol-sm-col-body">
                <template x-for="s in stateStatuses.quotation" :key="s.key">
                  <div class="ol-sm-status-box"
                       :class="{ 'ol-sm-status-box-active': selectedStatus === s.key }"
                       @click="selectStatus(s.key)">
                    <div class="ol-sm-status-name" x-text="s.name"></div>
                    <div class="ol-sm-status-value" x-text="'state=1, status=' + s.value"></div>
                    <div x-show="s.terminal" class="ol-sm-terminal-indicator" title="Terminal state"></div>
                    <div x-show="s.pause" class="ol-sm-pause-indicator" title="Paused state"></div>
                  </div>
                </template>
              </div>
            </div>

            {{-- Order column --}}
            <div class="ol-sm-column ol-sm-column-order">
              <div class="ol-sm-col-header ol-sm-col-header-order">
                <span>&#9679;</span> Order
              </div>
              <div class="ol-sm-col-body">
                <template x-for="s in stateStatuses.order" :key="s.key">
                  <div class="ol-sm-status-box"
                       :class="{ 'ol-sm-status-box-active': selectedStatus === s.key }"
                       @click="selectStatus(s.key)">
                    <div class="ol-sm-status-name" x-text="s.name"></div>
                    <div class="ol-sm-status-value" x-text="'state=2, status=' + s.value"></div>
                    <div x-show="s.terminal" class="ol-sm-terminal-indicator" title="Terminal state"></div>
                  </div>
                </template>
              </div>
            </div>
          </div>

          {{-- Legend --}}
          <div class="ol-sm-legend">
            <div class="ol-sm-legend-item">
              <div class="ol-sm-legend-dot" style="background: var(--ol-draft);"></div> Draft
            </div>
            <div class="ol-sm-legend-item">
              <div class="ol-sm-legend-dot" style="background: var(--ol-quote);"></div> Quotation
            </div>
            <div class="ol-sm-legend-item">
              <div class="ol-sm-legend-dot" style="background: var(--ol-order);"></div> Order
            </div>
            <div class="ol-sm-legend-item">
              <div class="ol-sm-legend-swatch ol-sm-legend-swatch-forward"></div> Forward
            </div>
            <div class="ol-sm-legend-item">
              <div class="ol-sm-legend-swatch ol-sm-legend-swatch-reverse" style="border: 1px dashed var(--ol-amber); background: transparent;"></div> Reverse
            </div>
            <div class="ol-sm-legend-item">
              <div class="ol-sm-legend-swatch ol-sm-legend-swatch-terminal"></div> Terminal
            </div>
            <div class="ol-sm-legend-item">
              <div class="ol-sm-legend-swatch ol-sm-legend-swatch-reinstate"></div> Reinstate
            </div>
            <div class="ol-sm-legend-item">
              <div class="ol-sm-legend-dot ol-sm-terminal-indicator" style="position:static;"></div> Terminal
            </div>
            <div class="ol-sm-legend-item">
              <div class="ol-sm-legend-dot ol-sm-pause-indicator" style="position:static;"></div> Paused
            </div>
          </div>
        </div>
      </div>

      {{-- Transitions panel --}}
      <div class="ol-panel" x-show="selectedStatus" x-transition>
        <div class="ol-panel-header">
          <span class="ol-panel-title">
            Transitions from <span class="ol-mono" x-text="selectedStatus"></span>
          </span>
          <button class="ol-btn ol-btn-outline" @click="selectedStatus = null" style="padding: 4px 12px; font-size: 12px;">Clear</button>
        </div>
        <div class="ol-panel-body ol-sm-transitions">
          <template x-for="tr in activeTransitions" :key="tr.from + '-' + tr.to">
            <div class="ol-sm-transition-row" :class="{ 'ol-sm-transition-highlight': tr.highlighted }">
              <span class="ol-sm-transition-from" x-text="tr.from"></span>
              <span class="ol-sm-transition-arrow"
                    :class="{
                      'ol-sm-transition-arrow-forward': tr.type === 'forward',
                      'ol-sm-transition-arrow-reverse': tr.type === 'reverse',
                      'ol-sm-transition-arrow-terminal': tr.type === 'terminal',
                      'ol-sm-transition-arrow-reinstate': tr.type === 'reinstate',
                    }"
                    x-text="tr.type === 'reverse' ? '&#8592;&#8943;' : '&#8594;'"></span>
              <span class="ol-sm-transition-to" x-text="tr.to"></span>
              <span class="ol-sm-transition-label" x-text="tr.label"></span>
              <span class="ol-sm-transition-event" x-text="tr.event"></span>
            </div>
          </template>
          <div x-show="activeTransitions.length === 0" class="ol-empty">
            No transitions defined from this status.
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  OPPORTUNITY DETAIL TAB                                        --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'detail'" x-transition class="ol-animate-in">

      {{-- Header --}}
      <div class="ol-panel" style="margin-bottom: 20px;">
        <div class="ol-panel-body">
          <div class="ol-detail-header">
            <div>
              <div class="ol-detail-opp-number">OPP-0000042</div>
              <div class="ol-detail-subject">Glastonbury Main Stage Lighting</div>
              <div class="ol-detail-badges">
                <span class="ol-badge ol-badge-order">Order</span>
                <span class="ol-badge ol-badge-order">On Hire</span>
              </div>
              <div class="ol-detail-meta">
                <div class="ol-detail-meta-item">
                  <span class="ol-detail-meta-label">Member</span>
                  Festival Republic Ltd
                </div>
                <div class="ol-detail-meta-item">
                  <span class="ol-detail-meta-label">Store</span>
                  London HQ
                </div>
                <div class="ol-detail-meta-item">
                  <span class="ol-detail-meta-label">Owner</span>
                  James Wilson
                </div>
                <div class="ol-detail-meta-item">
                  <span class="ol-detail-meta-label">Dates</span>
                  10 Mar 2026 &rarr; 15 Mar 2026
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Financial summary --}}
      <div class="ol-fin-grid">
        <div class="ol-fin-card">
          <div class="ol-fin-label">Rental</div>
          <div class="ol-fin-value">&pound;12,550.00</div>
        </div>
        <div class="ol-fin-card">
          <div class="ol-fin-label">Sale</div>
          <div class="ol-fin-value">&pound;0.00</div>
        </div>
        <div class="ol-fin-card">
          <div class="ol-fin-label">Service</div>
          <div class="ol-fin-value">&pound;2,400.00</div>
        </div>
        <div class="ol-fin-card">
          <div class="ol-fin-label">Sub-rental</div>
          <div class="ol-fin-value">&pound;1,000.00</div>
        </div>
        <div class="ol-fin-card">
          <div class="ol-fin-label">Subtotal</div>
          <div class="ol-fin-value">&pound;15,950.00</div>
        </div>
        <div class="ol-fin-card">
          <div class="ol-fin-label">Tax (20%)</div>
          <div class="ol-fin-value">&pound;3,190.00</div>
        </div>
        <div class="ol-fin-card ol-fin-card-total">
          <div class="ol-fin-label">Total</div>
          <div class="ol-fin-value">&pound;19,140.00</div>
        </div>
      </div>

      {{-- Line items table --}}
      <div class="ol-panel" style="margin-bottom: 20px;">
        <div class="ol-panel-header">
          <span class="ol-panel-title">Line Items</span>
          <span style="font-size: 12px; color: var(--ol-text-muted);">7 items</span>
        </div>
        <div class="ol-table-wrap">
          <table class="ol-table">
            <thead>
              <tr>
                <th style="width: 30px;">#</th>
                <th>Product</th>
                <th>Type</th>
                <th class="ol-th-right">Qty</th>
                <th class="ol-th-right">Unit Price</th>
                <th>Period</th>
                <th class="ol-th-right">Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="(item, idx) in lineItems" :key="item.id">
                <template x-if="true">
                  <tr>
                    <td colspan="8" style="padding: 0; border: none;">
                      {{-- Main row --}}
                      <table class="ol-table" style="margin: 0;">
                        <tbody>
                          <tr :class="{ 'ol-row-expandable': item.assets, 'ol-row-optional': item.optional }"
                              @click="item.assets && toggleItemExpand(item.id)">
                            <td style="width: 30px;">
                              <span x-show="item.assets" class="ol-expand-icon" :class="{ 'ol-expand-icon-open': expandedItems[item.id] }">&#9654;</span>
                              <span x-show="!item.assets" x-text="idx + 1" class="ol-table-muted"></span>
                            </td>
                            <td>
                              <span x-text="item.product" style="font-weight: 500;"></span>
                              <span x-show="item.optional" class="ol-badge ol-badge-amber" style="margin-left: 6px; font-size: 10px; padding: 1px 6px;">Optional</span>
                            </td>
                            <td><span class="ol-badge" :class="'ol-badge-' + item.typeBadge" x-text="item.type"></span></td>
                            <td class="ol-td-right ol-table-mono" x-text="item.qty"></td>
                            <td class="ol-td-right ol-table-mono" x-text="item.unitPrice"></td>
                            <td class="ol-table-muted" x-text="item.period"></td>
                            <td class="ol-td-right ol-table-mono" style="font-weight: 600;" x-text="item.total"></td>
                            <td>
                              <span x-show="item.status" class="ol-badge" :class="'ol-badge-' + item.statusBadge" x-text="item.status"></span>
                              <span x-show="!item.status" class="ol-table-muted">&mdash;</span>
                            </td>
                          </tr>

                          {{-- Asset sub-rows --}}
                          <template x-if="item.assets && expandedItems[item.id]">
                            <template x-for="asset in item.assets" :key="asset.assetNo">
                              <tr class="ol-asset-row ol-animate-slide">
                                <td></td>
                                <td class="ol-table-mono" x-text="asset.assetNo"></td>
                                <td class="ol-table-mono" x-text="asset.serial"></td>
                                <td>
                                  <span class="ol-status-dot" :class="'ol-status-dot-' + asset.statusDot"></span>
                                  <span x-text="asset.status"></span>
                                </td>
                                <td x-text="asset.container || '—'"></td>
                                <td x-text="asset.allocated"></td>
                                <td x-text="asset.dispatched"></td>
                                <td class="ol-table-muted" x-text="asset.notes || '—'"></td>
                              </tr>
                            </template>
                          </template>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                </template>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Action buttons --}}
      <div class="ol-actions">
        <button class="ol-btn ol-btn-primary">&#10003; Check In Equipment</button>
        <button class="ol-btn ol-btn-outline">+ Add Item</button>
        <button class="ol-btn ol-btn-danger">&#10005; Cancel Order</button>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  VERSION TREE TAB                                              --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'version-tree'" x-transition class="ol-animate-in">
      <div class="ol-panel" style="margin-bottom: 20px;">
        <div class="ol-panel-header">
          <span class="ol-panel-title">Version Tree</span>
          <span style="font-size: 12px; color: var(--ol-text-muted);">6 versions across 3 branches</span>
        </div>
        <div class="ol-panel-body">
          <div class="ol-vt-tree">
            <template x-for="node in versionTree" :key="node.id">
              <div>
                {{-- Node --}}
                <div class="ol-vt-node"
                     :class="{
                       'ol-vt-node-active': node.confirmed,
                       'ol-vt-node-selected': selectedVersion === node.id,
                     }"
                     @click="selectVersion(node.id)">
                  <div class="ol-vt-node-header">
                    <span class="ol-vt-version-label" x-text="node.label"></span>
                    <span class="ol-badge" :class="'ol-badge-' + node.typeBadge" x-text="node.type"></span>
                    <span class="ol-badge" :class="'ol-badge-' + node.statusBadge" x-text="node.status"></span>
                    <span x-show="node.confirmed" class="ol-vt-confirmed-marker">&#10003; CONFIRMED</span>
                    <span class="ol-vt-version-total" x-text="node.total"></span>
                  </div>
                  <div style="display: flex; gap: 12px; margin-top: 6px; align-items: center;">
                    <span class="ol-vt-version-name" x-text="'\"' + node.name + '\"'"></span>
                    <span class="ol-vt-version-date" x-text="'Sent ' + node.sent"></span>
                  </div>
                </div>

                {{-- Children --}}
                <template x-if="node.children && node.children.length > 0">
                  <div class="ol-vt-children">
                    <template x-for="child in node.children" :key="child.id">
                      <div>
                        <div class="ol-vt-node"
                             :class="{
                               'ol-vt-node-active': child.confirmed,
                               'ol-vt-node-selected': selectedVersion === child.id,
                             }"
                             @click="selectVersion(child.id)">
                          <div class="ol-vt-node-header">
                            <span class="ol-vt-version-label" x-text="child.label"></span>
                            <span class="ol-badge" :class="'ol-badge-' + child.typeBadge" x-text="child.type"></span>
                            <span class="ol-badge" :class="'ol-badge-' + child.statusBadge" x-text="child.status"></span>
                            <span x-show="child.confirmed" class="ol-vt-confirmed-marker">&#10003; CONFIRMED</span>
                            <span class="ol-vt-version-total" x-text="child.total"></span>
                          </div>
                          <div style="display: flex; gap: 12px; margin-top: 6px; align-items: center;">
                            <span class="ol-vt-version-name" x-text="'\"' + child.name + '\"'"></span>
                            <span class="ol-vt-version-date" x-text="'Sent ' + child.sent"></span>
                          </div>
                        </div>

                        {{-- Grandchildren --}}
                        <template x-if="child.children && child.children.length > 0">
                          <div class="ol-vt-children">
                            <template x-for="gc in child.children" :key="gc.id">
                              <div class="ol-vt-node"
                                   :class="{
                                     'ol-vt-node-active': gc.confirmed,
                                     'ol-vt-node-selected': selectedVersion === gc.id,
                                   }"
                                   @click="selectVersion(gc.id)">
                                <div class="ol-vt-node-header">
                                  <span class="ol-vt-version-label" x-text="gc.label"></span>
                                  <span class="ol-badge" :class="'ol-badge-' + gc.typeBadge" x-text="gc.type"></span>
                                  <span class="ol-badge" :class="'ol-badge-' + gc.statusBadge" x-text="gc.status"></span>
                                  <span x-show="gc.confirmed" class="ol-vt-confirmed-marker">&#10003; CONFIRMED</span>
                                  <span class="ol-vt-version-total" x-text="gc.total"></span>
                                </div>
                                <div style="display: flex; gap: 12px; margin-top: 6px; align-items: center;">
                                  <span class="ol-vt-version-name" x-text="'\"' + gc.name + '\"'"></span>
                                  <span class="ol-vt-version-date" x-text="'Sent ' + gc.sent"></span>
                                </div>
                              </div>
                            </template>
                          </div>
                        </template>
                      </div>
                    </template>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </div>
      </div>

      {{-- Version comparison --}}
      <div x-show="selectedVersion && selectedVersionData" x-transition class="ol-vc-panel">
        <div class="ol-panel">
          <div class="ol-panel-header">
            <span class="ol-panel-title">
              Comparing <span class="ol-mono" x-text="selectedVersionData?.label"></span> vs
              <span class="ol-mono">v3</span> (Confirmed)
            </span>
          </div>
          <div class="ol-table-wrap">
            <table class="ol-table">
              <thead>
                <tr>
                  <th style="width: 30px;"></th>
                  <th>Product</th>
                  <th class="ol-th-right">Qty</th>
                  <th class="ol-th-right">Unit Price</th>
                  <th class="ol-th-right">Total</th>
                  <th>Change</th>
                </tr>
              </thead>
              <tbody>
                <template x-for="diff in selectedVersionDiff" :key="diff.product">
                  <tr :class="{
                    'ol-vc-added': diff.change === 'added',
                    'ol-vc-removed': diff.change === 'removed',
                    'ol-vc-changed': diff.change === 'changed',
                  }">
                    <td>
                      <span x-show="diff.change === 'added'" class="ol-vc-indicator ol-vc-indicator-add">+</span>
                      <span x-show="diff.change === 'removed'" class="ol-vc-indicator ol-vc-indicator-remove">&minus;</span>
                      <span x-show="diff.change === 'changed'" class="ol-vc-indicator ol-vc-indicator-change">~</span>
                      <span x-show="diff.change === 'same'" class="ol-table-muted">&nbsp;</span>
                    </td>
                    <td x-text="diff.product"></td>
                    <td class="ol-td-right ol-table-mono" x-text="diff.qty"></td>
                    <td class="ol-td-right ol-table-mono" x-text="diff.unitPrice"></td>
                    <td class="ol-td-right ol-table-mono" x-text="diff.total" style="font-weight: 600;"></td>
                    <td>
                      <span x-show="diff.note" class="ol-table-muted" style="font-size: 12px;" x-text="diff.note"></span>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  EVENT STREAM TAB                                              --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'event-stream'" x-transition class="ol-animate-in">

      {{-- Filters --}}
      <div class="ol-es-filters">
        <template x-for="f in eventFilters" :key="f.id">
          <button class="ol-es-chip"
                  :class="{ 'ol-es-chip-active': activeEventFilter === f.id }"
                  @click="activeEventFilter = f.id"
                  x-text="f.label"></button>
        </template>
        <input type="text" class="ol-es-search" placeholder="Search events..."
               x-model="eventSearch" />
      </div>

      {{-- Event list --}}
      <div class="ol-panel">
        <div class="ol-panel-header">
          <span class="ol-panel-title">Event Log</span>
          <span style="font-size: 12px; color: var(--ol-text-muted);" x-text="filteredEvents.length + ' events'"></span>
        </div>
        <div>
          <template x-for="(ev, i) in filteredEvents" :key="ev.id">
            <div class="ol-es-event-row" @click="toggleEventExpand(ev.id)">
              <div class="ol-es-event-border" :class="'ol-es-border-' + ev.category"></div>
              <div class="ol-es-event-body">
                <div class="ol-es-event-top">
                  <span class="ol-es-event-name" x-text="ev.event"></span>
                  <span class="ol-es-event-actor">
                    <span x-text="ev.actor"></span>
                    <span class="ol-es-actor-badge" :class="'ol-es-actor-' + ev.actorType" x-text="ev.actorType"></span>
                  </span>
                  <span class="ol-es-event-time" x-text="ev.timestamp"></span>
                </div>
                <div class="ol-es-event-detail" x-text="ev.detail"></div>

                {{-- Expanded payload --}}
                <template x-if="expandedEvents[ev.id]">
                  <div class="ol-animate-slide">
                    <div class="ol-es-event-payload">
                      <template x-for="(val, key) in ev.payload" :key="key">
                        <div><span class="ol-es-payload-key" x-text="key + ': '"></span><span x-text="val"></span></div>
                      </template>
                    </div>
                    <div x-show="ev.correlation" class="ol-es-correlation" x-text="ev.correlation"></div>
                  </div>
                </template>
              </div>
            </div>
          </template>
          <div x-show="filteredEvents.length === 0" class="ol-empty">
            No events match the current filter.
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

@verbatim
<script>
function opportunityLifecycle() {
  return {
    /* ============================================================== */
    /*  TABS                                                           */
    /* ============================================================== */
    activeTab: 'state-machine',
    tabs: [
      { id: 'state-machine', label: 'State Machine' },
      { id: 'detail', label: 'Opportunity Detail' },
      { id: 'version-tree', label: 'Version Tree' },
      { id: 'event-stream', label: 'Event Stream' },
    ],

    /* ============================================================== */
    /*  STATE MACHINE DATA                                             */
    /* ============================================================== */
    selectedStatus: null,
    stateStatuses: {
      draft: [
        { key: 'draft/open', name: 'Open', value: 0, terminal: false, pause: false },
      ],
      quotation: [
        { key: 'quotation/provisional', name: 'Provisional', value: 0, terminal: false, pause: false },
        { key: 'quotation/reserved', name: 'Reserved', value: 1, terminal: false, pause: false },
        { key: 'quotation/lost', name: 'Lost', value: 2, terminal: true, pause: false },
        { key: 'quotation/dead', name: 'Dead', value: 3, terminal: true, pause: false },
        { key: 'quotation/postponed', name: 'Postponed', value: 4, terminal: false, pause: true },
      ],
      order: [
        { key: 'order/active', name: 'Active', value: 0, terminal: false, pause: false },
        { key: 'order/dispatched', name: 'Dispatched', value: 1, terminal: false, pause: false },
        { key: 'order/on-hire', name: 'On Hire', value: 2, terminal: false, pause: false },
        { key: 'order/returned', name: 'Returned', value: 3, terminal: false, pause: false },
        { key: 'order/checked', name: 'Checked', value: 4, terminal: false, pause: false },
        { key: 'order/complete', name: 'Complete', value: 5, terminal: false, pause: false },
        { key: 'order/cancelled', name: 'Cancelled', value: 6, terminal: true, pause: false },
      ],
    },

    transitions: [
      // Forward — cross-state
      { from: 'draft/open', to: 'quotation/provisional', label: 'Convert to Quote', event: 'OpportunityConvertedToQuote', type: 'forward' },
      { from: 'quotation/provisional', to: 'order/active', label: 'Convert to Order', event: 'OpportunityConvertedToOrder', type: 'forward' },
      { from: 'quotation/reserved', to: 'order/active', label: 'Convert to Order', event: 'OpportunityConvertedToOrder', type: 'forward' },

      // Forward — within quotation
      { from: 'quotation/provisional', to: 'quotation/reserved', label: 'Reserve', event: 'OpportunityReserved', type: 'forward' },

      // Forward — within order (linear flow)
      { from: 'order/active', to: 'order/dispatched', label: 'Begin Dispatch', event: 'OpportunityStatusPromoted', type: 'forward' },
      { from: 'order/dispatched', to: 'order/on-hire', label: 'All Dispatched', event: 'OpportunityStatusPromoted', type: 'forward' },
      { from: 'order/on-hire', to: 'order/returned', label: 'Return Equipment', event: 'OpportunityStatusPromoted', type: 'forward' },
      { from: 'order/returned', to: 'order/checked', label: 'Complete Check', event: 'OpportunityStatusPromoted', type: 'forward' },
      { from: 'order/checked', to: 'order/complete', label: 'Finalise', event: 'OpportunityCompleted', type: 'forward' },

      // Terminal — quotation
      { from: 'quotation/provisional', to: 'quotation/lost', label: 'Mark as Lost', event: 'OpportunityLost', type: 'terminal' },
      { from: 'quotation/provisional', to: 'quotation/dead', label: 'Mark as Dead', event: 'OpportunityDead', type: 'terminal' },
      { from: 'quotation/provisional', to: 'quotation/postponed', label: 'Postpone', event: 'OpportunityPostponed', type: 'terminal' },
      { from: 'quotation/reserved', to: 'quotation/lost', label: 'Mark as Lost', event: 'OpportunityLost', type: 'terminal' },
      { from: 'quotation/reserved', to: 'quotation/dead', label: 'Mark as Dead', event: 'OpportunityDead', type: 'terminal' },
      { from: 'quotation/reserved', to: 'quotation/postponed', label: 'Postpone', event: 'OpportunityPostponed', type: 'terminal' },

      // Terminal — order
      { from: 'order/active', to: 'order/cancelled', label: 'Cancel Order', event: 'OpportunityCancelled', type: 'terminal' },
      { from: 'order/dispatched', to: 'order/cancelled', label: 'Cancel Order', event: 'OpportunityCancelled', type: 'terminal' },
      { from: 'order/on-hire', to: 'order/cancelled', label: 'Cancel Order', event: 'OpportunityCancelled', type: 'terminal' },

      // Reverse
      { from: 'quotation/provisional', to: 'draft/open', label: 'Revert to Draft', event: 'OpportunityRevertedToDraft', type: 'reverse' },
      { from: 'quotation/reserved', to: 'draft/open', label: 'Revert to Draft', event: 'OpportunityRevertedToDraft', type: 'reverse' },
      { from: 'order/active', to: 'quotation/provisional', label: 'Revert to Quote', event: 'OpportunityRevertedToQuote', type: 'reverse' },

      // Reinstate
      { from: 'quotation/lost', to: 'quotation/provisional', label: 'Reinstate', event: 'OpportunityReinstated', type: 'reinstate' },
      { from: 'quotation/dead', to: 'quotation/provisional', label: 'Reinstate', event: 'OpportunityReinstated', type: 'reinstate' },
      { from: 'quotation/postponed', to: 'quotation/provisional', label: 'Reinstate', event: 'OpportunityReinstated', type: 'reinstate' },
      { from: 'order/cancelled', to: 'order/active', label: 'Reinstate', event: 'OpportunityReinstated', type: 'reinstate' },
    ],

    get activeTransitions() {
      if (!this.selectedStatus) return [];
      return this.transitions.filter(t => t.from === this.selectedStatus || t.to === this.selectedStatus);
    },

    selectStatus(key) {
      this.selectedStatus = this.selectedStatus === key ? null : key;
    },

    /* ============================================================== */
    /*  OPPORTUNITY DETAIL DATA                                        */
    /* ============================================================== */
    expandedItems: {},

    lineItems: [
      {
        id: 1, product: 'MegaPointe', type: 'Rental', typeBadge: 'blue', qty: 6, unitPrice: '\u00a385.00', period: '/day', total: '\u00a32,550.00', status: 'On Hire', statusBadge: 'order', optional: false,
        assets: [
          { assetNo: 'STK-001', serial: 'MP-2019-001', status: 'On Hire', statusDot: 'onhire', container: 'Case A', allocated: '8 Mar', dispatched: '9 Mar', notes: null },
          { assetNo: 'STK-002', serial: 'MP-2019-002', status: 'On Hire', statusDot: 'onhire', container: 'Case A', allocated: '8 Mar', dispatched: '9 Mar', notes: null },
          { assetNo: 'STK-003', serial: 'MP-2020-015', status: 'On Hire', statusDot: 'onhire', container: 'Case B', allocated: '8 Mar', dispatched: '9 Mar', notes: null },
          { assetNo: 'STK-004', serial: 'MP-2020-016', status: 'On Hire', statusDot: 'onhire', container: 'Case B', allocated: '8 Mar', dispatched: '9 Mar', notes: null },
          { assetNo: 'VS-VSI-2026-0042-001', serial: '(virtual)', status: 'On Hire', statusDot: 'virtual', container: null, allocated: '9 Mar', dispatched: '9 Mar', notes: 'Sub-hire' },
          { assetNo: 'VS-VSI-2026-0042-002', serial: '(virtual)', status: 'On Hire', statusDot: 'virtual', container: null, allocated: '9 Mar', dispatched: '9 Mar', notes: 'Sub-hire' },
        ],
      },
      {
        id: 2, product: 'K2 Line Array Module', type: 'Rental', typeBadge: 'blue', qty: 12, unitPrice: '\u00a3120.00', period: '/day', total: '\u00a37,200.00', status: 'On Hire', statusBadge: 'order', optional: false, assets: null,
      },
      {
        id: 3, product: 'Haze Machine MDG', type: 'Rental', typeBadge: 'blue', qty: 2, unitPrice: '\u00a355.00', period: '/day', total: '\u00a3550.00', status: 'Dispatched', statusBadge: 'amber', optional: false, assets: null,
      },
      {
        id: 4, product: 'Cable Loom 30m', type: 'Rental', typeBadge: 'blue', qty: 4, unitPrice: '\u00a325.00', period: '/day', total: '\u00a3500.00', status: 'On Hire', statusBadge: 'order', optional: false, assets: null,
      },
      {
        id: 5, product: 'grandMA3 Lighting Desk', type: 'Rental', typeBadge: 'blue', qty: 1, unitPrice: '\u00a3350.00', period: '/day', total: '\u00a31,750.00', status: 'On Hire', statusBadge: 'order', optional: false, assets: null,
      },
      {
        id: 6, product: 'Lighting Technician', type: 'Service', typeBadge: 'cyan', qty: 3, unitPrice: '\u00a3400.00', period: '/day', total: '\u00a32,400.00', status: null, statusBadge: null, optional: false, assets: null,
      },
      {
        id: 7, product: 'Sub-hire: SuperPointe \u00d74', type: 'Sub-rental', typeBadge: 'violet', qty: 4, unitPrice: '\u00a350.00', period: '/day', total: '\u00a31,000.00', status: 'On Hire', statusBadge: 'order', optional: false, assets: null,
      },
    ],

    toggleItemExpand(id) {
      this.expandedItems[id] = !this.expandedItems[id];
    },

    /* ============================================================== */
    /*  VERSION TREE DATA                                              */
    /* ============================================================== */
    selectedVersion: null,

    versionTree: [
      {
        id: 'v1', label: 'v1', name: 'Initial Quote', total: '\u00a314,200.00', type: 'Revision', typeBadge: 'blue', status: 'Superseded', statusBadge: 'amber', sent: '1 Mar', confirmed: false,
        children: [
          {
            id: 'v2', label: 'v2', name: 'Added K2 System', total: '\u00a318,500.00', type: 'Revision', typeBadge: 'blue', status: 'Superseded', statusBadge: 'amber', sent: '3 Mar', confirmed: false,
            children: [
              { id: 'v3', label: 'v3', name: '10% Discount Applied', total: '\u00a316,650.00', type: 'Revision', typeBadge: 'blue', status: 'Accepted', statusBadge: 'order', sent: '5 Mar', confirmed: true, children: [] },
            ],
          },
          {
            id: 'alt-1', label: 'alt-1', name: 'Budget Option', total: '\u00a39,800.00', type: 'Alternative', typeBadge: 'violet', status: 'Declined', statusBadge: 'red', sent: '3 Mar', confirmed: false,
            children: [
              { id: 'alt-1-v2', label: 'alt-1-v2', name: 'Budget + Crew', total: '\u00a312,200.00', type: 'Alternative', typeBadge: 'violet', status: 'Declined', statusBadge: 'red', sent: '4 Mar', confirmed: false, children: [] },
            ],
          },
          {
            id: 'alt-2', label: 'alt-2', name: 'Premium + Full Crew', total: '\u00a322,400.00', type: 'Alternative', typeBadge: 'violet', status: 'Sent', statusBadge: 'blue', sent: '5 Mar', confirmed: false, children: [],
          },
        ],
      },
    ],

    versionItems: {
      'v1': [
        { product: 'MegaPointe', qty: 6, unitPrice: '\u00a385.00', total: '\u00a32,550.00' },
        { product: 'Haze Machine MDG', qty: 2, unitPrice: '\u00a355.00', total: '\u00a3550.00' },
      ],
      'v2': [
        { product: 'MegaPointe', qty: 6, unitPrice: '\u00a385.00', total: '\u00a32,550.00' },
        { product: 'K2 Line Array Module', qty: 12, unitPrice: '\u00a3120.00', total: '\u00a37,200.00' },
        { product: 'Haze Machine MDG', qty: 2, unitPrice: '\u00a355.00', total: '\u00a3550.00' },
        { product: 'Cable Loom 30m', qty: 4, unitPrice: '\u00a325.00', total: '\u00a3500.00' },
      ],
      'v3': [
        { product: 'MegaPointe', qty: 6, unitPrice: '\u00a376.50', total: '\u00a32,295.00' },
        { product: 'K2 Line Array Module', qty: 12, unitPrice: '\u00a3108.00', total: '\u00a36,480.00' },
        { product: 'Haze Machine MDG', qty: 2, unitPrice: '\u00a349.50', total: '\u00a3495.00' },
        { product: 'Cable Loom 30m', qty: 4, unitPrice: '\u00a322.50', total: '\u00a3450.00' },
        { product: 'grandMA3 Lighting Desk', qty: 1, unitPrice: '\u00a3315.00', total: '\u00a31,575.00' },
      ],
      'alt-1': [
        { product: 'MegaPointe', qty: 4, unitPrice: '\u00a385.00', total: '\u00a31,700.00' },
        { product: 'Haze Machine MDG', qty: 1, unitPrice: '\u00a355.00', total: '\u00a3275.00' },
      ],
      'alt-1-v2': [
        { product: 'MegaPointe', qty: 4, unitPrice: '\u00a385.00', total: '\u00a31,700.00' },
        { product: 'Haze Machine MDG', qty: 1, unitPrice: '\u00a355.00', total: '\u00a3275.00' },
        { product: 'Lighting Technician', qty: 2, unitPrice: '\u00a3400.00', total: '\u00a31,600.00' },
      ],
      'alt-2': [
        { product: 'MegaPointe', qty: 8, unitPrice: '\u00a385.00', total: '\u00a33,400.00' },
        { product: 'K2 Line Array Module', qty: 16, unitPrice: '\u00a3120.00', total: '\u00a39,600.00' },
        { product: 'Haze Machine MDG', qty: 4, unitPrice: '\u00a355.00', total: '\u00a31,100.00' },
        { product: 'Cable Loom 30m', qty: 8, unitPrice: '\u00a325.00', total: '\u00a31,000.00' },
        { product: 'grandMA3 Lighting Desk', qty: 1, unitPrice: '\u00a3350.00', total: '\u00a31,750.00' },
        { product: 'Lighting Technician', qty: 4, unitPrice: '\u00a3400.00', total: '\u00a33,200.00' },
      ],
    },

    get selectedVersionData() {
      return this.findVersion(this.selectedVersion);
    },

    get selectedVersionDiff() {
      if (!this.selectedVersion || this.selectedVersion === 'v3') return [];
      const confirmed = this.versionItems['v3'] || [];
      const selected = this.versionItems[this.selectedVersion] || [];
      const diff = [];
      const confirmedMap = {};
      confirmed.forEach(i => { confirmedMap[i.product] = i; });
      const selectedMap = {};
      selected.forEach(i => { selectedMap[i.product] = i; });

      // Items in selected version
      selected.forEach(item => {
        const cItem = confirmedMap[item.product];
        if (!cItem) {
          diff.push({ ...item, change: 'removed', note: 'Not in confirmed version' });
        } else if (item.unitPrice !== cItem.unitPrice || item.qty !== cItem.qty) {
          diff.push({ ...item, change: 'changed', note: 'Price or qty differs' });
        } else {
          diff.push({ ...item, change: 'same', note: '' });
        }
      });

      // Items only in confirmed
      confirmed.forEach(item => {
        if (!selectedMap[item.product]) {
          diff.push({ ...item, change: 'added', note: 'Added in confirmed version' });
        }
      });

      return diff;
    },

    findVersion(id) {
      if (!id) return null;
      const search = (nodes) => {
        for (const n of nodes) {
          if (n.id === id) return n;
          if (n.children) {
            const found = search(n.children);
            if (found) return found;
          }
        }
        return null;
      };
      return search(this.versionTree);
    },

    selectVersion(id) {
      this.selectedVersion = this.selectedVersion === id ? null : id;
    },

    /* ============================================================== */
    /*  EVENT STREAM DATA                                              */
    /* ============================================================== */
    activeEventFilter: 'all',
    eventSearch: '',
    expandedEvents: {},

    eventFilters: [
      { id: 'all', label: 'All' },
      { id: 'opportunity', label: 'Opportunity' },
      { id: 'items', label: 'Items' },
      { id: 'assets', label: 'Assets' },
      { id: 'versions', label: 'Versions' },
      { id: 'status', label: 'Status Changes' },
    ],

    events: [
      {
        id: 1, timestamp: '28 Feb 09:15', event: 'OpportunityCreated', actor: 'James Wilson', actorType: 'user',
        detail: 'Subject: "Glastonbury Main Stage Lighting", Member: Festival Republic Ltd',
        category: 'create', filterGroup: 'opportunity',
        payload: { subject: 'Glastonbury Main Stage Lighting', member_id: 158, member_name: 'Festival Republic Ltd', store_id: 1, store_name: 'London HQ', starts_at: '2026-03-10', ends_at: '2026-03-15' },
        correlation: null,
      },
      {
        id: 2, timestamp: '28 Feb 09:18', event: 'ItemAddedToOpportunity', actor: 'James Wilson', actorType: 'user',
        detail: 'MegaPointe \u00d76 @ \u00a385/day',
        category: 'update', filterGroup: 'items',
        payload: { product_id: 42, product_name: 'MegaPointe', quantity: 6, unit_price: 8500, period: 'day', line_total: 255000 },
        correlation: null,
      },
      {
        id: 3, timestamp: '28 Feb 09:20', event: 'ItemAddedToOpportunity', actor: 'James Wilson', actorType: 'user',
        detail: 'K2 Line Array \u00d712 @ \u00a3120/day',
        category: 'update', filterGroup: 'items',
        payload: { product_id: 87, product_name: 'K2 Line Array Module', quantity: 12, unit_price: 12000, period: 'day', line_total: 720000 },
        correlation: null,
      },
      {
        id: 4, timestamp: '28 Feb 09:22', event: 'ItemAddedToOpportunity', actor: 'James Wilson', actorType: 'user',
        detail: 'Haze Machine \u00d72 @ \u00a355/day',
        category: 'update', filterGroup: 'items',
        payload: { product_id: 103, product_name: 'Haze Machine MDG', quantity: 2, unit_price: 5500, period: 'day', line_total: 55000 },
        correlation: null,
      },
      {
        id: 5, timestamp: '28 Feb 09:25', event: 'OpportunityConvertedToQuote', actor: 'James Wilson', actorType: 'user',
        detail: '\u2192 Quotation/Provisional',
        category: 'status', filterGroup: 'status',
        payload: { old_state: 'draft', old_status: 'open', new_state: 'quotation', new_status: 'provisional' },
        correlation: null,
      },
      {
        id: 6, timestamp: '1 Mar 10:00', event: 'VersionCreated', actor: 'James Wilson', actorType: 'user',
        detail: 'v1 "Initial Quote"',
        category: 'version', filterGroup: 'versions',
        payload: { version_id: 'v1', label: 'v1', name: 'Initial Quote', total: 1420000, item_count: 3 },
        correlation: null,
      },
      {
        id: 7, timestamp: '1 Mar 10:05', event: 'VersionSent', actor: 'James Wilson', actorType: 'user',
        detail: 'Sent to client via email',
        category: 'version', filterGroup: 'versions',
        payload: { version_id: 'v1', channel: 'email', recipient: 'bookings@festivalrepublic.com' },
        correlation: null,
      },
      {
        id: 8, timestamp: '3 Mar 11:00', event: 'VersionCreated', actor: 'James Wilson', actorType: 'user',
        detail: 'v2 "Added K2 System" (revision of v1)',
        category: 'version', filterGroup: 'versions',
        payload: { version_id: 'v2', label: 'v2', name: 'Added K2 System', parent_version: 'v1', type: 'revision', total: 1850000 },
        correlation: null,
      },
      {
        id: 9, timestamp: '3 Mar 11:02', event: 'ItemAddedToOpportunity', actor: 'James Wilson', actorType: 'user',
        detail: 'Cable Loom \u00d74 @ \u00a325/day',
        category: 'update', filterGroup: 'items',
        payload: { product_id: 211, product_name: 'Cable Loom 30m', quantity: 4, unit_price: 2500, period: 'day', line_total: 50000 },
        correlation: null,
      },
      {
        id: 10, timestamp: '3 Mar 11:05', event: 'VersionCreated', actor: 'James Wilson', actorType: 'user',
        detail: 'alt-1 "Budget Option" (alternative)',
        category: 'version', filterGroup: 'versions',
        payload: { version_id: 'alt-1', label: 'alt-1', name: 'Budget Option', parent_version: 'v1', type: 'alternative', total: 980000 },
        correlation: null,
      },
      {
        id: 11, timestamp: '3 Mar 14:00', event: 'VersionSent', actor: 'James Wilson', actorType: 'user',
        detail: 'v2 + alt-1 sent to client',
        category: 'version', filterGroup: 'versions',
        payload: { versions_sent: ['v2', 'alt-1'], channel: 'email', recipient: 'bookings@festivalrepublic.com' },
        correlation: null,
      },
      {
        id: 12, timestamp: '4 Mar 09:00', event: 'VersionDeclined', actor: 'System (client portal)', actorType: 'system',
        detail: 'alt-1 declined \u2014 "Need full rig"',
        category: 'version', filterGroup: 'versions',
        payload: { version_id: 'alt-1', reason: 'Need full rig', declined_via: 'client_portal' },
        correlation: null,
      },
      {
        id: 13, timestamp: '5 Mar 10:00', event: 'VersionCreated', actor: 'James Wilson', actorType: 'user',
        detail: 'v3 "10% Discount" (revision of v2)',
        category: 'version', filterGroup: 'versions',
        payload: { version_id: 'v3', label: 'v3', name: '10% Discount Applied', parent_version: 'v2', type: 'revision', total: 1665000 },
        correlation: null,
      },
      {
        id: 14, timestamp: '5 Mar 10:02', event: 'ItemDiscountChanged', actor: 'James Wilson', actorType: 'user',
        detail: 'All items: 10% discount applied',
        category: 'update', filterGroup: 'items',
        payload: { discount_type: 'percentage', discount_value: 10, applied_to: 'all_items', items_affected: 5 },
        correlation: null,
      },
      {
        id: 15, timestamp: '5 Mar 10:05', event: 'VersionSent', actor: 'James Wilson', actorType: 'user',
        detail: 'v3 sent to client',
        category: 'version', filterGroup: 'versions',
        payload: { version_id: 'v3', channel: 'email', recipient: 'bookings@festivalrepublic.com' },
        correlation: null,
      },
      {
        id: 16, timestamp: '5 Mar 15:30', event: 'VersionAccepted', actor: 'System (client portal)', actorType: 'system',
        detail: 'v3 accepted',
        category: 'version', filterGroup: 'versions',
        payload: { version_id: 'v3', accepted_via: 'client_portal', accepted_by: 'Sarah Chen (Festival Republic)' },
        correlation: null,
      },
      {
        id: 17, timestamp: '5 Mar 15:31', event: 'OpportunityConvertedToOrder', actor: 'System', actorType: 'system',
        detail: '\u2192 Order/Active, confirmed version: v3',
        category: 'status', filterGroup: 'status',
        payload: { old_state: 'quotation', old_status: 'provisional', new_state: 'order', new_status: 'active', confirmed_version: 'v3' },
        correlation: 'Triggered by VersionAccepted (event #16)',
      },
      {
        id: 18, timestamp: '8 Mar 08:00', event: 'AssetAllocated', actor: 'Dave Murray', actorType: 'user',
        detail: 'MegaPointe STK-001, STK-002 \u2192 Case A',
        category: 'asset', filterGroup: 'assets',
        payload: { item_id: 1, product_name: 'MegaPointe', assets: ['STK-001', 'STK-002'], container: 'Case A' },
        correlation: null,
      },
      {
        id: 19, timestamp: '8 Mar 08:05', event: 'AssetAllocated', actor: 'Dave Murray', actorType: 'user',
        detail: 'MegaPointe STK-003, STK-004 \u2192 Case B',
        category: 'asset', filterGroup: 'assets',
        payload: { item_id: 1, product_name: 'MegaPointe', assets: ['STK-003', 'STK-004'], container: 'Case B' },
        correlation: null,
      },
      {
        id: 20, timestamp: '8 Mar 08:10', event: 'ItemAddedToOpportunity', actor: 'James Wilson', actorType: 'user',
        detail: 'Lighting Technician \u00d73 @ \u00a3400/day (Service)',
        category: 'update', filterGroup: 'items',
        payload: { product_id: 305, product_name: 'Lighting Technician', quantity: 3, unit_price: 40000, period: 'day', type: 'service', line_total: 240000 },
        correlation: null,
      },
      {
        id: 21, timestamp: '9 Mar 07:00', event: 'AssetDispatched', actor: 'Dave Murray', actorType: 'user',
        detail: '4\u00d7 MegaPointe dispatched',
        category: 'asset', filterGroup: 'assets',
        payload: { item_id: 1, product_name: 'MegaPointe', assets_dispatched: ['STK-001', 'STK-002', 'STK-003', 'STK-004'], dispatch_method: 'vehicle', vehicle: 'VAN-003' },
        correlation: null,
      },
      {
        id: 22, timestamp: '9 Mar 07:05', event: 'AssetDispatched', actor: 'Dave Murray', actorType: 'user',
        detail: '12\u00d7 K2, 4\u00d7 Cable dispatched',
        category: 'asset', filterGroup: 'assets',
        payload: { items_dispatched: [{ item_id: 2, product: 'K2 Line Array Module', qty: 12 }, { item_id: 4, product: 'Cable Loom 30m', qty: 4 }], dispatch_method: 'vehicle', vehicle: 'TRUCK-001' },
        correlation: null,
      },
      {
        id: 23, timestamp: '9 Mar 07:10', event: 'BulkQuantityDispatched', actor: 'Dave Murray', actorType: 'user',
        detail: 'Haze Machine \u00d72 dispatched',
        category: 'asset', filterGroup: 'assets',
        payload: { item_id: 3, product_name: 'Haze Machine MDG', quantity_dispatched: 2, dispatch_method: 'vehicle', vehicle: 'TRUCK-001' },
        correlation: null,
      },
      {
        id: 24, timestamp: '9 Mar 12:00', event: 'OpportunityStatusPromoted', actor: 'System', actorType: 'system',
        detail: '\u2192 Order/Dispatched (auto)',
        category: 'status', filterGroup: 'status',
        payload: { old_status: 'active', new_status: 'dispatched', promotion_reason: 'All items have at least partial dispatch' },
        correlation: 'Triggered by AssetDispatched + BulkQuantityDispatched (events #21-#23)',
      },
      {
        id: 25, timestamp: '10 Mar 09:00', event: 'OpportunityStatusPromoted', actor: 'System', actorType: 'system',
        detail: '\u2192 Order/On Hire (all items on site)',
        category: 'status', filterGroup: 'status',
        payload: { old_status: 'dispatched', new_status: 'on_hire', promotion_reason: 'All dispatched assets confirmed on site' },
        correlation: 'Triggered by client confirmation via delivery note signature',
      },
    ],

    get filteredEvents() {
      let result = this.events;

      if (this.activeEventFilter !== 'all') {
        result = result.filter(e => e.filterGroup === this.activeEventFilter);
      }

      if (this.eventSearch.trim()) {
        const q = this.eventSearch.toLowerCase();
        result = result.filter(e =>
          e.event.toLowerCase().includes(q) ||
          e.actor.toLowerCase().includes(q) ||
          e.detail.toLowerCase().includes(q)
        );
      }

      return result;
    },

    toggleEventExpand(id) {
      this.expandedEvents[id] = !this.expandedEvents[id];
    },

    /* ============================================================== */
    /*  INIT                                                           */
    /* ============================================================== */
    init() {
      // Expand first line item by default to showcase assets
      this.expandedItems[1] = true;
    },
  };
}
</script>
@endverbatim
