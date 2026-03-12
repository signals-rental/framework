<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Permissions & Authorisation')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  PERMISSIONS ADMIN TOKENS — maps to brand system in app.css       */
  /* ================================================================ */
  :root {
    --pa-bg: var(--content-bg);
    --pa-panel: var(--card-bg);
    --pa-surface: var(--base);
    --pa-border: var(--card-border);
    --pa-border-subtle: var(--grey-border);
    --pa-text: var(--text-primary);
    --pa-text-secondary: var(--text-secondary);
    --pa-text-muted: var(--text-muted);
    --pa-accent: var(--green);
    --pa-accent-dim: var(--green-muted);
    --pa-hover: rgba(0, 0, 0, 0.03);
    --pa-shadow: var(--shadow-card);
    --pa-shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.08);
    --pa-blue: var(--blue);
    --pa-blue-bg: rgba(37, 99, 235, 0.08);
    --pa-green: var(--green);
    --pa-green-bg: rgba(5, 150, 105, 0.08);
    --pa-red: var(--red);
    --pa-red-bg: rgba(220, 38, 38, 0.06);
    --pa-amber: var(--amber);
    --pa-amber-bg: rgba(217, 119, 6, 0.08);
    --pa-violet: var(--violet);
    --pa-violet-bg: rgba(124, 58, 237, 0.08);
    --pa-sky: var(--sky);
    --pa-cyan: var(--cyan);
    --pa-rose: var(--rose);
    --pa-layer-area: var(--blue);
    --pa-layer-area-bg: rgba(37, 99, 235, 0.08);
    --pa-layer-action: var(--green);
    --pa-layer-action-bg: rgba(5, 150, 105, 0.08);
    --pa-layer-field: var(--amber);
    --pa-layer-field-bg: rgba(217, 119, 6, 0.08);
    --pa-toggle-on: var(--green);
    --pa-toggle-off: var(--grey-border);
    --pa-checkbox-bg: var(--white);
    --pa-group-header-bg: var(--table-header-bg);
    --pa-row-hover: var(--table-row-hover);
    --pa-table-border: var(--table-border);
    --pa-status-active: var(--green);
    --pa-status-invited: var(--amber);
    --pa-status-deactivated: var(--red);
    --pa-flash-bg: rgba(5, 150, 105, 0.15);
  }

  .dark {
    --pa-bg: var(--content-bg);
    --pa-panel: var(--card-bg);
    --pa-surface: var(--navy-mid);
    --pa-border: var(--card-border);
    --pa-border-subtle: #283040;
    --pa-text: var(--text-primary);
    --pa-text-secondary: var(--text-secondary);
    --pa-text-muted: var(--text-muted);
    --pa-accent: var(--green);
    --pa-accent-dim: rgba(5, 150, 105, 0.12);
    --pa-hover: rgba(255, 255, 255, 0.04);
    --pa-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --pa-shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.4);
    --pa-blue-bg: rgba(37, 99, 235, 0.15);
    --pa-green-bg: rgba(5, 150, 105, 0.12);
    --pa-red-bg: rgba(220, 38, 38, 0.12);
    --pa-amber-bg: rgba(217, 119, 6, 0.12);
    --pa-violet-bg: rgba(124, 58, 237, 0.15);
    --pa-layer-area-bg: rgba(37, 99, 235, 0.15);
    --pa-layer-action-bg: rgba(5, 150, 105, 0.15);
    --pa-layer-field-bg: rgba(217, 119, 6, 0.15);
    --pa-toggle-off: #374151;
    --pa-checkbox-bg: var(--navy-mid);
    --pa-group-header-bg: var(--table-header-bg);
    --pa-row-hover: var(--table-row-hover);
    --pa-table-border: var(--table-border);
    --pa-flash-bg: rgba(5, 150, 105, 0.25);
  }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes paFadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes paFlash {
    0% { background-color: transparent; }
    30% { background-color: var(--pa-flash-bg); }
    100% { background-color: transparent; }
  }

  @keyframes paPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
  }

  @keyframes paSlideIn {
    from { opacity: 0; transform: translateX(-8px); }
    to { opacity: 1; transform: translateX(0); }
  }

  @keyframes paCheckPop {
    0% { transform: scale(0.8); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */
  .pa-page {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 64px);
    background: var(--pa-bg);
    position: relative;
    font-family: var(--font-mono);
    font-size: 12px;
    line-height: 1.6;
    color: var(--pa-text);
    -webkit-font-smoothing: antialiased;
    padding: 24px;
  }

  .pa-container {
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
  }

  /* ================================================================ */
  /*  HEADER                                                           */
  /* ================================================================ */
  .pa-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
  }

  .pa-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .pa-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    color: var(--pa-text);
    letter-spacing: -0.01em;
    line-height: 1;
  }

  .pa-subtitle {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--pa-accent);
    margin-top: 2px;
  }

  /* ================================================================ */
  /*  TAB BAR                                                          */
  /* ================================================================ */
  .pa-tabs {
    display: flex;
    gap: 2px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--pa-border);
  }

  .pa-tab {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    padding: 10px 20px;
    color: var(--pa-text-muted);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.15s;
    margin-bottom: -1px;
  }

  .pa-tab:hover { color: var(--pa-text); }

  .pa-tab.active {
    color: var(--pa-accent);
    border-bottom-color: var(--pa-accent);
  }

  /* ================================================================ */
  /*  ROLES LAYOUT                                                     */
  /* ================================================================ */
  .pa-roles-layout {
    display: flex;
    gap: 20px;
    align-items: flex-start;
  }

  .pa-role-sidebar {
    width: 260px;
    flex-shrink: 0;
    background: var(--pa-panel);
    border: 1px solid var(--pa-border);
    overflow: hidden;
  }

  .pa-role-sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--pa-border);
    background: var(--pa-group-header-bg);
  }

  .pa-role-sidebar-title {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--pa-text-muted);
  }

  .pa-role-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    cursor: pointer;
    border-bottom: 1px solid var(--pa-border);
    transition: background 0.15s;
  }

  .pa-role-item:last-child { border-bottom: none; }
  .pa-role-item:hover { background: var(--pa-hover); }

  .pa-role-item.active {
    background: var(--pa-accent);
    color: #ffffff;
  }

  .pa-role-item.active .pa-role-name { color: #ffffff; }
  .pa-role-item.active .pa-role-meta { color: rgba(255, 255, 255, 0.7); }
  .pa-role-item.active .pa-role-user-count { background: rgba(255, 255, 255, 0.2); color: #ffffff; }
  .pa-role-item.active .pa-role-system-badge { background: rgba(255, 255, 255, 0.2); color: #ffffff; }

  .pa-role-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
  }

  .pa-role-name {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    color: var(--pa-text);
    line-height: 1.2;
  }

  .pa-role-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    color: var(--pa-text-muted);
  }

  .pa-role-user-count {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    padding: 1px 6px;
    background: var(--pa-blue-bg);
    color: var(--pa-blue);
    border-radius: 10px;
  }

  .pa-role-system-badge {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 1px 5px;
    background: var(--pa-amber-bg);
    color: var(--pa-amber);
    border-radius: 2px;
  }

  /* ================================================================ */
  /*  ROLE EDITOR (main area)                                          */
  /* ================================================================ */
  .pa-role-editor {
    flex: 1;
    min-width: 0;
    animation: paFadeIn 0.2s ease-out;
  }

  .pa-role-editor-header {
    background: var(--pa-panel);
    border: 1px solid var(--pa-border);
    padding: 20px 24px;
    margin-bottom: 16px;
  }

  .pa-role-editor-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 16px;
  }

  .pa-role-fields {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .pa-field-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .pa-field-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--pa-text-muted);
  }

  .pa-field-input {
    font-family: var(--font-mono);
    font-size: 13px;
    padding: 8px 12px;
    background: var(--pa-surface);
    border: 1px solid var(--pa-border);
    color: var(--pa-text);
    width: 100%;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .pa-field-input:focus {
    outline: none;
    border-color: var(--pa-accent);
    box-shadow: 0 0 0 2px var(--pa-accent-dim);
  }

  .pa-field-input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  .pa-field-textarea {
    font-family: var(--font-mono);
    font-size: 12px;
    padding: 8px 12px;
    background: var(--pa-surface);
    border: 1px solid var(--pa-border);
    color: var(--pa-text);
    width: 100%;
    min-height: 60px;
    resize: vertical;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .pa-field-textarea:focus {
    outline: none;
    border-color: var(--pa-accent);
    box-shadow: 0 0 0 2px var(--pa-accent-dim);
  }

  /* ================================================================ */
  /*  TOGGLE CARDS                                                     */
  /* ================================================================ */
  .pa-toggle-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
  }

  .pa-toggle-card {
    background: var(--pa-surface);
    border: 1px solid var(--pa-border);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .pa-toggle-card:hover {
    border-color: var(--pa-border-subtle);
    box-shadow: var(--pa-shadow);
  }

  .pa-toggle-card-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .pa-toggle-card-label {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--pa-text);
  }

  .pa-toggle-card-desc {
    font-size: 10px;
    color: var(--pa-text-muted);
  }

  .pa-toggle {
    position: relative;
    width: 36px;
    height: 20px;
    flex-shrink: 0;
    cursor: pointer;
  }

  .pa-toggle input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
  }

  .pa-toggle-track {
    position: absolute;
    inset: 0;
    background: var(--pa-toggle-off);
    border-radius: 10px;
    transition: background 0.2s;
  }

  .pa-toggle input:checked + .pa-toggle-track {
    background: var(--pa-toggle-on);
  }

  .pa-toggle-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    background: #ffffff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
  }

  .pa-toggle input:checked ~ .pa-toggle-thumb {
    transform: translateX(16px);
  }

  .pa-system-indicator {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 3px 8px;
    background: var(--pa-amber-bg);
    color: var(--pa-amber);
    border-radius: 2px;
  }

  .pa-owner-indicator {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 3px 8px;
    background: var(--pa-violet-bg);
    color: var(--pa-violet);
    border-radius: 2px;
  }

  /* ================================================================ */
  /*  PERMISSION MATRIX                                                */
  /* ================================================================ */
  .pa-matrix {
    background: var(--pa-panel);
    border: 1px solid var(--pa-border);
    overflow: hidden;
  }

  .pa-matrix-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--pa-border);
    background: var(--pa-group-header-bg);
  }

  .pa-matrix-title {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 700;
    color: var(--pa-text);
  }

  .pa-matrix-stats {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .pa-matrix-stat {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    color: var(--pa-text-muted);
  }

  .pa-matrix-stat strong {
    color: var(--pa-accent);
    font-weight: 700;
  }

  .pa-group-section {
    border-bottom: 1px solid var(--pa-border);
  }

  .pa-group-section:last-child { border-bottom: none; }

  .pa-group-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: var(--pa-group-header-bg);
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
  }

  .pa-group-header:hover { background: var(--pa-hover); }

  .pa-group-chevron {
    width: 16px;
    height: 16px;
    color: var(--pa-text-muted);
    transition: transform 0.2s;
    flex-shrink: 0;
  }

  .pa-group-chevron.collapsed { transform: rotate(-90deg); }

  .pa-group-name {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 700;
    color: var(--pa-text);
    flex: 1;
  }

  .pa-group-count {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    background: var(--pa-accent-dim);
    color: var(--pa-accent);
    border-radius: 10px;
  }

  .pa-group-actions {
    display: flex;
    gap: 4px;
  }

  .pa-group-action-btn {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 3px 8px;
    background: transparent;
    border: 1px solid var(--pa-border);
    color: var(--pa-text-muted);
    cursor: pointer;
    transition: all 0.15s;
  }

  .pa-group-action-btn:hover {
    border-color: var(--pa-accent);
    color: var(--pa-accent);
  }

  /* ================================================================ */
  /*  PERMISSION ROWS                                                  */
  /* ================================================================ */
  .pa-perm-grid {
    display: flex;
    flex-direction: column;
  }

  .pa-perm-row {
    display: grid;
    grid-template-columns: 24px 260px 1fr 40px;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    border-bottom: 1px solid var(--pa-table-border);
    transition: background 0.15s;
  }

  .pa-perm-row:last-child { border-bottom: none; }
  .pa-perm-row:hover { background: var(--pa-row-hover); }

  .pa-perm-row.flashing {
    animation: paFlash 0.6s ease-out;
  }

  .pa-layer-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .pa-layer-dot.area { background: var(--pa-layer-area); }
  .pa-layer-dot.action { background: var(--pa-layer-action); }
  .pa-layer-dot.field { background: var(--pa-layer-field); }

  .pa-perm-key {
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 500;
    color: var(--pa-text);
  }

  .pa-perm-desc {
    font-size: 11px;
    color: var(--pa-text-muted);
  }

  .pa-perm-check {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .pa-checkbox {
    position: relative;
    width: 18px;
    height: 18px;
    cursor: pointer;
  }

  .pa-checkbox input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
  }

  .pa-checkbox-mark {
    position: absolute;
    inset: 0;
    background: var(--pa-checkbox-bg);
    border: 1.5px solid var(--pa-border-subtle);
    border-radius: 3px;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .pa-checkbox-mark svg {
    width: 12px;
    height: 12px;
    color: #ffffff;
    opacity: 0;
    transition: opacity 0.15s;
  }

  .pa-checkbox input:checked + .pa-checkbox-mark {
    background: var(--pa-accent);
    border-color: var(--pa-accent);
  }

  .pa-checkbox input:checked + .pa-checkbox-mark svg {
    opacity: 1;
    animation: paCheckPop 0.2s ease-out;
  }

  .pa-checkbox.disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }

  /* ================================================================ */
  /*  LEGEND                                                           */
  /* ================================================================ */
  .pa-legend {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 10px 16px;
    border-top: 1px solid var(--pa-border);
    background: var(--pa-group-header-bg);
  }

  .pa-legend-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--pa-text-muted);
  }

  .pa-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    color: var(--pa-text-secondary);
  }

  /* ================================================================ */
  /*  USERS TABLE                                                      */
  /* ================================================================ */
  .pa-users-panel {
    background: var(--pa-panel);
    border: 1px solid var(--pa-border);
    overflow: hidden;
    animation: paFadeIn 0.2s ease-out;
  }

  .pa-users-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--pa-border);
    background: var(--pa-group-header-bg);
  }

  .pa-users-title {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 700;
    color: var(--pa-text);
  }

  .pa-btn {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    padding: 7px 14px;
    border: 1px solid var(--pa-border);
    background: var(--pa-panel);
    color: var(--pa-text-secondary);
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .pa-btn:hover {
    border-color: var(--pa-accent);
    color: var(--pa-text);
  }

  .pa-btn-primary {
    background: var(--pa-accent);
    color: #ffffff;
    border-color: var(--pa-accent);
  }

  .pa-btn-primary:hover {
    background: var(--pa-accent);
    filter: brightness(1.1);
    color: #ffffff;
  }

  .pa-btn svg { width: 14px; height: 14px; }

  .pa-user-table {
    width: 100%;
    border-collapse: collapse;
  }

  .pa-user-table th {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--pa-text-muted);
    padding: 8px 16px;
    text-align: left;
    background: var(--pa-group-header-bg);
    border-bottom: 1px solid var(--pa-table-border);
  }

  .pa-user-table td {
    padding: 10px 16px;
    border-bottom: 1px solid var(--pa-table-border);
    vertical-align: middle;
  }

  .pa-user-table tr:last-child td { border-bottom: none; }

  .pa-user-table tbody tr {
    cursor: pointer;
    transition: background 0.15s;
  }

  .pa-user-table tbody tr:hover { background: var(--pa-row-hover); }
  .pa-user-table tbody tr.active { background: var(--pa-accent-dim); }

  .pa-user-name {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--pa-text);
  }

  .pa-user-email {
    font-size: 11px;
    color: var(--pa-text-muted);
  }

  .pa-pill-list {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
  }

  .pa-pill {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 10px;
    white-space: nowrap;
  }

  .pa-pill-role {
    background: var(--pa-violet-bg);
    color: var(--pa-violet);
  }

  .pa-pill-store {
    background: var(--pa-blue-bg);
    color: var(--pa-blue);
  }

  .pa-pill-all {
    background: var(--pa-green-bg);
    color: var(--pa-green);
  }

  .pa-cost-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
  }

  .pa-cost-dot.yes { background: var(--pa-status-active); }
  .pa-cost-dot.no { background: var(--pa-toggle-off); }

  .pa-status-badge {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 2px 7px;
    border-radius: 2px;
  }

  .pa-status-active { background: var(--pa-green-bg); color: var(--pa-status-active); }
  .pa-status-invited { background: var(--pa-amber-bg); color: var(--pa-status-invited); }
  .pa-status-deactivated { background: var(--pa-red-bg); color: var(--pa-status-deactivated); }

  /* ================================================================ */
  /*  USER DETAIL PANEL                                                */
  /* ================================================================ */
  .pa-user-detail-layout {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    animation: paFadeIn 0.2s ease-out;
  }

  .pa-user-detail {
    width: 380px;
    flex-shrink: 0;
    background: var(--pa-panel);
    border: 1px solid var(--pa-border);
    overflow: hidden;
    position: sticky;
    top: 88px;
    animation: paSlideIn 0.2s ease-out;
  }

  .pa-user-detail-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--pa-border);
    background: var(--pa-group-header-bg);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .pa-user-detail-name {
    font-family: var(--font-display);
    font-size: 15px;
    font-weight: 700;
    color: var(--pa-text);
  }

  .pa-user-detail-close {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--pa-text-muted);
    transition: color 0.15s;
    background: none;
    border: none;
  }

  .pa-user-detail-close:hover { color: var(--pa-text); }
  .pa-user-detail-close svg { width: 16px; height: 16px; }

  .pa-user-detail-body {
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .pa-detail-section {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .pa-detail-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--pa-text-muted);
  }

  .pa-detail-value {
    font-size: 12px;
    color: var(--pa-text-secondary);
  }

  .pa-pill-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
  }

  .pa-pill-toggle {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    padding: 3px 10px;
    border: 1px solid var(--pa-border);
    border-radius: 12px;
    background: transparent;
    color: var(--pa-text-muted);
    cursor: pointer;
    transition: all 0.15s;
  }

  .pa-pill-toggle:hover { border-color: var(--pa-accent); color: var(--pa-text); }

  .pa-pill-toggle.selected {
    background: var(--pa-accent);
    color: #ffffff;
    border-color: var(--pa-accent);
  }

  .pa-detail-actions {
    display: flex;
    gap: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--pa-border);
  }

  /* ================================================================ */
  /*  PERMISSION REFERENCE                                             */
  /* ================================================================ */
  .pa-reference {
    animation: paFadeIn 0.2s ease-out;
  }

  .pa-reference-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
  }

  .pa-search {
    position: relative;
    flex: 1;
    min-width: 200px;
    max-width: 320px;
  }

  .pa-search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--pa-text-muted);
    pointer-events: none;
  }

  .pa-search-icon svg { width: 14px; height: 14px; }

  .pa-search input {
    width: 100%;
    padding: 7px 10px 7px 32px;
    background: var(--pa-panel);
    border: 1px solid var(--pa-border);
    color: var(--pa-text);
    font-family: var(--font-mono);
    font-size: 12px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .pa-search input::placeholder { color: var(--pa-text-muted); }
  .pa-search input:focus { outline: none; border-color: var(--pa-accent); box-shadow: 0 0 0 2px var(--pa-accent-dim); }

  .pa-filter-chips {
    display: flex;
    gap: 4px;
  }

  .pa-chip {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    padding: 6px 12px;
    background: var(--pa-panel);
    border: 1px solid var(--pa-border);
    color: var(--pa-text-secondary);
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
  }

  .pa-chip:hover { border-color: var(--pa-text-muted); color: var(--pa-text); }

  .pa-chip.active {
    background: var(--pa-accent);
    color: #ffffff;
    border-color: var(--pa-accent);
  }

  .pa-ref-table {
    background: var(--pa-panel);
    border: 1px solid var(--pa-border);
    overflow: hidden;
  }

  .pa-ref-table table {
    width: 100%;
    border-collapse: collapse;
  }

  .pa-ref-table th {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--pa-text-muted);
    padding: 8px 16px;
    text-align: left;
    background: var(--pa-group-header-bg);
    border-bottom: 1px solid var(--pa-table-border);
  }

  .pa-ref-table td {
    padding: 8px 16px;
    border-bottom: 1px solid var(--pa-table-border);
    vertical-align: middle;
  }

  .pa-ref-table tr:last-child td { border-bottom: none; }
  .pa-ref-table tbody tr { transition: background 0.15s; }
  .pa-ref-table tbody tr:hover { background: var(--pa-row-hover); }

  .pa-ref-key {
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 500;
    color: var(--pa-text);
  }

  .pa-layer-badge {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 7px;
    border-radius: 2px;
    white-space: nowrap;
  }

  .pa-layer-badge.area { background: var(--pa-layer-area-bg); color: var(--pa-layer-area); }
  .pa-layer-badge.action { background: var(--pa-layer-action-bg); color: var(--pa-layer-action); }
  .pa-layer-badge.field { background: var(--pa-layer-field-bg); color: var(--pa-layer-field); }

  .pa-ref-group {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    color: var(--pa-text-muted);
  }

  .pa-ref-desc {
    font-size: 11px;
    color: var(--pa-text-secondary);
  }

  .pa-empty {
    padding: 48px 24px;
    text-align: center;
    color: var(--pa-text-muted);
  }

  .pa-empty-icon {
    margin-bottom: 12px;
    color: var(--pa-text-muted);
    opacity: 0.4;
  }

  .pa-empty-icon svg {
    width: 32px;
    height: 32px;
  }
