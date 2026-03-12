<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Plugin System')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  PLUGIN SYSTEM TOKENS — maps to brand system in app.css          */
  /* ================================================================ */
  :root {
    --ps-bg: var(--content-bg);
    --ps-panel: var(--card-bg);
    --ps-surface: var(--base);
    --ps-border: var(--card-border);
    --ps-border-subtle: var(--grey-border);
    --ps-text: var(--text-primary);
    --ps-text-secondary: var(--text-secondary);
    --ps-text-muted: var(--text-muted);
    --ps-accent: var(--green);
    --ps-accent-dim: var(--green-muted);
    --ps-hover: rgba(0, 0, 0, 0.03);
    --ps-shadow: var(--shadow-card);
    --ps-status-active: var(--green);
    --ps-status-active-bg: rgba(5, 150, 105, 0.08);
    --ps-status-disabled: var(--amber);
    --ps-status-disabled-bg: rgba(217, 119, 6, 0.08);
    --ps-status-error: var(--red);
    --ps-status-error-bg: rgba(220, 38, 38, 0.08);
    --ps-hook-event: var(--violet);
    --ps-hook-event-bg: rgba(124, 58, 237, 0.08);
    --ps-hook-filter: var(--cyan);
    --ps-hook-filter-bg: rgba(8, 145, 178, 0.08);
    --ps-hook-validator: var(--amber);
    --ps-hook-validator-bg: rgba(217, 119, 6, 0.08);
    --ps-hook-decorator: var(--rose);
    --ps-hook-decorator-bg: rgba(225, 29, 72, 0.08);
    --ps-perm-defines: var(--green);
    --ps-perm-defines-bg: rgba(5, 150, 101, 0.08);
    --ps-perm-requires: var(--blue);
    --ps-perm-requires-bg: rgba(37, 99, 235, 0.08);
    --ps-stat-total-bg: rgba(37, 99, 235, 0.06);
    --ps-stat-total-color: var(--blue);
    --ps-detail-bg: var(--base);
    --ps-chip-bg: rgba(0, 0, 0, 0.04);
    --ps-chip-border: rgba(0, 0, 0, 0.06);
    --ps-code-bg: rgba(0, 0, 0, 0.04);
    --ps-table-header-bg: var(--table-header-bg);
    --ps-table-border: var(--table-border);
    --ps-row-hover: var(--table-row-hover);
    --ps-json-bg: rgba(15, 23, 42, 0.96);
    --ps-json-text: #e2e8f0;
    --ps-star-fill: #fbbf24;
    --ps-star-empty: var(--grey-border);
  }

  .dark {
    --ps-bg: var(--content-bg);
    --ps-panel: var(--card-bg);
    --ps-surface: var(--navy-mid);
    --ps-border: var(--card-border);
    --ps-border-subtle: #283040;
    --ps-text: var(--text-primary);
    --ps-text-secondary: var(--text-secondary);
    --ps-text-muted: var(--text-muted);
    --ps-accent: var(--green);
    --ps-accent-dim: rgba(5, 150, 105, 0.12);
    --ps-hover: rgba(255, 255, 255, 0.06);
    --ps-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --ps-status-active-bg: rgba(5, 150, 105, 0.15);
    --ps-status-disabled-bg: rgba(217, 119, 6, 0.15);
    --ps-status-error-bg: rgba(220, 38, 38, 0.15);
    --ps-hook-event-bg: rgba(124, 58, 237, 0.15);
    --ps-hook-filter-bg: rgba(8, 145, 178, 0.15);
    --ps-hook-validator-bg: rgba(217, 119, 6, 0.15);
    --ps-hook-decorator-bg: rgba(225, 29, 72, 0.15);
    --ps-perm-defines-bg: rgba(5, 150, 101, 0.15);
    --ps-perm-requires-bg: rgba(37, 99, 235, 0.15);
    --ps-stat-total-bg: rgba(37, 99, 235, 0.12);
    --ps-detail-bg: rgba(255, 255, 255, 0.03);
    --ps-chip-bg: rgba(255, 255, 255, 0.08);
    --ps-chip-border: rgba(255, 255, 255, 0.1);
    --ps-code-bg: rgba(255, 255, 255, 0.06);
    --ps-table-header-bg: var(--table-header-bg);
    --ps-table-border: var(--table-border);
    --ps-row-hover: var(--table-row-hover);
    --ps-json-bg: rgba(15, 23, 42, 0.98);
    --ps-json-text: #94a3b8;
    --ps-star-empty: #374151;
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */
  .ps-page {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 64px);
    background: var(--ps-bg);
    position: relative;
  }

  .ps-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 32px 64px;
    width: 100%;
  }

  /* ================================================================ */
  /*  HEADER                                                           */
  /* ================================================================ */
  .ps-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
  }

  .ps-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .ps-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    color: var(--ps-text);
    letter-spacing: -0.01em;
    line-height: 1;
  }

  .ps-subtitle {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ps-accent);
    margin-top: 2px;
  }

  /* ================================================================ */
  /*  TAB BAR                                                          */
  /* ================================================================ */
  .ps-tabs {
    display: flex;
    gap: 2px;
    border-bottom: 1px solid var(--ps-border);
    margin-bottom: 20px;
  }

  .ps-tab {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    padding: 10px 18px;
    color: var(--ps-text-muted);
    cursor: pointer;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    transition: all 0.15s;
    position: relative;
    bottom: -1px;
  }

  .ps-tab:hover { color: var(--ps-text-secondary); }

  .ps-tab.active {
    color: var(--ps-accent);
    border-bottom-color: var(--ps-accent);
  }

  /* ================================================================ */
  /*  STATS BAR                                                        */
  /* ================================================================ */
  .ps-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
  }

  .ps-stat-card {
    background: var(--ps-panel);
    border: 1px solid var(--ps-border);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .ps-stat-card:hover {
    border-color: var(--ps-border-subtle);
    box-shadow: var(--ps-shadow);
  }

  .ps-stat-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .ps-stat-icon svg { width: 18px; height: 18px; }

  .ps-stat-icon-total { background: var(--ps-stat-total-bg); color: var(--ps-stat-total-color); }
  .ps-stat-icon-active { background: var(--ps-status-active-bg); color: var(--ps-status-active); }
  .ps-stat-icon-disabled { background: var(--ps-status-disabled-bg); color: var(--ps-status-disabled); }
  .ps-stat-icon-hooks { background: var(--ps-hook-event-bg); color: var(--ps-hook-event); }

  .ps-stat-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ps-text-muted);
    margin-bottom: 2px;
  }

  .ps-stat-value {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 700;
    color: var(--ps-text);
    line-height: 1;
  }

  /* ================================================================ */
  /*  PLUGIN GRID                                                      */
  /* ================================================================ */
  .ps-plugin-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  .ps-plugin-card {
    background: var(--ps-panel);
    border: 1px solid var(--ps-border);
    padding: 18px 20px;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .ps-plugin-card:hover {
    border-color: var(--ps-border-subtle);
    box-shadow: var(--ps-shadow);
  }

  .ps-plugin-card.expanded {
    grid-column: 1 / -1;
  }

  .ps-plugin-top {
    display: flex;
    align-items: flex-start;
    gap: 14px;
  }

  .ps-plugin-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-display);
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
    flex-shrink: 0;
  }

  .ps-plugin-info {
    flex: 1;
    min-width: 0;
  }

  .ps-plugin-name-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 2px;
  }

  .ps-plugin-name {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 700;
    color: var(--ps-text);
  }

  .ps-plugin-version {
    font-family: var(--font-mono);
    font-size: 10px;
    padding: 1px 6px;
    background: var(--ps-chip-bg);
    border: 1px solid var(--ps-chip-border);
    color: var(--ps-text-muted);
  }

  .ps-plugin-vendor {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ps-text-muted);
    margin-bottom: 6px;
  }

  .ps-plugin-desc {
    font-family: var(--font-display);
    font-size: 12px;
    color: var(--ps-text-secondary);
    line-height: 1.5;
    margin-bottom: 10px;
  }

  .ps-plugin-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 12px;
  }

  .ps-plugin-tag {
    font-family: var(--font-mono);
    font-size: 10px;
    padding: 2px 8px;
    background: var(--ps-chip-bg);
    border: 1px solid var(--ps-chip-border);
    color: var(--ps-text-muted);
  }

  /* Status badge */
  .ps-status-badge {
    display: inline-flex;
    align-items: center;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 2px 10px;
    white-space: nowrap;
  }

  .ps-status-active { background: var(--ps-status-active-bg); color: var(--ps-status-active); }
  .ps-status-disabled { background: var(--ps-status-disabled-bg); color: var(--ps-status-disabled); }
  .ps-status-error { background: var(--ps-status-error-bg); color: var(--ps-status-error); }

  .ps-plugin-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 2px;
  }

  /* Toggle switch */
  .ps-toggle {
    position: relative;
    width: 34px;
    height: 18px;
    background: var(--ps-border-subtle);
    border-radius: 9px;
    cursor: pointer;
    transition: background 0.2s;
    flex-shrink: 0;
  }

  .ps-toggle.on { background: var(--ps-accent); }

  .ps-toggle-knob {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 14px;
    height: 14px;
    background: #ffffff;
    border-radius: 50%;
    transition: transform 0.2s;
  }

  .ps-toggle.on .ps-toggle-knob { transform: translateX(16px); }

  /* Action buttons */
  .ps-btn {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    padding: 5px 12px;
    border: 1px solid var(--ps-border);
    background: var(--ps-panel);
    color: var(--ps-text-secondary);
    cursor: pointer;
    transition: all 0.15s;
  }

  .ps-btn:hover { border-color: var(--ps-text-muted); color: var(--ps-text); }

  .ps-btn-danger:hover { border-color: var(--ps-status-error); color: var(--ps-status-error); }

  .ps-btn-accent {
    background: var(--ps-accent);
    color: #ffffff;
    border-color: var(--ps-accent);
  }

  .ps-btn-accent:hover { opacity: 0.9; color: #ffffff; }

  /* ================================================================ */
  /*  DETAIL PANEL (expanded plugin)                                   */
  /* ================================================================ */
  .ps-detail {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--ps-border);
    animation: psSlideDown 0.2s ease both;
  }

  .ps-detail-tabs {
    display: flex;
    gap: 2px;
    margin-bottom: 16px;
    border-bottom: 1px solid var(--ps-border);
  }

  .ps-detail-tab {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    padding: 8px 14px;
    color: var(--ps-text-muted);
    cursor: pointer;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    transition: all 0.15s;
    position: relative;
    bottom: -1px;
  }

  .ps-detail-tab:hover { color: var(--ps-text-secondary); }
  .ps-detail-tab.active { color: var(--ps-accent); border-bottom-color: var(--ps-accent); }

  .ps-detail-section {
    margin-bottom: 16px;
  }

  .ps-detail-label {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ps-text-muted);
    margin-bottom: 8px;
  }

  .ps-detail-value {
    font-family: var(--font-display);
    font-size: 12px;
    color: var(--ps-text-secondary);
    line-height: 1.5;
  }

  .ps-detail-meta-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
  }

  .ps-detail-meta-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .ps-detail-meta-key {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ps-text-muted);
  }

  .ps-detail-meta-val {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ps-text-secondary);
  }

  /* Permission badges */
  .ps-perm-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
  }

  .ps-perm-badge {
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 500;
    padding: 2px 8px;
    white-space: nowrap;
  }

  .ps-perm-defines { background: var(--ps-perm-defines-bg); color: var(--ps-perm-defines); }
  .ps-perm-requires { background: var(--ps-perm-requires-bg); color: var(--ps-perm-requires); }

  /* Data access chips */
  .ps-data-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
  }

  .ps-data-chip {
    font-family: var(--font-mono);
    font-size: 10px;
    padding: 3px 8px;
    background: var(--ps-chip-bg);
    border: 1px solid var(--ps-chip-border);
    color: var(--ps-text-secondary);
  }

  .ps-data-chip-field {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--ps-text-muted);
  }

  /* Detail tables */
  .ps-detail-table {
    width: 100%;
    border-collapse: collapse;
  }

  .ps-detail-table th {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ps-text-muted);
    padding: 6px 10px;
    text-align: left;
    background: var(--ps-table-header-bg);
    border-bottom: 1px solid var(--ps-table-border);
  }

  .ps-detail-table td {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ps-text-secondary);
    padding: 7px 10px;
    border-bottom: 1px solid var(--ps-table-border);
  }

  .ps-detail-table tr:last-child td { border-bottom: none; }
  .ps-detail-table tr:hover td { background: var(--ps-row-hover); }

  /* Hook type badges */
  .ps-hook-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    white-space: nowrap;
  }

  .ps-hook-badge svg { width: 11px; height: 11px; }

  .ps-hook-event { background: var(--ps-hook-event-bg); color: var(--ps-hook-event); }
  .ps-hook-filter { background: var(--ps-hook-filter-bg); color: var(--ps-hook-filter); }
  .ps-hook-validator { background: var(--ps-hook-validator-bg); color: var(--ps-hook-validator); }
  .ps-hook-decorator { background: var(--ps-hook-decorator-bg); color: var(--ps-hook-decorator); }

  /* Priority number */
  .ps-priority {
    font-family: var(--font-mono);
    font-size: 10px;
    padding: 1px 6px;
    background: var(--ps-chip-bg);
    border: 1px solid var(--ps-chip-border);
    color: var(--ps-text-muted);
  }

  /* Settings form */
  .ps-settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }

  .ps-form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .ps-form-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ps-text-muted);
  }

  .ps-form-input {
    font-family: var(--font-mono);
    font-size: 12px;
    padding: 7px 10px;
    background: var(--ps-surface);
    border: 1px solid var(--ps-border);
    color: var(--ps-text);
    transition: border-color 0.15s;
  }

  .ps-form-input:focus { outline: none; border-color: var(--ps-accent); box-shadow: 0 0 0 2px var(--ps-accent-dim); }

  .ps-form-select {
    font-family: var(--font-mono);
    font-size: 12px;
    padding: 7px 28px 7px 10px;
    background: var(--ps-surface);
    border: 1px solid var(--ps-border);
    color: var(--ps-text);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    transition: border-color 0.15s;
  }

  .ps-form-select:focus { outline: none; border-color: var(--ps-accent); box-shadow: 0 0 0 2px var(--ps-accent-dim); }

  .ps-form-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
  }

  .ps-form-toggle-label {
    font-family: var(--font-display);
    font-size: 12px;
    color: var(--ps-text-secondary);
  }

  /* Custom field type badge */
  .ps-cf-type {
    font-family: var(--font-mono);
    font-size: 10px;
    padding: 1px 6px;
    background: var(--ps-hook-event-bg);
    color: var(--ps-hook-event);
    white-space: nowrap;
  }

  /* ================================================================ */
  /*  BROWSE TAB                                                       */
  /* ================================================================ */
  .ps-browse-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
  }

  .ps-search {
    position: relative;
    flex: 1;
    min-width: 200px;
    max-width: 320px;
  }

  .ps-search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--ps-text-muted);
    pointer-events: none;
  }

  .ps-search-icon svg { width: 14px; height: 14px; }

  .ps-search input {
    width: 100%;
    padding: 7px 10px 7px 32px;
    background: var(--ps-panel);
    border: 1px solid var(--ps-border);
    color: var(--ps-text);
    font-family: var(--font-mono);
    font-size: 12px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .ps-search input::placeholder { color: var(--ps-text-muted); }
  .ps-search input:focus { outline: none; border-color: var(--ps-accent); box-shadow: 0 0 0 2px var(--ps-accent-dim); }

  .ps-filter-chips {
    display: flex;
    gap: 4px;
  }

  .ps-chip {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    padding: 6px 12px;
    background: var(--ps-panel);
    border: 1px solid var(--ps-border);
    color: var(--ps-text-secondary);
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
  }

  .ps-chip:hover { border-color: var(--ps-text-muted); color: var(--ps-text); }

  .ps-chip.active {
    background: var(--ps-accent);
    color: #ffffff;
    border-color: var(--ps-accent);
  }

  .ps-browse-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 14px;
  }

  .ps-browse-card {
    background: var(--ps-panel);
    border: 1px solid var(--ps-border);
    padding: 18px 20px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .ps-browse-card:hover {
    border-color: var(--ps-border-subtle);
    box-shadow: var(--ps-shadow);
  }

  .ps-browse-name {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 700;
    color: var(--ps-text);
    margin-bottom: 4px;
  }

  .ps-browse-author {
    font-family: var(--font-display);
    font-size: 11px;
    color: var(--ps-text-muted);
    margin-bottom: 8px;
  }

  .ps-browse-desc {
    font-family: var(--font-display);
    font-size: 12px;
    color: var(--ps-text-secondary);
    line-height: 1.5;
    margin-bottom: 12px;
  }

  .ps-browse-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
  }

  .ps-browse-installs {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ps-text-muted);
  }

  .ps-browse-stars {
    display: flex;
    gap: 2px;
  }

  .ps-star {
    width: 12px;
    height: 12px;
  }

  .ps-star-filled { color: var(--ps-star-fill); }
  .ps-star-empty { color: var(--ps-star-empty); }

  .ps-browse-category {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 2px 8px;
    background: var(--ps-chip-bg);
    border: 1px solid var(--ps-chip-border);
    color: var(--ps-text-muted);
  }

  .ps-browse-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
  }

  /* ================================================================ */
  /*  MANIFEST VIEWER TAB                                              */
  /* ================================================================ */
  .ps-manifest-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
  }

  .ps-manifest-select {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    padding: 7px 32px 7px 12px;
    background: var(--ps-panel);
    border: 1px solid var(--ps-border);
    color: var(--ps-text);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    transition: border-color 0.15s;
  }

  .ps-manifest-select:hover { border-color: var(--ps-accent); }
  .ps-manifest-select:focus { outline: none; border-color: var(--ps-accent); box-shadow: 0 0 0 2px var(--ps-accent-dim); }

  .ps-manifest-split {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    align-items: flex-start;
  }

  .ps-manifest-code-panel {
    background: var(--ps-json-bg);
    border: 1px solid var(--ps-border);
    overflow: hidden;
    max-height: calc(100vh - 280px);
    overflow-y: auto;
  }

  .ps-manifest-code-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  }

  .ps-manifest-code-title {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ps-json-text);
    opacity: 0.7;
  }

  .ps-manifest-code-format {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--ps-accent);
  }

  .ps-manifest-json {
    padding: 16px;
    font-family: var(--font-mono);
    font-size: 11px;
    line-height: 1.6;
    color: var(--ps-json-text);
    white-space: pre;
    overflow-x: auto;
  }

  .ps-json-key { color: #7dd3fc; }
  .ps-json-string { color: #86efac; }
  .ps-json-number { color: #fbbf24; }
  .ps-json-bool { color: #c084fc; }
  .ps-json-null { color: #94a3b8; }

  .ps-manifest-parsed {
    background: var(--ps-panel);
    border: 1px solid var(--ps-border);
    max-height: calc(100vh - 280px);
    overflow-y: auto;
  }

  .ps-manifest-parsed-header {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--ps-border);
  }

  .ps-manifest-parsed-title {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ps-text-muted);
  }

  .ps-manifest-section {
    border-bottom: 1px solid var(--ps-border);
  }

  .ps-manifest-section:last-child { border-bottom: none; }

  .ps-manifest-section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
  }

  .ps-manifest-section-header:hover { background: var(--ps-hover); }

  .ps-manifest-chevron {
    width: 14px;
    height: 14px;
    color: var(--ps-text-muted);
    transition: transform 0.2s;
    flex-shrink: 0;
  }

  .ps-manifest-chevron.collapsed { transform: rotate(-90deg); }

  .ps-manifest-section-name {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ps-text-secondary);
  }

  .ps-manifest-section-body {
    padding: 0 16px 14px;
  }

  .ps-manifest-kv {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 6px 12px;
    font-size: 12px;
  }

  .ps-manifest-kv-key {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    color: var(--ps-text-muted);
    padding: 3px 0;
  }

  .ps-manifest-kv-val {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ps-text);
    padding: 3px 0;
    word-break: break-all;
  }

  /* Scrollbar for manifest panels */
  .ps-manifest-code-panel::-webkit-scrollbar,
  .ps-manifest-parsed::-webkit-scrollbar { width: 6px; }
  .ps-manifest-code-panel::-webkit-scrollbar-track,
  .ps-manifest-parsed::-webkit-scrollbar-track { background: transparent; }
  .ps-manifest-code-panel::-webkit-scrollbar-thumb,
  .ps-manifest-parsed::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 3px; }
  .ps-manifest-code-panel::-webkit-scrollbar-thumb:hover,
  .ps-manifest-parsed::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes psFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes psSlideDown {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .ps-stat-card { animation: psFadeIn 0.3s ease both; }
  .ps-stat-card:nth-child(1) { animation-delay: 0s; }
  .ps-stat-card:nth-child(2) { animation-delay: 0.05s; }
  .ps-stat-card:nth-child(3) { animation-delay: 0.1s; }
  .ps-stat-card:nth-child(4) { animation-delay: 0.15s; }

  .ps-plugin-card { animation: psFadeIn 0.3s ease both; }
  .ps-browse-card { animation: psFadeIn 0.3s ease both; }

  /* ================================================================ */
  /*  EMPTY STATE                                                      */
  /* ================================================================ */
  .ps-empty {
    text-align: center;
    padding: 48px 24px;
    color: var(--ps-text-muted);
    font-family: var(--font-display);
    font-size: 13px;
  }

  .ps-empty-icon {
    width: 40px;
    height: 40px;
    margin: 0 auto 12px;
    color: var(--ps-text-muted);
    opacity: 0.4;
  }
</style>

<div class="ps-page" x-data="pluginSystem()" x-cloak>

  <div class="ps-container">

    {{-- ============================================================ --}}
    {{--  HEADER                                                       --}}
    {{-- ============================================================ --}}
    <div class="ps-header">
      <div class="ps-header-left">
        <div>
          <div class="ps-title">Plugin System</div>
          <div class="ps-subtitle">Composer-Based Plugin Architecture</div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  TAB BAR                                                      --}}
    {{-- ============================================================ --}}
    <div class="ps-tabs">
      <button class="ps-tab" :class="{ active: activeTab === 'installed' }" @click="activeTab = 'installed'">Installed</button>
      <button class="ps-tab" :class="{ active: activeTab === 'browse' }" @click="activeTab = 'browse'">Browse</button>
      <button class="ps-tab" :class="{ active: activeTab === 'manifest' }" @click="activeTab = 'manifest'">Manifest Viewer</button>
    </div>

    {{-- ============================================================ --}}
    {{--  STATS BAR                                                    --}}
    {{-- ============================================================ --}}
    <div class="ps-stats">
      <div class="ps-stat-card">
        <div class="ps-stat-icon ps-stat-icon-total">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          </svg>
        </div>
        <div>
          <div class="ps-stat-label">Installed Plugins</div>
          <div class="ps-stat-value" x-text="plugins.length"></div>
        </div>
      </div>
      <div class="ps-stat-card">
        <div class="ps-stat-icon ps-stat-icon-active">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
        </div>
        <div>
          <div class="ps-stat-label">Active</div>
          <div class="ps-stat-value" x-text="plugins.filter(p => p.status === 'active').length"></div>
        </div>
      </div>
      <div class="ps-stat-card">
        <div class="ps-stat-icon ps-stat-icon-disabled">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
          </svg>
        </div>
        <div>
          <div class="ps-stat-label">Disabled</div>
          <div class="ps-stat-value" x-text="plugins.filter(p => p.status === 'disabled').length"></div>
        </div>
      </div>
      <div class="ps-stat-card">
        <div class="ps-stat-icon ps-stat-icon-hooks">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
          </svg>
        </div>
        <div>
          <div class="ps-stat-label">Available Hooks</div>
          <div class="ps-stat-value" x-text="totalHooks"></div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  INSTALLED TAB                                                --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'installed'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">

      <div class="ps-plugin-grid">
        <template x-for="plugin in plugins" :key="plugin.package">
          <div class="ps-plugin-card" :class="{ expanded: expandedPlugin === plugin.package }">

            {{-- Card top --}}
            <div class="ps-plugin-top">
              <div class="ps-plugin-icon" :style="'background:' + plugin.color" x-text="plugin.name.charAt(0)"></div>
              <div class="ps-plugin-info">
                <div class="ps-plugin-name-row">
                  <span class="ps-plugin-name" x-text="plugin.name"></span>
                  <span class="ps-plugin-version" x-text="'v' + plugin.version"></span>
                  <span class="ps-status-badge"
                        :class="'ps-status-' + plugin.status"
                        x-text="plugin.status"></span>
                </div>
                <div class="ps-plugin-vendor" x-text="plugin.package"></div>
                <div class="ps-plugin-desc" x-text="plugin.description"></div>
                <div class="ps-plugin-tags">
                  <template x-for="tag in plugin.tags" :key="tag">
                    <span class="ps-plugin-tag" x-text="tag"></span>
                  </template>
                </div>
                <div class="ps-plugin-actions">
                  <div class="ps-toggle"
                       :class="{ on: plugin.status === 'active' }"
                       @click.stop="togglePlugin(plugin)">
                    <div class="ps-toggle-knob"></div>
                  </div>
                  <button class="ps-btn" @click.stop="expandPlugin(plugin)">
                    <span x-text="expandedPlugin === plugin.package ? 'Collapse' : 'Settings'"></span>
                  </button>
                  <button class="ps-btn ps-btn-danger" @click.stop>Uninstall</button>
                </div>
              </div>
            </div>

            {{-- Expanded detail panel --}}
            <div class="ps-detail"
                 x-show="expandedPlugin === plugin.package"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">

              {{-- Detail tabs --}}
              <div class="ps-detail-tabs">
                <button class="ps-detail-tab"
                        :class="{ active: detailTab === 'overview' }"
                        @click.stop="detailTab = 'overview'">Overview</button>
                <button class="ps-detail-tab"
                        :class="{ active: detailTab === 'permissions' }"
                        @click.stop="detailTab = 'permissions'">Permissions</button>
                <button class="ps-detail-tab"
                        :class="{ active: detailTab === 'data' }"
                        @click.stop="detailTab = 'data'">Data Access</button>
                <button class="ps-detail-tab"
                        :class="{ active: detailTab === 'hooks' }"
                        @click.stop="detailTab = 'hooks'">Hooks</button>
                <button class="ps-detail-tab"
                        :class="{ active: detailTab === 'slots' }"
                        @click.stop="detailTab = 'slots'">UI Slots</button>
                <button class="ps-detail-tab"
                        :class="{ active: detailTab === 'settings' }"
                        @click.stop="detailTab = 'settings'">Settings</button>
                <button class="ps-detail-tab"
                        :class="{ active: detailTab === 'jobs' }"
                        @click.stop="detailTab = 'jobs'"
                        x-show="plugin.jobs && plugin.jobs.length > 0">Jobs</button>
                <button class="ps-detail-tab"
                        :class="{ active: detailTab === 'tables' }"
                        @click.stop="detailTab = 'tables'"
                        x-show="plugin.tables && plugin.tables.length > 0">Tables</button>
              </div>

              {{-- OVERVIEW --}}
              <div x-show="detailTab === 'overview'">
                <div class="ps-detail-section">
                  <div class="ps-detail-label">Description</div>
                  <div class="ps-detail-value" x-text="plugin.full_description || plugin.description"></div>
                </div>
                <div class="ps-detail-meta-grid">
                  <div class="ps-detail-meta-item">
                    <span class="ps-detail-meta-key">Author</span>
                    <span class="ps-detail-meta-val" x-text="plugin.author"></span>
                  </div>
                  <div class="ps-detail-meta-item">
                    <span class="ps-detail-meta-key">License</span>
                    <span class="ps-detail-meta-val" x-text="plugin.license"></span>
                  </div>
                  <div class="ps-detail-meta-item">
                    <span class="ps-detail-meta-key">SDK Version</span>
                    <span class="ps-detail-meta-val" x-text="plugin.sdk_version"></span>
                  </div>
                </div>
              </div>

              {{-- PERMISSIONS --}}
              <div x-show="detailTab === 'permissions'">
                <div class="ps-detail-section">
                  <div class="ps-detail-label">Defines (new permissions)</div>
                  <div class="ps-perm-badges">
                    <template x-for="perm in plugin.permissions_defines || []" :key="perm">
                      <span class="ps-perm-badge ps-perm-defines" x-text="perm"></span>
                    </template>
                    <span x-show="!plugin.permissions_defines || plugin.permissions_defines.length === 0"
                          style="font-family:var(--font-display);font-size:12px;color:var(--ps-text-muted);">None</span>
                  </div>
                </div>
                <div class="ps-detail-section">
                  <div class="ps-detail-label">Requires (existing permissions)</div>
                  <div class="ps-perm-badges">
                    <template x-for="perm in plugin.permissions_requires || []" :key="perm">
                      <span class="ps-perm-badge ps-perm-requires" x-text="perm"></span>
                    </template>
                    <span x-show="!plugin.permissions_requires || plugin.permissions_requires.length === 0"
                          style="font-family:var(--font-display);font-size:12px;color:var(--ps-text-muted);">None</span>
                  </div>
                </div>
              </div>

              {{-- DATA ACCESS --}}
              <div x-show="detailTab === 'data'">
                <div class="ps-detail-section">
                  <div class="ps-detail-label">Reads</div>
                  <div class="ps-data-chips">
                    <template x-for="model in plugin.data_reads || []" :key="model">
                      <span class="ps-data-chip" x-text="model"></span>
                    </template>
                    <span x-show="!plugin.data_reads || plugin.data_reads.length === 0"
                          style="font-family:var(--font-display);font-size:12px;color:var(--ps-text-muted);">None</span>
                  </div>
                </div>
                <div class="ps-detail-section">
                  <div class="ps-detail-label">Writes</div>
                  <template x-for="write in plugin.data_writes || []" :key="write.model">
                    <div style="margin-bottom: 6px;">
                      <span class="ps-data-chip" x-text="write.model" style="margin-right:6px;"></span>
                      <template x-for="field in write.fields" :key="field">
                        <span class="ps-data-chip-field" x-text="field" style="margin-right:4px;"></span>
                      </template>
                    </div>
                  </template>
                  <span x-show="!plugin.data_writes || plugin.data_writes.length === 0"
                        style="font-family:var(--font-display);font-size:12px;color:var(--ps-text-muted);">None</span>
                </div>
                <div class="ps-detail-section">
                  <div class="ps-detail-label">Custom Fields</div>
                  <table class="ps-detail-table" x-show="plugin.custom_fields && plugin.custom_fields.length > 0">
                    <thead>
                      <tr>
                        <th>Field</th>
                        <th>Type</th>
                        <th>On Model</th>
                      </tr>
                    </thead>
                    <tbody>
                      <template x-for="cf in plugin.custom_fields || []" :key="cf.name">
                        <tr>
                          <td x-text="cf.name"></td>
                          <td><span class="ps-cf-type" x-text="cf.type"></span></td>
                          <td x-text="cf.model"></td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                  <span x-show="!plugin.custom_fields || plugin.custom_fields.length === 0"
                        style="font-family:var(--font-display);font-size:12px;color:var(--ps-text-muted);">None</span>
                </div>
              </div>

              {{-- HOOKS --}}
              <div x-show="detailTab === 'hooks'">
                <table class="ps-detail-table" x-show="plugin.hooks && plugin.hooks.length > 0">
                  <thead>
                    <tr>
                      <th>Type</th>
                      <th>Hook Name</th>
                      <th>Priority</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template x-for="hook in plugin.hooks || []" :key="hook.name + hook.type">
                      <tr>
                        <td>
                          <span class="ps-hook-badge" :class="'ps-hook-' + hook.type">
                            <template x-if="hook.type === 'event'">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            </template>
                            <template x-if="hook.type === 'filter'">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                            </template>
                            <template x-if="hook.type === 'validator'">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </template>
                            <template x-if="hook.type === 'decorator'">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/></svg>
                            </template>
                            <span x-text="hook.type"></span>
                          </span>
                        </td>
                        <td x-text="hook.name"></td>
                        <td><span class="ps-priority" x-text="'@' + hook.priority"></span></td>
                      </tr>
                    </template>
                  </tbody>
                </table>
                <div class="ps-empty" x-show="!plugin.hooks || plugin.hooks.length === 0">
                  <div>No hooks registered.</div>
                </div>
              </div>

              {{-- UI SLOTS --}}
              <div x-show="detailTab === 'slots'">
                <table class="ps-detail-table" x-show="plugin.slots && plugin.slots.length > 0">
                  <thead>
                    <tr>
                      <th>Slot Name</th>
                      <th>Component</th>
                      <th>Priority</th>
                      <th>Permission</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template x-for="slot in plugin.slots || []" :key="slot.slot">
                      <tr>
                        <td x-text="slot.slot"></td>
                        <td x-text="slot.component"></td>
                        <td><span class="ps-priority" x-text="'@' + slot.priority"></span></td>
                        <td x-text="slot.permission || '-'"></td>
                      </tr>
                    </template>
                  </tbody>
                </table>
                <div class="ps-empty" x-show="!plugin.slots || plugin.slots.length === 0">
                  <div>No UI slots registered.</div>
                </div>
              </div>

              {{-- SETTINGS --}}
              <div x-show="detailTab === 'settings'">
                <div class="ps-settings-grid" x-show="plugin.settings_schema && plugin.settings_schema.length > 0">
                  <template x-for="setting in plugin.settings_schema || []" :key="setting.key">
                    <div class="ps-form-group">
                      <template x-if="setting.type === 'boolean'">
                        <div class="ps-form-toggle-row">
                          <span class="ps-form-toggle-label" x-text="setting.label"></span>
                          <div class="ps-toggle" :class="{ on: setting.value }" @click.stop="setting.value = !setting.value">
                            <div class="ps-toggle-knob"></div>
                          </div>
                        </div>
                      </template>
                      <template x-if="setting.type === 'enum'">
                        <div>
                          <label class="ps-form-label" x-text="setting.label"></label>
                          <select class="ps-form-select" x-model="setting.value">
                            <template x-for="opt in setting.options || []" :key="opt">
                              <option :value="opt" x-text="opt"></option>
                            </template>
                          </select>
                        </div>
                      </template>
                      <template x-if="setting.type === 'string' || setting.type === 'encrypted'">
                        <div>
                          <label class="ps-form-label" x-text="setting.label"></label>
                          <input class="ps-form-input"
                                 :type="setting.type === 'encrypted' ? 'password' : 'text'"
                                 :value="setting.value"
                                 :placeholder="setting.placeholder || ''">
                        </div>
                      </template>
                    </div>
                  </template>
                </div>
                <div class="ps-empty" x-show="!plugin.settings_schema || plugin.settings_schema.length === 0">
                  <div>No settings defined.</div>
                </div>
              </div>

              {{-- JOBS --}}
              <div x-show="detailTab === 'jobs'">
                <table class="ps-detail-table" x-show="plugin.jobs && plugin.jobs.length > 0">
                  <thead>
                    <tr>
                      <th>Job Name</th>
                      <th>Queue</th>
                      <th>Frequency</th>
                      <th>Last Run</th>
                      <th>Next Run</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template x-for="job in plugin.jobs || []" :key="job.name">
                      <tr>
                        <td x-text="job.name"></td>
                        <td x-text="job.queue"></td>
                        <td x-text="job.frequency"></td>
                        <td x-text="job.last_run"></td>
                        <td x-text="job.next_run"></td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>

              {{-- TABLES --}}
              <div x-show="detailTab === 'tables'">
                <div class="ps-detail-section" x-show="plugin.tables && plugin.tables.length > 0">
                  <div class="ps-data-chips">
                    <template x-for="table in plugin.tables || []" :key="table">
                      <span class="ps-data-chip" x-text="table"></span>
                    </template>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </template>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  BROWSE TAB                                                   --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'browse'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">

      <div class="ps-browse-toolbar">
        <div class="ps-search">
          <span class="ps-search-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"/>
              <path d="M21 21l-4.35-4.35"/>
            </svg>
          </span>
          <input type="text"
                 placeholder="Search plugins..."
                 x-model.debounce.150ms="browseSearch"
                 @keydown.escape="browseSearch = ''">
        </div>
        <div class="ps-filter-chips">
          <button class="ps-chip" :class="{ active: browseCategory === 'all' }" @click="browseCategory = 'all'">All</button>
          <button class="ps-chip" :class="{ active: browseCategory === 'accounting' }" @click="browseCategory = 'accounting'">Accounting</button>
          <button class="ps-chip" :class="{ active: browseCategory === 'communication' }" @click="browseCategory = 'communication'">Communication</button>
          <button class="ps-chip" :class="{ active: browseCategory === 'scheduling' }" @click="browseCategory = 'scheduling'">Scheduling</button>
          <button class="ps-chip" :class="{ active: browseCategory === 'reporting' }" @click="browseCategory = 'reporting'">Reporting</button>
          <button class="ps-chip" :class="{ active: browseCategory === 'integration' }" @click="browseCategory = 'integration'">Integration</button>
        </div>
      </div>

      <div class="ps-browse-grid">
        <template x-for="item in filteredBrowse" :key="item.name">
          <div class="ps-browse-card">
            <div class="ps-browse-name" x-text="item.name"></div>
            <div class="ps-browse-author" x-text="'by ' + item.author"></div>
            <div class="ps-browse-desc" x-text="item.description"></div>
            <div class="ps-browse-meta">
              <span class="ps-browse-installs" x-text="item.installs.toLocaleString() + ' installs'"></span>
              <span class="ps-browse-stars" x-html="renderStars(item.rating)"></span>
              <span class="ps-browse-category" x-text="item.category"></span>
            </div>
            <div class="ps-browse-actions">
              <button class="ps-btn ps-btn-accent" @click.stop>Install</button>
              <button class="ps-btn" @click.stop="previewBrowse = previewBrowse === item.name ? null : item.name">Preview</button>
            </div>

            {{-- Browse preview --}}
            <div class="ps-detail" x-show="previewBrowse === item.name"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">
              <div class="ps-detail-meta-grid">
                <div class="ps-detail-meta-item">
                  <span class="ps-detail-meta-key">Package</span>
                  <span class="ps-detail-meta-val" x-text="item.package"></span>
                </div>
                <div class="ps-detail-meta-item">
                  <span class="ps-detail-meta-key">Version</span>
                  <span class="ps-detail-meta-val" x-text="item.latest_version"></span>
                </div>
                <div class="ps-detail-meta-item">
                  <span class="ps-detail-meta-key">License</span>
                  <span class="ps-detail-meta-val" x-text="item.license"></span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <div class="ps-empty" x-show="filteredBrowse.length === 0">
        <div class="ps-empty-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="11" cy="11" r="8"/>
            <path d="M21 21l-4.35-4.35"/>
          </svg>
        </div>
        <div>No plugins match your search.</div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  MANIFEST VIEWER TAB                                          --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'manifest'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">

      <div class="ps-manifest-toolbar">
        <select class="ps-manifest-select" x-model="manifestPlugin">
          <template x-for="plugin in plugins" :key="plugin.package">
            <option :value="plugin.package" x-text="plugin.name + ' (' + plugin.package + ')'"></option>
          </template>
        </select>
      </div>

      <div class="ps-manifest-split">

        {{-- JSON code panel --}}
        <div class="ps-manifest-code-panel">
          <div class="ps-manifest-code-header">
            <span class="ps-manifest-code-title">signals.json</span>
            <span class="ps-manifest-code-format">JSON</span>
          </div>
          <div class="ps-manifest-json" x-html="manifestJsonHtml"></div>
        </div>

        {{-- Parsed view panel --}}
        <div class="ps-manifest-parsed">
          <div class="ps-manifest-parsed-header">
            <span class="ps-manifest-parsed-title">Parsed View</span>
          </div>

          <template x-for="section in manifestSections" :key="section.key">
            <div class="ps-manifest-section">
              <div class="ps-manifest-section-header" @click="toggleManifestSection(section.key)">
                <svg class="ps-manifest-chevron" :class="{ collapsed: !manifestExpanded[section.key] }"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="6 9 12 15 18 9"/>
                </svg>
                <span class="ps-manifest-section-name" x-text="section.label"></span>
              </div>
              <div class="ps-manifest-section-body"
                   x-show="manifestExpanded[section.key]"
                   x-transition:enter="transition ease-out duration-200"
                   x-transition:enter-start="opacity-0"
                   x-transition:enter-end="opacity-100">
                <div class="ps-manifest-kv">
                  <template x-for="entry in section.entries" :key="entry.key">
                    <template x-if="true">
                      <div style="display:contents;">
                        <div class="ps-manifest-kv-key" x-text="entry.key"></div>
                        <div class="ps-manifest-kv-val" x-text="entry.value"></div>
                      </div>
                    </template>
                  </template>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

  </div>
