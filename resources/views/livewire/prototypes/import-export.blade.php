<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Import / Export Engine')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  IMPORT/EXPORT TOKENS — maps to brand system in app.css           */
  /* ================================================================ */
  :root {
    --ie-bg: var(--content-bg);
    --ie-panel: var(--card-bg);
    --ie-surface: var(--base);
    --ie-border: var(--card-border);
    --ie-border-subtle: var(--grey-border);
    --ie-text: var(--text-primary);
    --ie-text-secondary: var(--text-secondary);
    --ie-text-muted: var(--text-muted);
    --ie-accent: var(--green);
    --ie-accent-dim: var(--green-muted);
    --ie-hover: rgba(0, 0, 0, 0.03);
    --ie-shadow: var(--shadow-card);
    --ie-success: var(--green);
    --ie-success-bg: rgba(5, 150, 105, 0.08);
    --ie-warning: var(--amber);
    --ie-warning-bg: rgba(217, 119, 6, 0.08);
    --ie-error: var(--red);
    --ie-error-bg: rgba(220, 38, 38, 0.08);
    --ie-info: var(--blue);
    --ie-info-bg: rgba(37, 99, 235, 0.06);
    --ie-step-done: var(--green);
    --ie-step-active: var(--green);
    --ie-step-pending: var(--grey-border);
    --ie-transform-bg: rgba(124, 58, 237, 0.08);
    --ie-transform-color: var(--violet);
    --ie-dropzone-border: var(--grey-border);
    --ie-dropzone-bg: transparent;
    --ie-table-header-bg: var(--table-header-bg);
    --ie-table-row-hover: var(--table-row-hover);
    --ie-table-border: var(--table-border);
    --ie-progress-track: rgba(0, 0, 0, 0.06);
    --ie-progress-fill: var(--green);
  }

  .dark {
    --ie-bg: var(--content-bg);
    --ie-panel: var(--card-bg);
    --ie-surface: var(--navy-mid);
    --ie-border: var(--card-border);
    --ie-border-subtle: #283040;
    --ie-text: var(--text-primary);
    --ie-text-secondary: var(--text-secondary);
    --ie-text-muted: var(--text-muted);
    --ie-accent: var(--green);
    --ie-accent-dim: rgba(5, 150, 105, 0.12);
    --ie-hover: rgba(255, 255, 255, 0.06);
    --ie-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --ie-success-bg: rgba(5, 150, 105, 0.15);
    --ie-warning-bg: rgba(217, 119, 6, 0.15);
    --ie-error-bg: rgba(220, 38, 38, 0.15);
    --ie-info-bg: rgba(37, 99, 235, 0.12);
    --ie-transform-bg: rgba(124, 58, 237, 0.15);
    --ie-dropzone-border: #374151;
    --ie-table-header-bg: var(--table-header-bg);
    --ie-table-row-hover: var(--table-row-hover);
    --ie-table-border: var(--table-border);
    --ie-progress-track: rgba(255, 255, 255, 0.08);
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */
  .ie-page {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 64px);
    background: var(--ie-bg);
    position: relative;
  }

  .ie-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 32px 64px;
    width: 100%;
  }

  /* ================================================================ */
  /*  HEADER                                                           */
  /* ================================================================ */
  .ie-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
  }

  .ie-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .ie-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    color: var(--ie-text);
    letter-spacing: -0.01em;
    line-height: 1;
  }

  .ie-subtitle {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ie-accent);
    margin-top: 2px;
  }

  /* ================================================================ */
  /*  TAB BAR                                                          */
  /* ================================================================ */
  .ie-tabs {
    display: flex;
    gap: 2px;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--ie-border);
    padding-bottom: 0;
  }

  .ie-tab {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    padding: 10px 20px;
    color: var(--ie-text-muted);
    cursor: pointer;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: all 0.15s;
    white-space: nowrap;
  }

  .ie-tab:hover { color: var(--ie-text-secondary); }

  .ie-tab.active {
    color: var(--ie-accent);
    border-bottom-color: var(--ie-accent);
  }

  /* ================================================================ */
  /*  STAGE INDICATOR                                                  */
  /* ================================================================ */
  .ie-stages {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    margin-bottom: 28px;
    padding: 16px 0;
  }

  .ie-stage {
    display: flex;
    align-items: center;
    gap: 0;
  }

  .ie-stage-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 700;
    border: 2px solid var(--ie-step-pending);
    color: var(--ie-text-muted);
    background: var(--ie-panel);
    transition: all 0.3s;
    position: relative;
    z-index: 2;
  }

  .ie-stage-circle.done {
    border-color: var(--ie-step-done);
    background: var(--ie-step-done);
    color: #ffffff;
  }

  .ie-stage-circle.active {
    border-color: var(--ie-step-active);
    color: var(--ie-step-active);
    box-shadow: 0 0 0 3px var(--ie-accent-dim);
  }

  .ie-stage-circle svg {
    width: 14px;
    height: 14px;
  }

  .ie-stage-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ie-text-muted);
    position: absolute;
    top: calc(100% + 6px);
    left: 50%;
    transform: translateX(-50%);
    white-space: nowrap;
    transition: color 0.3s;
  }

  .ie-stage-circle.active .ie-stage-label,
  .ie-stage-circle.done .ie-stage-label { color: var(--ie-text-secondary); }

  .ie-stage-line {
    width: 60px;
    height: 2px;
    background: var(--ie-step-pending);
    transition: background 0.3s;
    z-index: 1;
  }

  .ie-stage-line.done { background: var(--ie-step-done); }

  /* ================================================================ */
  /*  CARDS / PANELS                                                   */
  /* ================================================================ */
  .ie-card {
    background: var(--ie-panel);
    border: 1px solid var(--ie-border);
    padding: 20px;
    margin-bottom: 16px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .ie-card:hover {
    box-shadow: var(--ie-shadow);
  }

  .ie-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
  }

  .ie-card-title {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 700;
    color: var(--ie-text);
  }

  .ie-section-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ie-text-muted);
    margin-bottom: 10px;
  }

  /* ================================================================ */
  /*  DROPZONE                                                         */
  /* ================================================================ */
  .ie-dropzone {
    border: 2px dashed var(--ie-dropzone-border);
    background: var(--ie-dropzone-bg);
    padding: 40px 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
  }

  .ie-dropzone:hover,
  .ie-dropzone.dragover {
    border-color: var(--ie-accent);
    background: var(--ie-accent-dim);
  }

  .ie-dropzone-icon {
    width: 40px;
    height: 40px;
    margin: 0 auto 12px;
    color: var(--ie-text-muted);
    opacity: 0.5;
  }

  .ie-dropzone-text {
    font-family: var(--font-display);
    font-size: 13px;
    color: var(--ie-text-secondary);
    margin-bottom: 4px;
  }

  .ie-dropzone-hint {
    font-family: var(--font-display);
    font-size: 11px;
    color: var(--ie-text-muted);
  }

  /* ================================================================ */
  /*  BUTTONS                                                          */
  /* ================================================================ */
  .ie-btn {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    padding: 8px 18px;
    border: 1px solid var(--ie-border);
    background: var(--ie-panel);
    color: var(--ie-text-secondary);
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
  }

  .ie-btn:hover { border-color: var(--ie-text-muted); color: var(--ie-text); }

  .ie-btn-primary {
    background: var(--ie-accent);
    color: #ffffff;
    border-color: var(--ie-accent);
  }

  .ie-btn-primary:hover { opacity: 0.9; color: #ffffff; }

  .ie-btn-primary:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }

  .ie-btn-danger {
    color: var(--ie-error);
    border-color: var(--ie-error);
  }

  .ie-btn-danger:hover { background: var(--ie-error-bg); }

  .ie-btn svg { width: 14px; height: 14px; }

  .ie-btn-group {
    display: flex;
    gap: 8px;
  }

  /* ================================================================ */
  /*  SELECT / INPUT                                                   */
  /* ================================================================ */
  .ie-select {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    padding: 7px 32px 7px 12px;
    background: var(--ie-panel);
    border: 1px solid var(--ie-border);
    color: var(--ie-text);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    transition: border-color 0.15s;
  }

  .ie-select:hover { border-color: var(--ie-accent); }
  .ie-select:focus { outline: none; border-color: var(--ie-accent); box-shadow: 0 0 0 2px var(--ie-accent-dim); }

  .ie-input {
    font-family: var(--font-mono);
    font-size: 12px;
    padding: 7px 12px;
    background: var(--ie-panel);
    border: 1px solid var(--ie-border);
    color: var(--ie-text);
    transition: border-color 0.15s, box-shadow 0.15s;
    width: 100%;
  }

  .ie-input::placeholder { color: var(--ie-text-muted); }
  .ie-input:focus { outline: none; border-color: var(--ie-accent); box-shadow: 0 0 0 2px var(--ie-accent-dim); }

  /* ================================================================ */
  /*  STAT CARDS                                                       */
  /* ================================================================ */
  .ie-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
  }

  .ie-stat-card {
    background: var(--ie-panel);
    border: 1px solid var(--ie-border);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .ie-stat-card:hover {
    border-color: var(--ie-border-subtle);
    box-shadow: var(--ie-shadow);
  }

  .ie-stat-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .ie-stat-icon svg { width: 18px; height: 18px; }

  .ie-stat-icon-success { background: var(--ie-success-bg); color: var(--ie-success); }
  .ie-stat-icon-warning { background: var(--ie-warning-bg); color: var(--ie-warning); }
  .ie-stat-icon-error { background: var(--ie-error-bg); color: var(--ie-error); }
  .ie-stat-icon-info { background: var(--ie-info-bg); color: var(--ie-info); }

  .ie-stat-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ie-text-muted);
    margin-bottom: 2px;
  }

  .ie-stat-value {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 700;
    color: var(--ie-text);
    line-height: 1;
  }

  /* ================================================================ */
  /*  PROGRESS BAR                                                     */
  /* ================================================================ */
  .ie-progress {
    height: 6px;
    background: var(--ie-progress-track);
    overflow: hidden;
    width: 100%;
  }

  .ie-progress-bar {
    height: 100%;
    background: var(--ie-progress-fill);
    transition: width 0.4s ease;
    position: relative;
  }

  .ie-progress-bar.animated::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    animation: ieShimmer 1.5s infinite;
  }

  .ie-progress-large {
    height: 10px;
    border-radius: 5px;
  }

  .ie-progress-large .ie-progress-bar {
    border-radius: 5px;
  }

  .ie-progress-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px;
  }

  .ie-progress-text {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    color: var(--ie-text-secondary);
  }

  .ie-progress-pct {
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 700;
    color: var(--ie-accent);
  }

  /* ================================================================ */
  /*  TABLE                                                            */
  /* ================================================================ */
  .ie-table-wrap {
    background: var(--ie-panel);
    border: 1px solid var(--ie-border);
    overflow: hidden;
  }

  .ie-table {
    width: 100%;
    border-collapse: collapse;
  }

  .ie-table th {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ie-text-muted);
    padding: 8px 16px;
    text-align: left;
    background: var(--ie-table-header-bg);
    border-bottom: 1px solid var(--ie-table-border);
  }

  .ie-table td {
    font-family: var(--font-display);
    font-size: 12px;
    color: var(--ie-text-secondary);
    padding: 10px 16px;
    border-bottom: 1px solid var(--ie-table-border);
    vertical-align: middle;
  }

  .ie-table tr:last-child td { border-bottom: none; }
  .ie-table tr:hover td { background: var(--ie-table-row-hover); }

  .ie-table-mono {
    font-family: var(--font-mono);
    font-size: 11px;
  }

  /* ================================================================ */
  /*  BADGES                                                           */
  /* ================================================================ */
  .ie-badge {
    display: inline-flex;
    align-items: center;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 8px;
    white-space: nowrap;
  }

  .ie-badge-success { background: var(--ie-success-bg); color: var(--ie-success); }
  .ie-badge-warning { background: var(--ie-warning-bg); color: var(--ie-warning); }
  .ie-badge-error { background: var(--ie-error-bg); color: var(--ie-error); }
  .ie-badge-info { background: var(--ie-info-bg); color: var(--ie-info); }
  .ie-badge-neutral {
    background: rgba(0, 0, 0, 0.04);
    color: var(--ie-text-muted);
  }

  .dark .ie-badge-neutral {
    background: rgba(255, 255, 255, 0.08);
  }

  /* ================================================================ */
  /*  TRANSFORM PILLS                                                  */
  /* ================================================================ */
  .ie-transforms {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
  }

  .ie-transform-pill {
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 500;
    padding: 2px 8px;
    background: var(--ie-transform-bg);
    color: var(--ie-transform-color);
    white-space: nowrap;
    cursor: default;
  }

  .ie-transform-arrow {
    color: var(--ie-text-muted);
    font-size: 12px;
    margin: 0 2px;
  }

  /* ================================================================ */
  /*  MAPPING TABLE                                                    */
  /* ================================================================ */
  .ie-mapping-row {
    display: grid;
    grid-template-columns: 180px 24px 200px 1fr;
    gap: 12px;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--ie-table-border);
    transition: background 0.1s;
  }

  .ie-mapping-row:hover { background: var(--ie-table-row-hover); }
  .ie-mapping-row:last-child { border-bottom: none; }

  .ie-mapping-source {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--ie-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .ie-mapping-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ie-text-muted);
  }

  .ie-mapping-arrow svg { width: 14px; height: 14px; }

  .ie-mapping-unmapped {
    color: var(--ie-warning);
    font-style: italic;
  }

  /* ================================================================ */
  /*  VALIDATION ROWS                                                  */
  /* ================================================================ */
  .ie-validation-row {
    display: grid;
    grid-template-columns: 60px 80px 1fr;
    gap: 12px;
    align-items: center;
    padding: 8px 16px;
    border-bottom: 1px solid var(--ie-table-border);
    cursor: pointer;
    transition: background 0.1s;
  }

  .ie-validation-row:hover { background: var(--ie-table-row-hover); }

  .ie-validation-row-num {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ie-text-muted);
  }

  .ie-validation-msg {
    font-family: var(--font-display);
    font-size: 12px;
    color: var(--ie-text-secondary);
  }

  .ie-validation-detail {
    background: var(--ie-surface);
    padding: 12px 16px;
    border-bottom: 1px solid var(--ie-table-border);
    animation: ieSlideDown 0.2s ease both;
  }

  .ie-validation-compare {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  .ie-validation-col-label {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ie-text-muted);
    margin-bottom: 8px;
  }

  .ie-validation-data {
    font-family: var(--font-mono);
    font-size: 11px;
    line-height: 1.6;
    color: var(--ie-text-secondary);
  }

  .ie-validation-data-row {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
  }

  .ie-validation-data-key {
    color: var(--ie-text-muted);
  }

  .ie-validation-data-val {
    color: var(--ie-text);
  }

  /* ================================================================ */
  /*  FILE INFO                                                        */
  /* ================================================================ */
  .ie-file-info {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 16px;
    background: var(--ie-surface);
    border: 1px solid var(--ie-border);
  }

  .ie-file-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--ie-info-bg);
    color: var(--ie-info);
    flex-shrink: 0;
  }

  .ie-file-icon svg { width: 18px; height: 18px; }

  .ie-file-name {
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 600;
    color: var(--ie-text);
  }

  .ie-file-meta {
    font-family: var(--font-display);
    font-size: 11px;
    color: var(--ie-text-muted);
    display: flex;
    gap: 12px;
    margin-top: 2px;
  }

  .ie-file-columns {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 8px;
  }

  .ie-file-col-tag {
    font-family: var(--font-mono);
    font-size: 10px;
    padding: 2px 8px;
    background: var(--ie-info-bg);
    color: var(--ie-info);
  }

  /* ================================================================ */
  /*  FORMAT CARDS                                                     */
  /* ================================================================ */
  .ie-format-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
  }

  .ie-format-card {
    background: var(--ie-panel);
    border: 2px solid var(--ie-border);
    padding: 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.15s;
  }

  .ie-format-card:hover { border-color: var(--ie-text-muted); }

  .ie-format-card.selected {
    border-color: var(--ie-accent);
    background: var(--ie-accent-dim);
  }

  .ie-format-card-icon {
    font-family: var(--font-mono);
    font-size: 14px;
    font-weight: 700;
    color: var(--ie-text);
    margin-bottom: 4px;
  }

  .ie-format-card-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ie-text-muted);
  }

  /* ================================================================ */
  /*  FILTER ROWS                                                      */
  /* ================================================================ */
  .ie-filter-row {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
  }

  .ie-filter-row .ie-select { flex: 1; min-width: 0; }
  .ie-filter-row .ie-input { flex: 1; min-width: 0; }

  .ie-filter-remove {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ie-text-muted);
    cursor: pointer;
    border: none;
    background: none;
    transition: color 0.15s;
    flex-shrink: 0;
  }

  .ie-filter-remove:hover { color: var(--ie-error); }
  .ie-filter-remove svg { width: 14px; height: 14px; }

  /* ================================================================ */
  /*  CHECKBOX                                                         */
  /* ================================================================ */
  .ie-checkbox-group {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
  }

  .ie-checkbox-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: var(--font-display);
    font-size: 12px;
    color: var(--ie-text-secondary);
    cursor: pointer;
    padding: 4px 0;
  }

  .ie-checkbox {
    width: 14px;
    height: 14px;
    border: 1px solid var(--ie-border);
    background: var(--ie-panel);
    cursor: pointer;
    accent-color: var(--ie-accent);
  }

  /* ================================================================ */
  /*  MIGRATION PLAN CARDS                                             */
  /* ================================================================ */
  .ie-plan-card {
    background: var(--ie-panel);
    border: 1px solid var(--ie-border);
    padding: 20px;
    margin-bottom: 12px;
  }

  .ie-plan-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
  }

  .ie-plan-title {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 700;
    color: var(--ie-text);
  }

  .ie-plan-template {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ie-text-muted);
  }

  .ie-plan-steps {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .ie-plan-step {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--ie-surface);
    border: 1px solid var(--ie-border-subtle);
    transition: background 0.15s;
  }

  .ie-plan-step-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .ie-plan-step-dot.done { background: var(--ie-success); }
  .ie-plan-step-dot.active { background: var(--ie-info); animation: iePulse 1.5s infinite; }
  .ie-plan-step-dot.pending { background: var(--ie-step-pending); }

  .ie-plan-step-name {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--ie-text);
    flex: 1;
  }

  .ie-plan-step-status {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .ie-plan-step-status.done { color: var(--ie-success); }
  .ie-plan-step-status.active { color: var(--ie-info); }
  .ie-plan-step-status.pending { color: var(--ie-text-muted); }

  /* ================================================================ */
  /*  PROFILE TABLE                                                    */
  /* ================================================================ */
  .ie-toggle {
    width: 32px;
    height: 18px;
    background: var(--ie-step-pending);
    border-radius: 9px;
    position: relative;
    cursor: pointer;
    transition: background 0.2s;
  }

  .ie-toggle.on { background: var(--ie-accent); }

  .ie-toggle::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #ffffff;
    transition: transform 0.2s;
  }

  .ie-toggle.on::after { transform: translateX(14px); }

  /* ================================================================ */
  /*  CONFLICT STRATEGY                                                */
  /* ================================================================ */
  .ie-strategy-cards {
    display: flex;
    gap: 8px;
  }

  .ie-strategy-card {
    flex: 1;
    padding: 10px 14px;
    background: var(--ie-panel);
    border: 2px solid var(--ie-border);
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
  }

  .ie-strategy-card:hover { border-color: var(--ie-text-muted); }

  .ie-strategy-card.selected {
    border-color: var(--ie-accent);
    background: var(--ie-accent-dim);
  }

  .ie-strategy-name {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    color: var(--ie-text);
    margin-bottom: 2px;
  }

  .ie-strategy-desc {
    font-family: var(--font-display);
    font-size: 10px;
    color: var(--ie-text-muted);
  }

  /* ================================================================ */
  /*  EMPTY STATE                                                      */
  /* ================================================================ */
  .ie-empty {
    text-align: center;
    padding: 48px 24px;
    color: var(--ie-text-muted);
    font-family: var(--font-display);
    font-size: 13px;
  }

  .ie-empty-icon {
    width: 40px;
    height: 40px;
    margin: 0 auto 12px;
    color: var(--ie-text-muted);
    opacity: 0.4;
  }

  /* ================================================================ */
  /*  INLINE ACTIONS ROW                                               */
  /* ================================================================ */
  .ie-actions-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    gap: 12px;
  }

  .ie-actions-right {
    display: flex;
    gap: 8px;
    margin-left: auto;
  }

  /* ================================================================ */
  /*  LIVE STATS                                                       */
  /* ================================================================ */
  .ie-live-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin: 16px 0;
  }

  .ie-live-stat {
    text-align: center;
    padding: 12px;
    background: var(--ie-surface);
    border: 1px solid var(--ie-border-subtle);
  }

  .ie-live-stat-value {
    font-family: var(--font-display);
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 4px;
  }

  .ie-live-stat-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ie-text-muted);
  }

  .ie-live-stat-value.success { color: var(--ie-success); }
  .ie-live-stat-value.info { color: var(--ie-info); }
  .ie-live-stat-value.warning { color: var(--ie-warning); }
  .ie-live-stat-value.error { color: var(--ie-error); }

  /* ================================================================ */
  /*  BATCH INDICATOR                                                  */
  /* ================================================================ */
  .ie-batch-info {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: var(--ie-surface);
    border: 1px solid var(--ie-border-subtle);
    margin-top: 12px;
  }

  .ie-batch-label {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    color: var(--ie-text-muted);
  }

  .ie-batch-value {
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 600;
    color: var(--ie-text);
  }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes ieShimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
  }

  @keyframes ieSlideDown {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes ieFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes iePulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
  }

  @keyframes ieSlideIn {
    from { opacity: 0; transform: translateX(-12px); }
    to { opacity: 1; transform: translateX(0); }
  }

  .ie-stat-card { animation: ieFadeIn 0.3s ease both; }
  .ie-stat-card:nth-child(1) { animation-delay: 0s; }
  .ie-stat-card:nth-child(2) { animation-delay: 0.05s; }
  .ie-stat-card:nth-child(3) { animation-delay: 0.1s; }
  .ie-stat-card:nth-child(4) { animation-delay: 0.15s; }

  .ie-mapping-row { animation: ieFadeIn 0.2s ease both; }

  .ie-plan-step { animation: ieSlideIn 0.25s ease both; }
  .ie-plan-step:nth-child(1) { animation-delay: 0s; }
  .ie-plan-step:nth-child(2) { animation-delay: 0.04s; }
  .ie-plan-step:nth-child(3) { animation-delay: 0.08s; }
  .ie-plan-step:nth-child(4) { animation-delay: 0.12s; }
  .ie-plan-step:nth-child(5) { animation-delay: 0.16s; }
  .ie-plan-step:nth-child(6) { animation-delay: 0.2s; }

  .ie-validation-row { animation: ieFadeIn 0.2s ease both; }
  .ie-validation-row:nth-child(1) { animation-delay: 0s; }
  .ie-validation-row:nth-child(2) { animation-delay: 0.03s; }
  .ie-validation-row:nth-child(3) { animation-delay: 0.06s; }
  .ie-validation-row:nth-child(4) { animation-delay: 0.09s; }
  .ie-validation-row:nth-child(5) { animation-delay: 0.12s; }

  .ie-stage { animation: ieFadeIn 0.3s ease both; }
  .ie-stage:nth-child(1) { animation-delay: 0s; }
  .ie-stage:nth-child(2) { animation-delay: 0.06s; }
  .ie-stage:nth-child(3) { animation-delay: 0.12s; }
  .ie-stage:nth-child(4) { animation-delay: 0.18s; }
  .ie-stage:nth-child(5) { animation-delay: 0.24s; }
  .ie-stage:nth-child(6) { animation-delay: 0.3s; }
  .ie-stage:nth-child(7) { animation-delay: 0.36s; }
  .ie-stage:nth-child(8) { animation-delay: 0.42s; }
  .ie-stage:nth-child(9) { animation-delay: 0.48s; }

  .ie-tab-content {
    animation: ieFadeIn 0.25s ease both;
  }

  /* ================================================================ */
  /*  SCROLLBAR                                                        */
  /* ================================================================ */
  .ie-page ::-webkit-scrollbar { width: 6px; }
  .ie-page ::-webkit-scrollbar-track { background: transparent; }
  .ie-page ::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.1); border-radius: 3px; }
  .ie-page ::-webkit-scrollbar-thumb:hover { background: rgba(0, 0, 0, 0.2); }
  .dark .ie-page ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); }
  .dark .ie-page ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }
