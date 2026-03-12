<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Settings & Administration')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  SETTINGS ADMIN TOKENS — maps to brand system in app.css          */
  /* ================================================================ */
  :root {
    --sa-bg: var(--content-bg);
    --sa-panel: var(--card-bg);
    --sa-surface: var(--base);
    --sa-border: var(--card-border);
    --sa-border-subtle: var(--grey-border);
    --sa-text: var(--text-primary);
    --sa-text-secondary: var(--text-secondary);
    --sa-text-muted: var(--text-muted);
    --sa-accent: var(--green);
    --sa-accent-dim: var(--green-muted);
    --sa-hover: rgba(0, 0, 0, 0.03);
    --sa-shadow: var(--shadow-card);
    --sa-shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.08);
    --sa-blue: var(--blue);
    --sa-blue-bg: rgba(37, 99, 235, 0.08);
    --sa-green: var(--green);
    --sa-green-bg: rgba(5, 150, 105, 0.08);
    --sa-red: var(--red);
    --sa-red-bg: rgba(220, 38, 38, 0.06);
    --sa-amber: var(--amber);
    --sa-amber-bg: rgba(217, 119, 6, 0.08);
    --sa-violet: var(--violet);
    --sa-violet-bg: rgba(124, 58, 237, 0.08);
    --sa-sidebar-w: 240px;
    --sa-nav-item-h: 34px;
    --sa-toggle-w: 40px;
    --sa-toggle-h: 22px;
  }

  .dark {
    --sa-bg: var(--content-bg);
    --sa-panel: var(--card-bg);
    --sa-surface: var(--navy-mid);
    --sa-border: var(--card-border);
    --sa-border-subtle: #283040;
    --sa-text: var(--text-primary);
    --sa-text-secondary: var(--text-secondary);
    --sa-text-muted: var(--text-muted);
    --sa-accent: var(--green);
    --sa-accent-dim: rgba(5, 150, 105, 0.12);
    --sa-hover: rgba(255, 255, 255, 0.04);
    --sa-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --sa-shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.25);
    --sa-blue-bg: rgba(37, 99, 235, 0.12);
    --sa-green-bg: rgba(5, 150, 105, 0.12);
    --sa-red-bg: rgba(220, 38, 38, 0.12);
    --sa-amber-bg: rgba(217, 119, 6, 0.12);
    --sa-violet-bg: rgba(124, 58, 237, 0.12);
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */
  .sa-page {
    display: flex;
    min-height: calc(100vh - 64px);
    background: var(--sa-bg);
    position: relative;
  }

  /* ================================================================ */
  /*  SIDEBAR                                                          */
  /* ================================================================ */
  .sa-sidebar {
    width: var(--sa-sidebar-w);
    flex-shrink: 0;
    background: var(--sa-panel);
    border-right: 1px solid var(--sa-border);
    padding: 20px 0;
    position: sticky;
    top: 64px;
    height: calc(100vh - 64px);
    overflow-y: auto;
  }

  .sa-sidebar-title {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--sa-text-muted);
    padding: 0 20px;
    margin-bottom: 16px;
  }

  .sa-nav-group {
    margin-bottom: 20px;
  }

  .sa-nav-group-label {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--sa-text-muted);
    padding: 0 20px;
    margin-bottom: 4px;
  }

  .sa-nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    height: var(--sa-nav-item-h);
    padding: 0 20px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 500;
    color: var(--sa-text-secondary);
    transition: background 0.12s, color 0.12s;
    position: relative;
    user-select: none;
  }

  .sa-nav-item:hover {
    background: var(--sa-hover);
    color: var(--sa-text);
  }

  .sa-nav-item.active {
    color: var(--sa-accent);
    background: var(--sa-green-bg);
  }

  .sa-nav-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 4px;
    bottom: 4px;
    width: 3px;
    background: var(--sa-accent);
    border-radius: 0 2px 2px 0;
  }

  .sa-nav-icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    opacity: 0.7;
  }

  .sa-nav-item.active .sa-nav-icon {
    opacity: 1;
  }

  /* ================================================================ */
  /*  MAIN CONTENT                                                     */
  /* ================================================================ */
  .sa-main {
    flex: 1;
    min-width: 0;
    padding: 28px 40px 64px;
    max-width: 960px;
  }

  .sa-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
  }

  .sa-section-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    color: var(--sa-text);
    letter-spacing: -0.01em;
  }

  .sa-section-desc {
    font-size: 13px;
    color: var(--sa-text-muted);
    margin-top: 4px;
  }

  /* ================================================================ */
  /*  FORM ELEMENTS                                                    */
  /* ================================================================ */
  .sa-form-group {
    margin-bottom: 20px;
  }

  .sa-form-label {
    display: block;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--sa-text);
    margin-bottom: 6px;
    letter-spacing: 0.01em;
  }

  .sa-form-hint {
    font-size: 11px;
    color: var(--sa-text-muted);
    margin-top: 4px;
  }

  .sa-input {
    width: 100%;
    height: 36px;
    padding: 0 12px;
    background: var(--sa-surface);
    border: 1px solid var(--sa-border-subtle);
    color: var(--sa-text);
    font-size: 13px;
    font-family: inherit;
    transition: border-color 0.15s, box-shadow 0.15s;
    outline: none;
  }

  .sa-input:focus {
    border-color: var(--sa-accent);
    box-shadow: 0 0 0 2px var(--sa-accent-dim);
  }

  .sa-input-narrow {
    width: 120px;
  }

  .sa-select {
    width: 100%;
    height: 36px;
    padding: 0 12px;
    background: var(--sa-surface);
    border: 1px solid var(--sa-border-subtle);
    color: var(--sa-text);
    font-size: 13px;
    font-family: inherit;
    cursor: pointer;
    outline: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 32px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .sa-select:focus {
    border-color: var(--sa-accent);
    box-shadow: 0 0 0 2px var(--sa-accent-dim);
  }

  .sa-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }

  .sa-form-row-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
  }

  /* ================================================================ */
  /*  RADIO PILLS                                                      */
  /* ================================================================ */
  .sa-radio-pills {
    display: inline-flex;
    border: 1px solid var(--sa-border-subtle);
    overflow: hidden;
  }

  .sa-radio-pill {
    padding: 6px 16px;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--sa-text-secondary);
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    user-select: none;
    background: var(--sa-surface);
    border-right: 1px solid var(--sa-border-subtle);
  }

  .sa-radio-pill:last-child {
    border-right: none;
  }

  .sa-radio-pill.active {
    background: var(--sa-accent);
    color: white;
  }

  /* ================================================================ */
  /*  TOGGLE SWITCH                                                    */
  /* ================================================================ */
  .sa-toggle {
    position: relative;
    width: var(--sa-toggle-w);
    height: var(--sa-toggle-h);
    background: var(--sa-border-subtle);
    border-radius: 11px;
    cursor: pointer;
    transition: background 0.2s;
    flex-shrink: 0;
  }

  .sa-toggle.on {
    background: var(--sa-accent);
  }

  .sa-toggle.locked {
    opacity: 0.5;
    cursor: not-allowed;
  }

  .sa-toggle-knob {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 16px;
    height: 16px;
    background: white;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
  }

  .sa-toggle.on .sa-toggle-knob {
    transform: translateX(18px);
  }

  .sa-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--sa-border-subtle);
  }

  .sa-toggle-row:last-child {
    border-bottom: none;
  }

  .sa-toggle-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--sa-text);
  }

  .sa-toggle-desc {
    font-size: 11px;
    color: var(--sa-text-muted);
    margin-top: 2px;
  }

  /* ================================================================ */
  /*  CARDS & PANELS                                                   */
  /* ================================================================ */
  .sa-card {
    background: var(--sa-panel);
    border: 1px solid var(--sa-border);
    box-shadow: var(--sa-shadow);
    padding: 24px;
    margin-bottom: 20px;
  }

  .sa-card-title {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 700;
    color: var(--sa-text);
    margin-bottom: 16px;
    letter-spacing: -0.01em;
  }

  /* ================================================================ */
  /*  TABLE                                                            */
  /* ================================================================ */
  .sa-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }

  .sa-table th {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sa-text-muted);
    background: var(--table-header-bg);
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid var(--sa-border);
  }

  .sa-table td {
    padding: 10px 12px;
    color: var(--sa-text);
    border-bottom: 1px solid var(--sa-border-subtle);
    vertical-align: middle;
  }

  .sa-table tr:hover td {
    background: var(--sa-hover);
  }

  .sa-table-code {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--sa-text-secondary);
    background: rgba(0, 0, 0, 0.04);
    padding: 2px 6px;
  }

  .dark .sa-table-code {
    background: rgba(255, 255, 255, 0.06);
  }

  /* ================================================================ */
  /*  BADGES                                                           */
  /* ================================================================ */
  .sa-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    letter-spacing: 0.02em;
  }

  .sa-badge-green {
    color: var(--sa-green);
    background: var(--sa-green-bg);
  }

  .sa-badge-amber {
    color: var(--sa-amber);
    background: var(--sa-amber-bg);
  }

  .sa-badge-red {
    color: var(--sa-red);
    background: var(--sa-red-bg);
  }

  .sa-badge-blue {
    color: var(--sa-blue);
    background: var(--sa-blue-bg);
  }

  .sa-badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
  }

  /* ================================================================ */
  /*  BUTTONS                                                          */
  /* ================================================================ */
  .sa-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s, transform 0.1s;
    border: none;
    outline: none;
    user-select: none;
  }

  .sa-btn:active {
    transform: scale(0.98);
  }

  .sa-btn-primary {
    background: var(--sa-accent);
    color: white;
  }

  .sa-btn-primary:hover {
    background: #048a5a;
  }

  .sa-btn-secondary {
    background: var(--sa-surface);
    color: var(--sa-text);
    border: 1px solid var(--sa-border-subtle);
  }

  .sa-btn-secondary:hover {
    background: var(--sa-hover);
  }

  .sa-btn-sm {
    padding: 5px 12px;
    font-size: 11px;
  }

  .sa-btn-icon {
    width: 14px;
    height: 14px;
  }

  /* ================================================================ */
  /*  MODULE TOGGLE CARDS                                              */
  /* ================================================================ */
  .sa-module-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .sa-module-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    background: var(--sa-surface);
    border: 1px solid var(--sa-border-subtle);
    transition: border-color 0.15s, background 0.15s;
  }

  .sa-module-card.enabled {
    border-color: var(--sa-accent);
    background: var(--sa-green-bg);
  }

  .sa-module-card.locked {
    opacity: 0.7;
  }

  .sa-module-info {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .sa-module-icon {
    width: 20px;
    height: 20px;
    color: var(--sa-text-muted);
  }

  .sa-module-card.enabled .sa-module-icon {
    color: var(--sa-accent);
  }

  .sa-module-name {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    color: var(--sa-text);
  }

  .sa-module-lock {
    font-size: 10px;
    color: var(--sa-text-muted);
    margin-left: 6px;
  }

  /* ================================================================ */
  /*  BRANDING                                                         */
  /* ================================================================ */
  .sa-upload-zone {
    width: 160px;
    height: 100px;
    border: 2px dashed var(--sa-border-subtle);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    background: var(--sa-surface);
  }

  .sa-upload-zone:hover {
    border-color: var(--sa-accent);
    background: var(--sa-green-bg);
  }

  .sa-upload-icon {
    width: 24px;
    height: 24px;
    color: var(--sa-text-muted);
  }

  .sa-upload-label {
    font-size: 11px;
    color: var(--sa-text-muted);
    font-weight: 500;
  }

  .sa-colour-picker {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .sa-colour-swatch {
    width: 36px;
    height: 36px;
    border: 2px solid var(--sa-border-subtle);
    cursor: pointer;
    transition: border-color 0.15s;
  }

  .sa-colour-swatch:hover {
    border-color: var(--sa-accent);
  }

  .sa-colour-value {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--sa-text-secondary);
  }

  /* ================================================================ */
  /*  TAX MATRIX                                                       */
  /* ================================================================ */
  .sa-tab-bar {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--sa-border);
    margin-bottom: 20px;
  }

  .sa-tab {
    padding: 8px 20px;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--sa-text-muted);
    cursor: pointer;
    transition: color 0.15s;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    user-select: none;
  }

  .sa-tab:hover {
    color: var(--sa-text);
  }

  .sa-tab.active {
    color: var(--sa-accent);
    border-bottom-color: var(--sa-accent);
  }

  .sa-matrix {
    border: 1px solid var(--sa-border);
    overflow-x: auto;
  }

  .sa-matrix table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }

  .sa-matrix th {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sa-text-muted);
    background: var(--table-header-bg);
    padding: 8px 12px;
    border: 1px solid var(--sa-border);
    text-align: center;
  }

  .sa-matrix th:first-child {
    text-align: left;
    min-width: 140px;
  }

  .sa-matrix td {
    padding: 6px 10px;
    text-align: center;
    border: 1px solid var(--sa-border-subtle);
    cursor: pointer;
    transition: background 0.12s;
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--sa-text);
  }

  .sa-matrix td:first-child {
    text-align: left;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--sa-text);
    cursor: default;
    background: var(--table-header-bg);
  }

  .sa-matrix td:not(:first-child):hover {
    background: var(--sa-green-bg);
  }

  .sa-matrix-select {
    width: 100%;
    border: none;
    background: transparent;
    text-align: center;
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--sa-text);
    cursor: pointer;
    outline: none;
    padding: 2px;
  }

  /* ================================================================ */
  /*  HEALTH CARDS                                                     */
  /* ================================================================ */
  .sa-health-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 12px;
  }

  .sa-health-card {
    background: var(--sa-surface);
    border: 1px solid var(--sa-border-subtle);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: border-color 0.15s;
  }

  .sa-health-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .sa-health-name {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    color: var(--sa-text);
  }

  .sa-health-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
  }

  .sa-health-dot-green {
    background: var(--sa-green);
    box-shadow: 0 0 6px rgba(5, 150, 105, 0.4);
  }

  .sa-health-dot-amber {
    background: var(--sa-amber);
    box-shadow: 0 0 6px rgba(217, 119, 6, 0.4);
  }

  .sa-health-dot-red {
    background: var(--sa-red);
    box-shadow: 0 0 6px rgba(220, 38, 38, 0.4);
  }

  .sa-health-status {
    font-size: 11px;
    font-weight: 600;
  }

  .sa-health-time {
    font-size: 10px;
    color: var(--sa-text-muted);
  }

  /* ================================================================ */
  /*  UNSAVED CHANGES BAR                                              */
  /* ================================================================ */
  .sa-unsaved-bar {
    position: fixed;
    bottom: 0;
    left: var(--sa-sidebar-w);
    right: 0;
    height: 48px;
    background: var(--navy);
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 32px;
    z-index: 50;
    animation: saSlideUp 0.25s ease both;
    box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.15);
  }

  .sa-unsaved-text {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .sa-unsaved-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--sa-amber);
    animation: saPulse 1.5s infinite;
  }

  /* ================================================================ */
  /*  TOAST                                                            */
  /* ================================================================ */
  .sa-toast {
    position: fixed;
    top: 80px;
    right: 32px;
    background: var(--sa-accent);
    color: white;
    padding: 12px 20px;
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    z-index: 200;
    box-shadow: var(--sa-shadow-lg);
    display: flex;
    align-items: center;
    gap: 8px;
    animation: saToastIn 0.3s ease both;
  }

  .sa-toast-icon {
    width: 16px;
    height: 16px;
  }

  /* ================================================================ */
  /*  EMAIL CONNECTION STATUS                                          */
  /* ================================================================ */
  .sa-connection-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    background: var(--sa-surface);
    border: 1px solid var(--sa-border-subtle);
  }

  .sa-connection-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
  }

  .sa-connection-dot.green {
    background: var(--sa-green);
    box-shadow: 0 0 6px rgba(5, 150, 105, 0.4);
  }

  .sa-connection-dot.red {
    background: var(--sa-red);
  }

  /* ================================================================ */
  /*  SIDE-BY-SIDE TABLES (Tax Classes)                                */
  /* ================================================================ */
  .sa-split-tables {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }

  /* ================================================================ */
  /*  ACTION LOG                                                       */
  /* ================================================================ */
  .sa-action-verb {
    font-weight: 600;
  }

  .sa-action-verb.created { color: var(--sa-green); }
  .sa-action-verb.updated { color: var(--sa-blue); }
  .sa-action-verb.deleted { color: var(--sa-red); }
  .sa-action-verb.issued { color: var(--sa-violet); }
  .sa-action-verb.confirmed { color: var(--sa-accent); }
  .sa-action-verb.invited { color: var(--sa-amber); }

  .sa-action-entity {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--sa-text-secondary);
  }

  /* ================================================================ */
  /*  PASSWORD FIELD                                                   */
  /* ================================================================ */
  .sa-input-password {
    font-family: var(--font-mono);
    letter-spacing: 0.15em;
  }

  /* ================================================================ */
  /*  KEYFRAMES                                                        */
  /* ================================================================ */
  @keyframes saSlideUp {
    from { transform: translateY(100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
  }

  @keyframes saToastIn {
    from { transform: translateX(40px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }

  @keyframes saToastOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(40px); opacity: 0; }
  }

  @keyframes saPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
  }

  @keyframes saFadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .sa-fade-in {
    animation: saFadeIn 0.2s ease both;
  }

  /* ================================================================ */
  /*  SCROLLBAR                                                        */
  /* ================================================================ */
  .sa-sidebar::-webkit-scrollbar {
    width: 4px;
  }

  .sa-sidebar::-webkit-scrollbar-thumb {
    background: var(--sa-border-subtle);
    border-radius: 2px;
  }

  /* ================================================================ */
  /*  DEFAULT BADGE (for stores)                                       */
  /* ================================================================ */
  .sa-default-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sa-accent);
    background: var(--sa-green-bg);
    padding: 2px 8px;
  }
