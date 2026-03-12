<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Custom Views')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  CUSTOM VIEWS TOKENS — maps to brand system in app.css            */
  /* ================================================================ */
  :root {
    --cv-bg: var(--content-bg);
    --cv-panel: var(--card-bg);
    --cv-surface: var(--base);
    --cv-border: var(--card-border);
    --cv-border-subtle: var(--grey-border);
    --cv-text: var(--text-primary);
    --cv-text-secondary: var(--text-secondary);
    --cv-text-muted: var(--text-muted);
    --cv-accent: var(--green);
    --cv-accent-dim: var(--green-muted);
    --cv-hover: rgba(0, 0, 0, 0.03);
    --cv-shadow: var(--shadow-card);
    --cv-badge-system: var(--blue);
    --cv-badge-system-bg: rgba(37, 99, 235, 0.08);
    --cv-badge-personal: var(--green);
    --cv-badge-personal-bg: rgba(5, 150, 105, 0.08);
    --cv-badge-role: var(--amber);
    --cv-badge-role-bg: rgba(217, 119, 6, 0.08);
    --cv-group-header-bg: var(--table-header-bg);
    --cv-row-hover: var(--table-row-hover);
    --cv-table-border: var(--table-border);
    --cv-chip-bg: rgba(0, 0, 0, 0.04);
    --cv-chip-border: rgba(0, 0, 0, 0.06);
    --cv-danger: var(--red);
    --cv-danger-bg: rgba(239, 68, 68, 0.08);
    --cv-star: var(--amber);
    --cv-star-dim: rgba(217, 119, 6, 0.15);
    --cv-drag-handle: var(--text-muted);
    --cv-input-bg: var(--card-bg);
    --cv-section-bg: var(--base);
  }

  .dark {
    --cv-bg: var(--content-bg);
    --cv-panel: var(--card-bg);
    --cv-surface: var(--navy-mid);
    --cv-border: var(--card-border);
    --cv-border-subtle: #283040;
    --cv-text: var(--text-primary);
    --cv-text-secondary: var(--text-secondary);
    --cv-text-muted: var(--text-muted);
    --cv-accent: var(--green);
    --cv-accent-dim: rgba(5, 150, 105, 0.12);
    --cv-hover: rgba(255, 255, 255, 0.04);
    --cv-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --cv-badge-system-bg: rgba(37, 99, 235, 0.15);
    --cv-badge-personal-bg: rgba(5, 150, 105, 0.15);
    --cv-badge-role-bg: rgba(217, 119, 6, 0.15);
    --cv-group-header-bg: var(--table-header-bg);
    --cv-row-hover: var(--table-row-hover);
    --cv-table-border: var(--table-border);
    --cv-chip-bg: rgba(255, 255, 255, 0.08);
    --cv-chip-border: rgba(255, 255, 255, 0.1);
    --cv-danger-bg: rgba(239, 68, 68, 0.15);
    --cv-star-dim: rgba(217, 119, 6, 0.2);
    --cv-input-bg: var(--navy-mid);
    --cv-section-bg: rgba(255, 255, 255, 0.03);
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */
  .cv-page {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 64px);
    background: var(--cv-bg);
    position: relative;
  }

  .cv-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 32px 64px;
    width: 100%;
  }

  .cv-main-grid {
    display: flex;
    gap: 24px;
    align-items: flex-start;
  }

  .cv-sidebar {
    width: 260px;
    flex-shrink: 0;
    position: sticky;
    top: 88px;
    max-height: calc(100vh - 120px);
    overflow-y: auto;
    background: var(--cv-panel);
    border: 1px solid var(--cv-border);
    box-shadow: var(--cv-shadow);
  }

  .cv-main-content {
    flex: 1;
    min-width: 0;
  }

  /* ================================================================ */
  /*  HEADER                                                           */
  /* ================================================================ */
  .cv-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
  }

  .cv-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .cv-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    color: var(--cv-text);
    letter-spacing: -0.01em;
    line-height: 1;
  }

  .cv-subtitle {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--cv-accent);
    margin-top: 2px;
  }

  .cv-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .cv-entity-select {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    padding: 7px 32px 7px 12px;
    background: var(--cv-panel);
    border: 1px solid var(--cv-border);
    color: var(--cv-text);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    transition: border-color 0.15s;
  }

  .cv-entity-select:hover { border-color: var(--cv-accent); }
  .cv-entity-select:focus { outline: none; border-color: var(--cv-accent); box-shadow: 0 0 0 2px var(--cv-accent-dim); }

  .cv-create-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    background: var(--cv-accent);
    border: 1px solid var(--cv-accent);
    color: #ffffff;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
  }

  .cv-create-btn:hover { opacity: 0.9; }
  .cv-create-btn svg { width: 14px; height: 14px; }

  /* ================================================================ */
  /*  SIDEBAR                                                          */
  /* ================================================================ */
  .cv-sidebar-header {
    padding: 14px 16px 10px;
    border-bottom: 1px solid var(--cv-border);
  }

  .cv-sidebar-title {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--cv-text-secondary);
    margin-bottom: 10px;
  }

  .cv-sidebar-search {
    position: relative;
  }

  .cv-sidebar-search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--cv-text-muted);
    pointer-events: none;
  }

  .cv-sidebar-search-icon svg { width: 13px; height: 13px; }

  .cv-sidebar-search input {
    width: 100%;
    padding: 6px 10px 6px 30px;
    background: var(--cv-input-bg);
    border: 1px solid var(--cv-border);
    color: var(--cv-text);
    font-family: var(--font-mono);
    font-size: 11px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .cv-sidebar-search input::placeholder { color: var(--cv-text-muted); }
  .cv-sidebar-search input:focus { outline: none; border-color: var(--cv-accent); box-shadow: 0 0 0 2px var(--cv-accent-dim); }

  .cv-sidebar-group {
    border-bottom: 1px solid var(--cv-border);
  }

  .cv-sidebar-group:last-child { border-bottom: none; }

  .cv-sidebar-group-label {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px 6px;
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--cv-text-muted);
  }

  .cv-sidebar-group-count {
    font-family: var(--font-mono);
    font-size: 9px;
    color: var(--cv-text-muted);
    opacity: 0.7;
  }

  .cv-view-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    cursor: pointer;
    transition: all 0.15s;
    border-left: 2px solid transparent;
    position: relative;
  }

  .cv-view-item:hover { background: var(--cv-hover); }

  .cv-view-item.cv-view-selected {
    background: var(--cv-accent-dim);
    border-left-color: var(--cv-accent);
  }

  .cv-view-name {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 500;
    color: var(--cv-text);
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .cv-view-selected .cv-view-name {
    font-weight: 600;
    color: var(--cv-accent);
  }

  .cv-view-badge {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 6px;
    flex-shrink: 0;
  }

  .cv-view-badge-system { background: var(--cv-badge-system-bg); color: var(--cv-badge-system); }
  .cv-view-badge-personal { background: var(--cv-badge-personal-bg); color: var(--cv-badge-personal); }
  .cv-view-badge-role { background: var(--cv-badge-role-bg); color: var(--cv-badge-role); }

  .cv-view-star {
    width: 14px;
    height: 14px;
    color: var(--cv-text-muted);
    flex-shrink: 0;
    opacity: 0.4;
    transition: all 0.15s;
  }

  .cv-view-star.cv-view-star-active {
    color: var(--cv-star);
    opacity: 1;
  }

  .cv-view-actions {
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.15s;
    flex-shrink: 0;
  }

  .cv-view-item:hover .cv-view-actions { opacity: 1; }

  .cv-view-action-btn {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid transparent;
    color: var(--cv-text-muted);
    cursor: pointer;
    transition: all 0.15s;
    padding: 0;
  }

  .cv-view-action-btn:hover { color: var(--cv-text); border-color: var(--cv-border); background: var(--cv-hover); }
  .cv-view-action-btn.cv-view-action-delete:hover { color: var(--cv-danger); border-color: var(--cv-danger); background: var(--cv-danger-bg); }
  .cv-view-action-btn svg { width: 12px; height: 12px; }

  .cv-sidebar-empty {
    text-align: center;
    padding: 32px 16px;
    color: var(--cv-text-muted);
    font-family: var(--font-display);
    font-size: 12px;
  }

  .cv-sidebar-empty-icon {
    width: 32px;
    height: 32px;
    margin: 0 auto 8px;
    color: var(--cv-text-muted);
    opacity: 0.4;
  }

  /* Sidebar scrollbar */
  .cv-sidebar::-webkit-scrollbar { width: 5px; }
  .cv-sidebar::-webkit-scrollbar-track { background: transparent; }
  .cv-sidebar::-webkit-scrollbar-thumb { background: var(--cv-border); border-radius: 3px; }
  .cv-sidebar::-webkit-scrollbar-thumb:hover { background: var(--cv-text-muted); }

  /* ================================================================ */
  /*  CONFIGURATION PANEL                                              */
  /* ================================================================ */
  .cv-config-panel {
    background: var(--cv-panel);
    border: 1px solid var(--cv-border);
    box-shadow: var(--cv-shadow);
    margin-bottom: 20px;
  }

  .cv-config-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--cv-border);
  }

  .cv-config-title {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 700;
    color: var(--cv-text);
  }

  .cv-config-badge {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 3px 8px;
  }

  .cv-config-body {
    padding: 20px;
  }

  .cv-config-row {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
  }

  .cv-config-row:last-child { margin-bottom: 0; }

  .cv-config-field {
    flex: 1;
  }

  .cv-config-field-narrow {
    width: 180px;
    flex: none;
  }

  .cv-config-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--cv-text-muted);
    margin-bottom: 6px;
    display: block;
  }

  .cv-config-input {
    width: 100%;
    padding: 8px 12px;
    background: var(--cv-input-bg);
    border: 1px solid var(--cv-border);
    color: var(--cv-text);
    font-family: var(--font-mono);
    font-size: 12px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .cv-config-input::placeholder { color: var(--cv-text-muted); }
  .cv-config-input:focus { outline: none; border-color: var(--cv-accent); box-shadow: 0 0 0 2px var(--cv-accent-dim); }

  .cv-config-select {
    width: 100%;
    padding: 8px 32px 8px 12px;
    background: var(--cv-input-bg);
    border: 1px solid var(--cv-border);
    color: var(--cv-text);
    font-family: var(--font-mono);
    font-size: 12px;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    transition: border-color 0.15s;
  }

  .cv-config-select:focus { outline: none; border-color: var(--cv-accent); box-shadow: 0 0 0 2px var(--cv-accent-dim); }

  /* Visibility pills */
  .cv-visibility-pills {
    display: flex;
    gap: 4px;
  }

  .cv-visibility-pill {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    padding: 6px 14px;
    background: var(--cv-panel);
    border: 1px solid var(--cv-border);
    color: var(--cv-text-secondary);
    cursor: pointer;
    transition: all 0.15s;
  }

  .cv-visibility-pill:hover { border-color: var(--cv-text-muted); color: var(--cv-text); }

  .cv-visibility-pill.cv-visibility-active {
    background: var(--cv-accent);
    color: #ffffff;
    border-color: var(--cv-accent);
  }

  /* Role checkboxes */
  .cv-role-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-top: 8px;
    padding: 12px;
    background: var(--cv-section-bg);
    border: 1px solid var(--cv-border);
  }

  .cv-role-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 12px;
    color: var(--cv-text-secondary);
    transition: color 0.15s;
  }

  .cv-role-checkbox:hover { color: var(--cv-text); }

  .cv-role-check-box {
    width: 16px;
    height: 16px;
    border: 1px solid var(--cv-border);
    background: var(--cv-input-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s;
  }

  .cv-role-check-box.cv-role-checked {
    background: var(--cv-accent);
    border-color: var(--cv-accent);
  }

  .cv-role-check-box svg { width: 10px; height: 10px; color: #ffffff; }

  /* ================================================================ */
  /*  SECTION DIVIDERS                                                 */
  /* ================================================================ */
  .cv-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--cv-border);
  }

  .cv-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
  }

  .cv-section-title {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--cv-text-secondary);
  }

  .cv-section-count {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--cv-text-muted);
  }

  /* ================================================================ */
  /*  COLUMN CONFIGURATION                                             */
  /* ================================================================ */
  .cv-column-list {
    border: 1px solid var(--cv-border);
    background: var(--cv-section-bg);
  }

  .cv-column-header {
    display: grid;
    grid-template-columns: 28px 1fr 1fr 80px 28px 28px 28px;
    gap: 8px;
    padding: 6px 12px;
    background: var(--cv-group-header-bg);
    border-bottom: 1px solid var(--cv-table-border);
    align-items: center;
  }

  .cv-column-th {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--cv-text-muted);
  }

  .cv-column-row {
    display: grid;
    grid-template-columns: 28px 1fr 1fr 80px 28px 28px 28px;
    gap: 8px;
    padding: 6px 12px;
    border-bottom: 1px solid var(--cv-table-border);
    align-items: center;
    transition: background 0.1s;
  }

  .cv-column-row:last-child { border-bottom: none; }
  .cv-column-row:hover { background: var(--cv-row-hover); }

  .cv-column-drag {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--cv-drag-handle);
    cursor: grab;
    opacity: 0.5;
    transition: opacity 0.15s;
  }

  .cv-column-drag:hover { opacity: 1; }
  .cv-column-drag svg { width: 14px; height: 14px; }

  .cv-column-field {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--cv-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .cv-column-label-input {
    width: 100%;
    padding: 4px 8px;
    background: var(--cv-input-bg);
    border: 1px solid var(--cv-border);
    color: var(--cv-text);
    font-family: var(--font-display);
    font-size: 11px;
    transition: border-color 0.15s;
  }

  .cv-column-label-input:focus { outline: none; border-color: var(--cv-accent); box-shadow: 0 0 0 2px var(--cv-accent-dim); }

  .cv-column-width-input {
    width: 100%;
    padding: 4px 8px;
    background: var(--cv-input-bg);
    border: 1px solid var(--cv-border);
    color: var(--cv-text);
    font-family: var(--font-mono);
    font-size: 11px;
    text-align: center;
    transition: border-color 0.15s;
  }

  .cv-column-width-input:focus { outline: none; border-color: var(--cv-accent); box-shadow: 0 0 0 2px var(--cv-accent-dim); }

  .cv-column-move-btn {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid transparent;
    color: var(--cv-text-muted);
    cursor: pointer;
    transition: all 0.15s;
    padding: 0;
  }

  .cv-column-move-btn:hover { color: var(--cv-accent); border-color: var(--cv-border); background: var(--cv-hover); }
  .cv-column-move-btn:disabled { opacity: 0.2; cursor: not-allowed; }
  .cv-column-move-btn:disabled:hover { color: var(--cv-text-muted); border-color: transparent; background: transparent; }
  .cv-column-move-btn svg { width: 12px; height: 12px; }

  .cv-column-remove-btn {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid transparent;
    color: var(--cv-text-muted);
    cursor: pointer;
    transition: all 0.15s;
    padding: 0;
  }

  .cv-column-remove-btn:hover { color: var(--cv-danger); border-color: var(--cv-danger); background: var(--cv-danger-bg); }
  .cv-column-remove-btn svg { width: 12px; height: 12px; }

  /* Add Column dropdown */
  .cv-add-column-wrap {
    position: relative;
    margin-top: 8px;
  }

  .cv-add-column-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: var(--cv-panel);
    border: 1px solid var(--cv-border);
    color: var(--cv-text-secondary);
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
  }

  .cv-add-column-btn:hover { border-color: var(--cv-accent); color: var(--cv-accent); }
  .cv-add-column-btn svg { width: 12px; height: 12px; }

  .cv-add-column-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    width: 320px;
    max-height: 320px;
    overflow-y: auto;
    background: var(--cv-panel);
    border: 1px solid var(--cv-border);
    box-shadow: var(--cv-shadow);
    z-index: 100;
  }

  .cv-add-column-group-label {
    padding: 8px 12px 4px;
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--cv-text-muted);
    background: var(--cv-group-header-bg);
    border-bottom: 1px solid var(--cv-table-border);
  }

  .cv-add-column-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    cursor: pointer;
    transition: background 0.1s;
    border-bottom: 1px solid var(--cv-table-border);
  }

  .cv-add-column-option:hover { background: var(--cv-hover); }
  .cv-add-column-option:last-child { border-bottom: none; }

  .cv-add-column-option.cv-add-column-disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }

  .cv-add-column-option.cv-add-column-disabled:hover { background: transparent; }

  .cv-add-column-field {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--cv-text);
  }

  .cv-add-column-label {
    font-family: var(--font-display);
    font-size: 10px;
    color: var(--cv-text-muted);
    margin-left: auto;
  }

  .cv-add-column-dropdown::-webkit-scrollbar { width: 5px; }
  .cv-add-column-dropdown::-webkit-scrollbar-track { background: transparent; }
  .cv-add-column-dropdown::-webkit-scrollbar-thumb { background: var(--cv-border); border-radius: 3px; }

  /* ================================================================ */
  /*  FILTER CONFIGURATION                                             */
  /* ================================================================ */
  .cv-filter-list {
    border: 1px solid var(--cv-border);
    background: var(--cv-section-bg);
  }

  .cv-filter-row {
    display: grid;
    grid-template-columns: 1fr 140px 1fr 28px;
    gap: 8px;
    padding: 8px 12px;
    border-bottom: 1px solid var(--cv-table-border);
    align-items: center;
  }

  .cv-filter-row:last-child { border-bottom: none; }

  .cv-filter-select {
    width: 100%;
    padding: 6px 28px 6px 8px;
    background: var(--cv-input-bg);
    border: 1px solid var(--cv-border);
    color: var(--cv-text);
    font-family: var(--font-mono);
    font-size: 11px;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    transition: border-color 0.15s;
  }

  .cv-filter-select:focus { outline: none; border-color: var(--cv-accent); box-shadow: 0 0 0 2px var(--cv-accent-dim); }

  .cv-filter-input {
    width: 100%;
    padding: 6px 8px;
    background: var(--cv-input-bg);
    border: 1px solid var(--cv-border);
    color: var(--cv-text);
    font-family: var(--font-mono);
    font-size: 11px;
    transition: border-color 0.15s;
  }

  .cv-filter-input:focus { outline: none; border-color: var(--cv-accent); box-shadow: 0 0 0 2px var(--cv-accent-dim); }

  .cv-filter-remove-btn {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid transparent;
    color: var(--cv-text-muted);
    cursor: pointer;
    transition: all 0.15s;
    padding: 0;
  }

  .cv-filter-remove-btn:hover { color: var(--cv-danger); border-color: var(--cv-danger); background: var(--cv-danger-bg); }
  .cv-filter-remove-btn svg { width: 12px; height: 12px; }

  .cv-add-filter-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: var(--cv-panel);
    border: 1px solid var(--cv-border);
    color: var(--cv-text-secondary);
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    margin-top: 8px;
  }

  .cv-add-filter-btn:hover { border-color: var(--cv-accent); color: var(--cv-accent); }
  .cv-add-filter-btn svg { width: 12px; height: 12px; }

  /* ================================================================ */
  /*  SORT & PER PAGE                                                  */
  /* ================================================================ */
  .cv-sort-row {
    display: flex;
    gap: 12px;
    align-items: flex-end;
  }

  .cv-sort-direction-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 8px 14px;
    background: var(--cv-panel);
    border: 1px solid var(--cv-border);
    color: var(--cv-text-secondary);
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
  }

  .cv-sort-direction-btn:hover { border-color: var(--cv-accent); color: var(--cv-text); }
  .cv-sort-direction-btn svg { width: 12px; height: 12px; }

  /* Save button */
  .cv-save-row {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--cv-border);
  }

  .cv-save-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 20px;
    background: var(--cv-accent);
    border: 1px solid var(--cv-accent);
    color: #ffffff;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
  }

  .cv-save-btn:hover { opacity: 0.9; }
  .cv-save-btn svg { width: 14px; height: 14px; }

  .cv-cancel-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 20px;
    background: var(--cv-panel);
    border: 1px solid var(--cv-border);
    color: var(--cv-text-secondary);
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
  }

  .cv-cancel-btn:hover { border-color: var(--cv-text-muted); color: var(--cv-text); }

  /* ================================================================ */
  /*  PREVIEW TABLE                                                    */
  /* ================================================================ */
  .cv-preview-panel {
    background: var(--cv-panel);
    border: 1px solid var(--cv-border);
    box-shadow: var(--cv-shadow);
  }

  .cv-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    border-bottom: 1px solid var(--cv-border);
  }

  .cv-preview-title {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--cv-text-secondary);
  }

  .cv-preview-subtitle {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--cv-text-muted);
  }

  /* Active filter chips above table */
  .cv-active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 10px 20px;
    border-bottom: 1px solid var(--cv-border);
    background: var(--cv-section-bg);
  }

  .cv-active-filter-chip {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: var(--cv-chip-bg);
    border: 1px solid var(--cv-chip-border);
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--cv-text-secondary);
    transition: all 0.15s;
  }

  .cv-active-filter-chip-key {
    font-weight: 600;
    color: var(--cv-text);
  }

  .cv-active-filter-chip-pred {
    color: var(--cv-accent);
    font-weight: 500;
  }

  .cv-active-filter-chip-remove {
    width: 14px;
    height: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--cv-text-muted);
    transition: color 0.15s;
    padding: 0;
    background: transparent;
    border: none;
  }

  .cv-active-filter-chip-remove:hover { color: var(--cv-danger); }
  .cv-active-filter-chip-remove svg { width: 10px; height: 10px; }

  .cv-preview-table {
    width: 100%;
    border-collapse: collapse;
  }

  .cv-preview-table th {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--cv-text-muted);
    padding: 8px 16px;
    text-align: left;
    background: var(--cv-group-header-bg);
    border-bottom: 1px solid var(--cv-table-border);
    white-space: nowrap;
    cursor: pointer;
    transition: color 0.15s;
    user-select: none;
  }

  .cv-preview-table th:hover { color: var(--cv-text-secondary); }

  .cv-preview-table th.cv-sort-active { color: var(--cv-accent); }

  .cv-preview-sort-icon {
    display: inline-block;
    width: 10px;
    height: 10px;
    margin-left: 4px;
    vertical-align: middle;
  }

  .cv-preview-table td {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--cv-text);
    padding: 10px 16px;
    border-bottom: 1px solid var(--cv-table-border);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 240px;
  }

  .cv-preview-table tr:last-child td { border-bottom: none; }
  .cv-preview-table tr:hover td { background: var(--cv-row-hover); }

  .cv-preview-state-badge {
    display: inline-block;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 8px;
  }

  .cv-preview-state-order { background: var(--cv-badge-personal-bg); color: var(--cv-badge-personal); }
  .cv-preview-state-quotation { background: var(--cv-badge-system-bg); color: var(--cv-badge-system); }
  .cv-preview-state-active { background: var(--cv-badge-personal-bg); color: var(--cv-badge-personal); }
  .cv-preview-state-draft { background: var(--cv-chip-bg); color: var(--cv-text-muted); }

  .cv-preview-status-unpaid { background: var(--cv-badge-role-bg); color: var(--cv-badge-role); }
  .cv-preview-status-paid { background: var(--cv-badge-personal-bg); color: var(--cv-badge-personal); }
  .cv-preview-status-overdue { background: var(--cv-danger-bg); color: var(--cv-danger); }

  .cv-preview-money {
    font-family: var(--font-mono);
    font-variant-numeric: tabular-nums;
  }

  /* ================================================================ */
  /*  STATUS BAR                                                       */
  /* ================================================================ */
  .cv-status-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 10px 20px;
    background: var(--cv-group-header-bg);
    border-top: 1px solid var(--cv-border);
    font-family: var(--font-display);
    font-size: 11px;
    color: var(--cv-text-muted);
  }

  .cv-status-item {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .cv-status-value {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 600;
    color: var(--cv-text-secondary);
  }

  .cv-status-sep {
    width: 1px;
    height: 14px;
    background: var(--cv-border);
  }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes cvFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes cvSlideIn {
    from { opacity: 0; transform: translateX(-8px); }
    to { opacity: 1; transform: translateX(0); }
  }

  .cv-view-item { animation: cvSlideIn 0.2s ease both; }
  .cv-column-row { animation: cvFadeIn 0.2s ease both; }
  .cv-filter-row { animation: cvFadeIn 0.2s ease both; }
  .cv-preview-panel { animation: cvFadeIn 0.3s ease both; }

  /* Staggered sidebar items */
  .cv-view-item:nth-child(1) { animation-delay: 0s; }
  .cv-view-item:nth-child(2) { animation-delay: 0.03s; }
  .cv-view-item:nth-child(3) { animation-delay: 0.06s; }
  .cv-view-item:nth-child(4) { animation-delay: 0.09s; }
  .cv-view-item:nth-child(5) { animation-delay: 0.12s; }
  .cv-view-item:nth-child(6) { animation-delay: 0.15s; }
  .cv-view-item:nth-child(7) { animation-delay: 0.18s; }

  /* Staggered column rows */
  .cv-column-row:nth-child(1) { animation-delay: 0s; }
  .cv-column-row:nth-child(2) { animation-delay: 0.03s; }
  .cv-column-row:nth-child(3) { animation-delay: 0.06s; }
  .cv-column-row:nth-child(4) { animation-delay: 0.09s; }
  .cv-column-row:nth-child(5) { animation-delay: 0.12s; }
  .cv-column-row:nth-child(6) { animation-delay: 0.15s; }
  .cv-column-row:nth-child(7) { animation-delay: 0.18s; }
  .cv-column-row:nth-child(8) { animation-delay: 0.21s; }

  /* ================================================================ */
  /*  EMPTY STATE                                                      */
  /* ================================================================ */
  .cv-empty {
    text-align: center;
    padding: 48px 24px;
    color: var(--cv-text-muted);
    font-family: var(--font-display);
    font-size: 13px;
  }

  .cv-empty-icon {
    width: 40px;
    height: 40px;
    margin: 0 auto 12px;
    color: var(--cv-text-muted);
    opacity: 0.4;
  }

  /* ================================================================ */
  /*  NO-SELECTION STATE                                               */
  /* ================================================================ */
  .cv-no-selection {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 64px 32px;
    background: var(--cv-panel);
    border: 1px solid var(--cv-border);
    box-shadow: var(--cv-shadow);
    text-align: center;
  }

  .cv-no-selection-icon {
    width: 48px;
    height: 48px;
    color: var(--cv-text-muted);
    opacity: 0.3;
    margin-bottom: 16px;
  }

  .cv-no-selection-title {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 600;
    color: var(--cv-text-secondary);
    margin-bottom: 4px;
  }

  .cv-no-selection-desc {
    font-family: var(--font-display);
    font-size: 12px;
    color: var(--cv-text-muted);
  }
</style>

<div class="cv-page" x-data="customViewsExplorer()" x-cloak>

  <div class="cv-container">

    {{-- ============================================================ --}}
    {{--  HEADER                                                       --}}
    {{-- ============================================================ --}}
    <div class="cv-header">
      <div class="cv-header-left">
        <div>
          <div class="cv-title">Custom Views</div>
          <div class="cv-subtitle">Saved List Configurations</div>
        </div>
      </div>
      <div class="cv-header-right">
        <select class="cv-entity-select"
                x-model="currentEntity"
                @change="onEntityChange()">
          <template x-for="e in entityOptions" :key="e.key">
            <option :value="e.key" x-text="e.label"></option>
          </template>
        </select>

        <button class="cv-create-btn" @click="createNewView()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Create View
        </button>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  MAIN GRID: SIDEBAR + CONTENT                                 --}}
    {{-- ============================================================ --}}
    <div class="cv-main-grid">

      {{-- ============================================================ --}}
      {{--  SIDEBAR                                                      --}}
      {{-- ============================================================ --}}
      <div class="cv-sidebar">
        <div class="cv-sidebar-header">
          <div class="cv-sidebar-title">Views</div>
          <div class="cv-sidebar-search">
            <span class="cv-sidebar-search-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
              </svg>
            </span>
            <input type="text"
                   placeholder="Search views..."
                   x-model.debounce.150ms="viewSearchQuery"
                   @keydown.escape="viewSearchQuery = ''">
          </div>
        </div>

        {{-- System Views --}}
        <template x-if="filteredSystemViews.length > 0">
          <div class="cv-sidebar-group">
            <div class="cv-sidebar-group-label">
              System Views
              <span class="cv-sidebar-group-count" x-text="filteredSystemViews.length"></span>
            </div>
            <template x-for="(view, idx) in filteredSystemViews" :key="'sys-' + view.id">
              <div class="cv-view-item"
                   :class="{ 'cv-view-selected': selectedViewId === view.id }"
                   :style="'animation-delay: ' + (idx * 0.03) + 's'"
                   @click="selectView(view)">
                <span class="cv-view-name" x-text="view.name"></span>
                <span class="cv-view-badge cv-view-badge-system">System</span>
                <svg class="cv-view-star" :class="{ 'cv-view-star-active': view.is_default }" viewBox="0 0 24 24" :fill="view.is_default ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
              </div>
            </template>
          </div>
        </template>

        {{-- Personal Views --}}
        <template x-if="filteredPersonalViews.length > 0">
          <div class="cv-sidebar-group">
            <div class="cv-sidebar-group-label">
              Personal Views
              <span class="cv-sidebar-group-count" x-text="filteredPersonalViews.length"></span>
            </div>
            <template x-for="(view, idx) in filteredPersonalViews" :key="'per-' + view.id">
              <div class="cv-view-item"
                   :class="{ 'cv-view-selected': selectedViewId === view.id }"
                   :style="'animation-delay: ' + (idx * 0.03) + 's'"
                   @click="selectView(view)">
                <span class="cv-view-name" x-text="view.name"></span>
                <span class="cv-view-badge"
                      :class="view.visibility === 'personal' ? 'cv-view-badge-personal' : 'cv-view-badge-role'"
                      x-text="view.visibility === 'personal' ? 'Personal' : 'Role'"></span>
                <svg class="cv-view-star" :class="{ 'cv-view-star-active': view.is_default }" viewBox="0 0 24 24" :fill="view.is_default ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                <div class="cv-view-actions">
                  <button class="cv-view-action-btn" @click.stop="editView(view)" title="Edit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                  </button>
                  <button class="cv-view-action-btn cv-view-action-delete" @click.stop="deleteView(view)" title="Delete">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6"/>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                  </button>
                </div>
              </div>
            </template>
          </div>
        </template>

        {{-- Empty search state --}}
        <template x-if="filteredSystemViews.length === 0 && filteredPersonalViews.length === 0">
          <div class="cv-sidebar-empty">
            <div class="cv-sidebar-empty-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
              </svg>
            </div>
            <div>No views match your search.</div>
          </div>
        </template>
      </div>

      {{-- ============================================================ --}}
      {{--  MAIN CONTENT                                                 --}}
      {{-- ============================================================ --}}
      <div class="cv-main-content">

        {{-- No selection state --}}
        <template x-if="!selectedViewId && !isEditing">
          <div class="cv-no-selection">
            <div class="cv-no-selection-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="3" width="18" height="18" rx="2"/>
                <path d="M3 9h18"/>
                <path d="M9 21V9"/>
              </svg>
            </div>
            <div class="cv-no-selection-title">Select a view</div>
            <div class="cv-no-selection-desc">Choose a view from the sidebar to preview, or create a new one.</div>
          </div>
        </template>

        {{-- Configuration Panel (editing or creating) --}}
        <template x-if="isEditing">
          <div class="cv-config-panel"
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="opacity-0 translate-y-2"
               x-transition:enter-end="opacity-100 translate-y-0">
            <div class="cv-config-header">
              <div class="cv-config-title" x-text="editingView.id ? 'Edit View' : 'Create New View'"></div>
              <div class="cv-config-badge"
                   :class="{
                     'cv-view-badge-system': editingView.visibility === 'system',
                     'cv-view-badge-personal': editingView.visibility === 'personal',
                     'cv-view-badge-role': editingView.visibility === 'role',
                   }"
                   x-text="editingView.visibility"></div>
            </div>
            <div class="cv-config-body">

              {{-- View Name --}}
              <div class="cv-config-row">
                <div class="cv-config-field">
                  <label class="cv-config-label">View Name</label>
                  <input type="text" class="cv-config-input"
                         x-model="editingView.name"
                         placeholder="Enter view name...">
                </div>
              </div>

              {{-- Visibility --}}
              <div class="cv-config-row">
                <div class="cv-config-field">
                  <label class="cv-config-label">Visibility</label>
                  <div class="cv-visibility-pills">
                    <button class="cv-visibility-pill"
                            :class="{ 'cv-visibility-active': editingView.visibility === 'personal' }"
                            @click="editingView.visibility = 'personal'">Personal</button>
                    <button class="cv-visibility-pill"
                            :class="{ 'cv-visibility-active': editingView.visibility === 'role' }"
                            @click="editingView.visibility = 'role'">Role</button>
                    <button class="cv-visibility-pill"
                            :class="{ 'cv-visibility-active': editingView.visibility === 'system' }"
                            @click="editingView.visibility = 'system'">System</button>
                  </div>
                </div>
              </div>

              {{-- Role Assignment (shown when visibility=role) --}}
              <template x-if="editingView.visibility === 'role'">
                <div class="cv-config-row">
                  <div class="cv-config-field">
                    <label class="cv-config-label">Assign to Roles</label>
                    <div class="cv-role-grid">
                      <template x-for="role in availableRoles" :key="role.key">
                        <div class="cv-role-checkbox" @click="toggleRole(role.key)">
                          <div class="cv-role-check-box"
                               :class="{ 'cv-role-checked': editingView.roles && editingView.roles.includes(role.key) }">
                            <template x-if="editingView.roles && editingView.roles.includes(role.key)">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                              </svg>
                            </template>
                          </div>
                          <span x-text="role.label"></span>
                        </div>
                      </template>
                    </div>
                  </div>
                </div>
              </template>

              {{-- Column Configuration --}}
              <div class="cv-section">
                <div class="cv-section-header">
                  <div class="cv-section-title">Columns</div>
                  <div class="cv-section-count" x-text="editingView.columns.length + ' selected'"></div>
                </div>

                <div class="cv-column-list">
                  <div class="cv-column-header">
                    <div class="cv-column-th"></div>
                    <div class="cv-column-th">Field</div>
                    <div class="cv-column-th">Label</div>
                    <div class="cv-column-th">Width</div>
                    <div class="cv-column-th"></div>
                    <div class="cv-column-th"></div>
                    <div class="cv-column-th"></div>
                  </div>
                  <template x-for="(col, colIdx) in editingView.columns" :key="'col-' + colIdx + '-' + col.field">
                    <div class="cv-column-row" :style="'animation-delay: ' + (colIdx * 0.03) + 's'">
                      <div class="cv-column-drag" title="Drag to reorder">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <line x1="8" y1="6" x2="16" y2="6"/>
                          <line x1="8" y1="12" x2="16" y2="12"/>
                          <line x1="8" y1="18" x2="16" y2="18"/>
                        </svg>
                      </div>
                      <div class="cv-column-field" x-text="col.field"></div>
                      <input type="text" class="cv-column-label-input"
                             x-model="col.label"
                             placeholder="Label...">
                      <input type="text" class="cv-column-width-input"
                             x-model="col.width"
                             placeholder="auto">
                      <button class="cv-column-move-btn"
                              :disabled="colIdx === 0"
                              @click="moveColumn(colIdx, -1)"
                              title="Move up">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="18 15 12 9 6 15"/>
                        </svg>
                      </button>
                      <button class="cv-column-move-btn"
                              :disabled="colIdx === editingView.columns.length - 1"
                              @click="moveColumn(colIdx, 1)"
                              title="Move down">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="6 9 12 15 18 9"/>
                        </svg>
                      </button>
                      <button class="cv-column-remove-btn"
                              @click="removeColumn(colIdx)"
                              title="Remove column">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <line x1="18" y1="6" x2="6" y2="18"/>
                          <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                      </button>
                    </div>
                  </template>
                </div>

                {{-- Add Column --}}
                <div class="cv-add-column-wrap" @click.outside="showColumnDropdown = false">
                  <button class="cv-add-column-btn" @click="showColumnDropdown = !showColumnDropdown">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="12" y1="5" x2="12" y2="19"/>
                      <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Column
                  </button>
                  <div class="cv-add-column-dropdown"
                       x-show="showColumnDropdown"
                       x-transition:enter="transition ease-out duration-150"
                       x-transition:enter-start="opacity-0 translate-y-1"
                       x-transition:enter-end="opacity-100 translate-y-0"
                       x-transition:leave="transition ease-in duration-100"
                       x-transition:leave-start="opacity-100 translate-y-0"
                       x-transition:leave-end="opacity-0 translate-y-1">
                    <template x-for="group in availableColumnGroups" :key="group.name">
                      <div>
                        <div class="cv-add-column-group-label" x-text="group.name"></div>
                        <template x-for="field in group.fields" :key="field.key">
                          <div class="cv-add-column-option"
                               :class="{ 'cv-add-column-disabled': isColumnSelected(field.key) }"
                               @click="!isColumnSelected(field.key) && addColumn(field)">
                            <span class="cv-add-column-field" x-text="field.key"></span>
                            <span class="cv-add-column-label" x-text="field.label"></span>
                          </div>
                        </template>
                      </div>
                    </template>
                  </div>
                </div>
              </div>

              {{-- Filters --}}
              <div class="cv-section">
                <div class="cv-section-header">
                  <div class="cv-section-title">Filters</div>
                  <div class="cv-section-count" x-text="editingView.filters.length + ' active'"></div>
                </div>

                <template x-if="editingView.filters.length > 0">
                  <div class="cv-filter-list">
                    <template x-for="(filter, filterIdx) in editingView.filters" :key="'filter-' + filterIdx">
                      <div class="cv-filter-row" :style="'animation-delay: ' + (filterIdx * 0.03) + 's'">
                        <select class="cv-filter-select" x-model="filter.field">
                          <option value="">Select field...</option>
                          <template x-for="f in allAvailableFields" :key="f.key">
                            <option :value="f.key" x-text="f.key"></option>
                          </template>
                        </select>
                        <select class="cv-filter-select" x-model="filter.predicate">
                          <template x-for="pred in predicates" :key="pred.value">
                            <option :value="pred.value" x-text="pred.label"></option>
                          </template>
                        </select>
                        <input type="text" class="cv-filter-input"
                               x-model="filter.value"
                               placeholder="Value..."
                               :disabled="['present', 'blank', 'null', 'not_null', 'true', 'false'].includes(filter.predicate)">
                        <button class="cv-filter-remove-btn"
                                @click="removeFilter(filterIdx)"
                                title="Remove filter">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                          </svg>
                        </button>
                      </div>
                    </template>
                  </div>
                </template>

                <button class="cv-add-filter-btn" @click="addFilter()">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                  </svg>
                  Add Filter
                </button>
              </div>

              {{-- Sort & Per Page --}}
              <div class="cv-section">
                <div class="cv-section-header">
                  <div class="cv-section-title">Sort & Pagination</div>
                </div>
                <div class="cv-sort-row">
                  <div class="cv-config-field">
                    <label class="cv-config-label">Sort By</label>
                    <select class="cv-config-select" x-model="editingView.sort_column">
                      <template x-for="f in allAvailableFields" :key="'sort-' + f.key">
                        <option :value="f.key" x-text="f.label"></option>
                      </template>
                    </select>
                  </div>
                  <div>
                    <label class="cv-config-label">Direction</label>
                    <button class="cv-sort-direction-btn" @click="editingView.sort_direction = editingView.sort_direction === 'asc' ? 'desc' : 'asc'">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <template x-if="editingView.sort_direction === 'asc'">
                          <g>
                            <polyline points="18 15 12 9 6 15"/>
                          </g>
                        </template>
                        <template x-if="editingView.sort_direction === 'desc'">
                          <g>
                            <polyline points="6 9 12 15 18 9"/>
                          </g>
                        </template>
                      </svg>
                      <span x-text="editingView.sort_direction === 'asc' ? 'Ascending' : 'Descending'"></span>
                    </button>
                  </div>
                  <div class="cv-config-field-narrow">
                    <label class="cv-config-label">Per Page</label>
                    <select class="cv-config-select" x-model="editingView.per_page">
                      <option value="10">10</option>
                      <option value="20">20</option>
                      <option value="50">50</option>
                      <option value="100">100</option>
                    </select>
                  </div>
                </div>
              </div>

              {{-- Save / Cancel --}}
              <div class="cv-save-row">
                <button class="cv-cancel-btn" @click="cancelEditing()">Cancel</button>
                <button class="cv-save-btn" @click="saveView()">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                  </svg>
                  Save View
                </button>
              </div>
            </div>
          </div>
        </template>

        {{-- ============================================================ --}}
        {{--  PREVIEW TABLE                                                --}}
        {{-- ============================================================ --}}
        <template x-if="activeView">
          <div class="cv-preview-panel">
            <div class="cv-preview-header">
              <div class="cv-preview-title" x-text="activeView.name"></div>
              <div class="cv-preview-subtitle" x-text="'Preview — ' + currentEntityLabel + ' entity'"></div>
            </div>

            {{-- Active filter chips --}}
            <template x-if="activeView.filters && activeView.filters.length > 0">
              <div class="cv-active-filters">
                <template x-for="(filter, fIdx) in activeView.filters" :key="'chip-' + fIdx">
                  <div class="cv-active-filter-chip">
                    <span class="cv-active-filter-chip-key" x-text="filter.field"></span>
                    <span class="cv-active-filter-chip-pred" x-text="filter.predicate"></span>
                    <span x-text="filter.value || ''" x-show="filter.value"></span>
                    <button class="cv-active-filter-chip-remove" @click="removePreviewFilter(fIdx)" title="Remove filter">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                      </svg>
                    </button>
                  </div>
                </template>
              </div>
            </template>

            {{-- Table --}}
            <div style="overflow-x: auto;">
              <table class="cv-preview-table">
                <thead>
                  <tr>
                    <template x-for="col in activeView.columns" :key="'th-' + col.field">
                      <th :class="{ 'cv-sort-active': activeView.sort_column === col.field }"
                          @click="togglePreviewSort(col.field)">
                        <span x-text="col.label"></span>
                        <template x-if="activeView.sort_column === col.field">
                          <svg class="cv-preview-sort-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <template x-if="activeView.sort_direction === 'asc'">
                              <polyline points="18 15 12 9 6 15"/>
                            </template>
                            <template x-if="activeView.sort_direction === 'desc'">
                              <polyline points="6 9 12 15 18 9"/>
                            </template>
                          </svg>
                        </template>
                      </th>
                    </template>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="(row, rowIdx) in previewData" :key="'row-' + rowIdx">
                    <tr>
                      <template x-for="col in activeView.columns" :key="'td-' + col.field + '-' + rowIdx">
                        <td>
                          <template x-if="col.field === 'state_name' || col.field === 'state'">
                            <span class="cv-preview-state-badge"
                                  :class="'cv-preview-state-' + (row[col.field] || '').toLowerCase()"
                                  x-text="row[col.field]"></span>
                          </template>
                          <template x-if="col.field === 'status'">
                            <span class="cv-preview-state-badge"
                                  :class="'cv-preview-status-' + (row[col.field] || '').toLowerCase()"
                                  x-text="row[col.field]"></span>
                          </template>
                          <template x-if="col.field === 'charge_total' || col.field === 'tax_total' || col.field === 'total'">
                            <span class="cv-preview-money" x-text="row[col.field]"></span>
                          </template>
                          <template x-if="col.field !== 'state_name' && col.field !== 'state' && col.field !== 'status' && col.field !== 'charge_total' && col.field !== 'tax_total' && col.field !== 'total'">
                            <span x-text="row[col.field] || '—'"></span>
                          </template>
                        </td>
                      </template>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>

            {{-- Status bar --}}
            <div class="cv-status-bar">
              <div class="cv-status-item">
                Showing
                <span class="cv-status-value" x-text="activeView.columns.length"></span>
                columns
              </div>
              <div class="cv-status-sep"></div>
              <div class="cv-status-item">
                <span class="cv-status-value" x-text="activeView.filters ? activeView.filters.length : 0"></span>
                filters active
              </div>
              <div class="cv-status-sep"></div>
              <div class="cv-status-item">
                Sorted by
                <span class="cv-status-value" x-text="activeView.sort_column"></span>
                <span class="cv-status-value" x-text="activeView.sort_direction"></span>
              </div>
              <div class="cv-status-sep"></div>
              <div class="cv-status-item">
                <span class="cv-status-value" x-text="activeView.per_page || 20"></span>
                per page
              </div>
            </div>
          </div>
        </template>

      </div>
    </div>

  </div>
