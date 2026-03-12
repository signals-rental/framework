<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Field Registry Schema Engine')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  FIELD REGISTRY TOKENS — maps to brand system in app.css          */
  /* ================================================================ */
  :root {
    --fr-bg: var(--content-bg);
    --fr-panel: var(--card-bg);
    --fr-surface: var(--base);
    --fr-border: var(--card-border);
    --fr-border-subtle: var(--grey-border);
    --fr-text: var(--text-primary);
    --fr-text-secondary: var(--text-secondary);
    --fr-text-muted: var(--text-muted);
    --fr-accent: var(--green);
    --fr-accent-dim: var(--green-muted);
    --fr-hover: rgba(0, 0, 0, 0.03);
    --fr-shadow: var(--shadow-card);
    --fr-type-string: var(--blue);
    --fr-type-integer: var(--sky);
    --fr-type-decimal: var(--cyan);
    --fr-type-boolean: var(--green);
    --fr-type-date: var(--amber);
    --fr-type-datetime: var(--amber);
    --fr-type-enum: var(--violet);
    --fr-type-currency: var(--green);
    --fr-type-json: var(--rose);
    --fr-type-relation: var(--blue);
    --fr-type-text: var(--blue);
    --fr-source-core: var(--green);
    --fr-source-core-bg: rgba(5, 150, 105, 0.08);
    --fr-source-computed: var(--amber);
    --fr-source-computed-bg: rgba(217, 119, 6, 0.08);
    --fr-source-custom: var(--violet);
    --fr-source-custom-bg: rgba(124, 58, 237, 0.08);
    --fr-stat-total-bg: rgba(37, 99, 235, 0.06);
    --fr-stat-total-color: var(--blue);
    --fr-detail-bg: var(--base);
    --fr-chip-bg: rgba(0, 0, 0, 0.04);
    --fr-chip-border: rgba(0, 0, 0, 0.06);
    --fr-code-bg: rgba(0, 0, 0, 0.04);
    --fr-dot-on: var(--green);
    --fr-dot-off: var(--grey-border);
    --fr-group-header-bg: var(--table-header-bg);
    --fr-row-hover: var(--table-row-hover);
    --fr-table-border: var(--table-border);
    --fr-json-bg: rgba(15, 23, 42, 0.96);
    --fr-json-text: #e2e8f0;
  }

  .dark {
    --fr-bg: var(--content-bg);
    --fr-panel: var(--card-bg);
    --fr-surface: var(--navy-mid);
    --fr-border: var(--card-border);
    --fr-border-subtle: #283040;
    --fr-text: var(--text-primary);
    --fr-text-secondary: var(--text-secondary);
    --fr-text-muted: var(--text-muted);
    --fr-accent: var(--green);
    --fr-accent-dim: rgba(5, 150, 105, 0.12);
    --fr-hover: rgba(255, 255, 255, 0.04);
    --fr-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --fr-source-core-bg: rgba(5, 150, 105, 0.15);
    --fr-source-computed-bg: rgba(217, 119, 6, 0.15);
    --fr-source-custom-bg: rgba(124, 58, 237, 0.15);
    --fr-stat-total-bg: rgba(37, 99, 235, 0.12);
    --fr-detail-bg: rgba(255, 255, 255, 0.03);
    --fr-chip-bg: rgba(255, 255, 255, 0.08);
    --fr-chip-border: rgba(255, 255, 255, 0.1);
    --fr-code-bg: rgba(255, 255, 255, 0.06);
    --fr-dot-off: #374151;
    --fr-group-header-bg: var(--table-header-bg);
    --fr-row-hover: var(--table-row-hover);
    --fr-table-border: var(--table-border);
    --fr-json-bg: rgba(15, 23, 42, 0.98);
    --fr-json-text: #94a3b8;
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */
  .fr-page {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 64px);
    background: var(--fr-bg);
    position: relative;
  }

  .fr-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 32px 64px;
    width: 100%;
  }

  .fr-main-grid {
    display: flex;
    gap: 24px;
    align-items: flex-start;
  }

  .fr-main-content {
    flex: 1;
    min-width: 0;
  }

  .fr-api-panel {
    width: 480px;
    flex-shrink: 0;
    position: sticky;
    top: 88px;
    max-height: calc(100vh - 120px);
    overflow-y: auto;
    background: var(--fr-json-bg);
    border: 1px solid var(--fr-border);
    box-shadow: var(--fr-shadow);
    transition: opacity 0.2s, transform 0.2s;
  }

  /* ================================================================ */
  /*  HEADER                                                           */
  /* ================================================================ */
  .fr-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
  }

  .fr-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .fr-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    color: var(--fr-text);
    letter-spacing: -0.01em;
    line-height: 1;
  }

  .fr-subtitle {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--fr-accent);
    margin-top: 2px;
  }

  .fr-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .fr-model-select {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    padding: 7px 32px 7px 12px;
    background: var(--fr-panel);
    border: 1px solid var(--fr-border);
    color: var(--fr-text);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    transition: border-color 0.15s;
  }

  .fr-model-select:hover { border-color: var(--fr-accent); }
  .fr-model-select:focus { outline: none; border-color: var(--fr-accent); box-shadow: 0 0 0 2px var(--fr-accent-dim); }

  .fr-api-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    background: var(--fr-panel);
    border: 1px solid var(--fr-border);
    color: var(--fr-text-secondary);
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    transition: all 0.15s;
  }

  .fr-api-toggle:hover { border-color: var(--fr-accent); color: var(--fr-text); }
  .fr-api-toggle.active { background: var(--fr-accent); color: #ffffff; border-color: var(--fr-accent); }
  .fr-api-toggle svg { width: 14px; height: 14px; }

  /* ================================================================ */
  /*  STATS BAR                                                        */
  /* ================================================================ */
  .fr-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
  }

  .fr-stat-card {
    background: var(--fr-panel);
    border: 1px solid var(--fr-border);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .fr-stat-card:hover {
    border-color: var(--fr-border-subtle);
    box-shadow: var(--fr-shadow);
  }

  .fr-stat-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .fr-stat-icon svg { width: 18px; height: 18px; }

  .fr-stat-icon-total { background: var(--fr-stat-total-bg); color: var(--fr-stat-total-color); }
  .fr-stat-icon-core { background: var(--fr-source-core-bg); color: var(--fr-source-core); }
  .fr-stat-icon-computed { background: var(--fr-source-computed-bg); color: var(--fr-source-computed); }
  .fr-stat-icon-custom { background: var(--fr-source-custom-bg); color: var(--fr-source-custom); }

  .fr-stat-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--fr-text-muted);
    margin-bottom: 2px;
  }

  .fr-stat-value {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 700;
    color: var(--fr-text);
    line-height: 1;
  }

  /* ================================================================ */
  /*  TOOLBAR                                                          */
  /* ================================================================ */
  .fr-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
  }

  .fr-search {
    position: relative;
    flex: 1;
    min-width: 200px;
    max-width: 320px;
  }

  .fr-search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--fr-text-muted);
    pointer-events: none;
  }

  .fr-search-icon svg { width: 14px; height: 14px; }

  .fr-search input {
    width: 100%;
    padding: 7px 10px 7px 32px;
    background: var(--fr-panel);
    border: 1px solid var(--fr-border);
    color: var(--fr-text);
    font-family: var(--font-mono);
    font-size: 12px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .fr-search input::placeholder { color: var(--fr-text-muted); }
  .fr-search input:focus { outline: none; border-color: var(--fr-accent); box-shadow: 0 0 0 2px var(--fr-accent-dim); }

  .fr-filter-chips {
    display: flex;
    gap: 4px;
  }

  .fr-chip {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    padding: 6px 12px;
    background: var(--fr-panel);
    border: 1px solid var(--fr-border);
    color: var(--fr-text-secondary);
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
  }

  .fr-chip:hover { border-color: var(--fr-text-muted); color: var(--fr-text); }

  .fr-chip.active {
    background: var(--fr-accent);
    color: #ffffff;
    border-color: var(--fr-accent);
  }

  .fr-group-select {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    padding: 6px 28px 6px 10px;
    background: var(--fr-panel);
    border: 1px solid var(--fr-border);
    color: var(--fr-text-secondary);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    transition: border-color 0.15s;
  }

  .fr-group-select:focus { outline: none; border-color: var(--fr-accent); box-shadow: 0 0 0 2px var(--fr-accent-dim); }

  /* ================================================================ */
  /*  FIELD TABLE                                                      */
  /* ================================================================ */
  .fr-table-wrap {
    background: var(--fr-panel);
    border: 1px solid var(--fr-border);
    overflow: hidden;
  }

  .fr-group-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--fr-group-header-bg);
    border-bottom: 1px solid var(--fr-table-border);
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
  }

  .fr-group-header:hover { background: var(--fr-hover); }

  .fr-group-chevron {
    width: 16px;
    height: 16px;
    color: var(--fr-text-muted);
    transition: transform 0.2s;
    flex-shrink: 0;
  }

  .fr-group-chevron.collapsed { transform: rotate(-90deg); }

  .fr-group-name {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--fr-text-secondary);
  }

  .fr-group-count {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--fr-text-muted);
    margin-left: auto;
  }

  .fr-group-fields {
    overflow: hidden;
    transition: max-height 0.25s ease;
  }

  /* Table header */
  .fr-table-header {
    display: grid;
    grid-template-columns: 180px 80px 80px 1fr 200px 32px;
    gap: 0;
    padding: 6px 16px;
    border-bottom: 1px solid var(--fr-table-border);
    background: var(--fr-group-header-bg);
  }

  .fr-th {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--fr-text-muted);
    padding: 4px 0;
  }

  .fr-th-caps {
    display: flex;
    gap: 4px;
    align-items: center;
  }

  .fr-th-cap-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--fr-text-muted);
    opacity: 0.5;
  }

  /* Field rows */
  .fr-field-row {
    display: grid;
    grid-template-columns: 180px 80px 80px 1fr 200px 32px;
    gap: 0;
    padding: 8px 16px;
    border-bottom: 1px solid var(--fr-table-border);
    cursor: pointer;
    transition: background 0.1s;
    align-items: center;
  }

  .fr-field-row:hover { background: var(--fr-row-hover); }
  .fr-field-row:last-child { border-bottom: none; }

  .fr-field-row.selected {
    background: var(--fr-accent-dim);
  }

  .fr-field-name {
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 500;
    color: var(--fr-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Type badge */
  .fr-type-badge {
    display: inline-flex;
    align-items: center;
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 500;
    padding: 2px 8px;
    white-space: nowrap;
  }

  .fr-type-string { background: rgba(37, 99, 235, 0.08); color: var(--fr-type-string); }
  .fr-type-text { background: rgba(37, 99, 235, 0.08); color: var(--fr-type-text); }
  .fr-type-integer { background: rgba(14, 165, 233, 0.08); color: var(--fr-type-integer); }
  .fr-type-decimal { background: rgba(8, 145, 178, 0.08); color: var(--fr-type-decimal); }
  .fr-type-boolean { background: rgba(5, 150, 105, 0.08); color: var(--fr-type-boolean); }
  .fr-type-date { background: rgba(217, 119, 6, 0.08); color: var(--fr-type-date); }
  .fr-type-datetime { background: rgba(217, 119, 6, 0.08); color: var(--fr-type-datetime); }
  .fr-type-enum { background: rgba(124, 58, 237, 0.08); color: var(--fr-type-enum); }
  .fr-type-currency { background: rgba(5, 150, 105, 0.08); color: var(--fr-type-currency); }
  .fr-type-json { background: rgba(225, 29, 72, 0.08); color: var(--fr-type-json); }
  .fr-type-relation { background: rgba(37, 99, 235, 0.08); color: var(--fr-type-relation); }

  .dark .fr-type-string { background: rgba(37, 99, 235, 0.15); }
  .dark .fr-type-text { background: rgba(37, 99, 235, 0.15); }
  .dark .fr-type-integer { background: rgba(14, 165, 233, 0.15); }
  .dark .fr-type-decimal { background: rgba(8, 145, 178, 0.15); }
  .dark .fr-type-boolean { background: rgba(5, 150, 105, 0.15); }
  .dark .fr-type-date { background: rgba(217, 119, 6, 0.15); }
  .dark .fr-type-datetime { background: rgba(217, 119, 6, 0.15); }
  .dark .fr-type-enum { background: rgba(124, 58, 237, 0.15); }
  .dark .fr-type-currency { background: rgba(5, 150, 105, 0.15); }
  .dark .fr-type-json { background: rgba(225, 29, 72, 0.15); }
  .dark .fr-type-relation { background: rgba(37, 99, 235, 0.15); }

  /* Source badge */
  .fr-source-badge {
    display: inline-flex;
    align-items: center;
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 2px 8px;
    white-space: nowrap;
  }

  .fr-source-core { background: var(--fr-source-core-bg); color: var(--fr-source-core); }
  .fr-source-computed { background: var(--fr-source-computed-bg); color: var(--fr-source-computed); }
  .fr-source-custom { background: var(--fr-source-custom-bg); color: var(--fr-source-custom); }

  .fr-field-label {
    font-family: var(--font-display);
    font-size: 12px;
    color: var(--fr-text-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Capability dots */
  .fr-caps {
    display: flex;
    gap: 6px;
    align-items: center;
  }

  .fr-cap-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--fr-dot-off);
    transition: background 0.15s;
    position: relative;
  }

  .fr-cap-dot.on { background: var(--fr-dot-on); }

  .fr-cap-dot::after {
    content: attr(data-cap);
    position: absolute;
    bottom: calc(100% + 6px);
    left: 50%;
    transform: translateX(-50%);
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--fr-text);
    background: var(--fr-panel);
    border: 1px solid var(--fr-border);
    padding: 3px 6px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s;
    z-index: 10;
    box-shadow: var(--fr-shadow);
  }

  .fr-cap-dot:hover::after { opacity: 1; }

  /* Expand arrow */
  .fr-expand-icon {
    width: 16px;
    height: 16px;
    color: var(--fr-text-muted);
    transition: transform 0.2s;
  }

  .fr-expand-icon.open { transform: rotate(90deg); }

  /* ================================================================ */
  /*  DETAIL PANEL                                                     */
  /* ================================================================ */
  .fr-detail {
    background: var(--fr-detail-bg);
    border-bottom: 1px solid var(--fr-table-border);
    padding: 16px 20px;
    animation: frSlideDown 0.2s ease both;
  }

  @keyframes frSlideDown {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .fr-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  .fr-detail-section {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .fr-detail-label {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--fr-text-muted);
  }

  .fr-detail-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
  }

  .fr-detail-tag {
    font-family: var(--font-mono);
    font-size: 10px;
    padding: 2px 8px;
    background: var(--fr-chip-bg);
    border: 1px solid var(--fr-chip-border);
    color: var(--fr-text-secondary);
  }

  .fr-detail-value {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--fr-text);
  }

  .fr-detail-code {
    font-family: var(--font-mono);
    font-size: 11px;
    line-height: 1.5;
    background: var(--fr-code-bg);
    border: 1px solid var(--fr-chip-border);
    padding: 8px 12px;
    color: var(--fr-text);
    white-space: pre-wrap;
    word-break: break-all;
  }

  .fr-detail-meta {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
  }

  .fr-detail-meta-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .fr-detail-meta-key {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--fr-text-muted);
  }

  .fr-detail-meta-val {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--fr-text-secondary);
  }

  .fr-detail-full {
    grid-column: 1 / -1;
  }

  /* ================================================================ */
  /*  API PREVIEW PANEL                                                */
  /* ================================================================ */
  .fr-api-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  }

  .fr-api-title {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--fr-json-text);
    opacity: 0.7;
  }

  .fr-api-endpoint {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--fr-accent);
  }

  .fr-api-json {
    padding: 16px;
    font-family: var(--font-mono);
    font-size: 11px;
    line-height: 1.6;
    color: var(--fr-json-text);
    white-space: pre;
    overflow-x: auto;
  }

  .fr-json-key { color: #7dd3fc; }
  .fr-json-string { color: #86efac; }
  .fr-json-number { color: #fbbf24; }
  .fr-json-bool { color: #c084fc; }
  .fr-json-null { color: #94a3b8; }
  .fr-json-bracket { color: #64748b; }
  .fr-json-comma { color: #64748b; }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes frFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .fr-stat-card { animation: frFadeIn 0.3s ease both; }
  .fr-stat-card:nth-child(1) { animation-delay: 0s; }
  .fr-stat-card:nth-child(2) { animation-delay: 0.05s; }
  .fr-stat-card:nth-child(3) { animation-delay: 0.1s; }
  .fr-stat-card:nth-child(4) { animation-delay: 0.15s; }

  .fr-field-row { animation: frFadeIn 0.2s ease both; }

  /* ================================================================ */
  /*  EMPTY STATE                                                      */
  /* ================================================================ */
  .fr-empty {
    text-align: center;
    padding: 48px 24px;
    color: var(--fr-text-muted);
    font-family: var(--font-display);
    font-size: 13px;
  }

  .fr-empty-icon {
    width: 40px;
    height: 40px;
    margin: 0 auto 12px;
    color: var(--fr-text-muted);
    opacity: 0.4;
  }

  /* ================================================================ */
  /*  SCROLLBAR (API panel)                                            */
  /* ================================================================ */
  .fr-api-panel::-webkit-scrollbar { width: 6px; }
  .fr-api-panel::-webkit-scrollbar-track { background: transparent; }
  .fr-api-panel::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 3px; }
  .fr-api-panel::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }
</style>

<div class="fr-page"
     x-data="fieldRegistryExplorer()"
     x-cloak>

  <div class="fr-container">

    {{-- ============================================================ --}}
    {{--  HEADER                                                       --}}
    {{-- ============================================================ --}}
    <div class="fr-header">
      <div class="fr-header-left">
        <div>
          <div class="fr-title">Field Registry</div>
          <div class="fr-subtitle">Schema Engine</div>
        </div>
      </div>
      <div class="fr-header-right">
        <select class="fr-model-select"
                x-model="currentModel"
                @change="selectedField = null">
          <template x-for="m in modelOptions" :key="m.key">
            <option :value="m.key" x-text="m.label"></option>
          </template>
        </select>

        <button class="fr-api-toggle"
                :class="{ active: showApiPreview }"
                @click="showApiPreview = !showApiPreview">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/>
            <polyline points="13 2 13 9 20 9"/>
          </svg>
          API Preview
        </button>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  STATS BAR                                                    --}}
    {{-- ============================================================ --}}
    <div class="fr-stats">
      <div class="fr-stat-card">
        <div class="fr-stat-icon fr-stat-icon-total">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <path d="M3 9h18M9 21V9"/>
          </svg>
        </div>
        <div>
          <div class="fr-stat-label">Total Fields</div>
          <div class="fr-stat-value" x-text="allFields.length"></div>
        </div>
      </div>
      <div class="fr-stat-card">
        <div class="fr-stat-icon fr-stat-icon-core">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
            <path d="M2 17l10 5 10-5"/>
            <path d="M2 12l10 5 10-5"/>
          </svg>
        </div>
        <div>
          <div class="fr-stat-label">Core</div>
          <div class="fr-stat-value" x-text="allFields.filter(f => f.source === 'core').length"></div>
        </div>
      </div>
      <div class="fr-stat-card">
        <div class="fr-stat-icon fr-stat-icon-computed">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="16 18 22 12 16 6"/>
            <polyline points="8 6 2 12 8 18"/>
          </svg>
        </div>
        <div>
          <div class="fr-stat-label">Computed</div>
          <div class="fr-stat-value" x-text="allFields.filter(f => f.source === 'computed').length"></div>
        </div>
      </div>
      <div class="fr-stat-card">
        <div class="fr-stat-icon fr-stat-icon-custom">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
          </svg>
        </div>
        <div>
          <div class="fr-stat-label">Custom</div>
          <div class="fr-stat-value" x-text="allFields.filter(f => f.source === 'custom').length"></div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  TOOLBAR                                                      --}}
    {{-- ============================================================ --}}
    <div class="fr-toolbar">
      <div class="fr-search">
        <span class="fr-search-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/>
            <path d="M21 21l-4.35-4.35"/>
          </svg>
        </span>
        <input type="text"
               placeholder="Search fields..."
               x-model.debounce.150ms="searchQuery"
               @keydown.escape="searchQuery = ''">
      </div>

      <div class="fr-filter-chips">
        <button class="fr-chip" :class="{ active: sourceFilter === 'all' }" @click="sourceFilter = 'all'">All</button>
        <button class="fr-chip" :class="{ active: sourceFilter === 'core' }" @click="sourceFilter = 'core'">Core</button>
        <button class="fr-chip" :class="{ active: sourceFilter === 'computed' }" @click="sourceFilter = 'computed'">Computed</button>
        <button class="fr-chip" :class="{ active: sourceFilter === 'custom' }" @click="sourceFilter = 'custom'">Custom</button>
      </div>

      <select class="fr-group-select"
              x-model="groupFilter">
        <option value="all">All Groups</option>
        <template x-for="g in availableGroups" :key="g">
          <option :value="g" x-text="g"></option>
        </template>
      </select>
    </div>

    {{-- ============================================================ --}}
    {{--  MAIN GRID (table + optional API panel)                       --}}
    {{-- ============================================================ --}}
    <div class="fr-main-grid">
      <div class="fr-main-content">

        {{-- Table header --}}
        <div class="fr-table-wrap">
          <div class="fr-table-header">
            <div class="fr-th">Field Name</div>
            <div class="fr-th">Type</div>
            <div class="fr-th">Source</div>
            <div class="fr-th">Label</div>
            <div class="fr-th">
              <div class="fr-th-caps" title="Filterable, Sortable, Searchable, Groupable, Aggregatable, Exportable, Importable">
                <span style="font-size:9px; letter-spacing:0.04em;">F</span>
                <span style="font-size:9px; letter-spacing:0.04em;">S</span>
                <span style="font-size:9px; letter-spacing:0.04em;">Q</span>
                <span style="font-size:9px; letter-spacing:0.04em;">G</span>
                <span style="font-size:9px; letter-spacing:0.04em;">A</span>
                <span style="font-size:9px; letter-spacing:0.04em;">E</span>
                <span style="font-size:9px; letter-spacing:0.04em;">I</span>
              </div>
            </div>
            <div class="fr-th"></div>
          </div>

          {{-- Grouped fields --}}
          <template x-for="group in filteredGroups" :key="group.name">
            <div>
              {{-- Group header --}}
              <div class="fr-group-header" @click="toggleGroup(group.name)">
                <svg class="fr-group-chevron" :class="{ collapsed: !expandedGroups[group.name] }"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="6 9 12 15 18 9"/>
                </svg>
                <span class="fr-group-name" x-text="group.name"></span>
                <span class="fr-group-count" x-text="group.fields.length + ' fields'"></span>
              </div>

              {{-- Field rows --}}
              <div class="fr-group-fields"
                   x-show="expandedGroups[group.name]"
                   x-transition:enter="transition ease-out duration-200"
                   x-transition:enter-start="opacity-0"
                   x-transition:enter-end="opacity-100">
                <template x-for="field in group.fields" :key="field.name">
                  <div>
                    <div class="fr-field-row"
                         :class="{ selected: selectedField && selectedField.name === field.name }"
                         @click="toggleField(field)">
                      <div class="fr-field-name" x-text="field.name"></div>
                      <div>
                        <span class="fr-type-badge" :class="'fr-type-' + field.type" x-text="field.type"></span>
                      </div>
                      <div>
                        <span class="fr-source-badge" :class="'fr-source-' + field.source" x-text="field.source"></span>
                      </div>
                      <div class="fr-field-label" x-text="field.label"></div>
                      <div class="fr-caps">
                        <span class="fr-cap-dot" :class="{ on: field.capabilities.filterable }" data-cap="Filterable"></span>
                        <span class="fr-cap-dot" :class="{ on: field.capabilities.sortable }" data-cap="Sortable"></span>
                        <span class="fr-cap-dot" :class="{ on: field.capabilities.searchable }" data-cap="Searchable"></span>
                        <span class="fr-cap-dot" :class="{ on: field.capabilities.groupable }" data-cap="Groupable"></span>
                        <span class="fr-cap-dot" :class="{ on: field.capabilities.aggregatable }" data-cap="Aggregatable"></span>
                        <span class="fr-cap-dot" :class="{ on: field.capabilities.exportable }" data-cap="Exportable"></span>
                        <span class="fr-cap-dot" :class="{ on: field.capabilities.importable }" data-cap="Importable"></span>
                      </div>
                      <div>
                        <svg class="fr-expand-icon"
                             :class="{ open: selectedField && selectedField.name === field.name }"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="9 18 15 12 9 6"/>
                        </svg>
                      </div>
                    </div>

                    {{-- Detail panel --}}
                    <div class="fr-detail"
                         x-show="selectedField && selectedField.name === field.name"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100">
                      <div class="fr-detail-grid">

                        {{-- Validation rules --}}
                        <div class="fr-detail-section" x-show="field.validation && field.validation.length > 0">
                          <div class="fr-detail-label">Validation Rules</div>
                          <div class="fr-detail-tags">
                            <template x-for="rule in field.validation || []" :key="rule">
                              <span class="fr-detail-tag" x-text="rule"></span>
                            </template>
                          </div>
                        </div>

                        {{-- Display metadata --}}
                        <div class="fr-detail-section" x-show="field.display">
                          <div class="fr-detail-label">Display</div>
                          <div class="fr-detail-meta">
                            <div class="fr-detail-meta-item" x-show="field.display && field.display.format">
                              <span class="fr-detail-meta-key">Format</span>
                              <span class="fr-detail-meta-val" x-text="field.display ? field.display.format : ''"></span>
                            </div>
                            <div class="fr-detail-meta-item" x-show="field.display && field.display.alignment">
                              <span class="fr-detail-meta-key">Align</span>
                              <span class="fr-detail-meta-val" x-text="field.display ? field.display.alignment : ''"></span>
                            </div>
                            <div class="fr-detail-meta-item" x-show="field.display && field.display.width_hint">
                              <span class="fr-detail-meta-key">Width</span>
                              <span class="fr-detail-meta-val" x-text="field.display ? field.display.width_hint : ''"></span>
                            </div>
                          </div>
                        </div>

                        {{-- Relation metadata --}}
                        <div class="fr-detail-section" x-show="field.type === 'relation' && field.relation">
                          <div class="fr-detail-label">Relation</div>
                          <div class="fr-detail-meta">
                            <div class="fr-detail-meta-item">
                              <span class="fr-detail-meta-key">Name</span>
                              <span class="fr-detail-meta-val" x-text="field.relation ? field.relation.relation_name : ''"></span>
                            </div>
                            <div class="fr-detail-meta-item">
                              <span class="fr-detail-meta-key">Type</span>
                              <span class="fr-detail-meta-val" x-text="field.relation ? field.relation.relation_type : ''"></span>
                            </div>
                            <div class="fr-detail-meta-item">
                              <span class="fr-detail-meta-key">Model</span>
                              <span class="fr-detail-meta-val" x-text="field.relation ? field.relation.related_model : ''"></span>
                            </div>
                            <div class="fr-detail-meta-item">
                              <span class="fr-detail-meta-key">Field</span>
                              <span class="fr-detail-meta-val" x-text="field.relation ? field.relation.related_field : ''"></span>
                            </div>
                          </div>
                        </div>

                        {{-- Computed SQL --}}
                        <div class="fr-detail-section fr-detail-full" x-show="field.source === 'computed' && field.sql_expression">
                          <div class="fr-detail-label">Computed SQL</div>
                          <div class="fr-detail-code" x-text="field.sql_expression"></div>
                        </div>

                        {{-- CRMS mapping --}}
                        <div class="fr-detail-section" x-show="field.crms">
                          <div class="fr-detail-label">CRMS Mapping</div>
                          <div class="fr-detail-meta">
                            <div class="fr-detail-meta-item">
                              <span class="fr-detail-meta-key">Field</span>
                              <span class="fr-detail-meta-val" x-text="field.crms ? field.crms.crms_field_name : ''"></span>
                            </div>
                            <div class="fr-detail-meta-item" x-show="field.crms && field.crms.crms_transform">
                              <span class="fr-detail-meta-key">Transform</span>
                              <span class="fr-detail-meta-val" x-text="field.crms ? field.crms.crms_transform : ''"></span>
                            </div>
                          </div>
                        </div>

                        {{-- Filter operators --}}
                        <div class="fr-detail-section" x-show="field.filter_operators && field.filter_operators.length > 0">
                          <div class="fr-detail-label">Filter Operators</div>
                          <div class="fr-detail-tags">
                            <template x-for="op in field.filter_operators || []" :key="op">
                              <span class="fr-detail-tag" x-text="op"></span>
                            </template>
                          </div>
                        </div>

                        {{-- Plugin (for custom fields) --}}
                        <div class="fr-detail-section" x-show="field.source === 'custom'">
                          <div class="fr-detail-label">Plugin</div>
                          <div class="fr-detail-value" x-text="field.plugin || 'User-defined'"></div>
                        </div>

                      </div>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </template>

          {{-- Empty state --}}
          <div class="fr-empty" x-show="filteredGroups.length === 0">
            <div class="fr-empty-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
              </svg>
            </div>
            <div>No fields match your current filters.</div>
          </div>
        </div>
      </div>

      {{-- API Preview Panel --}}
      <div class="fr-api-panel"
           x-show="showApiPreview"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 translate-x-4"
           x-transition:enter-end="opacity-100 translate-x-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="opacity-100 translate-x-0"
           x-transition:leave-end="opacity-0 translate-x-4">
        <div class="fr-api-header">
          <span class="fr-api-title">API Response</span>
          <span class="fr-api-endpoint" x-text="'/api/v1/schema/' + currentModel"></span>
        </div>
        <div class="fr-api-json" x-html="apiPreviewHtml"></div>
      </div>
    </div>

  </div>
</div>

@verbatim
<script>
function fieldRegistryExplorer() {

  /* ================================================================ */
  /*  MODEL DEFINITIONS                                                */
  /* ================================================================ */
  const models = {
    opportunities: buildOpportunityFields(),
    members: buildMemberFields(),
    products: buildProductFields(),
    invoices: buildInvoiceFields(),
    stores: buildStoreFields(),
  };

  const modelOptions = [
    { key: 'opportunities', label: 'Opportunities' },
    { key: 'members', label: 'Members' },
    { key: 'products', label: 'Products' },
    { key: 'invoices', label: 'Invoices' },
    { key: 'stores', label: 'Stores' },
  ];

  /* ================================================================ */
  /*  COMPONENT STATE                                                  */
  /* ================================================================ */
  return {
    currentModel: 'opportunities',
    searchQuery: '',
    sourceFilter: 'all',
    groupFilter: 'all',
    selectedField: null,
    showApiPreview: false,
    expandedGroups: {},
    modelOptions,

    /* ============================================================== */
    /*  COMPUTED                                                       */
    /* ============================================================== */
    get allFields() {
      return models[this.currentModel] || [];
    },

    get availableGroups() {
      const groups = [...new Set(this.allFields.map(f => f.group))];
      return groups.sort();
    },

    get filteredFields() {
      let fields = this.allFields;

      if (this.sourceFilter !== 'all') {
        fields = fields.filter(f => f.source === this.sourceFilter);
      }

      if (this.groupFilter !== 'all') {
        fields = fields.filter(f => f.group === this.groupFilter);
      }

      if (this.searchQuery.trim()) {
        const q = this.searchQuery.toLowerCase().trim();
        fields = fields.filter(f =>
          f.name.toLowerCase().includes(q) ||
          f.label.toLowerCase().includes(q)
        );
      }

      return fields;
    },

    get filteredGroups() {
      const grouped = {};
      this.filteredFields.forEach(f => {
        if (!grouped[f.group]) {
          grouped[f.group] = { name: f.group, fields: [] };
        }
        grouped[f.group].fields.push(f);
      });

      const order = ['Core', 'Dates', 'Financial', 'Relationships', 'Calculated', 'Custom Fields',
                      'Identity', 'Contact', 'Organisation', 'Membership', 'Catalog', 'Inventory',
                      'Pricing', 'Invoicing', 'Payments', 'Location'];
      const keys = Object.keys(grouped);
      keys.sort((a, b) => {
        const ai = order.indexOf(a);
        const bi = order.indexOf(b);
        if (ai === -1 && bi === -1) return a.localeCompare(b);
        if (ai === -1) return 1;
        if (bi === -1) return -1;
        return ai - bi;
      });

      return keys.map(k => grouped[k]);
    },

    get apiPreviewHtml() {
      const endpoint = this.currentModel;
      const fields = this.allFields.map(f => {
        const entry = {
          name: f.name,
          type: f.type,
          source: f.source,
          label: f.label,
          group: f.group,
          capabilities: f.capabilities,
          validation: f.validation || [],
          filter_operators: f.filter_operators || [],
        };
        if (f.display) entry.display = f.display;
        if (f.crms) entry.crms = f.crms;
        if (f.relation) entry.relation = f.relation;
        if (f.sql_expression) entry.sql_expression = f.sql_expression;
        if (f.source === 'custom') entry.plugin = f.plugin || null;
        return entry;
      });

      const obj = {
        model: endpoint,
        total_fields: fields.length,
        sources: {
          core: fields.filter(f => f.source === 'core').length,
          computed: fields.filter(f => f.source === 'computed').length,
          custom: fields.filter(f => f.source === 'custom').length,
        },
        fields: fields.slice(0, 5),
      };

      return this.syntaxHighlight(JSON.stringify(obj, null, 2));
    },

    /* ============================================================== */
    /*  METHODS                                                        */
    /* ============================================================== */
    init() {
      // Expand all groups on load
      this.availableGroups.forEach(g => {
        this.expandedGroups[g] = true;
      });

      this.$watch('currentModel', () => {
        this.selectedField = null;
        this.searchQuery = '';
        this.sourceFilter = 'all';
        this.groupFilter = 'all';
        this.$nextTick(() => {
          this.availableGroups.forEach(g => {
            this.expandedGroups[g] = true;
          });
        });
      });
    },

    toggleGroup(name) {
      this.expandedGroups[name] = !this.expandedGroups[name];
    },

    toggleField(field) {
      if (this.selectedField && this.selectedField.name === field.name) {
        this.selectedField = null;
      } else {
        this.selectedField = field;
      }
    },

    syntaxHighlight(json) {
      return json
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false)\b|-?\d+(?:\.\d*)?(?:[eE][+-]?\d+)?|\bnull\b)/g, function (match) {
          let cls = 'fr-json-number';
          if (/^"/.test(match)) {
            if (/:$/.test(match)) {
              cls = 'fr-json-key';
              // Remove trailing colon for wrapping, add it back after
              return '<span class="' + cls + '">' + match.slice(0, -1) + '</span>:';
            } else {
              cls = 'fr-json-string';
            }
          } else if (/true|false/.test(match)) {
            cls = 'fr-json-bool';
          } else if (/null/.test(match)) {
            cls = 'fr-json-null';
          }
          return '<span class="' + cls + '">' + match + '</span>';
        });
    },
  };
}

