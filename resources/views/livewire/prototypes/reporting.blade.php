<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Reporting Framework')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  REPORTING TOKENS — maps to brand system in app.css              */
  /* ================================================================ */

  :root {
    --rp-bg: var(--content-bg);
    --rp-panel: var(--card-bg);
    --rp-surface: var(--base);
    --rp-border: var(--card-border);
    --rp-border-subtle: var(--grey-border);
    --rp-text: var(--text-primary);
    --rp-text-secondary: var(--text-secondary);
    --rp-text-muted: var(--text-muted);
    --rp-accent: var(--green);
    --rp-accent-dim: var(--green-muted);
    --rp-hover: rgba(0, 0, 0, 0.04);
    --rp-shadow: var(--shadow-card);
    --rp-sidebar-w: 280px;
    --rp-blue: var(--blue);
    --rp-blue-light: var(--blue-light);
    --rp-green: var(--green);
    --rp-amber: var(--amber);
    --rp-red: var(--red);
    --rp-violet: var(--violet);
    --rp-sky: var(--sky);
    --rp-cyan: var(--cyan);
    --rp-rose: var(--rose);
    --rp-grey: var(--grey);
    --rp-grey-light: var(--grey-light);
    --rp-positive: var(--green);
    --rp-negative: var(--red);
    --rp-badge-dim: rgba(37, 99, 235, 0.08);
    --rp-badge-measure: rgba(5, 150, 105, 0.08);
    --rp-bar-1: var(--blue);
    --rp-bar-2: var(--green);
    --rp-bar-3: var(--amber);
    --rp-subtotal-bg: rgba(0, 0, 0, 0.02);
    --rp-grandtotal-bg: rgba(0, 0, 0, 0.05);
  }

  .dark {
    --rp-bg: var(--content-bg);
    --rp-panel: var(--card-bg);
    --rp-surface: var(--navy-mid);
    --rp-border: var(--card-border);
    --rp-border-subtle: #283040;
    --rp-text: var(--text-primary);
    --rp-text-secondary: var(--text-secondary);
    --rp-text-muted: var(--text-muted);
    --rp-accent: var(--green);
    --rp-accent-dim: rgba(5, 150, 105, 0.12);
    --rp-hover: rgba(255, 255, 255, 0.06);
    --rp-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --rp-badge-dim: rgba(37, 99, 235, 0.15);
    --rp-badge-measure: rgba(5, 150, 105, 0.15);
    --rp-subtotal-bg: rgba(255, 255, 255, 0.03);
    --rp-grandtotal-bg: rgba(255, 255, 255, 0.06);
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */

  .rp-page {
    display: flex;
    flex: 1 1 0;
    height: 0;
    min-height: 0;
    overflow: hidden;
    font-family: var(--font-mono);
    font-size: 12px;
    line-height: 1.5;
    color: var(--rp-text);
    -webkit-font-smoothing: antialiased;
  }

  /* ================================================================ */
  /*  SIDEBAR                                                          */
  /* ================================================================ */

  .rp-sidebar {
    width: var(--rp-sidebar-w);
    min-width: var(--rp-sidebar-w);
    background: var(--rp-panel);
    border-right: 1px solid var(--rp-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .rp-sidebar-header {
    padding: 16px 16px 12px;
    border-bottom: 1px solid var(--rp-border-subtle);
  }

  .rp-sidebar-title {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--rp-text-muted);
    margin-bottom: 10px;
  }

  .rp-search {
    width: 100%;
    background: var(--rp-surface);
    border: 1px solid var(--rp-border-subtle);
    border-radius: 6px;
    padding: 7px 10px 7px 30px;
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--rp-text);
    outline: none;
    transition: border-color 0.15s;
  }

  .rp-search:focus {
    border-color: var(--rp-blue);
  }

  .rp-search-wrap {
    position: relative;
  }

  .rp-search-icon {
    position: absolute;
    left: 9px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--rp-text-muted);
    width: 14px;
    height: 14px;
    pointer-events: none;
  }

  .rp-sidebar-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
  }

  .rp-group-label {
    font-family: var(--font-display);
    font-weight: 600;
    font-size: 10px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--rp-text-muted);
    padding: 12px 16px 6px;
  }

  .rp-report-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: all 0.12s;
  }

  .rp-report-item:hover {
    background: var(--rp-hover);
  }

  .rp-report-item.rp-active {
    background: var(--rp-accent-dim);
    border-left-color: var(--rp-accent);
  }

  .rp-report-name {
    flex: 1;
    font-size: 12px;
    font-weight: 500;
    color: var(--rp-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .rp-report-source {
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 2px 6px;
    border-radius: 4px;
    background: var(--rp-badge-dim);
    color: var(--rp-blue);
    white-space: nowrap;
  }

  .rp-pin-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px;
    color: var(--rp-text-muted);
    opacity: 0.4;
    transition: opacity 0.15s, color 0.15s;
    display: flex;
    align-items: center;
  }

  .rp-pin-btn:hover,
  .rp-pin-btn.rp-pinned {
    opacity: 1;
    color: var(--rp-amber);
  }

  /* ================================================================ */
  /*  MAIN CONTENT                                                     */
  /* ================================================================ */

  .rp-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--rp-bg);
  }

  .rp-main-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px;
  }

  /* ================================================================ */
  /*  REPORT HEADER                                                    */
  /* ================================================================ */

  .rp-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
  }

  .rp-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .rp-report-title {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 18px;
    color: var(--rp-text);
  }

  .rp-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* ================================================================ */
  /*  BUTTONS                                                          */
  /* ================================================================ */

  .rp-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 6px;
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--rp-border-subtle);
    background: var(--rp-panel);
    color: var(--rp-text-secondary);
    transition: all 0.12s;
    white-space: nowrap;
  }

  .rp-btn:hover {
    background: var(--rp-hover);
    border-color: var(--rp-grey-light);
  }

  .rp-btn svg {
    width: 14px;
    height: 14px;
  }

  .rp-btn-primary {
    background: var(--rp-accent);
    color: #fff;
    border-color: var(--rp-accent);
  }

  .rp-btn-primary:hover {
    background: #047857;
    border-color: #047857;
  }

  .rp-btn-sm {
    padding: 5px 10px;
    font-size: 10px;
  }

  .rp-btn-icon {
    padding: 7px 8px;
  }

  .rp-btn-icon.rp-active {
    background: var(--rp-badge-dim);
    border-color: var(--rp-blue);
    color: var(--rp-blue);
  }

  /* ================================================================ */
  /*  ACCORDION / BUILDER PANEL                                        */
  /* ================================================================ */

  .rp-builder {
    background: var(--rp-panel);
    border: 1px solid var(--rp-border);
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: var(--rp-shadow);
  }

  .rp-builder-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    cursor: pointer;
    user-select: none;
    border-radius: 8px;
    transition: background 0.12s;
  }

  .rp-builder-toggle:hover {
    background: var(--rp-hover);
  }

  .rp-builder-toggle-left {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .rp-builder-label {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--rp-text-secondary);
  }

  .rp-builder-chevron {
    width: 16px;
    height: 16px;
    color: var(--rp-text-muted);
    transition: transform 0.2s;
  }

  .rp-builder-chevron.rp-open {
    transform: rotate(180deg);
  }

  .rp-builder-body {
    padding: 0 16px 16px;
    display: grid;
    gap: 16px;
  }

  /* Builder sections */
  .rp-bld-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .rp-bld-label {
    font-family: var(--font-display);
    font-weight: 600;
    font-size: 10px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--rp-text-muted);
  }

  .rp-bld-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  /* ================================================================ */
  /*  FORM ELEMENTS                                                    */
  /* ================================================================ */

  .rp-select,
  .rp-input {
    background: var(--rp-surface);
    border: 1px solid var(--rp-border-subtle);
    border-radius: 6px;
    padding: 6px 10px;
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--rp-text);
    outline: none;
    transition: border-color 0.15s;
  }

  .rp-select:focus,
  .rp-input:focus {
    border-color: var(--rp-blue);
  }

  .rp-select {
    cursor: pointer;
    padding-right: 24px;
  }

  .rp-input-sm {
    width: 120px;
  }

  .rp-input-date {
    width: 140px;
  }

  /* ================================================================ */
  /*  CHIPS                                                            */
  /* ================================================================ */

  .rp-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--rp-surface);
    border: 1px solid var(--rp-border-subtle);
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 11px;
    color: var(--rp-text-secondary);
    transition: all 0.12s;
  }

  .rp-chip-label {
    font-weight: 500;
  }

  .rp-chip-remove {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--rp-text-muted);
    font-size: 13px;
    line-height: 1;
    padding: 0 1px;
    transition: color 0.15s;
    display: flex;
    align-items: center;
  }

  .rp-chip-remove:hover {
    color: var(--rp-red);
  }

  /* ================================================================ */
  /*  FILTER ROWS                                                      */
  /* ================================================================ */

  .rp-filter-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  /* ================================================================ */
  /*  TOGGLE SWITCH                                                    */
  /* ================================================================ */

  .rp-toggle-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .rp-toggle {
    position: relative;
    width: 36px;
    height: 20px;
    background: var(--rp-border-subtle);
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.2s;
    flex-shrink: 0;
  }

  .rp-toggle.rp-on {
    background: var(--rp-accent);
  }

  .rp-toggle-knob {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 2px rgba(0,0,0,0.15);
  }

  .rp-toggle.rp-on .rp-toggle-knob {
    transform: translateX(16px);
  }

  .rp-toggle-label {
    font-size: 11px;
    color: var(--rp-text-secondary);
  }

  /* ================================================================ */
  /*  MEASURE ROWS                                                     */
  /* ================================================================ */

  .rp-measure-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  /* ================================================================ */
  /*  RESULTS PANEL                                                    */
  /* ================================================================ */

  .rp-results {
    background: var(--rp-panel);
    border: 1px solid var(--rp-border);
    border-radius: 8px;
    box-shadow: var(--rp-shadow);
    overflow: hidden;
  }

  .rp-results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--rp-border-subtle);
  }

  .rp-results-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .rp-results-header-right {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .rp-results-title {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--rp-text-secondary);
  }

  .rp-results-count {
    font-size: 10px;
    color: var(--rp-text-muted);
    padding: 2px 8px;
    background: var(--rp-surface);
    border-radius: 10px;
  }

  /* ================================================================ */
  /*  VIZ TYPE SELECTOR                                                */
  /* ================================================================ */

  .rp-viz-btns {
    display: flex;
    gap: 2px;
    background: var(--rp-surface);
    border-radius: 6px;
    padding: 2px;
    border: 1px solid var(--rp-border-subtle);
  }

  .rp-viz-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 26px;
    border-radius: 4px;
    border: none;
    background: transparent;
    color: var(--rp-text-muted);
    cursor: pointer;
    transition: all 0.12s;
  }

  .rp-viz-btn:hover {
    color: var(--rp-text-secondary);
    background: var(--rp-hover);
  }

  .rp-viz-btn.rp-active {
    background: var(--rp-panel);
    color: var(--rp-blue);
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
  }

  .rp-viz-btn svg {
    width: 14px;
    height: 14px;
  }

  /* ================================================================ */
  /*  DATA TABLE                                                       */
  /* ================================================================ */

  .rp-table-wrap {
    overflow-x: auto;
  }

  .rp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }

  .rp-table th {
    background: var(--rp-surface);
    padding: 8px 12px;
    text-align: left;
    font-weight: 600;
    color: var(--rp-text-secondary);
    border-bottom: 1px solid var(--rp-border);
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 1;
  }

  .rp-table th.rp-col-measure {
    text-align: right;
  }

  .rp-table td {
    padding: 7px 12px;
    border-bottom: 1px solid var(--rp-border-subtle);
    color: var(--rp-text);
    white-space: nowrap;
  }

  .rp-table td.rp-col-measure {
    text-align: right;
    font-variant-numeric: tabular-nums;
  }

  .rp-table tr:hover td {
    background: var(--rp-hover);
  }

  .rp-table tr.rp-row-subtotal td {
    background: var(--rp-subtotal-bg);
    font-weight: 600;
    border-bottom-color: var(--rp-border);
  }

  .rp-table tr.rp-row-grandtotal td {
    background: var(--rp-grandtotal-bg);
    font-weight: 700;
    border-bottom: none;
    font-size: 12px;
  }

  /* Column badges */
  .rp-col-badge {
    display: inline-block;
    font-size: 8px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 1px 5px;
    border-radius: 3px;
    margin-left: 6px;
    vertical-align: middle;
  }

  .rp-col-badge-dim {
    background: var(--rp-badge-dim);
    color: var(--rp-blue);
  }

  .rp-col-badge-sum {
    background: var(--rp-badge-measure);
    color: var(--rp-green);
  }

  .rp-col-badge-count {
    background: rgba(217, 119, 6, 0.08);
    color: var(--rp-amber);
  }

  .rp-col-badge-avg {
    background: rgba(124, 58, 237, 0.08);
    color: var(--rp-violet);
  }

  /* Change indicators */
  .rp-change {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 10px;
    font-weight: 600;
  }

  .rp-change-pos {
    color: var(--rp-positive);
  }

  .rp-change-neg {
    color: var(--rp-negative);
  }

  .rp-change svg {
    width: 10px;
    height: 10px;
  }

  .rp-prev-val {
    color: var(--rp-text-muted);
    font-size: 10px;
  }

  /* ================================================================ */
  /*  BAR CHART                                                        */
  /* ================================================================ */

  .rp-chart-wrap {
    padding: 20px 16px;
  }

  .rp-chart-legend {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
    padding-left: 120px;
  }

  .rp-legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: var(--rp-text-secondary);
  }

  .rp-legend-swatch {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    flex-shrink: 0;
  }

  .rp-chart-group {
    margin-bottom: 14px;
  }

  .rp-chart-group-label {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 12px;
    color: var(--rp-text);
    margin-bottom: 8px;
    padding-left: 0;
  }

  .rp-bar-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
    height: 28px;
  }

  .rp-bar-label {
    width: 110px;
    text-align: right;
    font-size: 11px;
    color: var(--rp-text-secondary);
    flex-shrink: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .rp-bar-track {
    flex: 1;
    height: 22px;
    background: var(--rp-surface);
    border-radius: 4px;
    overflow: hidden;
    position: relative;
  }

  .rp-bar-fill {
    height: 100%;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 8px;
    font-size: 10px;
    font-weight: 600;
    color: #fff;
    transition: width 0.4s ease;
    min-width: 0;
  }

  .rp-bar-fill-1 { background: var(--rp-bar-1); }
  .rp-bar-fill-2 { background: var(--rp-bar-2); }
  .rp-bar-fill-3 { background: var(--rp-bar-3); }

  .rp-bar-value {
    font-size: 10px;
    font-weight: 600;
    color: var(--rp-text-muted);
    min-width: 60px;
    text-align: left;
    flex-shrink: 0;
  }

  /* ================================================================ */
  /*  LINE CHART (CSS only)                                            */
  /* ================================================================ */

  .rp-line-placeholder {
    padding: 40px;
    text-align: center;
    color: var(--rp-text-muted);
    font-size: 12px;
  }

  /* ================================================================ */
  /*  PIE CHART (CSS only)                                             */
  /* ================================================================ */

  .rp-pie-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 40px;
    padding: 30px 20px;
  }

  .rp-pie {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    position: relative;
    flex-shrink: 0;
  }

  .rp-pie-legend {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .rp-pie-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--rp-text);
  }

  .rp-pie-legend-swatch {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    flex-shrink: 0;
  }

  .rp-pie-legend-pct {
    color: var(--rp-text-muted);
    font-size: 11px;
    margin-left: auto;
    padding-left: 12px;
  }

  /* ================================================================ */
  /*  SCHEDULE DROPDOWN                                                */
  /* ================================================================ */

  .rp-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    background: var(--rp-panel);
    border: 1px solid var(--rp-border);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    min-width: 180px;
    z-index: 50;
    padding: 4px;
  }

  .dark .rp-dropdown {
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
  }

  .rp-dropdown-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    font-size: 11px;
    color: var(--rp-text-secondary);
    cursor: pointer;
    border-radius: 6px;
    transition: background 0.1s;
  }

  .rp-dropdown-item:hover {
    background: var(--rp-hover);
    color: var(--rp-text);
  }

  .rp-dropdown-item svg {
    width: 14px;
    height: 14px;
    color: var(--rp-text-muted);
  }

  /* ================================================================ */
  /*  ADD DROPDOWN                                                     */
  /* ================================================================ */

  .rp-add-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    background: var(--rp-panel);
    border: 1px solid var(--rp-border);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    min-width: 200px;
    z-index: 50;
    padding: 4px;
    max-height: 200px;
    overflow-y: auto;
  }

  .dark .rp-add-dropdown {
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
  }

  /* ================================================================ */
  /*  TOAST / NOTIFICATION                                             */
  /* ================================================================ */

  .rp-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--rp-panel);
    border: 1px solid var(--rp-border);
    border-radius: 8px;
    padding: 12px 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    font-size: 12px;
    color: var(--rp-text);
    display: flex;
    align-items: center;
    gap: 8px;
    z-index: 100;
    transition: opacity 0.3s, transform 0.3s;
  }

  .dark .rp-toast {
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
  }

  .rp-toast-icon {
    width: 16px;
    height: 16px;
    color: var(--rp-accent);
    flex-shrink: 0;
  }

  /* ================================================================ */
  /*  SCROLLBAR                                                        */
  /* ================================================================ */

  .rp-sidebar-list::-webkit-scrollbar,
  .rp-main-scroll::-webkit-scrollbar,
  .rp-table-wrap::-webkit-scrollbar {
    width: 6px;
    height: 6px;
  }

  .rp-sidebar-list::-webkit-scrollbar-thumb,
  .rp-main-scroll::-webkit-scrollbar-thumb,
  .rp-table-wrap::-webkit-scrollbar-thumb {
    background: var(--rp-border-subtle);
    border-radius: 3px;
  }

  .rp-sidebar-list::-webkit-scrollbar-track,
  .rp-main-scroll::-webkit-scrollbar-track,
  .rp-table-wrap::-webkit-scrollbar-track {
    background: transparent;
  }