</style>

<div class="pa-page" x-data="permissionsAdmin()" x-cloak>
  <div class="pa-container">

    {{-- ============================================================ --}}
    {{--  HEADER                                                      --}}
    {{-- ============================================================ --}}
    <div class="pa-header">
      <div class="pa-header-left">
        <div>
          <div class="pa-title">Permissions & Authorisation</div>
          <div class="pa-subtitle">Four-Layer Access Control</div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  TAB BAR                                                     --}}
    {{-- ============================================================ --}}
    <div class="pa-tabs">
      <button class="pa-tab" :class="{ active: activeTab === 'roles' }" @click="activeTab = 'roles'">Roles</button>
      <button class="pa-tab" :class="{ active: activeTab === 'users' }" @click="activeTab = 'users'">Users</button>
      <button class="pa-tab" :class="{ active: activeTab === 'reference' }" @click="activeTab = 'reference'">Permission Reference</button>
    </div>

    {{-- ============================================================ --}}
    {{--  ROLES TAB                                                   --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'roles'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
      <div class="pa-roles-layout">

        {{-- Role sidebar --}}
        <div class="pa-role-sidebar">
          <div class="pa-role-sidebar-header">
            <span class="pa-role-sidebar-title">Roles</span>
            <span style="font-size:10px; color:var(--pa-text-muted);" x-text="roles.length + ' roles'"></span>
          </div>
          <template x-for="role in roles" :key="role.id">
            <div class="pa-role-item"
                 :class="{ active: selectedRole && selectedRole.id === role.id }"
                 @click="selectRole(role)">
              <div class="pa-role-info">
                <div class="pa-role-name" x-text="role.name"></div>
                <div class="pa-role-meta">
                  <span class="pa-role-user-count" x-text="role.user_count + ' user' + (role.user_count !== 1 ? 's' : '')"></span>
                  <template x-if="role.is_system">
                    <span class="pa-role-system-badge">System</span>
                  </template>
                </div>
              </div>
            </div>
          </template>
        </div>

        {{-- Role editor --}}
        <div class="pa-role-editor" x-show="selectedRole" x-cloak>

          {{-- Role header card --}}
          <div class="pa-role-editor-header">
            <div class="pa-role-editor-top">
              <div class="pa-role-fields">
                <div class="pa-field-group">
                  <label class="pa-field-label">Role Name</label>
                  <input type="text"
                         class="pa-field-input"
                         :value="selectedRole ? selectedRole.name : ''"
                         :disabled="selectedRole && selectedRole.is_system"
                         @input="selectedRole && (selectedRole.name = $event.target.value)">
                </div>
                <div class="pa-field-group">
                  <label class="pa-field-label">Description</label>
                  <textarea class="pa-field-textarea"
                            :value="selectedRole ? selectedRole.description : ''"
                            @input="selectedRole && (selectedRole.description = $event.target.value)"></textarea>
                </div>
              </div>
            </div>

            {{-- Toggle cards --}}
            <div class="pa-toggle-row">
              <div class="pa-toggle-card">
                <div class="pa-toggle-card-info">
                  <div class="pa-toggle-card-label">Cost Visibility</div>
                  <div class="pa-toggle-card-desc">Can see cost and margin data</div>
                </div>
                <label class="pa-toggle">
                  <input type="checkbox"
                         :checked="selectedRole && selectedRole.cost_visibility"
                         @change="selectedRole && (selectedRole.cost_visibility = $event.target.checked)">
                  <span class="pa-toggle-track"></span>
                  <span class="pa-toggle-thumb"></span>
                </label>
              </div>

              <div class="pa-toggle-card">
                <div class="pa-toggle-card-info">
                  <div class="pa-toggle-card-label">All Stores</div>
                  <div class="pa-toggle-card-desc">Bypasses store scoping</div>
                </div>
                <label class="pa-toggle">
                  <input type="checkbox"
                         :checked="selectedRole && selectedRole.all_stores"
                         @change="selectedRole && (selectedRole.all_stores = $event.target.checked)">
                  <span class="pa-toggle-track"></span>
                  <span class="pa-toggle-thumb"></span>
                </label>
              </div>

              <div class="pa-toggle-card">
                <div class="pa-toggle-card-info">
                  <div class="pa-toggle-card-label">System Role</div>
                  <div class="pa-toggle-card-desc">Preset role, cannot be deleted</div>
                </div>
                <template x-if="selectedRole && selectedRole.is_owner">
                  <span class="pa-owner-indicator">Owner</span>
                </template>
                <template x-if="selectedRole && selectedRole.is_system && !selectedRole.is_owner">
                  <span class="pa-system-indicator">System</span>
                </template>
                <template x-if="selectedRole && !selectedRole.is_system">
                  <span style="font-size:11px; color:var(--pa-text-muted);">Custom</span>
                </template>
              </div>
            </div>
          </div>

          {{-- Permission matrix --}}
          <div class="pa-matrix">
            <div class="pa-matrix-header">
              <div class="pa-matrix-title">Permission Matrix</div>
              <div class="pa-matrix-stats">
                <div class="pa-matrix-stat">
                  <strong x-text="selectedRole ? countEnabledPermissions() : 0"></strong> / <span x-text="allPermissions.length"></span> enabled
                </div>
              </div>
            </div>

            {{-- Owner: all permissions message --}}
            <template x-if="selectedRole && selectedRole.is_owner">
              <div style="padding:16px; text-align:center; color:var(--pa-text-muted); font-size:12px;">
                <svg style="width:24px; height:24px; margin:0 auto 8px; color:var(--pa-violet);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                The Owner role has implicit access to all permissions. Permissions cannot be individually toggled.
              </div>
            </template>

            {{-- Permission groups --}}
            <template x-if="selectedRole && !selectedRole.is_owner">
              <div>
                <template x-for="group in permissionGroups" :key="group.name">
                  <div class="pa-group-section">
                    <div class="pa-group-header" @click="togglePermGroup(group.name)">
                      <svg class="pa-group-chevron"
                           :class="{ collapsed: !expandedPermGroups[group.name] }"
                           viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                      </svg>
                      <span class="pa-group-name" x-text="group.name"></span>
                      <span class="pa-group-count"
                            x-text="countGroupEnabled(group) + '/' + group.permissions.length"></span>
                      <div class="pa-group-actions" @click.stop>
                        <button class="pa-group-action-btn" @click="selectAllGroup(group)">All</button>
                        <button class="pa-group-action-btn" @click="deselectAllGroup(group)">None</button>
                      </div>
                    </div>

                    <div class="pa-perm-grid"
                         x-show="expandedPermGroups[group.name]"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100">
                      <template x-for="perm in group.permissions" :key="perm.key">
                        <div class="pa-perm-row"
                             :class="{ flashing: flashingPerm === perm.key }"
                             :data-perm="perm.key">
                          <div>
                            <span class="pa-layer-dot" :class="perm.layer"></span>
                          </div>
                          <div class="pa-perm-key" x-text="perm.key"></div>
                          <div class="pa-perm-desc" x-text="perm.description"></div>
                          <div class="pa-perm-check">
                            <label class="pa-checkbox">
                              <input type="checkbox"
                                     :checked="isPermEnabled(perm.key)"
                                     @change="togglePermission(perm.key, $event.target.checked)">
                              <span class="pa-checkbox-mark">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                  <polyline points="20 6 9 17 4 12"/>
                                </svg>
                              </span>
                            </label>
                          </div>
                        </div>
                      </template>
                    </div>
                  </div>
                </template>
              </div>
            </template>

            {{-- Legend --}}
            <div class="pa-legend">
              <span class="pa-legend-label">Layers:</span>
              <span class="pa-legend-item">
                <span class="pa-layer-dot area"></span>Area
              </span>
              <span class="pa-legend-item">
                <span class="pa-layer-dot action"></span>Action
              </span>
              <span class="pa-legend-item">
                <span class="pa-layer-dot field"></span>Field
              </span>
            </div>
          </div>

        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  USERS TAB                                                   --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'users'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
      <div :class="selectedUser ? 'pa-user-detail-layout' : ''">

        {{-- Users table --}}
        <div class="pa-users-panel" style="flex:1; min-width:0;">
          <div class="pa-users-toolbar">
            <div class="pa-users-title">Users</div>
            <button class="pa-btn pa-btn-primary">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <line x1="19" y1="8" x2="19" y2="14"/>
                <line x1="22" y1="11" x2="16" y2="11"/>
              </svg>
              Invite User
            </button>
          </div>
          <table class="pa-user-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Stores</th>
                <th>Costs</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="user in users" :key="user.id">
                <tr :class="{ active: selectedUser && selectedUser.id === user.id }"
                    @click="selectUser(user)">
                  <td>
                    <div class="pa-user-name" x-text="user.name"></div>
                  </td>
                  <td>
                    <div class="pa-user-email" x-text="user.email"></div>
                  </td>
                  <td>
                    <div class="pa-pill-list">
                      <template x-for="role in user.roles" :key="role">
                        <span class="pa-pill pa-pill-role" x-text="role"></span>
                      </template>
                    </div>
                  </td>
                  <td>
                    <div class="pa-pill-list">
                      <template x-if="user.all_stores">
                        <span class="pa-pill pa-pill-all">All Stores</span>
                      </template>
                      <template x-if="!user.all_stores">
                        <template x-for="store in user.stores" :key="store">
                          <span class="pa-pill pa-pill-store" x-text="store"></span>
                        </template>
                      </template>
                    </div>
                  </td>
                  <td>
                    <span class="pa-cost-dot" :class="user.cost_visibility ? 'yes' : 'no'"
                          :title="user.cost_visibility ? 'Can view costs' : 'Cannot view costs'"></span>
                  </td>
                  <td>
                    <span class="pa-status-badge"
                          :class="'pa-status-' + user.status"
                          x-text="user.status"></span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        {{-- User detail panel --}}
        <div class="pa-user-detail"
             x-show="selectedUser"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-x-4"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-cloak>
          <div class="pa-user-detail-header">
            <span class="pa-user-detail-name" x-text="selectedUser ? selectedUser.name : ''"></span>
            <button class="pa-user-detail-close" @click="selectedUser = null">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>
          <div class="pa-user-detail-body" x-show="selectedUser">
            <div class="pa-detail-section">
              <div class="pa-detail-label">Email</div>
              <div class="pa-detail-value" x-text="selectedUser ? selectedUser.email : ''"></div>
            </div>

            <div class="pa-detail-section">
              <div class="pa-detail-label">Roles</div>
              <div class="pa-pill-selector">
                <template x-for="role in roles" :key="role.id">
                  <button class="pa-pill-toggle"
                          :class="{ selected: selectedUser && selectedUser.roles.includes(role.name) }"
                          @click="toggleUserRole(role.name)"
                          x-text="role.name"></button>
                </template>
              </div>
            </div>

            <div class="pa-detail-section">
              <div class="pa-detail-label">Store Access</div>
              <div style="margin-bottom:6px;">
                <label class="pa-toggle" style="display:inline-flex; align-items:center; gap:8px; width:auto;">
                  <input type="checkbox"
                         :checked="selectedUser && selectedUser.all_stores"
                         @change="selectedUser && (selectedUser.all_stores = $event.target.checked)">
                  <span class="pa-toggle-track"></span>
                  <span class="pa-toggle-thumb"></span>
                </label>
                <span style="font-size:11px; color:var(--pa-text-secondary); margin-left:8px;">All Stores</span>
              </div>
              <div class="pa-pill-selector" x-show="selectedUser && !selectedUser.all_stores">
                <template x-for="store in allStores" :key="store">
                  <button class="pa-pill-toggle"
                          :class="{ selected: selectedUser && selectedUser.stores.includes(store) }"
                          @click="toggleUserStore(store)"
                          x-text="store"></button>
                </template>
              </div>
            </div>

            <div class="pa-detail-section">
              <div class="pa-detail-label">Cost Visibility Override</div>
              <label class="pa-toggle" style="display:inline-flex; align-items:center; gap:8px; width:auto;">
                <input type="checkbox"
                       :checked="selectedUser && selectedUser.cost_visibility"
                       @change="selectedUser && (selectedUser.cost_visibility = $event.target.checked)">
                <span class="pa-toggle-track"></span>
                <span class="pa-toggle-thumb"></span>
              </label>
              <span style="font-size:11px; color:var(--pa-text-secondary); margin-left:8px;"
                    x-text="selectedUser && selectedUser.cost_visibility ? 'Can view costs' : 'Cannot view costs'"></span>
            </div>

            <div class="pa-detail-section">
              <div class="pa-detail-label">Status</div>
              <div>
                <span class="pa-status-badge"
                      :class="selectedUser ? 'pa-status-' + selectedUser.status : ''"
                      x-text="selectedUser ? selectedUser.status : ''"></span>
              </div>
            </div>

            <div class="pa-detail-actions">
              <template x-if="selectedUser && selectedUser.status === 'active'">
                <button class="pa-btn" style="color:var(--pa-red);">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px; height:14px;">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                  </svg>
                  Deactivate
                </button>
              </template>
              <button class="pa-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px; height:14px;">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Reset Password
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  PERMISSION REFERENCE TAB                                    --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'reference'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
      <div class="pa-reference">

        <div class="pa-reference-toolbar">
          <div class="pa-search">
            <span class="pa-search-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
              </svg>
            </span>
            <input type="text"
                   placeholder="Search permissions..."
                   x-model.debounce.150ms="refSearch"
                   @keydown.escape="refSearch = ''">
          </div>

          <div class="pa-filter-chips">
            <button class="pa-chip" :class="{ active: refLayerFilter === 'all' }" @click="refLayerFilter = 'all'">All</button>
            <button class="pa-chip" :class="{ active: refLayerFilter === 'area' }" @click="refLayerFilter = 'area'">Area</button>
            <button class="pa-chip" :class="{ active: refLayerFilter === 'action' }" @click="refLayerFilter = 'action'">Action</button>
            <button class="pa-chip" :class="{ active: refLayerFilter === 'field' }" @click="refLayerFilter = 'field'">Field</button>
          </div>
        </div>

        <div class="pa-ref-table">
          <table>
            <thead>
              <tr>
                <th>Permission Key</th>
                <th>Label</th>
                <th>Description</th>
                <th>Layer</th>
                <th>Group</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="perm in filteredRefPermissions" :key="perm.key">
                <tr>
                  <td><span class="pa-ref-key" x-text="perm.key"></span></td>
                  <td x-text="perm.label"></td>
                  <td><span class="pa-ref-desc" x-text="perm.description"></span></td>
                  <td><span class="pa-layer-badge" :class="perm.layer" x-text="perm.layer"></span></td>
                  <td><span class="pa-ref-group" x-text="perm.group"></span></td>
                </tr>
              </template>
            </tbody>
          </table>

          <div class="pa-empty" x-show="filteredRefPermissions.length === 0">
            <div class="pa-empty-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
              </svg>
            </div>
            <div>No permissions match your search.</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

@verbatim
<script>
function permissionsAdmin() {

  /* ================================================================ */
  /*  PERMISSION DEFINITIONS                                           */
  /* ================================================================ */
  const allPermissions = [
    // Members
    { key: 'members.access', label: 'Access Members', description: 'View the members section', layer: 'area', group: 'Members', parent: null },
    { key: 'members.create', label: 'Create Members', description: 'Create new member records', layer: 'action', group: 'Members', parent: 'members.access' },
    { key: 'members.edit', label: 'Edit Members', description: 'Modify existing member records', layer: 'action', group: 'Members', parent: 'members.access' },
    { key: 'members.delete', label: 'Delete Members', description: 'Delete member records', layer: 'action', group: 'Members', parent: 'members.access' },

    // Opportunities
    { key: 'opportunities.access', label: 'Access Opportunities', description: 'View the opportunities section', layer: 'area', group: 'Opportunities', parent: null },
    { key: 'opportunities.create', label: 'Create Opportunities', description: 'Create new opportunities', layer: 'action', group: 'Opportunities', parent: 'opportunities.access' },
    { key: 'opportunities.edit', label: 'Edit Opportunities', description: 'Modify existing opportunities', layer: 'action', group: 'Opportunities', parent: 'opportunities.access' },
    { key: 'opportunities.delete', label: 'Delete Opportunities', description: 'Delete opportunities', layer: 'action', group: 'Opportunities', parent: 'opportunities.access' },
    { key: 'opportunities.convert', label: 'Convert Opportunities', description: 'Convert quotes to orders', layer: 'action', group: 'Opportunities', parent: 'opportunities.access' },
    { key: 'opportunities.dispatch', label: 'Dispatch Opportunities', description: 'Mark opportunities as dispatched', layer: 'action', group: 'Opportunities', parent: 'opportunities.access' },
    { key: 'opportunities.pricing', label: 'Manage Pricing', description: 'Override pricing and discounts', layer: 'action', group: 'Opportunities', parent: 'opportunities.access' },

    // Opportunity Costs
    { key: 'opportunities.costs.access', label: 'View Costs', description: 'View opportunity cost data', layer: 'field', group: 'Opportunity Costs', parent: 'opportunities.access' },
    { key: 'opportunities.costs.create', label: 'Add Costs', description: 'Add cost entries to opportunities', layer: 'field', group: 'Opportunity Costs', parent: 'opportunities.costs.access' },
    { key: 'opportunities.costs.update', label: 'Update Costs', description: 'Modify existing cost entries', layer: 'field', group: 'Opportunity Costs', parent: 'opportunities.costs.access' },
    { key: 'opportunities.costs.delete', label: 'Delete Costs', description: 'Remove cost entries', layer: 'field', group: 'Opportunity Costs', parent: 'opportunities.costs.access' },

    // Invoices
    { key: 'invoices.access', label: 'Access Invoices', description: 'View the invoices section', layer: 'area', group: 'Invoices', parent: null },
    { key: 'invoices.create', label: 'Create Invoices', description: 'Create new invoices', layer: 'action', group: 'Invoices', parent: 'invoices.access' },
    { key: 'invoices.edit', label: 'Edit Invoices', description: 'Modify draft invoices', layer: 'action', group: 'Invoices', parent: 'invoices.access' },
    { key: 'invoices.delete', label: 'Delete Invoices', description: 'Delete draft invoices', layer: 'action', group: 'Invoices', parent: 'invoices.access' },
    { key: 'invoices.issue', label: 'Issue Invoices', description: 'Issue invoices to customers', layer: 'action', group: 'Invoices', parent: 'invoices.access' },
    { key: 'invoices.void', label: 'Void Invoices', description: 'Void issued invoices', layer: 'action', group: 'Invoices', parent: 'invoices.access' },
    { key: 'invoices.credit', label: 'Credit Invoices', description: 'Create credit notes', layer: 'action', group: 'Invoices', parent: 'invoices.access' },

    // Products
    { key: 'products.access', label: 'Access Products', description: 'View the product catalogue', layer: 'area', group: 'Products', parent: null },
    { key: 'products.create', label: 'Create Products', description: 'Add new products', layer: 'action', group: 'Products', parent: 'products.access' },
    { key: 'products.edit', label: 'Edit Products', description: 'Modify product details', layer: 'action', group: 'Products', parent: 'products.access' },
    { key: 'products.delete', label: 'Delete Products', description: 'Remove products from catalogue', layer: 'action', group: 'Products', parent: 'products.access' },

    // Stock
    { key: 'stock.access', label: 'Access Stock', description: 'View stock and inventory', layer: 'area', group: 'Stock', parent: null },
    { key: 'stock.adjust', label: 'Adjust Stock', description: 'Make stock adjustments', layer: 'action', group: 'Stock', parent: 'stock.access' },
    { key: 'stock.transfer', label: 'Transfer Stock', description: 'Transfer stock between stores', layer: 'action', group: 'Stock', parent: 'stock.access' },

    // Services
    { key: 'services.view', label: 'View Services', description: 'View crew and services', layer: 'area', group: 'Services', parent: null },
    { key: 'services.create', label: 'Create Services', description: 'Add new service entries', layer: 'action', group: 'Services', parent: 'services.view' },
    { key: 'services.edit', label: 'Edit Services', description: 'Modify service details', layer: 'action', group: 'Services', parent: 'services.view' },
    { key: 'services.delete', label: 'Delete Services', description: 'Remove service entries', layer: 'action', group: 'Services', parent: 'services.view' },

    // Projects
    { key: 'projects.view', label: 'View Projects', description: 'View project records', layer: 'area', group: 'Projects', parent: null },
    { key: 'projects.create', label: 'Create Projects', description: 'Create new projects', layer: 'action', group: 'Projects', parent: 'projects.view' },
    { key: 'projects.edit', label: 'Edit Projects', description: 'Modify project details', layer: 'action', group: 'Projects', parent: 'projects.view' },
    { key: 'projects.delete', label: 'Delete Projects', description: 'Delete projects', layer: 'action', group: 'Projects', parent: 'projects.view' },

    // Reports
    { key: 'reports.view', label: 'View Reports', description: 'Access reporting dashboard', layer: 'area', group: 'Reports', parent: null },
    { key: 'reports.export', label: 'Export Reports', description: 'Export report data', layer: 'action', group: 'Reports', parent: 'reports.view' },

    // Settings & Admin
    { key: 'settings.view', label: 'View Settings', description: 'View system settings', layer: 'area', group: 'Settings & Admin', parent: null },
    { key: 'settings.manage', label: 'Manage Settings', description: 'Modify system settings', layer: 'action', group: 'Settings & Admin', parent: 'settings.view' },
    { key: 'users.view', label: 'View Users', description: 'View user accounts', layer: 'area', group: 'Settings & Admin', parent: null },
    { key: 'users.invite', label: 'Invite Users', description: 'Send user invitations', layer: 'action', group: 'Settings & Admin', parent: 'users.view' },
    { key: 'users.edit', label: 'Edit Users', description: 'Modify user details', layer: 'action', group: 'Settings & Admin', parent: 'users.view' },
    { key: 'users.deactivate', label: 'Deactivate Users', description: 'Deactivate user accounts', layer: 'action', group: 'Settings & Admin', parent: 'users.view' },
    { key: 'users.activate', label: 'Activate Users', description: 'Re-activate user accounts', layer: 'action', group: 'Settings & Admin', parent: 'users.view' },
    { key: 'users.delete', label: 'Delete Users', description: 'Delete user accounts', layer: 'action', group: 'Settings & Admin', parent: 'users.view' },
    { key: 'users.reset-password', label: 'Reset Passwords', description: 'Reset user passwords', layer: 'action', group: 'Settings & Admin', parent: 'users.view' },
    { key: 'roles.view', label: 'View Roles', description: 'View role definitions', layer: 'area', group: 'Settings & Admin', parent: null },
    { key: 'roles.manage', label: 'Manage Roles', description: 'Create and modify roles', layer: 'action', group: 'Settings & Admin', parent: 'roles.view' },

    // Data Management
    { key: 'static-data.manage', label: 'Manage Static Data', description: 'Edit reference data and lookups', layer: 'action', group: 'Data Management', parent: null },
    { key: 'tax.manage', label: 'Manage Tax', description: 'Configure tax rates and rules', layer: 'action', group: 'Data Management', parent: null },
    { key: 'custom-fields.manage', label: 'Manage Custom Fields', description: 'Create and configure custom fields', layer: 'action', group: 'Data Management', parent: null },
    { key: 'documents.manage', label: 'Manage Documents', description: 'Manage document templates', layer: 'action', group: 'Data Management', parent: null },
    { key: 'import.execute', label: 'Execute Imports', description: 'Run data imports', layer: 'action', group: 'Data Management', parent: null },
    { key: 'export.execute', label: 'Execute Exports', description: 'Run data exports', layer: 'action', group: 'Data Management', parent: null },

    // System
    { key: 'action-log.view', label: 'View Action Log', description: 'View the audit trail', layer: 'area', group: 'System', parent: null },
    { key: 'webhooks.manage', label: 'Manage Webhooks', description: 'Configure webhook subscriptions', layer: 'action', group: 'System', parent: null },
    { key: 'costs.view', label: 'View Costs', description: 'View cost and margin data globally', layer: 'field', group: 'System', parent: null },
  ];

  /* ================================================================ */
  /*  PERMISSION GROUPS (ordered)                                      */
  /* ================================================================ */
  const groupOrder = [
    'Members', 'Opportunities', 'Opportunity Costs', 'Invoices',
    'Products', 'Stock', 'Services', 'Projects', 'Reports',
    'Settings & Admin', 'Data Management', 'System'
  ];

  const permissionGroups = groupOrder.map(name => ({
    name,
    permissions: allPermissions.filter(p => p.group === name),
  })).filter(g => g.permissions.length > 0);

  /* ================================================================ */
  /*  ROLE DEFINITIONS                                                 */
  /* ================================================================ */
  const allPermKeys = allPermissions.map(p => p.key);

  const accessAndViewPerms = allPermissions
    .filter(p => p.key.endsWith('.access') || p.key.endsWith('.view'))
    .map(p => p.key);

  const roles = [
    {
      id: 1, name: 'Owner', description: 'Full system owner with implicit access to everything.',
      is_system: true, is_owner: true, cost_visibility: true, all_stores: true, user_count: 1,
      permissions: [...allPermKeys],
    },
    {
      id: 2, name: 'Admin', description: 'Full administrative access to all features.',
      is_system: true, is_owner: false, cost_visibility: true, all_stores: true, user_count: 2,
      permissions: [...allPermKeys],
    },
    {
      id: 3, name: 'Operations Manager', description: 'Manages opportunities, stock, compliance and reporting.',
      is_system: true, is_owner: false, cost_visibility: true, all_stores: false, user_count: 3,
      permissions: [
        'opportunities.access', 'opportunities.create', 'opportunities.edit', 'opportunities.delete',
        'opportunities.convert', 'opportunities.dispatch', 'opportunities.pricing',
        'opportunities.costs.access', 'opportunities.costs.create', 'opportunities.costs.update', 'opportunities.costs.delete',
        'invoices.access', 'invoices.create', 'invoices.edit', 'invoices.issue',
        'stock.access', 'stock.adjust', 'stock.transfer',
        'services.view', 'services.create', 'services.edit',
        'products.access', 'products.edit',
        'projects.view', 'projects.create', 'projects.edit',
        'reports.view', 'reports.export',
        'members.access', 'members.create', 'members.edit',
      ],
    },
    {
      id: 4, name: 'Sales', description: 'Creates and manages quotes and customer relationships.',
      is_system: true, is_owner: false, cost_visibility: false, all_stores: false, user_count: 4,
      permissions: [
        'opportunities.access', 'opportunities.create', 'opportunities.edit',
        'members.access', 'members.create', 'members.edit', 'members.delete',
        'products.access',
        'invoices.access',
        'reports.view',
      ],
    },
    {
      id: 5, name: 'Warehouse', description: 'Manages stock, dispatching and compliance for confirmed opportunities.',
      is_system: true, is_owner: false, cost_visibility: false, all_stores: false, user_count: 2,
      permissions: [
        'opportunities.access',
        'stock.access', 'stock.adjust', 'stock.transfer',
        'products.access',
        'services.view',
      ],
    },
    {
      id: 6, name: 'Read Only', description: 'View-only access across all sections.',
      is_system: true, is_owner: false, cost_visibility: false, all_stores: true, user_count: 1,
      permissions: [...accessAndViewPerms],
    },
  ];

  /* ================================================================ */
  /*  USER DEFINITIONS                                                 */
  /* ================================================================ */
  const users = [
    { id: 1, name: 'Ben Thompson', email: 'ben@company.com', roles: ['Owner'], stores: [], all_stores: true, cost_visibility: true, status: 'active' },
    { id: 2, name: 'Sarah Mitchell', email: 'sarah@company.com', roles: ['Admin'], stores: [], all_stores: true, cost_visibility: true, status: 'active' },
    { id: 3, name: 'James Wilson', email: 'james@company.com', roles: ['Operations Manager'], stores: ['London', 'Manchester'], all_stores: false, cost_visibility: true, status: 'active' },
    { id: 4, name: 'Emily Chen', email: 'emily@company.com', roles: ['Operations Manager'], stores: ['Edinburgh'], all_stores: false, cost_visibility: true, status: 'active' },
    { id: 5, name: 'Tom Baker', email: 'tom@company.com', roles: ['Sales'], stores: ['London'], all_stores: false, cost_visibility: false, status: 'active' },
    { id: 6, name: 'Lisa Park', email: 'lisa@company.com', roles: ['Sales'], stores: ['Manchester', 'Edinburgh'], all_stores: false, cost_visibility: false, status: 'active' },
    { id: 7, name: 'Dave Murray', email: 'dave@company.com', roles: ['Warehouse'], stores: ['London'], all_stores: false, cost_visibility: false, status: 'active' },
    { id: 8, name: 'Rachel Green', email: 'rachel@company.com', roles: ['Warehouse'], stores: ['Manchester'], all_stores: false, cost_visibility: false, status: 'invited' },
  ];

  const allStores = ['London', 'Manchester', 'Edinburgh'];

  /* ================================================================ */
  /*  COMPONENT STATE                                                  */
  /* ================================================================ */
  return {
    activeTab: 'roles',
    roles,
    users,
    allStores,
    allPermissions,
    permissionGroups,

    // Roles tab
    selectedRole: null,
    expandedPermGroups: {},
    flashingPerm: null,

    // Users tab
    selectedUser: null,

    // Reference tab
    refSearch: '',
    refLayerFilter: 'all',

    /* ============================================================== */
    /*  INIT                                                           */
    /* ============================================================== */
    init() {
      // Select first role on load
      this.selectRole(this.roles[0]);

      // Expand all permission groups
      this.permissionGroups.forEach(g => {
        this.expandedPermGroups[g.name] = true;
      });
    },

    /* ============================================================== */
    /*  ROLE METHODS                                                   */
    /* ============================================================== */
    selectRole(role) {
      // Deep clone to avoid reference issues
      this.selectedRole = JSON.parse(JSON.stringify(role));
    },

    togglePermGroup(name) {
      this.expandedPermGroups[name] = !this.expandedPermGroups[name];
    },

    isPermEnabled(key) {
      return this.selectedRole && this.selectedRole.permissions.includes(key);
    },

    togglePermission(key, checked) {
      if (!this.selectedRole) return;

      if (checked) {
        // Add permission
        if (!this.selectedRole.permissions.includes(key)) {
          this.selectedRole.permissions.push(key);
        }

        // Auto-enable parent (area permission)
        const perm = this.allPermissions.find(p => p.key === key);
        if (perm && perm.parent && !this.selectedRole.permissions.includes(perm.parent)) {
          this.selectedRole.permissions.push(perm.parent);
          this.flashDependency(perm.parent);
        }
      } else {
        // Remove permission
        this.selectedRole.permissions = this.selectedRole.permissions.filter(p => p !== key);

        // Auto-disable children if this is an area/parent permission
        const children = this.allPermissions.filter(p => p.parent === key);
        children.forEach(child => {
          this.selectedRole.permissions = this.selectedRole.permissions.filter(p => p !== child.key);
        });
      }
    },

    flashDependency(key) {
      this.flashingPerm = key;
      setTimeout(() => {
        this.flashingPerm = null;
      }, 600);
    },

    countEnabledPermissions() {
      if (!this.selectedRole) return 0;
      return this.selectedRole.permissions.length;
    },

    countGroupEnabled(group) {
      if (!this.selectedRole) return 0;
      return group.permissions.filter(p => this.selectedRole.permissions.includes(p.key)).length;
    },

    selectAllGroup(group) {
      if (!this.selectedRole || this.selectedRole.is_owner) return;
      group.permissions.forEach(p => {
        if (!this.selectedRole.permissions.includes(p.key)) {
          this.selectedRole.permissions.push(p.key);
        }
        // Also enable parents
        if (p.parent && !this.selectedRole.permissions.includes(p.parent)) {
          this.selectedRole.permissions.push(p.parent);
        }
      });
    },

    deselectAllGroup(group) {
      if (!this.selectedRole || this.selectedRole.is_owner) return;
      const keys = group.permissions.map(p => p.key);
      this.selectedRole.permissions = this.selectedRole.permissions.filter(p => !keys.includes(p));
    },

    /* ============================================================== */
    /*  USER METHODS                                                   */
    /* ============================================================== */
    selectUser(user) {
      if (this.selectedUser && this.selectedUser.id === user.id) {
        this.selectedUser = null;
      } else {
        this.selectedUser = JSON.parse(JSON.stringify(user));
      }
    },

    toggleUserRole(roleName) {
      if (!this.selectedUser) return;
      const idx = this.selectedUser.roles.indexOf(roleName);
      if (idx >= 0) {
        this.selectedUser.roles.splice(idx, 1);
      } else {
        this.selectedUser.roles.push(roleName);
      }
    },

    toggleUserStore(store) {
      if (!this.selectedUser) return;
      const idx = this.selectedUser.stores.indexOf(store);
      if (idx >= 0) {
        this.selectedUser.stores.splice(idx, 1);
      } else {
        this.selectedUser.stores.push(store);
      }
    },

    /* ============================================================== */
    /*  REFERENCE TAB                                                  */
    /* ============================================================== */
    get filteredRefPermissions() {
      let perms = this.allPermissions;

      if (this.refLayerFilter !== 'all') {
        perms = perms.filter(p => p.layer === this.refLayerFilter);
      }

      if (this.refSearch.trim()) {
        const q = this.refSearch.toLowerCase().trim();
        perms = perms.filter(p =>
          p.key.toLowerCase().includes(q) ||
          p.label.toLowerCase().includes(q) ||
          p.description.toLowerCase().includes(q) ||
          p.group.toLowerCase().includes(q)
        );
      }

      return perms;
    },
  };
}
</script>
@endverbatim