/* ================================================================== */
/*  OPPORTUNITY FIELDS                                                 */
/* ================================================================== */
function buildOpportunityFields() {
  return [
    // Core
    {
      name: 'subject', type: 'string', source: 'core', label: 'Subject', group: 'Core',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: true, aggregatable: false, exportable: true, importable: true },
      validation: ['required', 'string', 'max:255'],
      display: { format: 'text', alignment: 'left', width_hint: 'wide' },
      crms: { crms_field_name: 'subject', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_cont', '_not_cont', '_start', '_end', '_present', '_blank'],
    },
    {
      name: 'description', type: 'text', source: 'core', label: 'Description', group: 'Core',
      capabilities: { filterable: false, sortable: false, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'string', 'max:10000'],
      display: { format: 'text', alignment: 'left', width_hint: 'wide' },
      crms: { crms_field_name: 'description', crms_transform: null },
      filter_operators: ['_cont', '_not_cont', '_present', '_blank'],
    },
    {
      name: 'state', type: 'enum', source: 'core', label: 'State', group: 'Core',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: false },
      validation: ['required', 'string', 'in:draft,quotation,order,active,closed,cancelled'],
      display: { format: 'badge', alignment: 'left', width_hint: 'narrow' },
      crms: { crms_field_name: 'state', crms_transform: 'state_map' },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in'],
    },
    {
      name: 'status', type: 'enum', source: 'core', label: 'Status', group: 'Core',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: false },
      validation: ['required', 'string', 'in:provisional,confirmed,cancelled'],
      display: { format: 'badge', alignment: 'left', width_hint: 'narrow' },
      crms: { crms_field_name: 'status', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in'],
    },
    {
      name: 'number', type: 'string', source: 'core', label: 'Number', group: 'Core',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'string', 'max:50'],
      display: { format: 'text', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'number', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_cont', '_start', '_present', '_blank'],
    },
    {
      name: 'reference', type: 'string', source: 'core', label: 'Reference', group: 'Core',
      capabilities: { filterable: true, sortable: false, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'string', 'max:255'],
      display: { format: 'text', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'reference', crms_transform: null },
      filter_operators: ['_eq', '_cont', '_not_cont', '_present', '_blank'],
    },

    // Dates
    {
      name: 'starts_at', type: 'datetime', source: 'core', label: 'Starts At', group: 'Dates',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['required', 'date'],
      display: { format: 'datetime', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'starts_at', crms_transform: 'iso8601' },
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq', '_null', '_not_null'],
    },
    {
      name: 'ends_at', type: 'datetime', source: 'core', label: 'Ends At', group: 'Dates',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['required', 'date', 'after:starts_at'],
      display: { format: 'datetime', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'ends_at', crms_transform: 'iso8601' },
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq', '_null', '_not_null'],
    },
    {
      name: 'created_at', type: 'datetime', source: 'core', label: 'Created', group: 'Dates',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: [],
      display: { format: 'datetime', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'created_at', crms_transform: 'iso8601' },
      filter_operators: ['_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'updated_at', type: 'datetime', source: 'core', label: 'Updated', group: 'Dates',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: [],
      display: { format: 'datetime', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'updated_at', crms_transform: 'iso8601' },
      filter_operators: ['_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'confirmed_at', type: 'datetime', source: 'core', label: 'Confirmed At', group: 'Dates',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'date'],
      display: { format: 'datetime', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'confirmed_at', crms_transform: 'iso8601' },
      filter_operators: ['_eq', '_lt', '_lteq', '_gt', '_gteq', '_null', '_not_null'],
    },

    // Financial
    {
      name: 'charge_total', type: 'currency', source: 'core', label: 'Charge Total', group: 'Financial',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: ['required', 'integer', 'min:0'],
      display: { format: 'currency', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'charge_total', crms_transform: 'minor_to_decimal' },
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'tax_total', type: 'currency', source: 'core', label: 'Tax Total', group: 'Financial',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: ['required', 'integer', 'min:0'],
      display: { format: 'currency', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'tax_total', crms_transform: 'minor_to_decimal' },
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'discount_total', type: 'currency', source: 'core', label: 'Discount Total', group: 'Financial',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: ['required', 'integer', 'min:0'],
      display: { format: 'currency', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'discount_total', crms_transform: 'minor_to_decimal' },
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'currency_code', type: 'string', source: 'core', label: 'Currency', group: 'Financial',
      capabilities: { filterable: true, sortable: false, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: false },
      validation: ['required', 'string', 'size:3'],
      display: { format: 'text', alignment: 'center', width_hint: 'narrow' },
      crms: { crms_field_name: 'currency', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in'],
    },

    // Relationships
    {
      name: 'member_id', type: 'relation', source: 'core', label: 'Member', group: 'Relationships',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['required', 'integer', 'exists:members,id'],
      display: { format: 'relation', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'member_id', crms_transform: null },
      relation: { relation_name: 'member', relation_type: 'belongsTo', related_model: 'Member', related_field: 'id' },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in', '_null', '_not_null'],
    },
    {
      name: 'store_id', type: 'relation', source: 'core', label: 'Store', group: 'Relationships',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: false },
      validation: ['required', 'integer', 'exists:stores,id'],
      display: { format: 'relation', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'store_id', crms_transform: null },
      relation: { relation_name: 'store', relation_type: 'belongsTo', related_model: 'Store', related_field: 'id' },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in'],
    },
    {
      name: 'owner_id', type: 'relation', source: 'core', label: 'Owner', group: 'Relationships',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'integer', 'exists:members,id'],
      display: { format: 'relation', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'owner_id', crms_transform: null },
      relation: { relation_name: 'owner', relation_type: 'belongsTo', related_model: 'Member', related_field: 'id' },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in', '_null', '_not_null'],
    },
    {
      name: 'billing_address_id', type: 'relation', source: 'core', label: 'Billing Address', group: 'Relationships',
      capabilities: { filterable: false, sortable: false, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'integer', 'exists:addresses,id'],
      display: { format: 'relation', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'billing_address_id', crms_transform: null },
      relation: { relation_name: 'billingAddress', relation_type: 'belongsTo', related_model: 'Address', related_field: 'id' },
      filter_operators: ['_eq', '_null', '_not_null'],
    },

    // Computed
    {
      name: 'total_items', type: 'integer', source: 'computed', label: 'Total Items', group: 'Calculated',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: [],
      display: { format: 'number', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'item_count', crms_transform: null },
      sql_expression: 'SELECT COUNT(*) FROM opportunity_items WHERE opportunity_id = opportunities.id',
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'outstanding_balance', type: 'currency', source: 'computed', label: 'Outstanding Balance', group: 'Calculated',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: [],
      display: { format: 'currency', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'balance', crms_transform: 'minor_to_decimal' },
      sql_expression: "charge_total - COALESCE((SELECT SUM(amount) FROM payments WHERE payable_type = 'opportunity' AND payable_id = opportunities.id), 0)",
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'days_until_return', type: 'integer', source: 'computed', label: 'Days Until Return', group: 'Calculated',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: [],
      display: { format: 'number', alignment: 'right', width_hint: 'narrow' },
      crms: null,
      sql_expression: "EXTRACT(DAY FROM ends_at - NOW())",
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'margin_percentage', type: 'decimal', source: 'computed', label: 'Margin %', group: 'Calculated',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: [],
      display: { format: 'percentage', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'margin', crms_transform: 'to_percentage' },
      sql_expression: "(charge_total - cost_total) / NULLIF(charge_total, 0) * 100",
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'is_overdue', type: 'boolean', source: 'computed', label: 'Is Overdue', group: 'Calculated',
      capabilities: { filterable: true, sortable: false, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: false },
      validation: [],
      display: { format: 'boolean', alignment: 'center', width_hint: 'narrow' },
      crms: null,
      sql_expression: "ends_at < NOW() AND state != 'closed'",
      filter_operators: ['_true', '_false'],
    },

    // Custom
    {
      name: 'po_reference', type: 'string', source: 'custom', label: 'PO Reference', group: 'Custom Fields',
      capabilities: { filterable: true, sortable: false, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'string', 'max:255'],
      display: { format: 'text', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'custom_po_reference', crms_transform: null },
      plugin: null,
      filter_operators: ['_eq', '_not_eq', '_cont', '_present', '_blank'],
    },
    {
      name: 'venue_setup_notes', type: 'text', source: 'custom', label: 'Venue Setup Notes', group: 'Custom Fields',
      capabilities: { filterable: false, sortable: false, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'string', 'max:5000'],
      display: { format: 'text', alignment: 'left', width_hint: 'wide' },
      crms: { crms_field_name: 'custom_venue_setup_notes', crms_transform: null },
      plugin: null,
      filter_operators: ['_cont', '_not_cont', '_present', '_blank'],
    },
    {
      name: 'priority_level', type: 'enum', source: 'custom', label: 'Priority Level', group: 'Custom Fields',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'string', 'in:low,medium,high,urgent'],
      display: { format: 'badge', alignment: 'left', width_hint: 'narrow' },
      crms: { crms_field_name: 'custom_priority_level', crms_transform: null },
      plugin: null,
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in', '_null', '_not_null'],
    },
    {
      name: 'xero_invoice_id', type: 'string', source: 'custom', label: 'Xero Invoice ID', group: 'Custom Fields',
      capabilities: { filterable: true, sortable: false, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'string', 'max:255'],
      display: { format: 'text', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'custom_xero_invoice_id', crms_transform: null },
      plugin: 'xero',
      filter_operators: ['_eq', '_not_eq', '_present', '_blank'],
    },
    {
      name: 'estimated_crew_hours', type: 'decimal', source: 'custom', label: 'Est. Crew Hours', group: 'Custom Fields',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: ['nullable', 'numeric', 'min:0', 'max:9999.99'],
      display: { format: 'decimal', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'custom_estimated_crew_hours', crms_transform: null },
      plugin: null,
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
  ];
}