</div>

@verbatim
<script>
function customViewsExplorer() {

  /* ================================================================ */
  /*  ENTITY DEFINITIONS                                               */
  /* ================================================================ */
  const entityOptions = [
    { key: 'opportunities', label: 'Opportunities' },
    { key: 'members', label: 'Members' },
    { key: 'invoices', label: 'Invoices' },
    { key: 'products', label: 'Products' },
  ];

  const availableRoles = [
    { key: 'owner', label: 'Owner' },
    { key: 'admin', label: 'Admin' },
    { key: 'operations_manager', label: 'Operations Manager' },
    { key: 'sales', label: 'Sales' },
    { key: 'warehouse', label: 'Warehouse' },
    { key: 'read_only', label: 'Read Only' },
  ];

  const predicates = [
    { value: 'eq', label: 'eq' },
    { value: 'not_eq', label: 'not_eq' },
    { value: 'lt', label: 'lt' },
    { value: 'lteq', label: 'lteq' },
    { value: 'gt', label: 'gt' },
    { value: 'gteq', label: 'gteq' },
    { value: 'cont', label: 'cont' },
    { value: 'not_cont', label: 'not_cont' },
    { value: 'start', label: 'start' },
    { value: 'end', label: 'end' },
    { value: 'present', label: 'present' },
    { value: 'blank', label: 'blank' },
    { value: 'null', label: 'null' },
    { value: 'not_null', label: 'not_null' },
    { value: 'in', label: 'in' },
    { value: 'not_in', label: 'not_in' },
    { value: 'true', label: 'true' },
    { value: 'false', label: 'false' },
  ];

  /* ================================================================ */
  /*  AVAILABLE COLUMNS PER ENTITY                                     */
  /* ================================================================ */
  const columnDefs = {
    opportunities: [
      { group: 'Core', fields: [
        { key: 'id', label: 'ID' },
        { key: 'number', label: 'Number' },
        { key: 'subject', label: 'Subject' },
        { key: 'description', label: 'Description' },
        { key: 'state_name', label: 'State' },
        { key: 'status_name', label: 'Status' },
      ]},
      { group: 'Relationships', fields: [
        { key: 'member_name', label: 'Customer' },
        { key: 'store_name', label: 'Store' },
        { key: 'owner_name', label: 'Owner' },
        { key: 'venue_name', label: 'Venue' },
        { key: 'project_name', label: 'Project' },
      ]},
      { group: 'Dates', fields: [
        { key: 'starts_at', label: 'Starts At' },
        { key: 'ends_at', label: 'Ends At' },
        { key: 'created_at', label: 'Created At' },
        { key: 'updated_at', label: 'Updated At' },
      ]},
      { group: 'Financial', fields: [
        { key: 'charge_total', label: 'Charge Total' },
        { key: 'tax_total', label: 'Tax Total' },
        { key: 'tag_list', label: 'Tags' },
      ]},
      { group: 'Custom Fields', fields: [
        { key: 'cf.po_reference', label: 'PO Reference' },
        { key: 'cf.venue_setup_notes', label: 'Venue Setup Notes' },
        { key: 'cf.priority_level', label: 'Priority Level' },
      ]},
    ],
    members: [
      { group: 'Core', fields: [
        { key: 'id', label: 'ID' },
        { key: 'name', label: 'Name' },
        { key: 'membership_type', label: 'Type' },
        { key: 'email', label: 'Email' },
        { key: 'phone', label: 'Phone' },
        { key: 'active', label: 'Active' },
      ]},
      { group: 'Dates', fields: [
        { key: 'created_at', label: 'Created At' },
        { key: 'updated_at', label: 'Updated At' },
      ]},
    ],
    invoices: [
      { group: 'Core', fields: [
        { key: 'id', label: 'ID' },
        { key: 'number', label: 'Number' },
        { key: 'member_name', label: 'Customer' },
        { key: 'status', label: 'Status' },
        { key: 'total', label: 'Total' },
      ]},
      { group: 'Dates', fields: [
        { key: 'issued_at', label: 'Issued At' },
        { key: 'due_at', label: 'Due At' },
        { key: 'paid_at', label: 'Paid At' },
        { key: 'created_at', label: 'Created At' },
      ]},
    ],
    products: [
      { group: 'Core', fields: [
        { key: 'id', label: 'ID' },
        { key: 'name', label: 'Name' },
        { key: 'sku', label: 'SKU' },
        { key: 'category', label: 'Category' },
        { key: 'rate', label: 'Rate' },
        { key: 'stock_level', label: 'Stock Level' },
      ]},
      { group: 'Dates', fields: [
        { key: 'created_at', label: 'Created At' },
        { key: 'updated_at', label: 'Updated At' },
      ]},
    ],
  };

  /* ================================================================ */
  /*  VIEW DEFINITIONS PER ENTITY                                      */
  /* ================================================================ */
  const viewDefs = {
    opportunities: {
      system: [
        {
          id: 'opp-sys-1', name: 'All Opportunities', visibility: 'system', is_default: true,
          columns: [
            { field: 'number', label: '#', width: '90' },
            { field: 'subject', label: 'Subject', width: 'auto' },
            { field: 'member_name', label: 'Customer', width: 'auto' },
            { field: 'state_name', label: 'State', width: '100' },
            { field: 'starts_at', label: 'Starts', width: '120' },
            { field: 'charge_total', label: 'Total', width: '120' },
          ],
          filters: [],
          sort_column: 'created_at', sort_direction: 'desc', per_page: '20',
        },
        {
          id: 'opp-sys-2', name: 'Active Orders', visibility: 'system', is_default: false,
          columns: [
            { field: 'number', label: '#', width: '90' },
            { field: 'subject', label: 'Subject', width: 'auto' },
            { field: 'member_name', label: 'Customer', width: 'auto' },
            { field: 'store_name', label: 'Store', width: '120' },
            { field: 'starts_at', label: 'Starts', width: '120' },
            { field: 'ends_at', label: 'Ends', width: '120' },
            { field: 'charge_total', label: 'Total', width: '120' },
          ],
          filters: [{ field: 'state_name', predicate: 'eq', value: 'Order' }],
          sort_column: 'starts_at', sort_direction: 'asc', per_page: '20',
        },
        {
          id: 'opp-sys-3', name: 'Open Quotes', visibility: 'system', is_default: false,
          columns: [
            { field: 'number', label: '#', width: '90' },
            { field: 'subject', label: 'Subject', width: 'auto' },
            { field: 'member_name', label: 'Customer', width: 'auto' },
            { field: 'owner_name', label: 'Owner', width: '120' },
            { field: 'charge_total', label: 'Total', width: '120' },
            { field: 'created_at', label: 'Created', width: '120' },
          ],
          filters: [{ field: 'state_name', predicate: 'eq', value: 'Quotation' }],
          sort_column: 'created_at', sort_direction: 'desc', per_page: '20',
        },
        {
          id: 'opp-sys-4', name: 'Dispatching This Week', visibility: 'system', is_default: false,
          columns: [
            { field: 'number', label: '#', width: '90' },
            { field: 'subject', label: 'Subject', width: 'auto' },
            { field: 'member_name', label: 'Customer', width: 'auto' },
            { field: 'store_name', label: 'Store', width: '120' },
            { field: 'starts_at', label: 'Starts', width: '120' },
          ],
          filters: [
            { field: 'state_name', predicate: 'eq', value: 'Order' },
            { field: 'starts_at', predicate: 'lteq', value: '2026-03-08' },
          ],
          sort_column: 'starts_at', sort_direction: 'asc', per_page: '20',
        },
        {
          id: 'opp-sys-5', name: 'High Value', visibility: 'system', is_default: false,
          columns: [
            { field: 'number', label: '#', width: '90' },
            { field: 'subject', label: 'Subject', width: 'auto' },
            { field: 'member_name', label: 'Customer', width: 'auto' },
            { field: 'charge_total', label: 'Total', width: '120' },
            { field: 'store_name', label: 'Store', width: '120' },
          ],
          filters: [{ field: 'charge_total', predicate: 'gteq', value: '5000' }],
          sort_column: 'charge_total', sort_direction: 'desc', per_page: '20',
        },
      ],
      personal: [
        {
          id: 'opp-per-1', name: 'My London Orders', visibility: 'personal', is_default: false,
          columns: [
            { field: 'number', label: '#', width: '90' },
            { field: 'subject', label: 'Subject', width: 'auto' },
            { field: 'member_name', label: 'Customer', width: 'auto' },
            { field: 'starts_at', label: 'Starts', width: '120' },
            { field: 'ends_at', label: 'Ends', width: '120' },
            { field: 'charge_total', label: 'Total', width: '120' },
            { field: 'cf.po_reference', label: 'PO Ref', width: '100' },
          ],
          filters: [
            { field: 'store_id', predicate: 'eq', value: '1' },
            { field: 'owner_id', predicate: 'eq', value: '1' },
          ],
          sort_column: 'starts_at', sort_direction: 'asc', per_page: '20',
        },
        {
          id: 'opp-per-2', name: 'Needs PO Reference', visibility: 'personal', is_default: false,
          columns: [
            { field: 'number', label: '#', width: '90' },
            { field: 'subject', label: 'Subject', width: 'auto' },
            { field: 'member_name', label: 'Customer', width: 'auto' },
            { field: 'charge_total', label: 'Total', width: '120' },
            { field: 'cf.po_reference', label: 'PO Ref', width: '100' },
          ],
          filters: [
            { field: 'cf.po_reference', predicate: 'blank', value: '1' },
            { field: 'state_name', predicate: 'eq', value: 'Order' },
          ],
          sort_column: 'number', sort_direction: 'asc', per_page: '20',
        },
      ],
    },
    members: {
      system: [
        {
          id: 'mem-sys-1', name: 'All Members', visibility: 'system', is_default: true,
          columns: [
            { field: 'name', label: 'Name', width: 'auto' },
            { field: 'membership_type', label: 'Type', width: '120' },
            { field: 'email', label: 'Email', width: 'auto' },
            { field: 'phone', label: 'Phone', width: '140' },
            { field: 'created_at', label: 'Created', width: '120' },
          ],
          filters: [],
          sort_column: 'name', sort_direction: 'asc', per_page: '20',
        },
        {
          id: 'mem-sys-2', name: 'Organisations Only', visibility: 'system', is_default: false,
          columns: [
            { field: 'name', label: 'Name', width: 'auto' },
            { field: 'membership_type', label: 'Type', width: '120' },
            { field: 'email', label: 'Email', width: 'auto' },
            { field: 'phone', label: 'Phone', width: '140' },
            { field: 'created_at', label: 'Created', width: '120' },
          ],
          filters: [{ field: 'membership_type', predicate: 'eq', value: 'Organisation' }],
          sort_column: 'name', sort_direction: 'asc', per_page: '20',
        },
        {
          id: 'mem-sys-3', name: 'Active Contacts', visibility: 'system', is_default: false,
          columns: [
            { field: 'name', label: 'Name', width: 'auto' },
            { field: 'membership_type', label: 'Type', width: '120' },
            { field: 'email', label: 'Email', width: 'auto' },
            { field: 'phone', label: 'Phone', width: '140' },
            { field: 'created_at', label: 'Created', width: '120' },
          ],
          filters: [
            { field: 'membership_type', predicate: 'eq', value: 'Contact' },
            { field: 'active', predicate: 'true', value: '' },
          ],
          sort_column: 'name', sort_direction: 'asc', per_page: '20',
        },
      ],
      personal: [],
    },
    invoices: {
      system: [
        {
          id: 'inv-sys-1', name: 'All Invoices', visibility: 'system', is_default: true,
          columns: [
            { field: 'number', label: '#', width: '90' },
            { field: 'member_name', label: 'Customer', width: 'auto' },
            { field: 'status', label: 'Status', width: '100' },
            { field: 'total', label: 'Total', width: '120' },
            { field: 'issued_at', label: 'Issued', width: '120' },
            { field: 'due_at', label: 'Due', width: '120' },
          ],
          filters: [],
          sort_column: 'issued_at', sort_direction: 'desc', per_page: '20',
        },
        {
          id: 'inv-sys-2', name: 'Unpaid / Outstanding', visibility: 'system', is_default: false,
          columns: [
            { field: 'number', label: '#', width: '90' },
            { field: 'member_name', label: 'Customer', width: 'auto' },
            { field: 'status', label: 'Status', width: '100' },
            { field: 'total', label: 'Total', width: '120' },
            { field: 'issued_at', label: 'Issued', width: '120' },
            { field: 'due_at', label: 'Due', width: '120' },
          ],
          filters: [{ field: 'status', predicate: 'eq', value: 'Unpaid' }],
          sort_column: 'due_at', sort_direction: 'asc', per_page: '20',
        },
        {
          id: 'inv-sys-3', name: 'Overdue', visibility: 'system', is_default: false,
          columns: [
            { field: 'number', label: '#', width: '90' },
            { field: 'member_name', label: 'Customer', width: 'auto' },
            { field: 'status', label: 'Status', width: '100' },
            { field: 'total', label: 'Total', width: '120' },
            { field: 'issued_at', label: 'Issued', width: '120' },
            { field: 'due_at', label: 'Due', width: '120' },
          ],
          filters: [{ field: 'status', predicate: 'eq', value: 'Overdue' }],
          sort_column: 'due_at', sort_direction: 'asc', per_page: '20',
        },
      ],
      personal: [],
    },
    products: {
      system: [
        {
          id: 'prod-sys-1', name: 'All Products', visibility: 'system', is_default: true,
          columns: [
            { field: 'name', label: 'Name', width: 'auto' },
            { field: 'sku', label: 'SKU', width: '120' },
            { field: 'category', label: 'Category', width: '140' },
            { field: 'rate', label: 'Rate', width: '100' },
            { field: 'stock_level', label: 'Stock', width: '80' },
            { field: 'created_at', label: 'Created', width: '120' },
          ],
          filters: [],
          sort_column: 'name', sort_direction: 'asc', per_page: '20',
        },
      ],
      personal: [],
    },
  };

  /* ================================================================ */
  /*  MOCK TABLE DATA PER ENTITY                                       */
  /* ================================================================ */
  const mockData = {
    opportunities: [
      { id: 42, number: '0000042', subject: 'LED Wall Hire — Summer Festival', description: 'Full LED wall package for main stage', state_name: 'Order', status_name: 'Confirmed', member_name: 'Acme Events Ltd', store_name: 'London', owner_name: 'Ben Carter', venue_name: 'Victoria Park', project_name: 'Summer Fest 2026', starts_at: '15 Mar 2026', ends_at: '17 Mar 2026', charge_total: '\u00a315,990.00', tax_total: '\u00a33,198.00', tag_list: 'festival, outdoor', created_at: '02 Feb 2026', updated_at: '10 Feb 2026', 'cf.po_reference': 'PO-2026-0412', 'cf.venue_setup_notes': 'Load-in via Gate B', 'cf.priority_level': 'High' },
      { id: 41, number: '0000041', subject: 'PA System — Corporate Awards', description: 'Line array PA system for awards ceremony', state_name: 'Quotation', status_name: 'Provisional', member_name: 'MediaTech Solutions', store_name: 'Manchester', owner_name: 'Sarah Hughes', venue_name: 'Hilton Deansgate', project_name: '', starts_at: '22 Mar 2026', ends_at: '22 Mar 2026', charge_total: '\u00a34,250.00', tax_total: '\u00a3850.00', tag_list: 'corporate', created_at: '18 Feb 2026', updated_at: '20 Feb 2026', 'cf.po_reference': '', 'cf.venue_setup_notes': 'Ballroom, ground-floor access', 'cf.priority_level': 'Medium' },
      { id: 40, number: '0000040', subject: 'Lighting Rig — Theatre Production', description: 'Intelligent lighting rig for 3-week run', state_name: 'Order', status_name: 'Confirmed', member_name: 'Westfield Theatre Co', store_name: 'London', owner_name: 'Ben Carter', venue_name: 'Lyric Theatre', project_name: 'Spring Season', starts_at: '10 Mar 2026', ends_at: '30 Mar 2026', charge_total: '\u00a38,750.00', tax_total: '\u00a31,750.00', tag_list: 'theatre, lighting', created_at: '01 Feb 2026', updated_at: '05 Feb 2026', 'cf.po_reference': 'PO-WT-889', 'cf.venue_setup_notes': '', 'cf.priority_level': 'High' },
      { id: 39, number: '0000039', subject: 'AV Package — Conference', description: 'Full AV setup for 2-day conference', state_name: 'Order', status_name: 'Confirmed', member_name: 'Global Finance Group', store_name: 'London', owner_name: 'James Mitchell', venue_name: 'ExCeL London', project_name: '', starts_at: '18 Mar 2026', ends_at: '19 Mar 2026', charge_total: '\u00a312,400.00', tax_total: '\u00a32,480.00', tag_list: 'conference, AV', created_at: '25 Jan 2026', updated_at: '08 Feb 2026', 'cf.po_reference': 'GFG-Q1-2026', 'cf.venue_setup_notes': 'Hall 3, dock access', 'cf.priority_level': 'Medium' },
      { id: 38, number: '0000038', subject: 'DJ Setup — Wedding Reception', description: 'DJ booth, speakers, and lighting', state_name: 'Quotation', status_name: 'Provisional', member_name: 'Private Client', store_name: 'Bristol', owner_name: 'Sarah Hughes', venue_name: 'The Orangery', project_name: '', starts_at: '28 Mar 2026', ends_at: '28 Mar 2026', charge_total: '\u00a31,850.00', tax_total: '\u00a3370.00', tag_list: 'wedding', created_at: '20 Feb 2026', updated_at: '22 Feb 2026', 'cf.po_reference': '', 'cf.venue_setup_notes': 'Garden marquee', 'cf.priority_level': 'Low' },
    ],
    members: [
      { id: 1, name: 'Acme Events Ltd', membership_type: 'Organisation', email: 'info@acmeevents.co.uk', phone: '+44 20 7946 0958', active: 'Yes', created_at: '15 Jan 2025', updated_at: '02 Feb 2026' },
      { id: 2, name: 'MediaTech Solutions', membership_type: 'Organisation', email: 'hello@mediatech.io', phone: '+44 161 496 0738', active: 'Yes', created_at: '03 Mar 2025', updated_at: '18 Feb 2026' },
      { id: 3, name: 'Ben Carter', membership_type: 'Contact', email: 'ben@acmeevents.co.uk', phone: '+44 7700 900123', active: 'Yes', created_at: '15 Jan 2025', updated_at: '10 Feb 2026' },
      { id: 4, name: 'Sarah Hughes', membership_type: 'Contact', email: 'sarah@mediatech.io', phone: '+44 7700 900456', active: 'Yes', created_at: '03 Mar 2025', updated_at: '20 Feb 2026' },
      { id: 5, name: 'Westfield Theatre Co', membership_type: 'Organisation', email: 'bookings@westfieldtheatre.com', phone: '+44 20 7123 4567', active: 'Yes', created_at: '10 Nov 2024', updated_at: '01 Feb 2026' },
    ],
    invoices: [
      { id: 1, number: 'INV-0001', member_name: 'Acme Events Ltd', status: 'Paid', total: '\u00a315,990.00', issued_at: '10 Feb 2026', due_at: '10 Mar 2026', paid_at: '25 Feb 2026', created_at: '10 Feb 2026' },
      { id: 2, number: 'INV-0002', member_name: 'Westfield Theatre Co', status: 'Unpaid', total: '\u00a38,750.00', issued_at: '05 Feb 2026', due_at: '05 Mar 2026', paid_at: '', created_at: '05 Feb 2026' },
      { id: 3, number: 'INV-0003', member_name: 'Global Finance Group', status: 'Unpaid', total: '\u00a312,400.00', issued_at: '08 Feb 2026', due_at: '08 Mar 2026', paid_at: '', created_at: '08 Feb 2026' },
      { id: 4, number: 'INV-0004', member_name: 'MediaTech Solutions', status: 'Overdue', total: '\u00a33,200.00', issued_at: '15 Jan 2026', due_at: '14 Feb 2026', paid_at: '', created_at: '15 Jan 2026' },
      { id: 5, number: 'INV-0005', member_name: 'Private Client', status: 'Paid', total: '\u00a31,850.00', issued_at: '22 Feb 2026', due_at: '22 Mar 2026', paid_at: '01 Mar 2026', created_at: '22 Feb 2026' },
    ],
    products: [
      { id: 1, name: 'Martin Audio WPL Line Array', sku: 'MA-WPL-001', category: 'Sound', rate: '\u00a3250.00', stock_level: '12', created_at: '01 Jan 2025', updated_at: '15 Feb 2026' },
      { id: 2, name: 'Robe BMFL Spot', sku: 'RB-BMFL-001', category: 'Lighting', rate: '\u00a375.00', stock_level: '24', created_at: '01 Jan 2025', updated_at: '10 Jan 2026' },
      { id: 3, name: 'ROE Visual CB5 LED Panel', sku: 'ROE-CB5-001', category: 'Video', rate: '\u00a345.00', stock_level: '120', created_at: '15 Mar 2025', updated_at: '20 Feb 2026' },
      { id: 4, name: 'Shure ULXD Wireless Mic', sku: 'SH-ULXD-001', category: 'Sound', rate: '\u00a335.00', stock_level: '30', created_at: '01 Feb 2025', updated_at: '18 Feb 2026' },
      { id: 5, name: 'MDG ATMe Haze Machine', sku: 'MDG-ATME-001', category: 'Effects', rate: '\u00a3120.00', stock_level: '6', created_at: '20 May 2025', updated_at: '01 Mar 2026' },
    ],
  };

  /* ================================================================ */
  /*  NEXT ID COUNTER                                                  */
  /* ================================================================ */
  let nextId = 100;

  /* ================================================================ */
  /*  COMPONENT STATE                                                  */
  /* ================================================================ */
  return {
    currentEntity: 'opportunities',
    viewSearchQuery: '',
    selectedViewId: null,
    isEditing: false,
    showColumnDropdown: false,
    entityOptions,
    availableRoles,
    predicates,

    // Deep-clone of the view being edited
    editingView: null,

    /* ============================================================== */
    /*  COMPUTED                                                       */
    /* ============================================================== */
    get currentEntityLabel() {
      const opt = entityOptions.find(e => e.key === this.currentEntity);
      return opt ? opt.label : '';
    },

    get currentViews() {
      return viewDefs[this.currentEntity] || { system: [], personal: [] };
    },

    get filteredSystemViews() {
      const q = this.viewSearchQuery.toLowerCase().trim();
      const views = this.currentViews.system || [];
      if (!q) return views;
      return views.filter(v => v.name.toLowerCase().includes(q));
    },

    get filteredPersonalViews() {
      const q = this.viewSearchQuery.toLowerCase().trim();
      const views = this.currentViews.personal || [];
      if (!q) return views;
      return views.filter(v => v.name.toLowerCase().includes(q));
    },

    get allViews() {
      return [...(this.currentViews.system || []), ...(this.currentViews.personal || [])];
    },

    get activeView() {
      if (this.isEditing) return this.editingView;
      if (!this.selectedViewId) return null;
      return this.allViews.find(v => v.id === this.selectedViewId) || null;
    },

    get availableColumnGroups() {
      return columnDefs[this.currentEntity] || [];
    },

    get allAvailableFields() {
      const groups = columnDefs[this.currentEntity] || [];
      const fields = [];
      groups.forEach(g => {
        g.fields.forEach(f => fields.push(f));
      });
      return fields;
    },

    get previewData() {
      return mockData[this.currentEntity] || [];
    },

    /* ============================================================== */
    /*  METHODS                                                        */
    /* ============================================================== */
    init() {
      // Select the default view on load
      const defaultView = this.allViews.find(v => v.is_default);
      if (defaultView) {
        this.selectedViewId = defaultView.id;
      }
    },

    onEntityChange() {
      this.selectedViewId = null;
      this.isEditing = false;
      this.editingView = null;
      this.viewSearchQuery = '';
      this.showColumnDropdown = false;
      this.$nextTick(() => {
        const defaultView = this.allViews.find(v => v.is_default);
        if (defaultView) {
          this.selectedViewId = defaultView.id;
        }
      });
    },

    selectView(view) {
      this.selectedViewId = view.id;
      this.isEditing = false;
      this.editingView = null;
      this.showColumnDropdown = false;
    },

    createNewView() {
      this.selectedViewId = null;
      this.editingView = {
        id: null,
        name: '',
        visibility: 'personal',
        is_default: false,
        roles: [],
        columns: [],
        filters: [],
        sort_column: 'created_at',
        sort_direction: 'desc',
        per_page: '20',
      };
      this.isEditing = true;
      this.showColumnDropdown = false;
    },

    editView(view) {
      this.selectedViewId = view.id;
      this.editingView = JSON.parse(JSON.stringify(view));
      if (!this.editingView.roles) this.editingView.roles = [];
      this.isEditing = true;
      this.showColumnDropdown = false;
    },

    deleteView(view) {
      const personal = viewDefs[this.currentEntity].personal;
      const idx = personal.findIndex(v => v.id === view.id);
      if (idx !== -1) {
        personal.splice(idx, 1);
      }
      if (this.selectedViewId === view.id) {
        this.selectedViewId = null;
        this.isEditing = false;
        this.editingView = null;
      }
    },

    cancelEditing() {
      this.isEditing = false;
      this.editingView = null;
    },

    saveView() {
      if (!this.editingView.name.trim()) return;

      if (this.editingView.id) {
        // Update existing view
        const personal = viewDefs[this.currentEntity].personal;
        const idx = personal.findIndex(v => v.id === this.editingView.id);
        if (idx !== -1) {
          personal[idx] = JSON.parse(JSON.stringify(this.editingView));
        }
        this.selectedViewId = this.editingView.id;
      } else {
        // Create new view
        this.editingView.id = 'custom-' + (++nextId);
        viewDefs[this.currentEntity].personal.push(JSON.parse(JSON.stringify(this.editingView)));
        this.selectedViewId = this.editingView.id;
      }

      this.isEditing = false;
      this.editingView = null;
    },

    toggleRole(roleKey) {
      if (!this.editingView.roles) this.editingView.roles = [];
      const idx = this.editingView.roles.indexOf(roleKey);
      if (idx === -1) {
        this.editingView.roles.push(roleKey);
      } else {
        this.editingView.roles.splice(idx, 1);
      }
    },

    /* Column methods */
    isColumnSelected(fieldKey) {
      if (!this.editingView) return false;
      return this.editingView.columns.some(c => c.field === fieldKey);
    },

    addColumn(field) {
      if (this.isColumnSelected(field.key)) return;
      this.editingView.columns.push({
        field: field.key,
        label: field.label,
        width: 'auto',
      });
      this.showColumnDropdown = false;
    },

    removeColumn(idx) {
      this.editingView.columns.splice(idx, 1);
    },

    moveColumn(idx, direction) {
      const newIdx = idx + direction;
      if (newIdx < 0 || newIdx >= this.editingView.columns.length) return;
      const cols = this.editingView.columns;
      const temp = cols[idx];
      cols[idx] = cols[newIdx];
      cols[newIdx] = temp;
      // Force reactivity
      this.editingView.columns = [...cols];
    },

    /* Filter methods */
    addFilter() {
      this.editingView.filters.push({
        field: '',
        predicate: 'eq',
        value: '',
      });
    },

    removeFilter(idx) {
      this.editingView.filters.splice(idx, 1);
    },

    /* Preview methods */
    removePreviewFilter(idx) {
      if (this.isEditing && this.editingView) {
        this.editingView.filters.splice(idx, 1);
      }
    },

    togglePreviewSort(field) {
      if (!this.activeView) return;
      const view = this.isEditing ? this.editingView : this.activeView;
      if (view.sort_column === field) {
        view.sort_direction = view.sort_direction === 'asc' ? 'desc' : 'asc';
      } else {
        view.sort_column = field;
        view.sort_direction = 'asc';
      }
    },
  };
}
</script>
@endverbatim