</div>

@verbatim
<script>
function pluginSystem() {

  /* ================================================================ */
  /*  MOCK DATA — INSTALLED PLUGINS                                    */
  /* ================================================================ */
  const plugins = [
    {
      name: 'Xero Integration',
      package: 'signals/xero-integration',
      version: '2.1.0',
      status: 'active',
      color: '#2563eb',
      author: 'Signals Team',
      license: 'MIT',
      sdk_version: '>=1.0',
      tags: ['accounting', 'invoicing', 'integration'],
      description: 'Bi-directional sync with Xero for invoices, payments, and contacts.',
      full_description: 'Bi-directional sync with Xero for invoices, payments, and contacts. Automatically pushes confirmed opportunities as Xero invoices, reconciles payments, and keeps contact details synchronised across both platforms.',
      permissions_defines: ['xero.sync.trigger', 'xero.sync.view', 'xero.settings.manage'],
      permissions_requires: ['opportunities.read', 'opportunities.update', 'members.read', 'members.update', 'invoices.read'],
      data_reads: ['opportunities', 'members', 'invoices', 'stores'],
      data_writes: [
        { model: 'opportunities', fields: ['status', 'notes', 'custom_fields'] },
        { model: 'members', fields: ['custom_fields'] },
      ],
      custom_fields: [
        { name: 'xero_invoice_id', type: 'string', model: 'opportunities' },
        { name: 'xero_sync_status', type: 'enum (pending/synced/failed/skipped)', model: 'opportunities' },
        { name: 'xero_contact_id', type: 'string', model: 'members' },
      ],
      hooks: [
        { type: 'event', name: 'opportunity.confirmed', priority: 10 },
        { type: 'event', name: 'opportunity.invoiced', priority: 10 },
        { type: 'event', name: 'member.created', priority: 20 },
        { type: 'event', name: 'member.updated', priority: 20 },
        { type: 'filter', name: 'invoice.line_items.prepare', priority: 50 },
        { type: 'filter', name: 'member.api_response.prepare', priority: 100 },
      ],
      slots: [
        { slot: 'opportunity.detail.tabs', component: 'XeroSyncStatus', priority: 50, permission: 'xero.sync.view' },
        { slot: 'member.detail.tabs', component: 'XeroContactLink', priority: 50, permission: 'xero.sync.view' },
        { slot: 'dashboard.widgets', component: 'XeroSyncSummary', priority: 80, permission: 'xero.sync.view' },
        { slot: 'opportunity.detail.header_actions', component: 'XeroSyncButton', priority: 10, permission: 'xero.sync.trigger' },
      ],
      settings_schema: [
        { key: 'client_id', label: 'Client ID', type: 'string', value: 'xero-app-abc123', placeholder: 'Enter Xero client ID' },
        { key: 'client_secret', label: 'Client Secret', type: 'encrypted', value: '', placeholder: 'Enter secret' },
        { key: 'tenant_id', label: 'Tenant ID', type: 'string', value: 'org-xyz789', placeholder: 'Enter Xero tenant ID' },
        { key: 'auto_sync', label: 'Auto Sync', type: 'boolean', value: true },
        { key: 'sync_direction', label: 'Sync Direction', type: 'enum', value: 'bidirectional', options: ['signals_to_xero', 'xero_to_signals', 'bidirectional'] },
        { key: 'default_account_code', label: 'Default Account Code', type: 'string', value: '200', placeholder: 'e.g. 200' },
      ],
      jobs: [
        { name: 'SyncPaymentStatus', queue: 'default', frequency: 'Daily @02:00', last_run: '2026-03-05 02:00:12', next_run: '2026-03-06 02:00:00' },
        { name: 'SyncContacts', queue: 'default', frequency: 'Hourly', last_run: '2026-03-05 14:00:08', next_run: '2026-03-05 15:00:00' },
      ],
      tables: ['plugin_xero_sync_log', 'plugin_xero_credentials'],
    },
    {
      name: 'Transport & Delivery',
      package: 'signals/transport',
      version: '1.3.2',
      status: 'active',
      color: '#0891b2',
      author: 'Signals Team',
      license: 'MIT',
      sdk_version: '>=1.0',
      tags: ['logistics', 'delivery', 'tracking'],
      description: 'Delivery scheduling, vehicle assignment, and real-time tracking.',
      full_description: 'Delivery scheduling, vehicle assignment, and real-time tracking. Manage delivery routes, assign vehicles to opportunities, and provide customers with live tracking updates via SMS or email notifications.',
      permissions_defines: ['transport.schedule.manage', 'transport.track.view'],
      permissions_requires: ['opportunities.read', 'stock.read'],
      data_reads: ['opportunities', 'stock_items', 'stores'],
      data_writes: [
        { model: 'opportunities', fields: ['custom_fields', 'notes'] },
      ],
      custom_fields: [
        { name: 'delivery_tracking_id', type: 'string', model: 'opportunities' },
        { name: 'delivery_status', type: 'enum (pending/in_transit/delivered)', model: 'opportunities' },
        { name: 'vehicle_reg', type: 'string', model: 'opportunities' },
      ],
      hooks: [
        { type: 'event', name: 'opportunity.confirmed', priority: 30 },
        { type: 'event', name: 'opportunity.dispatched', priority: 10 },
        { type: 'event', name: 'opportunity.returned', priority: 10 },
        { type: 'filter', name: 'opportunity.list.columns', priority: 80 },
      ],
      slots: [
        { slot: 'opportunity.detail.tabs', component: 'DeliveryTracker', priority: 60, permission: 'transport.track.view' },
        { slot: 'opportunity.list.columns', component: 'DeliveryStatusColumn', priority: 40, permission: null },
      ],
      settings_schema: [
        { key: 'tracking_provider', label: 'Tracking Provider', type: 'enum', value: 'internal', options: ['internal', 'track24', 'aftership'] },
        { key: 'api_key', label: 'Provider API Key', type: 'encrypted', value: '', placeholder: 'Enter API key' },
        { key: 'auto_dispatch', label: 'Auto Dispatch', type: 'boolean', value: false },
        { key: 'notification_email', label: 'Notification Email', type: 'string', value: 'delivery@example.com', placeholder: 'email@example.com' },
      ],
      jobs: [
        { name: 'UpdateTrackingStatus', queue: 'default', frequency: 'Every 15 min', last_run: '2026-03-05 14:45:02', next_run: '2026-03-05 15:00:00' },
      ],
      tables: ['plugin_transport_routes', 'plugin_transport_vehicles'],
    },
    {
      name: 'Advanced Reporting',
      package: 'signals/advanced-reporting',
      version: '1.0.0',
      status: 'disabled',
      color: '#d97706',
      author: 'Signals Team',
      license: 'MIT',
      sdk_version: '>=1.0',
      tags: ['reporting', 'analytics', 'charts'],
      description: 'Enhanced reporting with chart visualisations and scheduled email reports.',
      full_description: 'Enhanced reporting with chart visualisations and scheduled email reports. Includes bar charts, line graphs, pie charts, and pivot tables. Schedule reports to be emailed to stakeholders on a daily, weekly, or monthly basis.',
      permissions_defines: ['reporting.advanced.access', 'reporting.schedules.manage'],
      permissions_requires: ['opportunities.read', 'invoices.read', 'members.read', 'products.read'],
      data_reads: ['opportunities', 'invoices', 'members', 'products', 'stores'],
      data_writes: [],
      custom_fields: [],
      hooks: [
        { type: 'filter', name: 'report.available_types', priority: 10 },
        { type: 'decorator', name: 'report.render', priority: 50 },
      ],
      slots: [
        { slot: 'dashboard.widgets', component: 'AdvancedChartWidget', priority: 70, permission: 'reporting.advanced.access' },
        { slot: 'navigation.reports', component: 'AdvancedReportsMenu', priority: 20, permission: 'reporting.advanced.access' },
      ],
      settings_schema: [
        { key: 'chart_library', label: 'Chart Library', type: 'enum', value: 'chartjs', options: ['chartjs', 'apexcharts', 'echarts'] },
        { key: 'email_from', label: 'Report Email From', type: 'string', value: 'reports@example.com', placeholder: 'sender@example.com' },
        { key: 'cache_reports', label: 'Cache Reports', type: 'boolean', value: true },
      ],
      jobs: [
        { name: 'GenerateScheduledReports', queue: 'exports', frequency: 'Daily @06:00', last_run: 'Never', next_run: '-' },
      ],
      tables: ['plugin_report_schedules', 'plugin_report_cache'],
    },
    {
      name: 'Crew Management',
      package: 'signals/crew-management',
      version: '0.9.1',
      status: 'active',
      color: '#7c3aed',
      author: 'Signals Community',
      license: 'MIT',
      sdk_version: '>=1.0',
      tags: ['crew', 'scheduling', 'services'],
      description: 'Crew scheduling, availability, and cost tracking for events.',
      full_description: 'Crew scheduling, availability, and cost tracking for events. Assign crew members to opportunities, track their availability across a calendar view, and automatically calculate labour costs based on hourly rates and shift durations.',
      permissions_defines: ['crew.schedule.manage', 'crew.availability.view', 'crew.costs.view'],
      permissions_requires: ['opportunities.read', 'opportunities.update', 'members.read'],
      data_reads: ['opportunities', 'members', 'stores'],
      data_writes: [
        { model: 'opportunities', fields: ['custom_fields'] },
      ],
      custom_fields: [
        { name: 'crew_lead', type: 'relation', model: 'opportunities' },
        { name: 'crew_call_time', type: 'datetime', model: 'opportunities' },
      ],
      hooks: [
        { type: 'event', name: 'opportunity.confirmed', priority: 40 },
        { type: 'event', name: 'opportunity.cancelled', priority: 10 },
        { type: 'validator', name: 'opportunity.confirm.validate', priority: 20 },
        { type: 'filter', name: 'opportunity.cost_summary.prepare', priority: 60 },
        { type: 'decorator', name: 'opportunity.detail.sidebar', priority: 30 },
      ],
      slots: [
        { slot: 'opportunity.detail.tabs', component: 'CrewAssignment', priority: 40, permission: 'crew.schedule.manage' },
        { slot: 'dashboard.widgets', component: 'CrewAvailabilityWidget', priority: 60, permission: 'crew.availability.view' },
        { slot: 'calendar.events', component: 'CrewShiftOverlay', priority: 50, permission: 'crew.schedule.manage' },
      ],
      settings_schema: [
        { key: 'default_hourly_rate', label: 'Default Hourly Rate', type: 'string', value: '25.00', placeholder: '0.00' },
        { key: 'overtime_multiplier', label: 'Overtime Multiplier', type: 'string', value: '1.5', placeholder: '1.5' },
        { key: 'auto_notify_crew', label: 'Auto Notify Crew', type: 'boolean', value: true },
        { key: 'min_break_hours', label: 'Min Break Between Shifts (hrs)', type: 'string', value: '11', placeholder: '11' },
      ],
      jobs: [
        { name: 'SendCrewReminders', queue: 'notifications', frequency: 'Daily @18:00', last_run: '2026-03-04 18:00:05', next_run: '2026-03-05 18:00:00' },
        { name: 'CalculateLabourCosts', queue: 'default', frequency: 'Hourly', last_run: '2026-03-05 14:00:11', next_run: '2026-03-05 15:00:00' },
      ],
      tables: ['plugin_crew_assignments', 'plugin_crew_shifts', 'plugin_crew_rates'],
    },
  ];

  /* ================================================================ */
  /*  MOCK DATA — BROWSE / MARKETPLACE                                 */
  /* ================================================================ */
  const browsePlugins = [
    {
      name: 'QuickBooks Integration',
      package: 'signals/quickbooks',
      author: 'Signals Team',
      description: 'Sync invoices, payments, and customer records with QuickBooks Online.',
      category: 'accounting',
      installs: 342,
      rating: 4.5,
      latest_version: '1.2.0',
      license: 'MIT',
    },
    {
      name: 'SMS Notifications',
      package: 'signals/sms-notifications',
      author: 'Signals Community',
      description: 'Send SMS alerts for booking confirmations, delivery updates, and reminders via Twilio or Vonage.',
      category: 'communication',
      installs: 891,
      rating: 4.8,
      latest_version: '2.0.1',
      license: 'MIT',
    },
    {
      name: 'Google Calendar Sync',
      package: 'signals/google-calendar',
      author: 'Third Party Labs',
      description: 'Sync opportunity dates and crew schedules with Google Calendar for team visibility.',
      category: 'scheduling',
      installs: 567,
      rating: 4.2,
      latest_version: '1.1.3',
      license: 'MIT',
    },
    {
      name: 'Damage Assessment',
      package: 'signals/damage-assessment',
      author: 'Signals Community',
      description: 'Photo-based damage reporting with condition tracking, cost estimation, and insurance claim support.',
      category: 'compliance',
      installs: 123,
      rating: 4.0,
      latest_version: '0.8.0',
      license: 'MIT',
    },
    {
      name: 'Fleet Management',
      package: 'signals/fleet-management',
      author: 'Signals Team',
      description: 'Track vehicle maintenance, fuel costs, MOT dates, and assign vehicles to delivery routes.',
      category: 'logistics',
      installs: 234,
      rating: 4.3,
      latest_version: '1.0.2',
      license: 'MIT',
    },
    {
      name: 'Client Portal',
      package: 'signals/client-portal',
      author: 'Signals Team',
      description: 'Self-service portal for clients to view quotes, approve orders, track deliveries, and download invoices.',
      category: 'crm',
      installs: 1205,
      rating: 4.7,
      latest_version: '2.3.0',
      license: 'Proprietary',
    },
  ];

  /* ================================================================ */
  /*  COMPONENT STATE                                                  */
  /* ================================================================ */
  return {
    activeTab: 'installed',
    plugins,
    browsePlugins,

    /* Installed tab */
    expandedPlugin: null,
    detailTab: 'overview',

    /* Browse tab */
    browseSearch: '',
    browseCategory: 'all',
    previewBrowse: null,

    /* Manifest tab */
    manifestPlugin: plugins[0].package,
    manifestExpanded: {},

    /* ============================================================== */
    /*  COMPUTED                                                       */
    /* ============================================================== */
    get totalHooks() {
      let count = 0;
      this.plugins.forEach(p => { count += (p.hooks || []).length; });
      return count;
    },

    get filteredBrowse() {
      let items = this.browsePlugins;

      if (this.browseCategory !== 'all') {
        items = items.filter(i => i.category === this.browseCategory);
      }

      if (this.browseSearch.trim()) {
        const q = this.browseSearch.toLowerCase().trim();
        items = items.filter(i =>
          i.name.toLowerCase().includes(q) ||
          i.description.toLowerCase().includes(q) ||
          i.category.toLowerCase().includes(q)
        );
      }

      return items;
    },

    get currentManifestPlugin() {
      return this.plugins.find(p => p.package === this.manifestPlugin) || this.plugins[0];
    },

    get manifestJsonHtml() {
      const p = this.currentManifestPlugin;
      const manifest = {
        name: p.package,
        version: p.version,
        description: p.description,
        author: p.author,
        license: p.license,
        sdk: p.sdk_version,
        permissions: {
          defines: p.permissions_defines || [],
          requires: p.permissions_requires || [],
        },
        data_access: {
          reads: p.data_reads || [],
          writes: (p.data_writes || []).map(w => ({ model: w.model, fields: w.fields })),
        },
        custom_fields: (p.custom_fields || []).map(cf => ({
          name: cf.name,
          type: cf.type,
          model: cf.model,
        })),
        hooks: (p.hooks || []).map(h => ({
          type: h.type,
          name: h.name,
          priority: h.priority,
        })),
        slots: (p.slots || []).map(s => ({
          slot: s.slot,
          component: s.component,
          priority: s.priority,
        })),
        settings: (p.settings_schema || []).map(s => ({
          key: s.key,
          type: s.type,
          label: s.label,
        })),
        tables: p.tables || [],
      };

      return this.syntaxHighlight(JSON.stringify(manifest, null, 2));
    },

    get manifestSections() {
      const p = this.currentManifestPlugin;
      return [
        {
          key: 'identity',
          label: 'Identity',
          entries: [
            { key: 'Package', value: p.package },
            { key: 'Version', value: p.version },
            { key: 'Author', value: p.author },
            { key: 'License', value: p.license },
            { key: 'SDK Version', value: p.sdk_version },
          ],
        },
        {
          key: 'permissions',
          label: 'Permissions',
          entries: [
            { key: 'Defines', value: (p.permissions_defines || []).join(', ') || 'None' },
            { key: 'Requires', value: (p.permissions_requires || []).join(', ') || 'None' },
          ],
        },
        {
          key: 'data_access',
          label: 'Data Access',
          entries: [
            { key: 'Reads', value: (p.data_reads || []).join(', ') || 'None' },
            { key: 'Writes', value: (p.data_writes || []).map(w => w.model + ' (' + w.fields.join(', ') + ')').join('; ') || 'None' },
          ],
        },
        {
          key: 'custom_fields',
          label: 'Custom Fields',
          entries: (p.custom_fields || []).map(cf => ({
            key: cf.name,
            value: cf.type + ' on ' + cf.model,
          })),
        },
        {
          key: 'hooks',
          label: 'Hooks (' + (p.hooks || []).length + ')',
          entries: (p.hooks || []).map(h => ({
            key: h.type + ' @' + h.priority,
            value: h.name,
          })),
        },
        {
          key: 'slots',
          label: 'UI Slots (' + (p.slots || []).length + ')',
          entries: (p.slots || []).map(s => ({
            key: s.slot,
            value: s.component + ' @' + s.priority,
          })),
        },
        {
          key: 'settings',
          label: 'Settings Schema',
          entries: (p.settings_schema || []).map(s => ({
            key: s.key,
            value: s.type + (s.options ? ' [' + s.options.join(', ') + ']' : ''),
          })),
        },
        {
          key: 'tables',
          label: 'Tables',
          entries: (p.tables || []).map(t => ({
            key: t,
            value: 'plugin-owned',
          })),
        },
        {
          key: 'jobs',
          label: 'Scheduled Jobs',
          entries: (p.jobs || []).map(j => ({
            key: j.name,
            value: j.frequency + ' on ' + j.queue,
          })),
        },
      ].filter(s => s.entries.length > 0);
    },

    /* ============================================================== */
    /*  METHODS                                                        */
    /* ============================================================== */
    init() {
      this.manifestSections.forEach(s => {
        this.manifestExpanded[s.key] = true;
      });
    },

    togglePlugin(plugin) {
      plugin.status = plugin.status === 'active' ? 'disabled' : 'active';
    },

    expandPlugin(plugin) {
      if (this.expandedPlugin === plugin.package) {
        this.expandedPlugin = null;
      } else {
        this.expandedPlugin = plugin.package;
        this.detailTab = 'overview';
      }
    },

    toggleManifestSection(key) {
      this.manifestExpanded[key] = !this.manifestExpanded[key];
    },

    renderStars(rating) {
      let html = '';
      const full = Math.floor(rating);
      const half = rating % 1 >= 0.3;
      for (let i = 0; i < 5; i++) {
        if (i < full) {
          html += '<svg class="ps-star ps-star-filled" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        } else if (i === full && half) {
          html += '<svg class="ps-star ps-star-filled" viewBox="0 0 24 24" fill="currentColor" opacity="0.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        } else {
          html += '<svg class="ps-star ps-star-empty" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        }
      }
      return html;
    },

    syntaxHighlight(json) {
      return json
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false)\b|-?\d+(?:\.\d*)?(?:[eE][+-]?\d+)?|\bnull\b)/g, function (match) {
          let cls = 'ps-json-number';
          if (/^"/.test(match)) {
            if (/:$/.test(match)) {
              cls = 'ps-json-key';
              return '<span class="' + cls + '">' + match.slice(0, -1) + '</span>:';
            } else {
              cls = 'ps-json-string';
            }
          } else if (/true|false/.test(match)) {
            cls = 'ps-json-bool';
          } else if (/null/.test(match)) {
            cls = 'ps-json-null';
          }
          return '<span class="' + cls + '">' + match + '</span>';
        });
    },
  };
}
</script>
@endverbatim