/* ================================================================== */
/*  MEMBER FIELDS                                                      */
/* ================================================================== */
function buildMemberFields() {
  return [
    {
      name: 'name', type: 'string', source: 'core', label: 'Name', group: 'Identity',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: true },
      validation: ['required', 'string', 'max:255'],
      display: { format: 'text', alignment: 'left', width_hint: 'wide' },
      crms: { crms_field_name: 'name', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_cont', '_not_cont', '_start', '_end', '_present', '_blank'],
    },
    {
      name: 'membership_type', type: 'enum', source: 'core', label: 'Type', group: 'Identity',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: true },
      validation: ['required', 'string', 'in:contact,organisation,venue,user'],
      display: { format: 'badge', alignment: 'left', width_hint: 'narrow' },
      crms: { crms_field_name: 'member_type', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in'],
    },
    {
      name: 'email', type: 'string', source: 'core', label: 'Email', group: 'Contact',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: true },
      validation: ['nullable', 'email', 'max:255'],
      display: { format: 'email', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'email', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_cont', '_present', '_blank'],
    },
    {
      name: 'phone', type: 'string', source: 'core', label: 'Phone', group: 'Contact',
      capabilities: { filterable: true, sortable: false, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: true },
      validation: ['nullable', 'string', 'max:50'],
      display: { format: 'phone', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'phone_number', crms_transform: null },
      filter_operators: ['_eq', '_cont', '_present', '_blank'],
    },
    {
      name: 'company_name', type: 'string', source: 'core', label: 'Company Name', group: 'Organisation',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: true, aggregatable: false, exportable: true, importable: true },
      validation: ['nullable', 'string', 'max:255'],
      display: { format: 'text', alignment: 'left', width_hint: 'wide' },
      crms: { crms_field_name: 'company_name', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_cont', '_present', '_blank'],
    },
    {
      name: 'is_active', type: 'boolean', source: 'core', label: 'Active', group: 'Membership',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: false },
      validation: ['boolean'],
      display: { format: 'boolean', alignment: 'center', width_hint: 'narrow' },
      crms: { crms_field_name: 'active', crms_transform: null },
      filter_operators: ['_true', '_false'],
    },
    {
      name: 'created_at', type: 'datetime', source: 'core', label: 'Created', group: 'Membership',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: [],
      display: { format: 'datetime', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'created_at', crms_transform: 'iso8601' },
      filter_operators: ['_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'total_revenue', type: 'currency', source: 'computed', label: 'Total Revenue', group: 'Membership',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: [],
      display: { format: 'currency', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'revenue', crms_transform: 'minor_to_decimal' },
      sql_expression: "SELECT SUM(charge_total) FROM opportunities WHERE member_id = members.id AND state NOT IN ('cancelled', 'draft')",
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'opportunity_count', type: 'integer', source: 'computed', label: 'Opportunities', group: 'Membership',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: [],
      display: { format: 'number', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'opportunity_count', crms_transform: null },
      sql_expression: "SELECT COUNT(*) FROM opportunities WHERE member_id = members.id",
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'loyalty_tier', type: 'enum', source: 'custom', label: 'Loyalty Tier', group: 'Custom Fields',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'string', 'in:bronze,silver,gold,platinum'],
      display: { format: 'badge', alignment: 'left', width_hint: 'narrow' },
      crms: { crms_field_name: 'custom_loyalty_tier', crms_transform: null },
      plugin: null,
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in', '_null', '_not_null'],
    },
  ];
}