</style>

<div class="ie-page" x-data="importExportEngine()" x-cloak>

  <div class="ie-container">

    {{-- ============================================================ --}}
    {{--  HEADER                                                       --}}
    {{-- ============================================================ --}}
    <div class="ie-header">
      <div class="ie-header-left">
        <div>
          <div class="ie-title">Import / Export</div>
          <div class="ie-subtitle">Data Migration Engine</div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  TAB BAR                                                      --}}
    {{-- ============================================================ --}}
    <div class="ie-tabs">
      <template x-for="tab in tabs" :key="tab.key">
        <button class="ie-tab"
                :class="{ active: activeTab === tab.key }"
                @click="activeTab = tab.key"
                x-text="tab.label"></button>
      </template>
    </div>

    {{-- ============================================================ --}}
    {{--  IMPORT TAB                                                   --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'import'" class="ie-tab-content">

      {{-- Stage indicator --}}
      <div class="ie-stages">
        <template x-for="(stage, idx) in importStages" :key="stage.key">
          <div class="ie-stage">
            <div class="ie-stage-circle"
                 :class="{
                   done: importStep > idx + 1,
                   active: importStep === idx + 1
                 }"
                 @click="goToStep(idx + 1)">
              <template x-if="importStep > idx + 1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </template>
              <template x-if="importStep <= idx + 1">
                <span x-text="idx + 1"></span>
              </template>
              <span class="ie-stage-label" x-text="stage.label"></span>
            </div>
            <template x-if="idx < importStages.length - 1">
              <div class="ie-stage-line" :class="{ done: importStep > idx + 1 }"></div>
            </template>
          </div>
        </template>
      </div>

      {{-- ======================================================== --}}
      {{--  STAGE 1: Upload & Parse                                  --}}
      {{-- ======================================================== --}}
      <div x-show="importStep === 1"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0">

        <div class="ie-card">
          <div class="ie-card-header">
            <div class="ie-card-title">Upload File</div>
            <select class="ie-select" x-model="targetModel">
              <template x-for="m in modelOptions" :key="m.key">
                <option :value="m.key" x-text="m.label"></option>
              </template>
            </select>
          </div>

          {{-- Dropzone --}}
          <div class="ie-dropzone"
               :class="{ dragover: isDragging }"
               @dragover.prevent="isDragging = true"
               @dragleave.prevent="isDragging = false"
               @drop.prevent="isDragging = false; simulateUpload()"
               @click="simulateUpload()">
            <div class="ie-dropzone-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
              </svg>
            </div>
            <div class="ie-dropzone-text">Drop file here or click to browse</div>
            <div class="ie-dropzone-hint">Supports CSV, XLSX, JSON</div>
          </div>
        </div>

        {{-- File info (shown after upload) --}}
        <div x-show="uploadedFile" class="ie-card"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0">
          <div class="ie-section-label">Uploaded File</div>
          <div class="ie-file-info">
            <div class="ie-file-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
            </div>
            <div style="flex: 1;">
              <div class="ie-file-name" x-text="uploadedFile?.name"></div>
              <div class="ie-file-meta">
                <span x-text="uploadedFile?.size"></span>
                <span>&middot;</span>
                <span x-text="uploadedFile?.rows + ' rows'"></span>
                <span>&middot;</span>
                <span class="ie-badge ie-badge-info" x-text="uploadedFile?.format"></span>
              </div>
              <div class="ie-file-columns">
                <template x-for="col in uploadedFile?.columns || []" :key="col">
                  <span class="ie-file-col-tag" x-text="col"></span>
                </template>
              </div>
            </div>
          </div>

          <div class="ie-actions-row">
            <div></div>
            <button class="ie-btn ie-btn-primary" @click="importStep = 2">
              Continue to Mapping
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
              </svg>
            </button>
          </div>
        </div>
      </div>

      {{-- ======================================================== --}}
      {{--  STAGE 2: Field Mapping                                   --}}
      {{-- ======================================================== --}}
      <div x-show="importStep === 2"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0">

        <div class="ie-card">
          <div class="ie-card-header">
            <div class="ie-card-title">Field Mapping</div>
            <div class="ie-btn-group">
              <button class="ie-btn" @click="autoMap()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                </svg>
                Auto-Map
              </button>
            </div>
          </div>

          {{-- Mapping table --}}
          <div class="ie-table-wrap">
            <table class="ie-table">
              <thead>
                <tr>
                  <th>Source Column</th>
                  <th style="width: 24px;"></th>
                  <th>Target Field</th>
                  <th>Transforms</th>
                </tr>
              </thead>
            </table>

            <template x-for="(mapping, idx) in fieldMappings" :key="idx">
              <div class="ie-mapping-row">
                <div class="ie-mapping-source" x-text="mapping.source"></div>
                <div class="ie-mapping-arrow">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                  </svg>
                </div>
                <div>
                  <template x-if="mapping.target">
                    <select class="ie-select" style="width: 100%;" x-model="mapping.target">
                      <option value="">-- unmapped --</option>
                      <template x-for="group in targetFieldGroups" :key="group.name">
                        <optgroup :label="group.name">
                          <template x-for="f in group.fields" :key="f.key">
                            <option :value="f.key" x-text="f.label"></option>
                          </template>
                        </optgroup>
                      </template>
                    </select>
                  </template>
                  <template x-if="!mapping.target">
                    <span class="ie-mapping-unmapped">Unmapped</span>
                  </template>
                </div>
                <div class="ie-transforms">
                  <template x-for="(t, tidx) in mapping.transforms" :key="tidx">
                    <span class="ie-transform-pill" x-text="t"></span>
                  </template>
                  <template x-if="mapping.transforms.length === 0 && mapping.target">
                    <span style="font-family: var(--font-display); font-size: 11px; color: var(--ie-text-muted);">No transforms</span>
                  </template>
                </div>
              </div>
            </template>
          </div>
        </div>

        {{-- Conflict & Duplicate settings --}}
        <div class="ie-card">
          <div class="ie-section-label">Conflict Strategy</div>
          <div class="ie-strategy-cards">
            <template x-for="s in conflictStrategies" :key="s.key">
              <div class="ie-strategy-card"
                   :class="{ selected: conflictStrategy === s.key }"
                   @click="conflictStrategy = s.key">
                <div class="ie-strategy-name" x-text="s.name"></div>
                <div class="ie-strategy-desc" x-text="s.desc"></div>
              </div>
            </template>
          </div>

          <div style="margin-top: 16px;">
            <div class="ie-section-label">Duplicate Detection Fields</div>
            <div class="ie-checkbox-group">
              <template x-for="f in duplicateFields" :key="f.key">
                <label class="ie-checkbox-label">
                  <input type="checkbox" class="ie-checkbox" :checked="f.selected" @change="f.selected = !f.selected">
                  <span x-text="f.label"></span>
                </label>
              </template>
            </div>
          </div>
        </div>

        <div class="ie-actions-row">
          <button class="ie-btn" @click="importStep = 1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="15 18 9 12 15 6"/>
            </svg>
            Back
          </button>
          <div class="ie-actions-right">
            <button class="ie-btn ie-btn-primary" @click="importStep = 3">
              Run Validation
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
              </svg>
            </button>
          </div>
        </div>
      </div>

      {{-- ======================================================== --}}
      {{--  STAGE 3: Validate (Dry Run)                              --}}
      {{-- ======================================================== --}}
      <div x-show="importStep === 3"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0">

        {{-- Progress --}}
        <div class="ie-card" x-show="validationRunning">
          <div class="ie-progress-label">
            <span class="ie-progress-text">Validating rows...</span>
            <span class="ie-progress-pct" x-text="validationProgress + '%'"></span>
          </div>
          <div class="ie-progress ie-progress-large">
            <div class="ie-progress-bar animated" :style="'width: ' + validationProgress + '%'"></div>
          </div>
        </div>

        {{-- Results --}}
        <div x-show="!validationRunning">
          <div class="ie-stats">
            <div class="ie-stat-card">
              <div class="ie-stat-icon ie-stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="3" width="18" height="18" rx="2"/>
                  <path d="M3 9h18"/>
                </svg>
              </div>
              <div>
                <div class="ie-stat-label">Total Rows</div>
                <div class="ie-stat-value" x-text="validationResults.total"></div>
              </div>
            </div>
            <div class="ie-stat-card">
              <div class="ie-stat-icon ie-stat-icon-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                  <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
              </div>
              <div>
                <div class="ie-stat-label">Valid</div>
                <div class="ie-stat-value" x-text="validationResults.valid"></div>
              </div>
            </div>
            <div class="ie-stat-card">
              <div class="ie-stat-icon ie-stat-icon-warning">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                  <line x1="12" y1="9" x2="12" y2="13"/>
                  <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
              </div>
              <div>
                <div class="ie-stat-label">Warnings</div>
                <div class="ie-stat-value" x-text="validationResults.warnings"></div>
              </div>
            </div>
            <div class="ie-stat-card">
              <div class="ie-stat-icon ie-stat-icon-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/>
                  <line x1="15" y1="9" x2="9" y2="15"/>
                  <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
              </div>
              <div>
                <div class="ie-stat-label">Errors</div>
                <div class="ie-stat-value" x-text="validationResults.errors"></div>
              </div>
            </div>
          </div>

          {{-- Row-level results --}}
          <div class="ie-card">
            <div class="ie-card-header">
              <div class="ie-card-title">Row Results</div>
              <div class="ie-btn-group">
                <button class="ie-btn" :class="{ 'ie-btn-primary': validationFilter === 'all' }" @click="validationFilter = 'all'" style="padding: 5px 12px; font-size: 11px;">All</button>
                <button class="ie-btn" :class="{ 'ie-btn-primary': validationFilter === 'warning' }" @click="validationFilter = 'warning'" style="padding: 5px 12px; font-size: 11px;">Warnings</button>
                <button class="ie-btn" :class="{ 'ie-btn-primary': validationFilter === 'error' }" @click="validationFilter = 'error'" style="padding: 5px 12px; font-size: 11px;">Errors</button>
              </div>
            </div>

            <div class="ie-table-wrap">
              <table class="ie-table">
                <thead>
                  <tr>
                    <th style="width: 60px;">Row</th>
                    <th style="width: 80px;">Status</th>
                    <th>Message</th>
                  </tr>
                </thead>
              </table>

              <template x-for="row in filteredValidationRows" :key="row.row">
                <div>
                  <div class="ie-validation-row" @click="toggleValidationDetail(row.row)">
                    <div class="ie-validation-row-num" x-text="'#' + row.row"></div>
                    <div>
                      <span class="ie-badge"
                            :class="{
                              'ie-badge-success': row.status === 'valid',
                              'ie-badge-warning': row.status === 'warning',
                              'ie-badge-error': row.status === 'error'
                            }"
                            x-text="row.status"></span>
                    </div>
                    <div class="ie-validation-msg" x-text="row.message"></div>
                  </div>

                  {{-- Expandable detail --}}
                  <div class="ie-validation-detail"
                       x-show="expandedValidationRow === row.row"
                       x-transition:enter="transition ease-out duration-150"
                       x-transition:enter-start="opacity-0"
                       x-transition:enter-end="opacity-100">
                    <div class="ie-validation-compare">
                      <div>
                        <div class="ie-validation-col-label">Raw Data</div>
                        <div class="ie-validation-data">
                          <template x-for="(val, key) in row.raw" :key="key">
                            <div class="ie-validation-data-row">
                              <span class="ie-validation-data-key" x-text="key"></span>
                              <span class="ie-validation-data-val" x-text="val"></span>
                            </div>
                          </template>
                        </div>
                      </div>
                      <div>
                        <div class="ie-validation-col-label">Mapped Data</div>
                        <div class="ie-validation-data">
                          <template x-for="(val, key) in row.mapped" :key="key">
                            <div class="ie-validation-data-row">
                              <span class="ie-validation-data-key" x-text="key"></span>
                              <span class="ie-validation-data-val" x-text="val"></span>
                            </div>
                          </template>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <div class="ie-actions-row">
            <button class="ie-btn" @click="importStep = 2">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
              </svg>
              Back to Mapping
            </button>
            <div class="ie-actions-right">
              <button class="ie-btn ie-btn-primary"
                      :disabled="validationResults.errors > 0 && !acknowledgeErrors"
                      @click="importStep = 4; startCommit()">
                Proceed to Import
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="9 18 15 12 9 6"/>
                </svg>
              </button>
              <label class="ie-checkbox-label" x-show="validationResults.errors > 0" style="margin-left: 8px;">
                <input type="checkbox" class="ie-checkbox" x-model="acknowledgeErrors">
                <span style="font-size: 11px; color: var(--ie-warning);">Proceed with errors</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      {{-- ======================================================== --}}
      {{--  STAGE 4: Commit (Progress)                               --}}
      {{-- ======================================================== --}}
      <div x-show="importStep === 4"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0">

        <div class="ie-card">
          <div class="ie-card-header">
            <div class="ie-card-title">Importing Data</div>
            <div class="ie-btn-group">
              <button class="ie-btn" @click="commitPaused = !commitPaused">
                <template x-if="!commitPaused">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="6" y="4" width="4" height="16"/>
                    <rect x="14" y="4" width="4" height="16"/>
                  </svg>
                </template>
                <template x-if="commitPaused">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="5 3 19 12 5 21 5 3"/>
                  </svg>
                </template>
                <span x-text="commitPaused ? 'Resume' : 'Pause'"></span>
              </button>
              <button class="ie-btn ie-btn-danger" @click="importStep = 5">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="3" width="18" height="18" rx="2"/>
                  <line x1="9" y1="9" x2="15" y2="15"/>
                  <line x1="15" y1="9" x2="9" y2="15"/>
                </svg>
                Cancel
              </button>
            </div>
          </div>

          <div class="ie-progress-label">
            <span class="ie-progress-text" x-text="commitPaused ? 'Paused' : 'Processing...'"></span>
            <span class="ie-progress-pct" x-text="commitProgress + '%'"></span>
          </div>
          <div class="ie-progress ie-progress-large">
            <div class="ie-progress-bar" :class="{ animated: !commitPaused }" :style="'width: ' + commitProgress + '%'"></div>
          </div>

          <div class="ie-live-stats">
            <div class="ie-live-stat">
              <div class="ie-live-stat-value success" x-text="commitStats.created"></div>
              <div class="ie-live-stat-label">Created</div>
            </div>
            <div class="ie-live-stat">
              <div class="ie-live-stat-value info" x-text="commitStats.updated"></div>
              <div class="ie-live-stat-label">Updated</div>
            </div>
            <div class="ie-live-stat">
              <div class="ie-live-stat-value warning" x-text="commitStats.skipped"></div>
              <div class="ie-live-stat-label">Skipped</div>
            </div>
            <div class="ie-live-stat">
              <div class="ie-live-stat-value error" x-text="commitStats.failed"></div>
              <div class="ie-live-stat-label">Failed</div>
            </div>
          </div>

          <div class="ie-batch-info">
            <span class="ie-batch-label">Current Batch</span>
            <span class="ie-batch-value" x-text="'Batch ' + commitBatch + ' of ' + commitTotalBatches"></span>
            <span class="ie-batch-label" style="margin-left: auto;">Rows Processed</span>
            <span class="ie-batch-value" x-text="commitRowsProcessed + ' / ' + validationResults.total"></span>
          </div>
        </div>
      </div>

      {{-- ======================================================== --}}
      {{--  STAGE 5: Report                                          --}}
      {{-- ======================================================== --}}
      <div x-show="importStep === 5"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0">

        <div class="ie-stats">
          <div class="ie-stat-card">
            <div class="ie-stat-icon ie-stat-icon-success">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                <path d="M9 12l2 2 4-4"/>
              </svg>
            </div>
            <div>
              <div class="ie-stat-label">Created</div>
              <div class="ie-stat-value" x-text="reportStats.created"></div>
            </div>
          </div>
          <div class="ie-stat-card">
            <div class="ie-stat-icon ie-stat-icon-info">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
            </div>
            <div>
              <div class="ie-stat-label">Updated</div>
              <div class="ie-stat-value" x-text="reportStats.updated"></div>
            </div>
          </div>
          <div class="ie-stat-card">
            <div class="ie-stat-icon ie-stat-icon-warning">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </div>
            <div>
              <div class="ie-stat-label">Skipped</div>
              <div class="ie-stat-value" x-text="reportStats.skipped"></div>
            </div>
          </div>
          <div class="ie-stat-card">
            <div class="ie-stat-icon ie-stat-icon-error">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
              </svg>
            </div>
            <div>
              <div class="ie-stat-label">Failed</div>
              <div class="ie-stat-value" x-text="reportStats.failed"></div>
            </div>
          </div>
        </div>

        {{-- Failed rows table --}}
        <div class="ie-card" x-show="reportFailedRows.length > 0">
          <div class="ie-card-header">
            <div class="ie-card-title">Failed Rows</div>
          </div>
          <div class="ie-table-wrap">
            <table class="ie-table">
              <thead>
                <tr>
                  <th style="width: 60px;">Row</th>
                  <th>Field</th>
                  <th>Error</th>
                  <th>Value</th>
                </tr>
              </thead>
              <tbody>
                <template x-for="row in reportFailedRows" :key="row.row">
                  <tr>
                    <td class="ie-table-mono" x-text="'#' + row.row"></td>
                    <td class="ie-table-mono" x-text="row.field"></td>
                    <td x-text="row.error"></td>
                    <td class="ie-table-mono" x-text="row.value"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ie-actions-row">
          <div class="ie-btn-group">
            <button class="ie-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
              Download Error Report
            </button>
          </div>
          <div class="ie-actions-right">
            <button class="ie-btn ie-btn-primary" @click="resetImport()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="1 4 1 10 7 10"/>
                <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
              </svg>
              Start New Import
            </button>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  EXPORT TAB                                                   --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'export'" class="ie-tab-content">

      <div class="ie-card">
        <div class="ie-card-header">
          <div class="ie-card-title">Export Data</div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
          <div>
            <div class="ie-section-label">Source</div>
            <select class="ie-select" style="width: 100%;" x-model="exportSource">
              <option value="custom_view">Custom View</option>
              <option value="model">Model (Direct)</option>
            </select>
          </div>
          <div>
            <div class="ie-section-label">Model</div>
            <select class="ie-select" style="width: 100%;" x-model="exportModel">
              <template x-for="m in modelOptions" :key="m.key">
                <option :value="m.key" x-text="m.label"></option>
              </template>
            </select>
          </div>
        </div>

        <div class="ie-section-label">Format</div>
        <div class="ie-format-cards" style="margin-bottom: 20px;">
          <template x-for="fmt in exportFormats" :key="fmt.key">
            <div class="ie-format-card"
                 :class="{ selected: exportFormat === fmt.key }"
                 @click="exportFormat = fmt.key">
              <div class="ie-format-card-icon" x-text="fmt.icon"></div>
              <div class="ie-format-card-label" x-text="fmt.label"></div>
            </div>
          </template>
        </div>

        {{-- Filters --}}
        <div class="ie-section-label">Filters</div>
        <div style="margin-bottom: 16px;">
          <template x-for="(filter, idx) in exportFilters" :key="idx">
            <div class="ie-filter-row">
              <select class="ie-select" x-model="filter.field">
                <option value="">Select field...</option>
                <template x-for="f in exportFilterFields" :key="f">
                  <option :value="f" x-text="f"></option>
                </template>
              </select>
              <select class="ie-select" x-model="filter.predicate">
                <option value="_eq">equals</option>
                <option value="_not_eq">not equals</option>
                <option value="_cont">contains</option>
                <option value="_gt">greater than</option>
                <option value="_lt">less than</option>
                <option value="_present">present</option>
                <option value="_blank">blank</option>
                <option value="_true">is true</option>
                <option value="_false">is false</option>
              </select>
              <input class="ie-input" x-model="filter.value" placeholder="Value...">
              <button class="ie-filter-remove" @click="exportFilters.splice(idx, 1)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18"/>
                  <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </div>
          </template>
          <button class="ie-btn" @click="exportFilters.push({ field: '', predicate: '_eq', value: '' })" style="margin-top: 4px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"/>
              <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Filter
          </button>
        </div>

        {{-- Column selector --}}
        <div class="ie-section-label">Columns</div>
        <div class="ie-checkbox-group" style="margin-bottom: 16px;">
          <template x-for="col in exportColumns" :key="col.key">
            <label class="ie-checkbox-label">
              <input type="checkbox" class="ie-checkbox" :checked="col.selected" @change="col.selected = !col.selected">
              <span x-text="col.label"></span>
            </label>
          </template>
        </div>

        <div class="ie-actions-row">
          <div></div>
          <button class="ie-btn ie-btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Export
          </button>
        </div>
      </div>

      {{-- Recent exports --}}
      <div class="ie-card">
        <div class="ie-card-header">
          <div class="ie-card-title">Recent Exports</div>
        </div>
        <div class="ie-table-wrap">
          <table class="ie-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Format</th>
                <th>Rows</th>
                <th>Size</th>
                <th>Status</th>
                <th>Created</th>
                <th style="width: 80px;"></th>
              </tr>
            </thead>
            <tbody>
              <template x-for="exp in recentExports" :key="exp.name">
                <tr>
                  <td style="font-weight: 600; color: var(--ie-text);" x-text="exp.name"></td>
                  <td><span class="ie-badge ie-badge-info" x-text="exp.format"></span></td>
                  <td class="ie-table-mono" x-text="exp.rows.toLocaleString()"></td>
                  <td class="ie-table-mono" x-text="exp.size"></td>
                  <td>
                    <span class="ie-badge"
                          :class="{
                            'ie-badge-success': exp.status === 'Completed',
                            'ie-badge-info': exp.status === 'Processing'
                          }"
                          x-text="exp.status"></span>
                  </td>
                  <td x-text="exp.created"></td>
                  <td>
                    <template x-if="exp.status === 'Completed'">
                      <button class="ie-btn" style="padding: 4px 10px; font-size: 10px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 12px; height: 12px;">
                          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                          <polyline points="7 10 12 15 17 10"/>
                          <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Download
                      </button>
                    </template>
                    <template x-if="exp.status === 'Processing'">
                      <span style="font-family: var(--font-display); font-size: 10px; color: var(--ie-text-muted);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 12px; height: 12px; animation: iePulse 1.5s infinite; display: inline-block; vertical-align: middle;">
                          <circle cx="12" cy="12" r="10"/>
                        </svg>
                      </span>
                    </template>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  MIGRATION PLANS TAB                                          --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'migration'" class="ie-tab-content">

      <div class="ie-plan-card">
        <div class="ie-plan-header">
          <div>
            <div class="ie-plan-title" x-text="migrationPlan.name"></div>
            <div class="ie-plan-template" x-text="'Template: ' + migrationPlan.template"></div>
          </div>
          <div style="display: flex; align-items: center; gap: 12px;">
            <span class="ie-badge ie-badge-info" x-text="migrationPlan.status"></span>
          </div>
        </div>

        <div class="ie-progress-label">
          <span class="ie-progress-text">Overall Progress</span>
          <span class="ie-progress-pct" x-text="migrationPlan.progress + '%'"></span>
        </div>
        <div class="ie-progress ie-progress-large" style="margin-bottom: 20px;">
          <div class="ie-progress-bar animated" :style="'width: ' + migrationPlan.progress + '%'"></div>
        </div>

        <div class="ie-section-label">Steps</div>
        <div class="ie-plan-steps">
          <template x-for="step in migrationPlan.steps" :key="step.name">
            <div class="ie-plan-step">
              <div class="ie-plan-step-dot" :class="step.status"></div>
              <div class="ie-plan-step-name" x-text="step.name"></div>
              <div class="ie-plan-step-status" :class="step.status">
                <template x-if="step.status === 'done'">
                  <span>Completed</span>
                </template>
                <template x-if="step.status === 'active'">
                  <span>In Progress</span>
                </template>
                <template x-if="step.status === 'pending'">
                  <span>Pending</span>
                </template>
              </div>
              <template x-if="step.count">
                <span class="ie-table-mono" style="font-size: 11px; color: var(--ie-text-muted);" x-text="step.count + ' records'"></span>
              </template>
            </div>
          </template>
        </div>
      </div>

      <div class="ie-empty">
        <div class="ie-empty-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
        </div>
        <div>Create a new migration plan to move data between systems.</div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  PROFILES TAB                                                 --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'profiles'" class="ie-tab-content">

      <div class="ie-card">
        <div class="ie-card-header">
          <div class="ie-card-title">Saved Mapping Profiles</div>
          <button class="ie-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"/>
              <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            New Profile
          </button>
        </div>

        <div class="ie-table-wrap">
          <table class="ie-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Target Model</th>
                <th>Format</th>
                <th>Shared</th>
                <th style="width: 120px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="profile in savedProfiles" :key="profile.name">
                <tr>
                  <td style="font-weight: 600; color: var(--ie-text);" x-text="profile.name"></td>
                  <td>
                    <span class="ie-badge ie-badge-neutral" x-text="profile.target"></span>
                  </td>
                  <td>
                    <span class="ie-badge ie-badge-info" x-text="profile.format"></span>
                  </td>
                  <td>
                    <div class="ie-toggle" :class="{ on: profile.shared }" @click="profile.shared = !profile.shared"></div>
                  </td>
                  <td>
                    <div class="ie-btn-group">
                      <button class="ie-btn" style="padding: 4px 10px; font-size: 10px;">Edit</button>
                      <button class="ie-btn ie-btn-danger" style="padding: 4px 10px; font-size: 10px;">Delete</button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