</style>

<div class="sa-page" x-data="settingsAdmin()" x-cloak>
  <!-- ============================================================ -->
  <!-- SIDEBAR NAVIGATION                                            -->
  <!-- ============================================================ -->
  <aside class="sa-sidebar">
    <div class="sa-sidebar-title">Settings</div>

    <template x-for="group in navGroups" :key="group.label">
      <div class="sa-nav-group">
        <div class="sa-nav-group-label" x-text="group.label"></div>
        <template x-for="item in group.items" :key="item.key">
          <div class="sa-nav-item"
               :class="{ active: activeSection === item.key }"
               @click="switchSection(item.key)">
            <svg class="sa-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <template x-if="item.icon === 'building'">
                <g><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9h.01"/><path d="M9 13h.01"/><path d="M9 17h.01"/></g>
              </template>
              <template x-if="item.icon === 'store'">
                <g><path d="M3 9l1-4h16l1 4"/><path d="M3 9v12h18V9"/><path d="M9 21V13h6v8"/></g>
              </template>
              <template x-if="item.icon === 'palette'">
                <g><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12" r=".5" fill="currentColor"/><path d="M12 2a10 10 0 0 0 0 20c.6 0 1-.4 1-1v-1a2 2 0 0 1 2-2h1a2 2 0 0 0 2-2 3 3 0 0 1 3-3 1 1 0 0 0 1-1 10 10 0 0 0-10-10"/></g>
              </template>
              <template x-if="item.icon === 'blocks'">
                <g><rect x="2" y="2" width="8" height="8" rx="1"/><rect x="14" y="2" width="8" height="8" rx="1"/><rect x="2" y="14" width="8" height="8" rx="1"/><rect x="14" y="14" width="8" height="8" rx="1"/></g>
              </template>
              <template x-if="item.icon === 'shield'">
                <g><path d="M12 2s8 4 8 10-8 10-8 10-8-4-8-10 8-10 8-10z"/></g>
              </template>
              <template x-if="item.icon === 'sliders'">
                <g><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="2" y1="14" x2="6" y2="14"/><line x1="10" y1="8" x2="14" y2="8"/><line x1="18" y1="16" x2="22" y2="16"/></g>
              </template>
              <template x-if="item.icon === 'mail'">
                <g><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/></g>
              </template>
              <template x-if="item.icon === 'layers'">
                <g><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></g>
              </template>
              <template x-if="item.icon === 'tags'">
                <g><path d="M9 5H2v7l9 9 7-7-9-9z"/><circle cx="5" cy="9" r="1" fill="currentColor"/></g>
              </template>
              <template x-if="item.icon === 'percent'">
                <g><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></g>
              </template>
              <template x-if="item.icon === 'clock'">
                <g><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></g>
              </template>
              <template x-if="item.icon === 'heart-pulse'">
                <g><path d="M12 21C12 21 3 13.5 3 8.5a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 5-9 12.5-9 12.5z"/><path d="M3.5 12h4l1.5-3 3 6 1.5-3h4.5"/></g>
              </template>
            </svg>
            <span x-text="item.label"></span>
          </div>
        </template>
      </div>
    </template>
  </aside>

  <!-- ============================================================ -->
  <!-- MAIN CONTENT                                                  -->
  <!-- ============================================================ -->
  <main class="sa-main">
    <!-- COMPANY DETAILS -->
    <div x-show="activeSection === 'company'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">Company Details</div>
          <div class="sa-section-desc">Core information about your organisation</div>
        </div>
      </div>
      <div class="sa-card">
        <div class="sa-form-group">
          <label class="sa-form-label">Company Name</label>
          <input type="text" class="sa-input" x-model="forms.company.name" @input="markDirty()" />
        </div>
        <div class="sa-form-row">
          <div class="sa-form-group">
            <label class="sa-form-label">Country</label>
            <select class="sa-select" x-model="forms.company.country" @change="markDirty()">
              <option value="GB">United Kingdom</option>
              <option value="US">United States</option>
              <option value="AU">Australia</option>
              <option value="DE">Germany</option>
              <option value="FR">France</option>
            </select>
          </div>
          <div class="sa-form-group">
            <label class="sa-form-label">Timezone</label>
            <select class="sa-select" x-model="forms.company.timezone" @change="markDirty()">
              <option value="Europe/London">Europe/London</option>
              <option value="America/New_York">America/New_York</option>
              <option value="Australia/Sydney">Australia/Sydney</option>
              <option value="Europe/Berlin">Europe/Berlin</option>
            </select>
          </div>
        </div>
        <div class="sa-form-row">
          <div class="sa-form-group">
            <label class="sa-form-label">Default Currency</label>
            <select class="sa-select" x-model="forms.company.currency" @change="markDirty()">
              <option value="GBP">GBP — British Pound</option>
              <option value="USD">USD — US Dollar</option>
              <option value="EUR">EUR — Euro</option>
              <option value="AUD">AUD — Australian Dollar</option>
            </select>
          </div>
          <div class="sa-form-group">
            <label class="sa-form-label">Date Format</label>
            <select class="sa-select" x-model="forms.company.dateFormat" @change="markDirty()">
              <option value="DD/MM/YYYY">DD/MM/YYYY</option>
              <option value="MM/DD/YYYY">MM/DD/YYYY</option>
              <option value="YYYY-MM-DD">YYYY-MM-DD</option>
            </select>
          </div>
        </div>
        <div class="sa-form-row">
          <div class="sa-form-group">
            <label class="sa-form-label">Time Format</label>
            <div class="sa-radio-pills">
              <div class="sa-radio-pill" :class="{ active: forms.company.timeFormat === '12h' }" @click="forms.company.timeFormat = '12h'; markDirty()">12h</div>
              <div class="sa-radio-pill" :class="{ active: forms.company.timeFormat === '24h' }" @click="forms.company.timeFormat = '24h'; markDirty()">24h</div>
            </div>
          </div>
          <div class="sa-form-group">
            <label class="sa-form-label">Fiscal Year Start</label>
            <select class="sa-select" x-model="forms.company.fiscalStart" @change="markDirty()">
              <template x-for="m in months" :key="m">
                <option :value="m" x-text="m"></option>
              </template>
            </select>
          </div>
        </div>
      </div>
      <div style="display: flex; justify-content: flex-end;">
        <button class="sa-btn sa-btn-primary" @click="save()">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
          Save Changes
        </button>
      </div>
    </div>

    <!-- STORES -->
    <div x-show="activeSection === 'stores'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">Stores</div>
          <div class="sa-section-desc">Manage warehouse and branch locations</div>
        </div>
        <button class="sa-btn sa-btn-primary sa-btn-sm">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Store
        </button>
      </div>
      <div class="sa-card" style="padding: 0; overflow: hidden;">
        <table class="sa-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Code</th>
              <th>Address</th>
              <th>Phone</th>
              <th>Default</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <template x-for="store in stores" :key="store.code">
              <tr>
                <td style="font-weight: 600;" x-text="store.name"></td>
                <td><span class="sa-table-code" x-text="store.code"></span></td>
                <td x-text="store.address"></td>
                <td style="white-space: nowrap;" x-text="store.phone"></td>
                <td>
                  <template x-if="store.isDefault">
                    <span class="sa-default-tag">Default</span>
                  </template>
                  <template x-if="!store.isDefault">
                    <span style="color: var(--sa-text-muted); font-size: 12px;">&mdash;</span>
                  </template>
                </td>
                <td>
                  <span class="sa-badge sa-badge-green">
                    <span class="sa-badge-dot"></span>
                    <span x-text="store.status"></span>
                  </span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- BRANDING -->
    <div x-show="activeSection === 'branding'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">Branding</div>
          <div class="sa-section-desc">Customise your company logo and brand colours</div>
        </div>
      </div>
      <div class="sa-card">
        <div class="sa-form-row">
          <div class="sa-form-group">
            <label class="sa-form-label">Company Logo</label>
            <div class="sa-upload-zone">
              <svg class="sa-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <span class="sa-upload-label">Upload logo</span>
            </div>
            <div class="sa-form-hint">PNG or SVG, max 2MB. Recommended 400x100px.</div>
          </div>
          <div class="sa-form-group">
            <label class="sa-form-label">Favicon</label>
            <div class="sa-upload-zone" style="width: 100px; height: 100px;">
              <svg class="sa-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2"/><circle cx="12" cy="12" r="4"/></svg>
              <span class="sa-upload-label">Upload</span>
            </div>
            <div class="sa-form-hint">ICO or PNG, 32x32px.</div>
          </div>
        </div>
        <div class="sa-form-row" style="margin-top: 20px;">
          <div class="sa-form-group">
            <label class="sa-form-label">Primary Colour</label>
            <div class="sa-colour-picker">
              <div class="sa-colour-swatch" :style="'background:' + forms.branding.primaryColour"></div>
              <input type="text" class="sa-input" style="width: 120px;" x-model="forms.branding.primaryColour" @input="markDirty()" />
            </div>
          </div>
          <div class="sa-form-group">
            <label class="sa-form-label">Secondary Colour</label>
            <div class="sa-colour-picker">
              <div class="sa-colour-swatch" :style="'background:' + forms.branding.secondaryColour"></div>
              <input type="text" class="sa-input" style="width: 120px;" x-model="forms.branding.secondaryColour" @input="markDirty()" />
            </div>
          </div>
        </div>
      </div>
      <div style="display: flex; justify-content: flex-end;">
        <button class="sa-btn sa-btn-primary" @click="save()">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
          Save Changes
        </button>
      </div>
    </div>

    <!-- MODULES -->
    <div x-show="activeSection === 'modules'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">Modules</div>
          <div class="sa-section-desc">Enable or disable application modules</div>
        </div>
      </div>
      <div class="sa-card">
        <div class="sa-module-grid">
          <template x-for="mod in modules" :key="mod.key">
            <div class="sa-module-card" :class="{ enabled: mod.enabled, locked: mod.locked }">
              <div class="sa-module-info">
                <svg class="sa-module-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <template x-if="mod.icon === 'users'"><g><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4-4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></g></template>
                  <template x-if="mod.icon === 'file-text'"><g><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></g></template>
                  <template x-if="mod.icon === 'credit-card'"><g><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></g></template>
                  <template x-if="mod.icon === 'package'"><g><path d="M16.5 9.4l-9-5.19"/><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></g></template>
                  <template x-if="mod.icon === 'hard-hat'"><g><path d="M2 18a1 1 0 001 1h18a1 1 0 001-1v-2a1 1 0 00-1-1H3a1 1 0 00-1 1v2z"/><path d="M10 15V6a2 2 0 012-2v0a2 2 0 012 2v9"/><path d="M4 15v-3a8 8 0 0116 0v3"/></g></template>
                  <template x-if="mod.icon === 'folder'"><g><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></g></template>
                  <template x-if="mod.icon === 'clipboard-check'"><g><path d="M9 2h6v4H9z"/><rect x="4" y="4" width="16" height="18" rx="2"/><path d="M9 14l2 2 4-4"/></g></template>
                  <template x-if="mod.icon === 'truck'"><g><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></g></template>
                  <template x-if="mod.icon === 'message-circle'"><g><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></g></template>
                  <template x-if="mod.icon === 'webhook'"><g><path d="M18 16.98h-5.99c-1.66 0-3.01-1.34-3.01-3s1.35-3 3.01-3H18"/><path d="M6 16.98H5.99c-1.66 0-3.01-1.34-3.01-3s1.35-3 3.01-3H6"/><path d="M12 7c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3z"/></g></template>
                </svg>
                <span class="sa-module-name" x-text="mod.name"></span>
                <template x-if="mod.locked">
                  <span class="sa-module-lock">Always on</span>
                </template>
              </div>
              <div class="sa-toggle" :class="{ on: mod.enabled, locked: mod.locked }" @click="!mod.locked && (mod.enabled = !mod.enabled, markDirty())">
                <div class="sa-toggle-knob"></div>
              </div>
            </div>
          </template>
        </div>
      </div>
      <div style="display: flex; justify-content: flex-end;">
        <button class="sa-btn sa-btn-primary" @click="save()">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
          Save Changes
        </button>
      </div>
    </div>

    <!-- SECURITY SETTINGS -->
    <div x-show="activeSection === 'security'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">Security Settings</div>
          <div class="sa-section-desc">Password policies, session management, and two-factor authentication</div>
        </div>
      </div>
      <div class="sa-card">
        <div class="sa-card-title">Password Policy</div>
        <div class="sa-form-row">
          <div class="sa-form-group">
            <label class="sa-form-label">Minimum Length</label>
            <input type="number" class="sa-input sa-input-narrow" x-model.number="forms.security.passwordMinLength" @input="markDirty()" min="6" max="128" />
          </div>
          <div class="sa-form-group">
            <label class="sa-form-label">Max Login Attempts</label>
            <input type="number" class="sa-input sa-input-narrow" x-model.number="forms.security.maxLoginAttempts" @input="markDirty()" min="1" max="20" />
          </div>
        </div>
        <div class="sa-toggle-row">
          <div>
            <div class="sa-toggle-label">Require Uppercase Letter</div>
          </div>
          <div class="sa-toggle" :class="{ on: forms.security.requireUppercase }" @click="forms.security.requireUppercase = !forms.security.requireUppercase; markDirty()">
            <div class="sa-toggle-knob"></div>
          </div>
        </div>
        <div class="sa-toggle-row">
          <div>
            <div class="sa-toggle-label">Require Number</div>
          </div>
          <div class="sa-toggle" :class="{ on: forms.security.requireNumber }" @click="forms.security.requireNumber = !forms.security.requireNumber; markDirty()">
            <div class="sa-toggle-knob"></div>
          </div>
        </div>
        <div class="sa-toggle-row">
          <div>
            <div class="sa-toggle-label">Require Symbol</div>
          </div>
          <div class="sa-toggle" :class="{ on: forms.security.requireSymbol }" @click="forms.security.requireSymbol = !forms.security.requireSymbol; markDirty()">
            <div class="sa-toggle-knob"></div>
          </div>
        </div>
      </div>
      <div class="sa-card">
        <div class="sa-card-title">Session &amp; Authentication</div>
        <div class="sa-form-row">
          <div class="sa-form-group">
            <label class="sa-form-label">Session Timeout (minutes)</label>
            <input type="number" class="sa-input sa-input-narrow" x-model.number="forms.security.sessionTimeout" @input="markDirty()" min="5" max="1440" />
          </div>
          <div class="sa-form-group">
            <label class="sa-form-label">Lockout Duration (minutes)</label>
            <input type="number" class="sa-input sa-input-narrow" x-model.number="forms.security.lockoutDuration" @input="markDirty()" min="1" max="1440" />
          </div>
        </div>
        <div class="sa-toggle-row">
          <div>
            <div class="sa-toggle-label">Two-Factor Authentication Required</div>
            <div class="sa-toggle-desc">All users must enable 2FA to access their accounts</div>
          </div>
          <div class="sa-toggle" :class="{ on: forms.security.twoFactorRequired }" @click="forms.security.twoFactorRequired = !forms.security.twoFactorRequired; markDirty()">
            <div class="sa-toggle-knob"></div>
          </div>
        </div>
      </div>
      <div style="display: flex; justify-content: flex-end;">
        <button class="sa-btn sa-btn-primary" @click="save()">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
          Save Changes
        </button>
      </div>
    </div>

    <!-- GENERAL PREFERENCES -->
    <div x-show="activeSection === 'general'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">General Preferences</div>
          <div class="sa-section-desc">Application-wide display and behaviour settings</div>
        </div>
      </div>
      <div class="sa-card">
        <div class="sa-form-row">
          <div class="sa-form-group">
            <label class="sa-form-label">Default Items Per Page</label>
            <select class="sa-select" x-model="forms.general.perPage" @change="markDirty()">
              <option value="10">10</option>
              <option value="20">20</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>
          <div class="sa-form-group">
            <label class="sa-form-label">Default Opportunity Prefix</label>
            <input type="text" class="sa-input" style="width: 120px;" x-model="forms.general.oppPrefix" @input="markDirty()" />
          </div>
        </div>
        <div class="sa-form-row">
          <div class="sa-form-group">
            <label class="sa-form-label">Default Invoice Prefix</label>
            <input type="text" class="sa-input" style="width: 120px;" x-model="forms.general.invPrefix" @input="markDirty()" />
          </div>
          <div class="sa-form-group">
            <label class="sa-form-label">Number Padding</label>
            <select class="sa-select" x-model="forms.general.numberPadding" @change="markDirty()">
              <option value="4">4 digits (0001)</option>
              <option value="5">5 digits (00001)</option>
              <option value="6">6 digits (000001)</option>
              <option value="7">7 digits (0000001)</option>
            </select>
          </div>
        </div>
      </div>
      <div style="display: flex; justify-content: flex-end;">
        <button class="sa-btn sa-btn-primary" @click="save()">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
          Save Changes
        </button>
      </div>
    </div>

    <!-- EMAIL SETTINGS -->
    <div x-show="activeSection === 'email'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">Email Settings</div>
          <div class="sa-section-desc">Configure outgoing email transport and defaults</div>
        </div>
        <div class="sa-connection-status">
          <span class="sa-connection-dot" :class="forms.email.connectionStatus === 'connected' ? 'green' : 'red'"></span>
          <span x-text="forms.email.connectionStatus === 'connected' ? 'Connected' : 'Not tested'" :style="'color:' + (forms.email.connectionStatus === 'connected' ? 'var(--sa-green)' : 'var(--sa-red)')"></span>
        </div>
      </div>
      <div class="sa-card">
        <div class="sa-form-group">
          <label class="sa-form-label">Mailer</label>
          <select class="sa-select" x-model="forms.email.mailer" @change="markDirty()" style="width: 240px;">
            <option value="smtp">SMTP</option>
            <option value="ses">Amazon SES</option>
            <option value="mailgun">Mailgun</option>
            <option value="postmark">Postmark</option>
            <option value="log">Log (testing only)</option>
          </select>
        </div>

        <!-- SMTP fields -->
        <template x-if="forms.email.mailer === 'smtp'">
          <div class="sa-fade-in">
            <div class="sa-form-row">
              <div class="sa-form-group">
                <label class="sa-form-label">SMTP Host</label>
                <input type="text" class="sa-input" x-model="forms.email.smtpHost" @input="markDirty()" />
              </div>
              <div class="sa-form-group">
                <label class="sa-form-label">Port</label>
                <input type="number" class="sa-input sa-input-narrow" x-model.number="forms.email.smtpPort" @input="markDirty()" />
              </div>
            </div>
            <div class="sa-form-row">
              <div class="sa-form-group">
                <label class="sa-form-label">Username</label>
                <input type="text" class="sa-input" x-model="forms.email.smtpUsername" @input="markDirty()" />
              </div>
              <div class="sa-form-group">
                <label class="sa-form-label">Password</label>
                <input type="password" class="sa-input sa-input-password" x-model="forms.email.smtpPassword" @input="markDirty()" />
              </div>
            </div>
            <div class="sa-form-group">
              <label class="sa-form-label">Encryption</label>
              <div class="sa-radio-pills">
                <div class="sa-radio-pill" :class="{ active: forms.email.smtpEncryption === 'tls' }" @click="forms.email.smtpEncryption = 'tls'; markDirty()">TLS</div>
                <div class="sa-radio-pill" :class="{ active: forms.email.smtpEncryption === 'ssl' }" @click="forms.email.smtpEncryption = 'ssl'; markDirty()">SSL</div>
                <div class="sa-radio-pill" :class="{ active: forms.email.smtpEncryption === 'none' }" @click="forms.email.smtpEncryption = 'none'; markDirty()">None</div>
              </div>
            </div>
          </div>
        </template>

        <!-- SES fields -->
        <template x-if="forms.email.mailer === 'ses'">
          <div class="sa-fade-in">
            <div class="sa-form-row">
              <div class="sa-form-group">
                <label class="sa-form-label">Access Key</label>
                <input type="text" class="sa-input" x-model="forms.email.sesKey" @input="markDirty()" />
              </div>
              <div class="sa-form-group">
                <label class="sa-form-label">Secret Key</label>
                <input type="password" class="sa-input sa-input-password" x-model="forms.email.sesSecret" @input="markDirty()" />
              </div>
            </div>
            <div class="sa-form-group">
              <label class="sa-form-label">Region</label>
              <select class="sa-select" style="width: 240px;" x-model="forms.email.sesRegion" @change="markDirty()">
                <option value="us-east-1">us-east-1</option>
                <option value="us-west-2">us-west-2</option>
                <option value="eu-west-1">eu-west-1</option>
                <option value="eu-west-2">eu-west-2</option>
              </select>
            </div>
          </div>
        </template>

        <!-- Mailgun fields -->
        <template x-if="forms.email.mailer === 'mailgun'">
          <div class="sa-fade-in">
            <div class="sa-form-row">
              <div class="sa-form-group">
                <label class="sa-form-label">Domain</label>
                <input type="text" class="sa-input" x-model="forms.email.mailgunDomain" @input="markDirty()" />
              </div>
              <div class="sa-form-group">
                <label class="sa-form-label">Secret</label>
                <input type="password" class="sa-input sa-input-password" x-model="forms.email.mailgunSecret" @input="markDirty()" />
              </div>
            </div>
          </div>
        </template>

        <!-- Postmark fields -->
        <template x-if="forms.email.mailer === 'postmark'">
          <div class="sa-fade-in">
            <div class="sa-form-group">
              <label class="sa-form-label">Server Token</label>
              <input type="password" class="sa-input sa-input-password" style="width: 320px;" x-model="forms.email.postmarkToken" @input="markDirty()" />
            </div>
          </div>
        </template>

        <div style="border-top: 1px solid var(--sa-border-subtle); margin-top: 20px; padding-top: 20px;">
          <div class="sa-form-row">
            <div class="sa-form-group">
              <label class="sa-form-label">From Address</label>
              <input type="email" class="sa-input" x-model="forms.email.fromAddress" @input="markDirty()" />
            </div>
            <div class="sa-form-group">
              <label class="sa-form-label">From Name</label>
              <input type="text" class="sa-input" x-model="forms.email.fromName" @input="markDirty()" />
            </div>
          </div>
          <div class="sa-form-group">
            <label class="sa-form-label">Reply-To Address</label>
            <input type="email" class="sa-input" style="width: 320px;" x-model="forms.email.replyTo" @input="markDirty()" />
          </div>
        </div>
      </div>
      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button class="sa-btn sa-btn-secondary" @click="sendTestEmail()">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4z"/></svg>
          Send Test Email
        </button>
        <button class="sa-btn sa-btn-primary" @click="save()">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
          Save Changes
        </button>
      </div>
    </div>

    <!-- PRODUCT GROUPS (Static Data) -->
    <div x-show="activeSection === 'product-groups'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">Product Groups</div>
          <div class="sa-section-desc">Organise products into categories for reporting and filtering</div>
        </div>
        <button class="sa-btn sa-btn-primary sa-btn-sm">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Group
        </button>
      </div>
      <div class="sa-card" style="padding: 0; overflow: hidden;">
        <table class="sa-table">
          <thead><tr><th>Name</th><th>Code</th><th>Products</th><th>Status</th></tr></thead>
          <tbody>
            <template x-for="pg in productGroups" :key="pg.code">
              <tr>
                <td style="font-weight: 600;" x-text="pg.name"></td>
                <td><span class="sa-table-code" x-text="pg.code"></span></td>
                <td x-text="pg.count"></td>
                <td><span class="sa-badge sa-badge-green"><span class="sa-badge-dot"></span>Active</span></td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- REVENUE GROUPS (Static Data) -->
    <div x-show="activeSection === 'revenue-groups'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">Revenue Groups</div>
          <div class="sa-section-desc">Group revenue by category for financial reporting</div>
        </div>
        <button class="sa-btn sa-btn-primary sa-btn-sm">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Group
        </button>
      </div>
      <div class="sa-card" style="padding: 0; overflow: hidden;">
        <table class="sa-table">
          <thead><tr><th>Name</th><th>Code</th><th>Status</th></tr></thead>
          <tbody>
            <template x-for="rg in revenueGroups" :key="rg.code">
              <tr>
                <td style="font-weight: 600;" x-text="rg.name"></td>
                <td><span class="sa-table-code" x-text="rg.code"></span></td>
                <td><span class="sa-badge sa-badge-green"><span class="sa-badge-dot"></span>Active</span></td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAGS (Static Data) -->
    <div x-show="activeSection === 'tags'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">Tags</div>
          <div class="sa-section-desc">Manage tags used across the application</div>
        </div>
        <button class="sa-btn sa-btn-primary sa-btn-sm">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Tag
        </button>
      </div>
      <div class="sa-card" style="padding: 0; overflow: hidden;">
        <table class="sa-table">
          <thead><tr><th>Name</th><th>Colour</th><th>Used By</th></tr></thead>
          <tbody>
            <template x-for="tag in tags" :key="tag.name">
              <tr>
                <td style="font-weight: 600;" x-text="tag.name"></td>
                <td>
                  <span style="display: inline-flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; border-radius: 3px;" :style="'background:' + tag.colour"></span>
                    <span class="sa-colour-value" x-text="tag.colour"></span>
                  </span>
                </td>
                <td x-text="tag.count + ' items'"></td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAXATION -->
    <div x-show="activeSection === 'taxation'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">Taxation</div>
          <div class="sa-section-desc">Tax classes, rates, and rules matrix</div>
        </div>
      </div>

      <div class="sa-tab-bar">
        <div class="sa-tab" :class="{ active: taxTab === 'classes' }" @click="taxTab = 'classes'">Tax Classes</div>
        <div class="sa-tab" :class="{ active: taxTab === 'rates' }" @click="taxTab = 'rates'">Tax Rates</div>
        <div class="sa-tab" :class="{ active: taxTab === 'rules' }" @click="taxTab = 'rules'">Tax Rules</div>
      </div>

      <!-- Tax Classes -->
      <div x-show="taxTab === 'classes'" class="sa-fade-in">
        <div class="sa-split-tables">
          <div class="sa-card">
            <div class="sa-card-title">Organisation Tax Classes</div>
            <table class="sa-table">
              <thead><tr><th>Name</th><th>Default</th></tr></thead>
              <tbody>
                <template x-for="tc in taxOrgClasses" :key="tc.name">
                  <tr>
                    <td style="font-weight: 600;" x-text="tc.name"></td>
                    <td>
                      <template x-if="tc.isDefault"><span class="sa-default-tag">Default</span></template>
                      <template x-if="!tc.isDefault"><span style="color: var(--sa-text-muted);">&mdash;</span></template>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
          <div class="sa-card">
            <div class="sa-card-title">Product Tax Classes</div>
            <table class="sa-table">
              <thead><tr><th>Name</th><th>Default</th></tr></thead>
              <tbody>
                <template x-for="tc in taxProductClasses" :key="tc.name">
                  <tr>
                    <td style="font-weight: 600;" x-text="tc.name"></td>
                    <td>
                      <template x-if="tc.isDefault"><span class="sa-default-tag">Default</span></template>
                      <template x-if="!tc.isDefault"><span style="color: var(--sa-text-muted);">&mdash;</span></template>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Tax Rates -->
      <div x-show="taxTab === 'rates'" class="sa-fade-in">
        <div class="sa-card" style="padding: 0; overflow: hidden;">
          <table class="sa-table">
            <thead>
              <tr><th>Name</th><th>Rate</th><th>Compound</th><th>Active</th></tr>
            </thead>
            <tbody>
              <template x-for="rate in taxRates" :key="rate.name">
                <tr>
                  <td style="font-weight: 600;" x-text="rate.name"></td>
                  <td><span class="sa-table-code" x-text="rate.rate + '%'"></span></td>
                  <td>
                    <div class="sa-toggle" :class="{ on: rate.compound }" @click="rate.compound = !rate.compound; markDirty()" style="margin: 0;">
                      <div class="sa-toggle-knob"></div>
                    </div>
                  </td>
                  <td>
                    <div class="sa-toggle" :class="{ on: rate.active }" @click="rate.active = !rate.active; markDirty()" style="margin: 0;">
                      <div class="sa-toggle-knob"></div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tax Rules Matrix -->
      <div x-show="taxTab === 'rules'" class="sa-fade-in">
        <div class="sa-card">
          <div class="sa-card-title">Tax Rule Matrix</div>
          <p style="font-size: 12px; color: var(--sa-text-muted); margin-bottom: 16px;">Click a cell to change the applied tax rate for each organisation / product class combination.</p>
          <div class="sa-matrix">
            <table>
              <thead>
                <tr>
                  <th>Org \ Product</th>
                  <template x-for="pc in taxProductClasses" :key="pc.name">
                    <th x-text="pc.name"></th>
                  </template>
                </tr>
              </thead>
              <tbody>
                <template x-for="(oc, oi) in taxOrgClasses" :key="oc.name">
                  <tr>
                    <td x-text="oc.name"></td>
                    <template x-for="(pc, pi) in taxProductClasses" :key="pc.name">
                      <td>
                        <select class="sa-matrix-select" x-model="taxRuleMatrix[oi][pi]" @change="markDirty()">
                          <template x-for="rate in taxRates" :key="rate.name">
                            <option :value="rate.name" x-text="rate.name + ' (' + rate.rate + '%)'"></option>
                          </template>
                          <option value="none">None</option>
                        </select>
                      </td>
                    </template>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
        <button class="sa-btn sa-btn-primary" @click="save()">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
          Save Changes
        </button>
      </div>
    </div>

    <!-- ACTION LOG -->
    <div x-show="activeSection === 'action-log'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">Action Log</div>
          <div class="sa-section-desc">Audit trail of recent changes across the system</div>
        </div>
      </div>
      <div class="sa-card" style="padding: 0; overflow: hidden;">
        <table class="sa-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>User</th>
              <th>Action</th>
              <th>Entity</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <template x-for="log in actionLogs" :key="log.time + log.action + log.entity">
              <tr>
                <td style="white-space: nowrap; color: var(--sa-text-muted); font-size: 12px;" x-text="log.time"></td>
                <td style="font-weight: 500;" x-text="log.user"></td>
                <td><span class="sa-action-verb" :class="log.action" x-text="log.action"></span></td>
                <td><span class="sa-action-entity" x-text="log.entity"></span></td>
                <td style="font-size: 12px; color: var(--sa-text-secondary);" x-text="log.details"></td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SYSTEM HEALTH -->
    <div x-show="activeSection === 'health'" x-transition class="sa-fade-in">
      <div class="sa-section-header">
        <div>
          <div class="sa-section-title">System Health</div>
          <div class="sa-section-desc">Service connection status and diagnostics</div>
        </div>
        <button class="sa-btn sa-btn-secondary sa-btn-sm" @click="refreshHealth()">
          <svg class="sa-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
          Refresh
        </button>
      </div>
      <div class="sa-health-grid">
        <template x-for="svc in healthServices" :key="svc.name">
          <div class="sa-health-card">
            <div class="sa-health-top">
              <span class="sa-health-name" x-text="svc.name"></span>
              <span class="sa-health-dot" :class="'sa-health-dot-' + svc.status"></span>
            </div>
            <div class="sa-health-status" :style="'color: var(--sa-' + svc.status + ')'">
              <span x-text="svc.statusLabel"></span>
            </div>
            <div class="sa-health-time" x-text="'Last checked: ' + svc.lastChecked"></div>
          </div>
        </template>
      </div>
    </div>
  </main>

  <!-- ============================================================ -->
  <!-- UNSAVED CHANGES BAR                                           -->
  <!-- ============================================================ -->
  <div class="sa-unsaved-bar" x-show="isDirty" x-transition>
    <span class="sa-unsaved-text">
      <span class="sa-unsaved-dot"></span>
      You have unsaved changes
    </span>
    <div style="display: flex; gap: 8px;">
      <button class="sa-btn sa-btn-secondary" style="background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.2);" @click="discard()">Discard</button>
      <button class="sa-btn sa-btn-primary" @click="save()">Save Changes</button>
    </div>
  </div>

  <!-- ============================================================ -->
  <!-- TOAST NOTIFICATION                                            -->
  <!-- ============================================================ -->
  <div class="sa-toast" x-show="showToast" x-transition:enter="saToastIn" x-transition:leave="saToastOut">
    <svg class="sa-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <span x-text="toastMessage"></span>
  </div>