/* ================================================================== */
/*  PRODUCT FIELDS                                                     */
/* ================================================================== */
function buildProductFields() {
  return [
    {
      name: 'name', type: 'string', source: 'core', label: 'Name', group: 'Catalog',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: true },
      validation: ['required', 'string', 'max:255'],
      display: { format: 'text', alignment: 'left', width_hint: 'wide' },
      crms: { crms_field_name: 'name', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_cont', '_not_cont', '_start', '_end', '_present', '_blank'],
    },
    {
      name: 'sku', type: 'string', source: 'core', label: 'SKU', group: 'Catalog',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: true },
      validation: ['nullable', 'string', 'max:100'],
      display: { format: 'text', alignment: 'left', width_hint: 'narrow' },
      crms: { crms_field_name: 'sku', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_cont', '_start', '_present', '_blank'],
    },
    {
      name: 'product_type', type: 'enum', source: 'core', label: 'Type', group: 'Catalog',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: true },
      validation: ['required', 'string', 'in:equipment,consumable,service,transport,virtual'],
      display: { format: 'badge', alignment: 'left', width_hint: 'narrow' },
      crms: { crms_field_name: 'product_type', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in'],
    },
    {
      name: 'rate', type: 'currency', source: 'core', label: 'Rate', group: 'Pricing',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: true },
      validation: ['required', 'integer', 'min:0'],
      display: { format: 'currency', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'rate', crms_transform: 'minor_to_decimal' },
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'replacement_cost', type: 'currency', source: 'core', label: 'Replacement Cost', group: 'Pricing',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: true },
      validation: ['nullable', 'integer', 'min:0'],
      display: { format: 'currency', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'replacement_cost', crms_transform: 'minor_to_decimal' },
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq', '_null', '_not_null'],
    },
    {
      name: 'stock_quantity', type: 'integer', source: 'core', label: 'Stock Qty', group: 'Inventory',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: true },
      validation: ['required', 'integer', 'min:0'],
      display: { format: 'number', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'quantity_owned', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'is_active', type: 'boolean', source: 'core', label: 'Active', group: 'Catalog',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: true },
      validation: ['boolean'],
      display: { format: 'boolean', alignment: 'center', width_hint: 'narrow' },
      crms: { crms_field_name: 'active', crms_transform: null },
      filter_operators: ['_true', '_false'],
    },
    {
      name: 'available_quantity', type: 'integer', source: 'computed', label: 'Available Qty', group: 'Inventory',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: [],
      display: { format: 'number', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'quantity_available', crms_transform: null },
      sql_expression: "stock_quantity - COALESCE((SELECT SUM(quantity) FROM opportunity_items WHERE product_id = products.id AND state = 'active'), 0)",
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'utilisation_rate', type: 'decimal', source: 'computed', label: 'Utilisation %', group: 'Inventory',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: [],
      display: { format: 'percentage', alignment: 'right', width_hint: 'narrow' },
      crms: null,
      sql_expression: "(stock_quantity - available_quantity) / NULLIF(stock_quantity, 0) * 100",
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'weight_kg', type: 'decimal', source: 'custom', label: 'Weight (kg)', group: 'Custom Fields',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: true },
      validation: ['nullable', 'numeric', 'min:0', 'max:99999.99'],
      display: { format: 'decimal', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'custom_weight_kg', crms_transform: null },
      plugin: null,
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq', '_null', '_not_null'],
    },
  ];
}