</style>

<div class="rp-page" x-data="reportingFramework()">

  {{-- ═══════════════════ SIDEBAR ═══════════════════ --}}
  <aside class="rp-sidebar">
    <div class="rp-sidebar-header">
      <div class="rp-sidebar-title">Reports</div>
      <div class="rp-search-wrap">
        <svg class="rp-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" class="rp-search" placeholder="Search reports..." x-model="search" />
      </div>
    </div>

    <div class="rp-sidebar-list">
      {{-- System Reports --}}
      <template x-if="filteredReports('system').length > 0">
        <div>
          <div class="rp-group-label">System Reports</div>
          <template x-for="report in filteredReports('system')" :key="report.id">
            <div class="rp-report-item"
                 :class="{ 'rp-active': selectedReport?.id === report.id }"
                 @click="selectReport(report)">
              <span class="rp-report-name" x-text="report.name"></span>
              <span class="rp-report-source" x-text="report.source"></span>
              <button class="rp-pin-btn" :class="{ 'rp-pinned': report.pinned }" @click.stop="report.pinned = !report.pinned">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5v6l1 1 1-1v-6h5v-2z"/></svg>
              </button>
            </div>
          </template>
        </div>
      </template>

      {{-- My Reports --}}
      <template x-if="filteredReports('custom').length > 0">
        <div>
          <div class="rp-group-label">My Reports</div>
          <template x-for="report in filteredReports('custom')" :key="report.id">
            <div class="rp-report-item"
                 :class="{ 'rp-active': selectedReport?.id === report.id }"
                 @click="selectReport(report)">
              <span class="rp-report-name" x-text="report.name"></span>
              <span class="rp-report-source" x-text="report.source"></span>
              <button class="rp-pin-btn" :class="{ 'rp-pinned': report.pinned }" @click.stop="report.pinned = !report.pinned">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5v6l1 1 1-1v-6h5v-2z"/></svg>
              </button>
            </div>
          </template>
        </div>
      </template>
    </div>
  </aside>

  {{-- ═══════════════════ MAIN CONTENT ═══════════════════ --}}
  <div class="rp-main">
    <div class="rp-main-scroll">

      {{-- ── Report header ── --}}
      <div class="rp-header">
        <div class="rp-header-left">
          <h1 class="rp-report-title" x-text="selectedReport?.name ?? 'Select a Report'"></h1>
        </div>
        <div class="rp-header-actions">
          <button class="rp-btn rp-btn-sm" @click="showToast('CSV export started')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
          </button>
          <button class="rp-btn rp-btn-sm" @click="showToast('PDF export started')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Export PDF
          </button>
          <button class="rp-btn rp-btn-sm" @click="showToast('Report saved')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Report
          </button>
          <div class="relative" style="position: relative;">
            <button class="rp-btn rp-btn-sm" @click="scheduleOpen = !scheduleOpen">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Schedule
            </button>
            <div class="rp-dropdown" x-show="scheduleOpen" @click.away="scheduleOpen = false" x-transition>
              <div class="rp-dropdown-item" @click="showToast('Scheduled: Daily'); scheduleOpen = false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Daily
              </div>
              <div class="rp-dropdown-item" @click="showToast('Scheduled: Weekly'); scheduleOpen = false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Weekly (Monday)
              </div>
              <div class="rp-dropdown-item" @click="showToast('Scheduled: Monthly'); scheduleOpen = false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Monthly (1st)
              </div>
              <div class="rp-dropdown-item" @click="showToast('Scheduled: Quarterly'); scheduleOpen = false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Quarterly
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ═══════════════════ REPORT BUILDER ═══════════════════ --}}
      <div class="rp-builder">
        <div class="rp-builder-toggle" @click="builderOpen = !builderOpen">
          <div class="rp-builder-toggle-left">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            <span class="rp-builder-label">Report Builder</span>
          </div>
          <svg class="rp-builder-chevron" :class="{ 'rp-open': builderOpen }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>

        <div class="rp-builder-body" x-show="builderOpen" x-transition>

          {{-- Row 1: Data Source + Date Range --}}
          <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
            {{-- Data Source --}}
            <div class="rp-bld-section">
              <div class="rp-bld-label">Data Source</div>
              <select class="rp-select" x-model="builder.dataSource" @change="onDataSourceChange()">
                <template x-for="src in dataSources" :key="src">
                  <option :value="src" x-text="src"></option>
                </template>
              </select>
            </div>

            {{-- Date Range --}}
            <div class="rp-bld-section">
              <div class="rp-bld-label">Date Range</div>
              <div class="rp-bld-row">
                <select class="rp-select" x-model="builder.dateField">
                  <template x-for="f in dateFields" :key="f">
                    <option :value="f" x-text="f"></option>
                  </template>
                </select>
                <select class="rp-select" x-model="builder.datePreset" @change="onDatePresetChange()">
                  <template x-for="p in datePresets" :key="p">
                    <option :value="p" x-text="p"></option>
                  </template>
                </select>
                <template x-if="builder.datePreset === 'Custom'">
                  <div class="rp-bld-row">
                    <input type="date" class="rp-input rp-input-date" x-model="builder.dateFrom" />
                    <span style="color: var(--rp-text-muted);">to</span>
                    <input type="date" class="rp-input rp-input-date" x-model="builder.dateTo" />
                  </div>
                </template>
              </div>
            </div>
          </div>

          {{-- Dimensions --}}
          <div class="rp-bld-section">
            <div class="rp-bld-label">Dimensions</div>
            <div class="rp-bld-row">
              <template x-for="(dim, idx) in builder.dimensions" :key="idx">
                <div class="rp-chip">
                  <span class="rp-chip-label" x-text="dim.field"></span>
                  <template x-if="dim.showBucket">
                    <select class="rp-select" style="padding: 2px 6px; font-size: 10px; min-width: 70px;" x-model="dim.bucket">
                      <option value="">No bucket</option>
                      <template x-for="b in buckets" :key="b">
                        <option :value="b" x-text="b"></option>
                      </template>
                    </select>
                  </template>
                  <button class="rp-chip-remove" @click="builder.dimensions.splice(idx, 1)">&times;</button>
                </div>
              </template>

              <div style="position: relative;">
                <button class="rp-btn rp-btn-sm" @click="dimDropdownOpen = !dimDropdownOpen">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  Add Dimension
                </button>
                <div class="rp-add-dropdown" x-show="dimDropdownOpen" @click.away="dimDropdownOpen = false" x-transition>
                  <template x-for="f in availableDimFields()" :key="f.field">
                    <div class="rp-dropdown-item" @click="addDimension(f); dimDropdownOpen = false" x-text="f.field"></div>
                  </template>
                </div>
              </div>
            </div>
          </div>

          {{-- Measures --}}
          <div class="rp-bld-section">
            <div class="rp-bld-label">Measures</div>
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <template x-for="(m, idx) in builder.measures" :key="idx">
                <div class="rp-measure-row">
                  <select class="rp-select" x-model="m.field">
                    <template x-for="f in measureFields" :key="f">
                      <option :value="f" x-text="f"></option>
                    </template>
                  </select>
                  <select class="rp-select" x-model="m.agg">
                    <template x-for="a in aggregations" :key="a">
                      <option :value="a" x-text="a"></option>
                    </template>
                  </select>
                  <input type="text" class="rp-input rp-input-sm" placeholder="Label" x-model="m.label" />
                  <button class="rp-chip-remove" @click="builder.measures.splice(idx, 1)">&times;</button>
                </div>
              </template>
              <div>
                <button class="rp-btn rp-btn-sm" @click="addMeasure()">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  Add Measure
                </button>
              </div>
            </div>
          </div>

          {{-- Filters --}}
          <div class="rp-bld-section">
            <div class="rp-bld-label">Filters</div>
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <template x-for="(f, idx) in builder.filters" :key="idx">
                <div class="rp-filter-row">
                  <select class="rp-select" x-model="f.field">
                    <template x-for="ff in filterFields" :key="ff">
                      <option :value="ff" x-text="ff"></option>
                    </template>
                  </select>
                  <select class="rp-select" x-model="f.operator">
                    <template x-for="op in filterOperators" :key="op.value">
                      <option :value="op.value" x-text="op.label"></option>
                    </template>
                  </select>
                  <input type="text" class="rp-input rp-input-sm" placeholder="Value" x-model="f.value" />
                  <button class="rp-chip-remove" @click="builder.filters.splice(idx, 1)">&times;</button>
                </div>
              </template>
              <div>
                <button class="rp-btn rp-btn-sm" @click="addFilter()">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  Add Filter
                </button>
              </div>
            </div>
          </div>

          {{-- Comparison + Run --}}
          <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 16px;">
            <div class="rp-bld-section">
              <div class="rp-bld-label">Comparison</div>
              <div class="rp-toggle-wrap">
                <div class="rp-toggle" :class="{ 'rp-on': builder.comparisonEnabled }" @click="builder.comparisonEnabled = !builder.comparisonEnabled">
                  <div class="rp-toggle-knob"></div>
                </div>
                <span class="rp-toggle-label" x-text="builder.comparisonEnabled ? 'Enabled' : 'Disabled'"></span>
                <template x-if="builder.comparisonEnabled">
                  <select class="rp-select" x-model="builder.comparisonType">
                    <option value="previous_period">Previous Period</option>
                    <option value="same_period_last_year">Same Period Last Year</option>
                  </select>
                </template>
              </div>
            </div>

            <button class="rp-btn rp-btn-primary" @click="runReport()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><polygon points="5 3 19 12 5 21 5 3"/></svg>
              Run Report
            </button>
          </div>
        </div>
      </div>

      {{-- ═══════════════════ RESULTS PANEL ═══════════════════ --}}
      <template x-if="hasResults">
        <div class="rp-results">
          <div class="rp-results-header">
            <div class="rp-results-header-left">
              <span class="rp-results-title">Results</span>
              <span class="rp-results-count" x-text="resultRows.length + ' rows'"></span>
            </div>
            <div class="rp-results-header-right">
              {{-- Visualization type selector --}}
              <div class="rp-viz-btns">
                {{-- Table --}}
                <button class="rp-viz-btn" :class="{ 'rp-active': vizType === 'table' }" @click="vizType = 'table'" title="Table">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                </button>
                {{-- Bar --}}
                <button class="rp-viz-btn" :class="{ 'rp-active': vizType === 'bar' }" @click="vizType = 'bar'" title="Bar Chart">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="12" width="4" height="9"/><rect x="10" y="7" width="4" height="14"/><rect x="17" y="3" width="4" height="18"/></svg>
                </button>
                {{-- Line --}}
                <button class="rp-viz-btn" :class="{ 'rp-active': vizType === 'line' }" @click="vizType = 'line'" title="Line Chart">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 8 13 13 8 8 2 14"/></svg>
                </button>
                {{-- Pie --}}
                <button class="rp-viz-btn" :class="{ 'rp-active': vizType === 'pie' }" @click="vizType = 'pie'" title="Pie Chart">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                </button>
              </div>
            </div>
          </div>

          {{-- TABLE VIEW --}}
          <template x-if="vizType === 'table'">
            <div class="rp-table-wrap">
              <table class="rp-table">
                <thead>
                  <tr>
                    <template x-for="col in tableColumns" :key="col.key">
                      <th :class="{ 'rp-col-measure': col.type !== 'dim' }">
                        <span x-text="col.label"></span>
                        <span class="rp-col-badge"
                              :class="{
                                'rp-col-badge-dim': col.badge === 'DIM',
                                'rp-col-badge-sum': col.badge === 'SUM',
                                'rp-col-badge-count': col.badge === 'COUNT',
                                'rp-col-badge-avg': col.badge === 'AVG'
                              }"
                              x-text="col.badge"></span>
                      </th>
                    </template>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="(row, idx) in tableData" :key="idx">
                    <tr :class="{
                          'rp-row-subtotal': row._type === 'subtotal',
                          'rp-row-grandtotal': row._type === 'grandtotal'
                        }">
                      <template x-for="col in tableColumns" :key="col.key">
                        <td :class="{ 'rp-col-measure': col.type !== 'dim' }">
                          <template x-if="col.type === 'dim'">
                            <span x-text="row[col.key] ?? ''"></span>
                          </template>
                          <template x-if="col.type === 'measure'">
                            <span x-text="row[col.key] ?? ''"></span>
                          </template>
                          <template x-if="col.type === 'prev'">
                            <span class="rp-prev-val" x-text="row[col.key] ?? ''"></span>
                          </template>
                          <template x-if="col.type === 'change'">
                            <span class="rp-change" :class="row[col.key + '_dir'] === 'up' ? 'rp-change-pos' : 'rp-change-neg'">
                              <template x-if="row[col.key + '_dir'] === 'up'">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 14l5-5 5 5z"/></svg>
                              </template>
                              <template x-if="row[col.key + '_dir'] === 'down'">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
                              </template>
                              <span x-text="row[col.key] ?? ''"></span>
                            </span>
                          </template>
                        </td>
                      </template>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </template>

          {{-- BAR CHART VIEW --}}
          <template x-if="vizType === 'bar'">
            <div class="rp-chart-wrap">
              <div class="rp-chart-legend">
                <template x-for="(month, mIdx) in chartMonths" :key="mIdx">
                  <div class="rp-legend-item">
                    <div class="rp-legend-swatch" :style="'background:' + barColors[mIdx]"></div>
                    <span x-text="month"></span>
                  </div>
                </template>
              </div>

              <template x-for="group in chartGroups" :key="group.store">
                <div class="rp-chart-group">
                  <div class="rp-chart-group-label" x-text="group.store"></div>
                  <template x-for="(bar, bIdx) in group.bars" :key="bIdx">
                    <div class="rp-bar-row">
                      <div class="rp-bar-label" x-text="bar.month"></div>
                      <div class="rp-bar-track">
                        <div class="rp-bar-fill"
                             :class="'rp-bar-fill-' + (bIdx + 1)"
                             :style="'width:' + bar.pct + '%'">
                          <template x-if="bar.pct > 15">
                            <span x-text="bar.formatted"></span>
                          </template>
                        </div>
                      </div>
                      <div class="rp-bar-value" x-text="bar.formatted"></div>
                    </div>
                  </template>
                </div>
              </template>
            </div>
          </template>

          {{-- LINE CHART VIEW --}}
          <template x-if="vizType === 'line'">
            <div class="rp-line-placeholder">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 12px; display: block; color: var(--rp-text-muted);"><polyline points="22 12 18 8 13 13 8 8 2 14"/></svg>
              Line chart visualisation would render here using a charting library (e.g. Chart.js, ApexCharts).
              <br>The data is available — this prototype focuses on the table and bar chart views.
            </div>
          </template>

          {{-- PIE CHART VIEW --}}
          <template x-if="vizType === 'pie'">
            <div class="rp-pie-wrap">
              <div class="rp-pie" :style="pieGradient"></div>
              <div class="rp-pie-legend">
                <template x-for="(slice, sIdx) in pieSlices" :key="sIdx">
                  <div class="rp-pie-legend-item">
                    <div class="rp-pie-legend-swatch" :style="'background:' + slice.color"></div>
                    <span x-text="slice.label"></span>
                    <span class="rp-pie-legend-pct" x-text="slice.formatted + ' (' + slice.pct + '%)'"></span>
                  </div>
                </template>
              </div>
            </div>
          </template>

        </div>
      </template>
    </div>
  </div>

  {{-- ═══════════════════ TOAST ═══════════════════ --}}
  <div class="rp-toast" x-show="toast.show" x-transition @click="toast.show = false">
    <svg class="rp-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <span x-text="toast.message"></span>
  </div>