</div>

@verbatim
<script>
function settingsAdmin() {
  return {
    /* ============================================================ */
    /*  NAVIGATION                                                   */
    /* ============================================================ */
    activeSection: 'company',
    taxTab: 'classes',
    isDirty: false,
    showToast: false,
    toastMessage: '',

    months: ['January','February','March','April','May','June','July','August','September','October','November','December'],

    navGroups: [
      {
        label: 'Account',
        items: [
          { key: 'company', label: 'Company Details', icon: 'building' },
          { key: 'stores', label: 'Stores', icon: 'store' },
          { key: 'branding', label: 'Branding', icon: 'palette' },
          { key: 'modules', label: 'Modules', icon: 'blocks' },
        ],
      },
      {
        label: 'Users & Security',
        items: [
          { key: 'security', label: 'Security Settings', icon: 'shield' },
        ],
      },
      {
        label: 'Preferences',
        items: [
          { key: 'general', label: 'General', icon: 'sliders' },
          { key: 'email', label: 'Email Settings', icon: 'mail' },
        ],
      },
      {
        label: 'Static Data',
        items: [
          { key: 'product-groups', label: 'Product Groups', icon: 'layers' },
          { key: 'revenue-groups', label: 'Revenue Groups', icon: 'layers' },
          { key: 'tags', label: 'Tags', icon: 'tags' },
        ],
      },
      {
        label: 'Taxation',
        items: [
          { key: 'taxation', label: 'Tax Configuration', icon: 'percent' },
        ],
      },
      {
        label: 'System',
        items: [
          { key: 'action-log', label: 'Action Log', icon: 'clock' },
          { key: 'health', label: 'System Health', icon: 'heart-pulse' },
        ],
      },
    ],

    /* ============================================================ */
    /*  FORM DATA                                                    */
    /* ============================================================ */
    forms: {
      company: {
        name: 'Voltage Software Ltd',
        country: 'GB',
        timezone: 'Europe/London',
        currency: 'GBP',
        dateFormat: 'DD/MM/YYYY',
        timeFormat: '24h',
        fiscalStart: 'January',
      },
      branding: {
        primaryColour: '#059669',
        secondaryColour: '#2563eb',
      },
      security: {
        passwordMinLength: 12,
        requireUppercase: true,
        requireNumber: true,
        requireSymbol: false,
        sessionTimeout: 120,
        twoFactorRequired: false,
        maxLoginAttempts: 5,
        lockoutDuration: 15,
      },
      general: {
        perPage: '20',
        oppPrefix: 'OPP-',
        invPrefix: 'INV-',
        numberPadding: '7',
      },
      email: {
        mailer: 'smtp',
        smtpHost: 'smtp.mailgun.org',
        smtpPort: 587,
        smtpUsername: 'postmaster@mg.voltage.software',
        smtpPassword: 'secret-password-here',
        smtpEncryption: 'tls',
        sesKey: '',
        sesSecret: '',
        sesRegion: 'eu-west-1',
        mailgunDomain: '',
        mailgunSecret: '',
        postmarkToken: '',
        fromAddress: 'noreply@voltage.software',
        fromName: 'Voltage Software',
        replyTo: 'support@voltage.software',
        connectionStatus: 'connected',
      },
    },

    /* ============================================================ */
    /*  STORES                                                       */
    /* ============================================================ */
    stores: [
      { name: 'London HQ', code: 'LON', address: '42 Bermondsey St, London SE1', phone: '+44 20 7946 0958', isDefault: true, status: 'Active' },
      { name: 'Manchester', code: 'MAN', address: '15 Deansgate, Manchester M3', phone: '+44 161 496 0123', isDefault: false, status: 'Active' },
      { name: 'Edinburgh', code: 'EDI', address: '8 Princes St, Edinburgh EH2', phone: '+44 131 496 0456', isDefault: false, status: 'Active' },
    ],

    /* ============================================================ */
    /*  MODULES                                                      */
    /* ============================================================ */
    modules: [
      { key: 'crm', name: 'CRM / Members', icon: 'users', enabled: true, locked: true },
      { key: 'opportunities', name: 'Opportunities / Quoting', icon: 'file-text', enabled: true, locked: false },
      { key: 'invoicing', name: 'Invoicing & Payments', icon: 'credit-card', enabled: true, locked: false },
      { key: 'stock', name: 'Stock / Inventory', icon: 'package', enabled: true, locked: false },
      { key: 'crew', name: 'Crew & Services', icon: 'hard-hat', enabled: false, locked: false },
      { key: 'projects', name: 'Projects', icon: 'folder', enabled: false, locked: false },
      { key: 'inspections', name: 'Inspections & Compliance', icon: 'clipboard-check', enabled: true, locked: false },
      { key: 'sub-hire', name: 'Sub-Hire / Shortage Resolution', icon: 'truck', enabled: false, locked: false },
      { key: 'discussions', name: 'Discussions', icon: 'message-circle', enabled: true, locked: false },
      { key: 'webhooks', name: 'Webhooks', icon: 'webhook', enabled: true, locked: false },
    ],

    /* ============================================================ */
    /*  STATIC DATA                                                  */
    /* ============================================================ */
    productGroups: [
      { name: 'Audio', code: 'AUD', count: 142 },
      { name: 'Lighting', code: 'LIG', count: 236 },
      { name: 'Video', code: 'VID', count: 89 },
      { name: 'Staging', code: 'STG', count: 54 },
      { name: 'Power & Distribution', code: 'PWR', count: 78 },
      { name: 'Rigging', code: 'RIG', count: 61 },
      { name: 'Consumables', code: 'CON', count: 33 },
    ],

    revenueGroups: [
      { name: 'Equipment Hire', code: 'HIRE' },
      { name: 'Labour', code: 'LABR' },
      { name: 'Transport', code: 'TRNS' },
      { name: 'Sales', code: 'SALE' },
      { name: 'Consumables', code: 'CONS' },
    ],

    tags: [
      { name: 'VIP Client', colour: '#7c3aed', count: 12 },
      { name: 'Festival', colour: '#d97706', count: 8 },
      { name: 'Corporate', colour: '#2563eb', count: 34 },
      { name: 'Wedding', colour: '#ec4899', count: 21 },
      { name: 'Recurring', colour: '#059669', count: 15 },
      { name: 'Urgent', colour: '#dc2626', count: 3 },
    ],

    /* ============================================================ */
    /*  TAXATION                                                     */
    /* ============================================================ */
    taxOrgClasses: [
      { name: 'Domestic', isDefault: true },
      { name: 'EU', isDefault: false },
      { name: 'Non-EU', isDefault: false },
      { name: 'Exempt', isDefault: false },
    ],

    taxProductClasses: [
      { name: 'Standard Rated', isDefault: true },
      { name: 'Zero Rated', isDefault: false },
      { name: 'Exempt', isDefault: false },
      { name: 'Reduced Rate', isDefault: false },
    ],

    taxRates: [
      { name: 'UK Standard', rate: 20, compound: false, active: true },
      { name: 'UK Reduced', rate: 5, compound: false, active: true },
      { name: 'Zero Rate', rate: 0, compound: false, active: true },
      { name: 'EU Standard', rate: 21, compound: false, active: true },
      { name: 'EU Reduced', rate: 9, compound: false, active: true },
    ],

    taxRuleMatrix: [
      ['UK Standard', 'Zero Rate', 'none', 'UK Reduced'],
      ['EU Standard', 'Zero Rate', 'none', 'EU Reduced'],
      ['none', 'none', 'none', 'none'],
      ['none', 'none', 'none', 'none'],
    ],

    /* ============================================================ */
    /*  ACTION LOG                                                   */
    /* ============================================================ */
    actionLogs: [
      { time: '5 min ago', user: 'Ben Thompson', action: 'updated', entity: 'Settings', details: 'Changed email.smtp_host' },
      { time: '1 hour ago', user: 'Sarah Mitchell', action: 'created', entity: 'Member', details: 'Acme Events Ltd' },
      { time: '2 hours ago', user: 'James Wilson', action: 'issued', entity: 'Invoice', details: 'INV-0000089' },
      { time: '3 hours ago', user: 'Ben Thompson', action: 'updated', entity: 'Role', details: 'Operations Manager \u2014 added stock.transfer' },
      { time: 'Yesterday', user: 'Emily Chen', action: 'confirmed', entity: 'Opportunity', details: 'OPP-0000042' },
      { time: 'Yesterday', user: 'Tom Baker', action: 'created', entity: 'Opportunity', details: 'LED Wall Hire' },
      { time: '2 days ago', user: 'Ben Thompson', action: 'updated', entity: 'Settings', details: 'Changed security.password_min_length' },
      { time: '3 days ago', user: 'Sarah Mitchell', action: 'invited', entity: 'User', details: 'rachel@company.com' },
    ],

    /* ============================================================ */
    /*  SYSTEM HEALTH                                                */
    /* ============================================================ */
    healthServices: [
      { name: 'Database', status: 'green', statusLabel: 'Connected', lastChecked: '2 min ago' },
      { name: 'Redis', status: 'green', statusLabel: 'Connected', lastChecked: '2 min ago' },
      { name: 'S3 Storage', status: 'amber', statusLabel: 'Untested', lastChecked: 'Never' },
      { name: 'Queue', status: 'green', statusLabel: 'Processing', lastChecked: '1 min ago' },
      { name: 'Email', status: 'green', statusLabel: 'Connected', lastChecked: '5 min ago' },
    ],

    /* ============================================================ */
    /*  METHODS                                                      */
    /* ============================================================ */
    switchSection(key) {
      if (this.isDirty) {
        if (!confirm('You have unsaved changes. Discard them?')) {
          return;
        }
        this.isDirty = false;
      }
      this.activeSection = key;
    },

    markDirty() {
      this.isDirty = true;
    },

    discard() {
      this.isDirty = false;
    },

    save() {
      this.isDirty = false;
      this.toastMessage = 'Settings saved successfully';
      this.showToast = true;
      setTimeout(() => { this.showToast = false; }, 2500);
    },

    sendTestEmail() {
      this.toastMessage = 'Test email sent successfully';
      this.showToast = true;
      this.forms.email.connectionStatus = 'connected';
      setTimeout(() => { this.showToast = false; }, 2500);
    },

    refreshHealth() {
      this.healthServices = this.healthServices.map(svc => ({
        ...svc,
        lastChecked: 'Just now',
      }));
      this.toastMessage = 'Health checks refreshed';
      this.showToast = true;
      setTimeout(() => { this.showToast = false; }, 2500);
    },
  };
}
</script>
@endverbatim