/* ================================================================== */
/*  INVOICE FIELDS                                                     */
/* ================================================================== */
function buildInvoiceFields() {
  return [
    {
      name: 'number', type: 'string', source: 'core', label: 'Invoice Number', group: 'Invoicing',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['required', 'string', 'max:50'],
      display: { format: 'text', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'invoice_number', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_cont', '_start', '_present', '_blank'],
    },
    {
      name: 'state', type: 'enum', source: 'core', label: 'State', group: 'Invoicing',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: false },
      validation: ['required', 'string', 'in:draft,issued,paid,void,credited'],
      display: { format: 'badge', alignment: 'left', width_hint: 'narrow' },
      crms: { crms_field_name: 'status', crms_transform: 'invoice_state_map' },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in'],
    },
    {
      name: 'total', type: 'currency', source: 'core', label: 'Total', group: 'Invoicing',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: ['required', 'integer', 'min:0'],
      display: { format: 'currency', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'total', crms_transform: 'minor_to_decimal' },
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'issued_at', type: 'datetime', source: 'core', label: 'Issued At', group: 'Invoicing',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'date'],
      display: { format: 'datetime', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'issue_date', crms_transform: 'iso8601' },
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq', '_null', '_not_null'],
    },
    {
      name: 'due_at', type: 'datetime', source: 'core', label: 'Due Date', group: 'Invoicing',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'date', 'after_or_equal:issued_at'],
      display: { format: 'datetime', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'due_date', crms_transform: 'iso8601' },
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq', '_null', '_not_null'],
    },
    {
      name: 'member_id', type: 'relation', source: 'core', label: 'Member', group: 'Invoicing',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['required', 'integer', 'exists:members,id'],
      display: { format: 'relation', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'member_id', crms_transform: null },
      relation: { relation_name: 'member', relation_type: 'belongsTo', related_model: 'Member', related_field: 'id' },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in'],
    },
    {
      name: 'opportunity_id', type: 'relation', source: 'core', label: 'Opportunity', group: 'Invoicing',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'integer', 'exists:opportunities,id'],
      display: { format: 'relation', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'opportunity_id', crms_transform: null },
      relation: { relation_name: 'opportunity', relation_type: 'belongsTo', related_model: 'Opportunity', related_field: 'id' },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in', '_null', '_not_null'],
    },
    {
      name: 'amount_paid', type: 'currency', source: 'computed', label: 'Amount Paid', group: 'Payments',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: [],
      display: { format: 'currency', alignment: 'right', width_hint: 'narrow' },
      crms: { crms_field_name: 'paid', crms_transform: 'minor_to_decimal' },
      sql_expression: "COALESCE((SELECT SUM(amount) FROM payments WHERE invoice_id = invoices.id), 0)",
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'is_overdue', type: 'boolean', source: 'computed', label: 'Is Overdue', group: 'Payments',
      capabilities: { filterable: true, sortable: false, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: false },
      validation: [],
      display: { format: 'boolean', alignment: 'center', width_hint: 'narrow' },
      crms: null,
      sql_expression: "due_at < NOW() AND state = 'issued'",
      filter_operators: ['_true', '_false'],
    },
    {
      name: 'xero_reference', type: 'string', source: 'custom', label: 'Xero Reference', group: 'Custom Fields',
      capabilities: { filterable: true, sortable: false, searchable: false, groupable: false, aggregatable: false, exportable: true, importable: false },
      validation: ['nullable', 'string', 'max:255'],
      display: { format: 'text', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'custom_xero_reference', crms_transform: null },
      plugin: 'xero',
      filter_operators: ['_eq', '_not_eq', '_present', '_blank'],
    },
  ];
}