</div>

@verbatim
<script>
function reportingFramework() {
  return {
    // ─── STATE ───
    search: '',
    selectedReport: null,
    builderOpen: true,
    vizType: 'table',
    hasResults: true,
    scheduleOpen: false,
    dimDropdownOpen: false,
    toast: { show: false, message: '' },

    // ─── SAVED REPORTS ───
    reports: [
      { id: 1, name: 'Revenue by Store', source: 'Opportunities', group: 'system', pinned: true },
      { id: 2, name: 'Monthly Bookings', source: 'Opportunities', group: 'system', pinned: false },
      { id: 3, name: 'Outstanding Invoices', source: 'Invoices', group: 'system', pinned: true },
      { id: 4, name: 'Top Clients', source: 'Members', group: 'system', pinned: false },
      { id: 5, name: 'Q1 Product Performance', source: 'Products', group: 'custom', pinned: false },
    ],

    // ─── BUILDER OPTIONS ───
    dataSources: ['Opportunities', 'Invoices', 'Members', 'Products'],
    dateFields: ['starts_at', 'created_at', 'ends_at', 'updated_at'],
    datePresets: ['This Month', 'Last Month', 'This Quarter', 'Last Quarter', 'This Year', 'Last Year', 'Trailing 30 Days', 'Trailing 90 Days', 'Custom'],
    buckets: ['Day', 'Week', 'Month', 'Quarter', 'Year'],
    aggregations: ['Sum', 'Count', 'Avg', 'Min', 'Max'],
    measureFields: ['charge_total', 'charge_out_total', 'discount_total', 'tax_total', 'id', 'quantity'],
    filterFields: ['state', 'store.name', 'member.name', 'charge_total', 'currency_code', 'created_at'],
    filterOperators: [
      { value: 'eq', label: 'equals' },
      { value: 'not_eq', label: 'not equals' },
      { value: 'gt', label: 'greater than' },
      { value: 'lt', label: 'less than' },
      { value: 'gteq', label: '>= ' },
      { value: 'lteq', label: '<=' },
      { value: 'cont', label: 'contains' },
      { value: 'not_cont', label: 'not contains' },
      { value: 'present', label: 'is present' },
      { value: 'blank', label: 'is blank' },
      { value: 'in', label: 'in list' },
    ],

    // Dimension fields with bucket capability
    allDimFields: [
      { field: 'store.name', showBucket: false },
      { field: 'starts_at', showBucket: true },
      { field: 'ends_at', showBucket: true },
      { field: 'created_at', showBucket: true },
      { field: 'state', showBucket: false },
      { field: 'member.name', showBucket: false },
      { field: 'currency_code', showBucket: false },
      { field: 'category.name', showBucket: false },
    ],

    // ─── BUILDER STATE ───
    builder: {
      dataSource: 'Opportunities',
      dateField: 'starts_at',
      datePreset: 'This Quarter',
      dateFrom: '',
      dateTo: '',
      dimensions: [
        { field: 'store.name', showBucket: false, bucket: '' },
        { field: 'starts_at', showBucket: true, bucket: 'Month' },
      ],
      measures: [
        { field: 'charge_total', agg: 'Sum', label: 'Revenue' },
        { field: 'id', agg: 'Count', label: 'Bookings' },
      ],
      filters: [
        { field: 'state', operator: 'eq', value: 'confirmed' },
      ],
      comparisonEnabled: true,
      comparisonType: 'previous_period',
    },

    // ─── MOCK RESULT DATA ───
    resultRows: [],
    subtotals: {},
    grandTotal: {},

    // ─── BAR CHART DATA ───
    barColors: ['var(--rp-bar-1)', 'var(--rp-bar-2)', 'var(--rp-bar-3)'],
    chartMonths: ['Jan 2026', 'Feb 2026', 'Mar 2026'],

    // ─── PIE COLORS ───
    pieColors: ['#2563eb', '#059669', '#d97706'],

    // ─── INIT ───
    init() {
      this.selectedReport = this.reports[0];
      this.loadMockData();
    },

    // ─── METHODS ───
    filteredReports(group) {
      const q = this.search.toLowerCase().trim();
      return this.reports.filter(r => {
        if (r.group !== group) return false;
        if (!q) return true;
        return r.name.toLowerCase().includes(q) || r.source.toLowerCase().includes(q);
      });
    },

    selectReport(report) {
      this.selectedReport = report;
      this.hasResults = true;
      this.loadMockData();
    },

    availableDimFields() {
      const used = this.builder.dimensions.map(d => d.field);
      return this.allDimFields.filter(f => !used.includes(f.field));
    },

    addDimension(fieldDef) {
      this.builder.dimensions.push({
        field: fieldDef.field,
        showBucket: fieldDef.showBucket,
        bucket: fieldDef.showBucket ? 'Month' : '',
      });
    },

    addMeasure() {
      this.builder.measures.push({ field: 'charge_total', agg: 'Sum', label: '' });
    },

    addFilter() {
      this.builder.filters.push({ field: 'state', operator: 'eq', value: '' });
    },

    onDataSourceChange() {
      // Mock: just keep current data
    },

    onDatePresetChange() {
      if (this.builder.datePreset === 'Custom') {
        this.builder.dateFrom = '2026-01-01';
        this.builder.dateTo = '2026-03-31';
      }
    },

    runReport() {
      this.hasResults = true;
      this.loadMockData();
      this.builderOpen = false;
      this.showToast('Report executed');
    },

    loadMockData() {
      this.resultRows = [
        { store: 'London', month: 'Jan 2026', revenue: 84500, bookings: 47, prevRevenue: 72300, prevBookings: 41 },
        { store: 'London', month: 'Feb 2026', revenue: 91200, bookings: 52, prevRevenue: 78400, prevBookings: 44 },
        { store: 'London', month: 'Mar 2026', revenue: 78600, bookings: 43, prevRevenue: 81200, prevBookings: 45 },
        { store: 'Manchester', month: 'Jan 2026', revenue: 58300, bookings: 34, prevRevenue: 52100, prevBookings: 30 },
        { store: 'Manchester', month: 'Feb 2026', revenue: 62700, bookings: 37, prevRevenue: 55800, prevBookings: 33 },
        { store: 'Manchester', month: 'Mar 2026', revenue: 55100, bookings: 31, prevRevenue: 57200, prevBookings: 34 },
        { store: 'Edinburgh', month: 'Jan 2026', revenue: 41200, bookings: 24, prevRevenue: 38600, prevBookings: 22 },
        { store: 'Edinburgh', month: 'Feb 2026', revenue: 45800, bookings: 27, prevRevenue: 40100, prevBookings: 24 },
        { store: 'Edinburgh', month: 'Mar 2026', revenue: 38400, bookings: 22, prevRevenue: 39500, prevBookings: 23 },
      ];

      this.subtotals = {
        'London':     { revenue: 254300, bookings: 142, prevRevenue: 231900, prevBookings: 130 },
        'Manchester': { revenue: 176100, bookings: 102, prevRevenue: 165100, prevBookings: 97 },
        'Edinburgh':  { revenue: 125400, bookings: 73,  prevRevenue: 118200, prevBookings: 69 },
      };

      this.grandTotal = { revenue: 555800, bookings: 317, prevRevenue: 515200, prevBookings: 296 };
    },

    // ─── TABLE COLUMNS ───
    get tableColumns() {
      const cols = [
        { key: 'store', label: 'Store', type: 'dim', badge: 'DIM' },
        { key: 'month', label: 'Month', type: 'dim', badge: 'DIM' },
        { key: 'revenue', label: 'Revenue', type: 'measure', badge: 'SUM' },
        { key: 'bookings', label: 'Bookings', type: 'measure', badge: 'COUNT' },
      ];

      if (this.builder.comparisonEnabled) {
        cols.push(
          { key: 'prevRevenue', label: 'Prev Revenue', type: 'prev', badge: '' },
          { key: 'prevBookings', label: 'Prev Bookings', type: 'prev', badge: '' },
          { key: 'revChange', label: 'Rev %', type: 'change', badge: '' },
          { key: 'bookChange', label: 'Book %', type: 'change', badge: '' },
        );
      }

      return cols;
    },

    // ─── TABLE DATA (with subtotals + grand total) ───
    get tableData() {
      const rows = [];
      const stores = ['London', 'Manchester', 'Edinburgh'];
      const comp = this.builder.comparisonEnabled;

      stores.forEach(store => {
        const storeRows = this.resultRows.filter(r => r.store === store);
        storeRows.forEach(r => {
          const row = {
            _type: 'data',
            store: r.store,
            month: r.month,
            revenue: this.fmtCurrency(r.revenue),
            bookings: r.bookings.toLocaleString(),
          };

          if (comp) {
            row.prevRevenue = this.fmtCurrency(r.prevRevenue);
            row.prevBookings = r.prevBookings.toLocaleString();
            const revPct = this.calcChange(r.revenue, r.prevRevenue);
            const bookPct = this.calcChange(r.bookings, r.prevBookings);
            row.revChange = revPct.label;
            row.revChange_dir = revPct.dir;
            row.bookChange = bookPct.label;
            row.bookChange_dir = bookPct.dir;
          }

          rows.push(row);
        });

        // Subtotal
        const sub = this.subtotals[store];
        if (sub) {
          const subRow = {
            _type: 'subtotal',
            store: store,
            month: '\u2014',
            revenue: this.fmtCurrency(sub.revenue),
            bookings: sub.bookings.toLocaleString(),
          };
          if (comp) {
            subRow.prevRevenue = this.fmtCurrency(sub.prevRevenue);
            subRow.prevBookings = sub.prevBookings.toLocaleString();
            const revPct = this.calcChange(sub.revenue, sub.prevRevenue);
            const bookPct = this.calcChange(sub.bookings, sub.prevBookings);
            subRow.revChange = revPct.label;
            subRow.revChange_dir = revPct.dir;
            subRow.bookChange = bookPct.label;
            subRow.bookChange_dir = bookPct.dir;
          }
          rows.push(subRow);
        }
      });

      // Grand total
      const gt = this.grandTotal;
      const gtRow = {
        _type: 'grandtotal',
        store: 'Grand Total',
        month: '',
        revenue: this.fmtCurrency(gt.revenue),
        bookings: gt.bookings.toLocaleString(),
      };
      if (comp) {
        gtRow.prevRevenue = this.fmtCurrency(gt.prevRevenue);
        gtRow.prevBookings = gt.prevBookings.toLocaleString();
        const revPct = this.calcChange(gt.revenue, gt.prevRevenue);
        const bookPct = this.calcChange(gt.bookings, gt.prevBookings);
        gtRow.revChange = revPct.label;
        gtRow.revChange_dir = revPct.dir;
        gtRow.bookChange = bookPct.label;
        gtRow.bookChange_dir = bookPct.dir;
      }
      rows.push(gtRow);

      return rows;
    },

    // ─── BAR CHART DATA ───
    get chartGroups() {
      const stores = ['London', 'Manchester', 'Edinburgh'];
      const maxVal = Math.max(...this.resultRows.map(r => r.revenue));

      return stores.map(store => {
        const storeRows = this.resultRows.filter(r => r.store === store);
        return {
          store,
          bars: storeRows.map(r => ({
            month: r.month,
            value: r.revenue,
            pct: maxVal > 0 ? Math.round((r.revenue / maxVal) * 100) : 0,
            formatted: this.fmtCurrency(r.revenue),
          })),
        };
      });
    },

    // ─── PIE CHART DATA ───
    get pieSlices() {
      const stores = ['London', 'Manchester', 'Edinburgh'];
      const totals = stores.map((store, i) => ({
        label: store,
        value: this.subtotals[store]?.revenue ?? 0,
        color: this.pieColors[i],
      }));

      const sum = totals.reduce((a, b) => a + b.value, 0);
      return totals.map(t => ({
        ...t,
        pct: sum > 0 ? ((t.value / sum) * 100).toFixed(1) : '0.0',
        formatted: this.fmtCurrency(t.value),
      }));
    },

    get pieGradient() {
      const slices = this.pieSlices;
      let cumulative = 0;
      const stops = [];

      slices.forEach(slice => {
        const start = cumulative;
        cumulative += parseFloat(slice.pct);
        stops.push(`${slice.color} ${start}% ${cumulative}%`);
      });

      return `background: conic-gradient(${stops.join(', ')});`;
    },

    // ─── FORMATTERS ───
    fmtCurrency(val) {
      return '\u00A3' + val.toLocaleString('en-GB');
    },

    calcChange(current, previous) {
      if (!previous || previous === 0) return { label: '\u2014', dir: 'up' };
      const pct = ((current - previous) / previous * 100).toFixed(1);
      const dir = pct >= 0 ? 'up' : 'down';
      const prefix = pct >= 0 ? '+' : '';
      return { label: prefix + pct + '%', dir };
    },

    showToast(message) {
      this.toast.message = message;
      this.toast.show = true;
      setTimeout(() => { this.toast.show = false; }, 3000);
    },
  };
}
</script>
@endverbatim