@verbatim
<script>
function importExportEngine() {

  /* ================================================================ */
  /*  TABS                                                             */
  /* ================================================================ */
  const tabs = [
    { key: 'import', label: 'Import' },
    { key: 'export', label: 'Export' },
    { key: 'migration', label: 'Migration Plans' },
    { key: 'profiles', label: 'Profiles' },
  ];

  /* ================================================================ */
  /*  MODEL OPTIONS                                                    */
  /* ================================================================ */
  const modelOptions = [
    { key: 'products', label: 'Products' },
    { key: 'members', label: 'Members' },
    { key: 'opportunities', label: 'Opportunities' },
    { key: 'invoices', label: 'Invoices' },
  ];

  /* ================================================================ */
  /*  IMPORT STAGES                                                    */
  /* ================================================================ */
  const importStages = [
    { key: 'upload', label: 'Upload' },
    { key: 'map', label: 'Map' },
    { key: 'validate', label: 'Validate' },
    { key: 'commit', label: 'Commit' },
    { key: 'report', label: 'Report' },
  ];

  /* ================================================================ */
  /*  MOCK DATA                                                        */
  /* ================================================================ */
  const mockUploadedFile = {
    name: 'products_march_2026.csv',
    size: '1.2 MB',
    rows: 250,
    format: 'CSV',
    columns: [
      'Product Name', 'SKU', 'Description', 'Day Rate \u00a3', 'Week Rate \u00a3',
      'Replacement Cost', 'Weight (kg)', 'Category', 'Barcode',
      'Manufacturer', 'Active', 'Notes'
    ]
  };

  const mockFieldMappings = [
    { source: 'Product Name', target: 'name', transforms: ['trim'] },
    { source: 'SKU', target: 'sku', transforms: ['trim', 'uppercase'] },
    { source: 'Description', target: 'description', transforms: ['trim', 'strip_html'] },
    { source: 'Day Rate \u00a3', target: 'day_rate', transforms: ['strip_currency', 'to_decimal', 'round:2'] },
    { source: 'Week Rate \u00a3', target: 'week_rate', transforms: ['strip_currency', 'to_decimal', 'round:2'] },
    { source: 'Replacement Cost', target: 'replacement_cost', transforms: ['strip_currency', 'to_decimal'] },
    { source: 'Weight (kg)', target: 'weight', transforms: ['to_decimal'] },
    { source: 'Category', target: 'product_group', transforms: ['map_values'] },
    { source: 'Barcode', target: 'barcode', transforms: ['trim'] },
    { source: 'Manufacturer', target: '', transforms: [] },
    { source: 'Active', target: 'active', transforms: ['to_boolean'] },
    { source: 'Notes', target: '', transforms: [] },
  ];

  const mockTargetFieldGroups = [
    {
      name: 'Core',
      fields: [
        { key: 'name', label: 'Name' },
        { key: 'sku', label: 'SKU' },
        { key: 'description', label: 'Description' },
        { key: 'barcode', label: 'Barcode' },
        { key: 'active', label: 'Active' },
        { key: 'weight', label: 'Weight' },
        { key: 'product_group', label: 'Product Group' },
      ]
    },
    {
      name: 'Financial',
      fields: [
        { key: 'day_rate', label: 'Day Rate' },
        { key: 'week_rate', label: 'Week Rate' },
        { key: 'replacement_cost', label: 'Replacement Cost' },
        { key: 'purchase_price', label: 'Purchase Price' },
      ]
    },
    {
      name: 'Dates',
      fields: [
        { key: 'created_at', label: 'Created At' },
        { key: 'updated_at', label: 'Updated At' },
      ]
    },
    {
      name: 'Custom',
      fields: [
        { key: 'cf_manufacturer', label: 'Manufacturer' },
        { key: 'cf_colour', label: 'Colour' },
        { key: 'cf_power_draw', label: 'Power Draw (W)' },
      ]
    }
  ];

  const mockValidationRows = [
    {
      row: 12,
      status: 'valid',
      message: 'All fields passed validation',
      raw: { 'Product Name': 'QSC K12.2', 'SKU': 'SPK-012', 'Day Rate \u00a3': '\u00a345.00', 'Category': 'Sound' },
      mapped: { name: 'QSC K12.2', sku: 'SPK-012', day_rate: '45.00', product_group: '1' }
    },
    {
      row: 45,
      status: 'error',
      message: 'day_rate \u2014 value \'\u00a3abc\' cannot be converted to decimal',
      raw: { 'Product Name': 'Broken Entry', 'SKU': 'BRK-001', 'Day Rate \u00a3': '\u00a3abc', 'Category': 'Sound' },
      mapped: { name: 'Broken Entry', sku: 'BRK-001', day_rate: null, product_group: '1' }
    },
    {
      row: 78,
      status: 'warning',
      message: 'weight \u2014 empty value, will default to 0',
      raw: { 'Product Name': 'LED Par Can', 'SKU': 'LGT-078', 'Day Rate \u00a3': '\u00a315.00', 'Weight (kg)': '' },
      mapped: { name: 'LED Par Can', sku: 'LGT-078', day_rate: '15.00', weight: '0' }
    },
    {
      row: 102,
      status: 'error',
      message: 'sku \u2014 duplicate detected \'SPK-001\'',
      raw: { 'Product Name': 'Duplicate Speaker', 'SKU': 'SPK-001', 'Day Rate \u00a3': '\u00a355.00', 'Category': 'Sound' },
      mapped: { name: 'Duplicate Speaker', sku: 'SPK-001', day_rate: '55.00', product_group: '1' }
    },
    {
      row: 189,
      status: 'warning',
      message: 'product_group \u2014 unmapped value \'Rigging\', will be set to null',
      raw: { 'Product Name': 'Chain Hoist 1T', 'SKU': 'RIG-189', 'Day Rate \u00a3': '\u00a385.00', 'Category': 'Rigging' },
      mapped: { name: 'Chain Hoist 1T', sku: 'RIG-189', day_rate: '85.00', product_group: null }
    },
  ];

  const mockRecentExports = [
    { name: 'All Products', format: 'CSV', rows: 342, size: '48 KB', status: 'Completed', created: '2 hours ago' },
    { name: 'Active Members', format: 'XLSX', rows: 156, size: '124 KB', status: 'Completed', created: 'Yesterday' },
    { name: 'Q1 Invoices', format: 'PDF', rows: 89, size: '2.1 MB', status: 'Completed', created: '3 days ago' },
    { name: 'Stock Levels', format: 'JSON', rows: 1204, size: '312 KB', status: 'Processing', created: 'Just now' },
  ];

  const mockMigrationPlan = {
    name: 'CRMS Migration',
    template: 'signals/crms-migration',
    status: 'Running',
    progress: 45,
    steps: [
      { name: 'Members', status: 'done', count: 1240 },
      { name: 'Products', status: 'done', count: 856 },
      { name: 'Opportunities', status: 'active', count: 423 },
      { name: 'Invoices', status: 'pending', count: null },
      { name: 'Stock', status: 'pending', count: null },
      { name: 'Custom Fields', status: 'pending', count: null },
    ]
  };

  const mockSavedProfiles = [
    { name: 'Standard Product Import', target: 'Products', format: 'CSV', shared: true },
    { name: 'Member Upload', target: 'Members', format: 'XLSX', shared: false },
    { name: 'CRMS Product Mapping', target: 'Products', format: 'CSV', shared: true },
  ];

  const mockReportFailedRows = [
    { row: 45, field: 'day_rate', error: 'Cannot convert \'\u00a3abc\' to decimal', value: '\u00a3abc' },
    { row: 102, field: 'sku', error: 'Duplicate value \'SPK-001\' already exists', value: 'SPK-001' },
    { row: 134, field: 'name', error: 'Required field is empty', value: '' },
    { row: 201, field: 'day_rate', error: 'Value exceeds maximum (999999.99)', value: '\u00a31,500,000.00' },
    { row: 218, field: 'barcode', error: 'Invalid barcode format', value: 'XX-INVALID' },
    { row: 225, field: 'weight', error: 'Negative values not allowed', value: '-5.2' },
    { row: 237, field: 'sku', error: 'Value exceeds max length (50)', value: 'THIS-SKU-IS-WAY-TOO-LONG-FOR-THE-DATABASE-FIELD-LIMIT-EXCEEDED' },
    { row: 244, field: 'product_group', error: 'Referenced product group does not exist', value: 'Pyrotechnics' },
  ];

  const conflictStrategies = [
    { key: 'skip', name: 'Skip', desc: 'Skip duplicate rows' },
    { key: 'overwrite', name: 'Overwrite', desc: 'Replace existing data' },
    { key: 'merge', name: 'Merge', desc: 'Update only empty fields' },
    { key: 'flag', name: 'Flag', desc: 'Mark for manual review' },
  ];

  const duplicateFields = [
    { key: 'sku', label: 'SKU', selected: true },
    { key: 'barcode', label: 'Barcode', selected: true },
    { key: 'name', label: 'Name', selected: false },
    { key: 'email', label: 'Email', selected: false },
  ];

  const exportFormats = [
    { key: 'csv', label: 'CSV', icon: 'CSV' },
    { key: 'xlsx', label: 'Excel', icon: 'XLS' },
    { key: 'json', label: 'JSON', icon: 'JSON' },
    { key: 'pdf', label: 'PDF', icon: 'PDF' },
  ];

  const exportFilterFields = [
    'name', 'sku', 'active', 'product_group', 'day_rate', 'week_rate',
    'replacement_cost', 'weight', 'created_at', 'updated_at'
  ];

  const exportColumns = [
    { key: 'name', label: 'Name', selected: true },
    { key: 'sku', label: 'SKU', selected: true },
    { key: 'description', label: 'Description', selected: true },
    { key: 'day_rate', label: 'Day Rate', selected: true },
    { key: 'week_rate', label: 'Week Rate', selected: true },
    { key: 'replacement_cost', label: 'Replacement Cost', selected: false },
    { key: 'weight', label: 'Weight', selected: false },
    { key: 'product_group', label: 'Product Group', selected: true },
    { key: 'barcode', label: 'Barcode', selected: false },
    { key: 'active', label: 'Active', selected: true },
    { key: 'created_at', label: 'Created At', selected: false },
    { key: 'updated_at', label: 'Updated At', selected: false },
  ];

  /* ================================================================ */
  /*  COMPONENT STATE                                                  */
  /* ================================================================ */
  return {
    tabs,
    activeTab: 'import',
    modelOptions,
    importStages,

    /* Import state */
    importStep: 1,
    targetModel: 'products',
    isDragging: false,
    uploadedFile: null,
    fieldMappings: mockFieldMappings,
    targetFieldGroups: mockTargetFieldGroups,
    conflictStrategy: 'skip',
    conflictStrategies,
    duplicateFields,

    /* Validation state */
    validationRunning: false,
    validationProgress: 0,
    validationResults: { total: 250, valid: 230, warnings: 12, errors: 8 },
    validationRows: mockValidationRows,
    validationFilter: 'all',
    expandedValidationRow: null,
    acknowledgeErrors: false,

    /* Commit state */
    commitProgress: 0,
    commitPaused: false,
    commitStats: { created: 0, updated: 0, skipped: 0, failed: 0 },
    commitBatch: 1,
    commitTotalBatches: 5,
    commitRowsProcessed: 0,
    commitInterval: null,

    /* Report state */
    reportStats: { created: 198, updated: 32, skipped: 12, failed: 8 },
    reportFailedRows: mockReportFailedRows,

    /* Export state */
    exportSource: 'model',
    exportModel: 'products',
    exportFormat: 'csv',
    exportFormats,
    exportFilters: [
      { field: 'active', predicate: '_true', value: '' },
    ],
    exportFilterFields,
    exportColumns,
    recentExports: mockRecentExports,

    /* Migration state */
    migrationPlan: mockMigrationPlan,

    /* Profiles state */
    savedProfiles: mockSavedProfiles,

    /* ============================================================== */
    /*  COMPUTED                                                       */
    /* ============================================================== */
    get filteredValidationRows() {
      if (this.validationFilter === 'all') return this.validationRows;
      return this.validationRows.filter(r => r.status === this.validationFilter);
    },

    /* ============================================================== */
    /*  METHODS                                                        */
    /* ============================================================== */
    simulateUpload() {
      this.uploadedFile = mockUploadedFile;
    },

    autoMap() {
      this.fieldMappings = this.fieldMappings.map(m => {
        if (!m.target && m.source === 'Manufacturer') {
          return { ...m, target: 'cf_manufacturer', transforms: ['trim'] };
        }
        if (!m.target && m.source === 'Notes') {
          return { ...m, target: 'description', transforms: ['trim'] };
        }
        return m;
      });
    },

    goToStep(step) {
      if (step <= this.importStep) {
        this.importStep = step;
      }
    },

    toggleValidationDetail(rowNum) {
      this.expandedValidationRow = this.expandedValidationRow === rowNum ? null : rowNum;
    },

    startCommit() {
      this.commitProgress = 0;
      this.commitPaused = false;
      this.commitStats = { created: 0, updated: 0, skipped: 0, failed: 0 };
      this.commitBatch = 1;
      this.commitRowsProcessed = 0;

      if (this.commitInterval) clearInterval(this.commitInterval);

      this.commitInterval = setInterval(() => {
        if (this.commitPaused) return;

        this.commitProgress = Math.min(this.commitProgress + 2, 100);
        this.commitRowsProcessed = Math.floor((this.commitProgress / 100) * 250);
        this.commitBatch = Math.min(Math.ceil(this.commitProgress / 20), 5);

        /* Simulate incrementing stats */
        const processed = this.commitRowsProcessed;
        this.commitStats.created = Math.floor(processed * 0.79);
        this.commitStats.updated = Math.floor(processed * 0.13);
        this.commitStats.skipped = Math.floor(processed * 0.05);
        this.commitStats.failed = Math.floor(processed * 0.03);

        if (this.commitProgress >= 100) {
          clearInterval(this.commitInterval);
          this.commitInterval = null;
          setTimeout(() => { this.importStep = 5; }, 600);
        }
      }, 150);
    },

    resetImport() {
      this.importStep = 1;
      this.uploadedFile = null;
      this.fieldMappings = JSON.parse(JSON.stringify(mockFieldMappings));
      this.validationRunning = false;
      this.validationProgress = 0;
      this.acknowledgeErrors = false;
      this.expandedValidationRow = null;
      this.commitProgress = 0;
      this.commitPaused = false;
      if (this.commitInterval) {
        clearInterval(this.commitInterval);
        this.commitInterval = null;
      }
    },

    /* ============================================================== */
    /*  INIT: simulate validation on step 3 entry                     */
    /* ============================================================== */
    init() {
      this.$watch('importStep', (val) => {
        if (val === 3) {
          this.validationRunning = true;
          this.validationProgress = 0;

          const interval = setInterval(() => {
            this.validationProgress = Math.min(this.validationProgress + 8, 100);
            if (this.validationProgress >= 100) {
              clearInterval(interval);
              setTimeout(() => { this.validationRunning = false; }, 300);
            }
          }, 80);
        }
      });
    },
  };
}
</script>
@endverbatim