/* ================================================================== */
/*  STORE FIELDS                                                       */
/* ================================================================== */
function buildStoreFields() {
  return [
    {
      name: 'name', type: 'string', source: 'core', label: 'Name', group: 'Identity',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: true },
      validation: ['required', 'string', 'max:255'],
      display: { format: 'text', alignment: 'left', width_hint: 'wide' },
      crms: { crms_field_name: 'name', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_cont', '_not_cont', '_start', '_present', '_blank'],
    },
    {
      name: 'code', type: 'string', source: 'core', label: 'Code', group: 'Identity',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: true },
      validation: ['required', 'string', 'max:20'],
      display: { format: 'text', alignment: 'left', width_hint: 'narrow' },
      crms: { crms_field_name: 'code', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_cont', '_start'],
    },
    {
      name: 'is_active', type: 'boolean', source: 'core', label: 'Active', group: 'Identity',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: true },
      validation: ['boolean'],
      display: { format: 'boolean', alignment: 'center', width_hint: 'narrow' },
      crms: { crms_field_name: 'active', crms_transform: null },
      filter_operators: ['_true', '_false'],
    },
    {
      name: 'address_line_1', type: 'string', source: 'core', label: 'Address', group: 'Location',
      capabilities: { filterable: false, sortable: false, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: true },
      validation: ['nullable', 'string', 'max:255'],
      display: { format: 'text', alignment: 'left', width_hint: 'wide' },
      crms: { crms_field_name: 'address', crms_transform: null },
      filter_operators: ['_cont', '_present', '_blank'],
    },
    {
      name: 'city', type: 'string', source: 'core', label: 'City', group: 'Location',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: true, aggregatable: false, exportable: true, importable: true },
      validation: ['nullable', 'string', 'max:100'],
      display: { format: 'text', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'city', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_cont', '_in'],
    },
    {
      name: 'country_code', type: 'string', source: 'core', label: 'Country', group: 'Location',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: true },
      validation: ['nullable', 'string', 'size:2'],
      display: { format: 'country', alignment: 'left', width_hint: 'narrow' },
      crms: { crms_field_name: 'country', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_in', '_not_in'],
    },
    {
      name: 'opportunity_count', type: 'integer', source: 'computed', label: 'Opportunities', group: 'Identity',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: [],
      display: { format: 'number', alignment: 'right', width_hint: 'narrow' },
      crms: null,
      sql_expression: "SELECT COUNT(*) FROM opportunities WHERE store_id = stores.id",
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'total_stock_value', type: 'currency', source: 'computed', label: 'Stock Value', group: 'Identity',
      capabilities: { filterable: true, sortable: true, searchable: false, groupable: false, aggregatable: true, exportable: true, importable: false },
      validation: [],
      display: { format: 'currency', alignment: 'right', width_hint: 'narrow' },
      crms: null,
      sql_expression: "SELECT SUM(p.replacement_cost * si.quantity) FROM store_inventory si JOIN products p ON p.id = si.product_id WHERE si.store_id = stores.id",
      filter_operators: ['_eq', '_not_eq', '_lt', '_lteq', '_gt', '_gteq'],
    },
    {
      name: 'timezone', type: 'string', source: 'core', label: 'Timezone', group: 'Location',
      capabilities: { filterable: true, sortable: false, searchable: false, groupable: true, aggregatable: false, exportable: true, importable: true },
      validation: ['nullable', 'string', 'timezone'],
      display: { format: 'text', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'timezone', crms_transform: null },
      filter_operators: ['_eq', '_not_eq', '_in'],
    },
    {
      name: 'manager_name', type: 'string', source: 'custom', label: 'Manager Name', group: 'Custom Fields',
      capabilities: { filterable: true, sortable: true, searchable: true, groupable: false, aggregatable: false, exportable: true, importable: true },
      validation: ['nullable', 'string', 'max:255'],
      display: { format: 'text', alignment: 'left', width_hint: 'medium' },
      crms: { crms_field_name: 'custom_manager_name', crms_transform: null },
      plugin: null,
      filter_operators: ['_eq', '_not_eq', '_cont', '_present', '_blank'],
    },
  ];
}
</script>
@endverbatim
