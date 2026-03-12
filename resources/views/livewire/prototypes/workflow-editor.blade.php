<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.editor')] #[Title('Workflow Editor')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  WORKFLOW TOKENS — maps to brand system in app.css               */
  /* ================================================================ */
  :root {
    --wf-bg: var(--content-bg);
    --wf-panel: var(--card-bg);
    --wf-surface: var(--base);
    --wf-border: var(--card-border);
    --wf-border-subtle: var(--grey-border);
    --wf-text: var(--text-primary);
    --wf-text-secondary: var(--text-secondary);
    --wf-text-muted: var(--text-muted);
    --wf-accent: var(--green);
    --wf-accent-dim: var(--green-muted);
    --wf-hover: rgba(0, 0, 0, 0.04);
    --wf-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    --wf-trigger-color: var(--violet);
    --wf-trigger-bg: rgba(124, 58, 237, 0.08);
    --wf-action-color: var(--blue);
    --wf-action-bg: rgba(37, 99, 235, 0.06);
    --wf-condition-color: var(--amber);
    --wf-condition-bg: rgba(217, 119, 6, 0.06);
    --wf-delay-color: var(--cyan);
    --wf-delay-bg: rgba(8, 145, 178, 0.06);
    --wf-halt-color: var(--red);
    --wf-halt-bg: rgba(220, 38, 38, 0.06);
    --wf-branch-yes: var(--green);
    --wf-branch-no: var(--red);
    --wf-line-color: var(--grey-light);
    --wf-line-width: 1.5px;
    --wf-topbar-h: 52px;
    --wf-sidebar-w: 280px;
    --wf-config-w: 340px;
    --wf-node-w: 320px;
  }

  .dark {
    --wf-bg: var(--content-bg);
    --wf-panel: var(--card-bg);
    --wf-surface: var(--navy-mid);
    --wf-border: var(--card-border);
    --wf-border-subtle: #283040;
    --wf-text: var(--text-primary);
    --wf-text-secondary: var(--text-secondary);
    --wf-text-muted: var(--text-muted);
    --wf-accent: var(--green);
    --wf-accent-dim: rgba(5, 150, 105, 0.12);
    --wf-hover: rgba(255, 255, 255, 0.06);
    --wf-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --wf-trigger-bg: rgba(124, 58, 237, 0.15);
    --wf-action-bg: rgba(37, 99, 235, 0.12);
    --wf-condition-bg: rgba(217, 119, 6, 0.12);
    --wf-delay-bg: rgba(8, 145, 178, 0.12);
    --wf-halt-bg: rgba(220, 38, 38, 0.12);
  }

  /* ================================================================ */
  /*  TOP BAR                                                          */
  /* ================================================================ */
  .wf-topbar {
    height: var(--wf-topbar-h);
    background: var(--navy);
    border-bottom: 1px solid var(--wf-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    z-index: 100;
    position: relative;
  }

  .wf-topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .wf-topbar-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--grey-light);
    cursor: pointer;
    transition: color 0.15s;
  }

  .wf-topbar-brand:hover { color: #ffffff; }
  .wf-topbar-brand svg { opacity: 0.5; }

  .wf-topbar-sep {
    width: 1px;
    height: 20px;
    background: rgba(255, 255, 255, 0.12);
  }

  .wf-workflow-name {
    font-size: 14px;
    font-weight: 600;
    color: #ffffff;
    background: none;
    border: 1px solid transparent;
    padding: 4px 8px;
    outline: none;
    transition: border-color 0.15s, background 0.15s;
    font-family: var(--font-mono);
    min-width: 200px;
  }

  .wf-workflow-name:hover { border-color: rgba(255, 255, 255, 0.15); }
  .wf-workflow-name:focus { border-color: var(--green); background: rgba(255, 255, 255, 0.06); }

  .wf-status-badge {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 3px 10px;
    background: rgba(5, 150, 105, 0.15);
    color: var(--green);
  }

  .wf-status-badge.draft {
    background: rgba(217, 119, 6, 0.15);
    color: var(--amber);
  }

  .wf-topbar-center {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: stretch;
    height: 100%;
  }

  .wf-topbar-tab {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 14px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--grey-light);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.15s;
    user-select: none;
    background: none;
    border-top: none;
    border-left: none;
    border-right: none;
  }

  .wf-topbar-tab:hover { color: #ffffff; }
  .wf-topbar-tab.active { color: #ffffff; border-bottom-color: var(--wf-accent); }
  .wf-topbar-tab svg { width: 14px; height: 14px; opacity: 0.5; }
  .wf-topbar-tab.active svg { opacity: 0.8; }

  .wf-topbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .wf-version {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--grey);
    padding: 0 8px;
  }

  .wf-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.06);
    color: var(--grey-light);
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
  }

  .wf-btn:hover { background: rgba(255, 255, 255, 0.1); color: #ffffff; }
  .wf-btn svg { width: 14px; height: 14px; }

  .wf-btn-primary {
    background: var(--green);
    color: #ffffff;
    border-color: var(--green);
  }

  .wf-btn-primary:hover {
    background: #06b07a;
    border-color: #06b07a;
    color: #ffffff;
  }

  /* ================================================================ */
  /*  ALERT BAR                                                        */
  /* ================================================================ */
  .wf-alert-bar {
    background: var(--blue-pale);
    border-bottom: 1px solid rgba(37, 99, 235, 0.12);
    padding: 10px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-shrink: 0;
  }

  .wf-alert-left {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--wf-text);
  }

  .wf-alert-icon {
    width: 20px;
    height: 20px;
    color: var(--blue);
    flex-shrink: 0;
  }

  .wf-alert-actions {
    display: flex;
    gap: 8px;
  }

  .wf-alert-btn {
    padding: 6px 16px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
  }

  .wf-alert-btn-outline {
    border: 1px solid var(--wf-border);
    background: var(--wf-panel);
    color: var(--wf-text-secondary);
  }

  .wf-alert-btn-outline:hover { border-color: var(--wf-text-secondary); color: var(--wf-text); }

  .wf-alert-btn-fill {
    border: 1px solid var(--green);
    background: var(--green);
    color: #ffffff;
  }

  .wf-alert-btn-fill:hover { background: #06b07a; border-color: #06b07a; }

  .dark .wf-alert-bar {
    background: rgba(37, 99, 235, 0.08);
    border-bottom-color: rgba(37, 99, 235, 0.15);
  }

  /* ================================================================ */
  /*  MAIN LAYOUT                                                      */
  /* ================================================================ */
  .wf-main {
    display: flex;
    flex: 1;
    min-height: 0;
    overflow: hidden;
  }

  /* ================================================================ */
  /*  SIDEBAR: Handler Palette                                         */
  /* ================================================================ */
  .wf-sidebar {
    width: var(--wf-sidebar-w);
    min-width: var(--wf-sidebar-w);
    background: var(--wf-panel);
    border-right: 1px solid var(--wf-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .wf-sidebar-header {
    padding: 12px 14px;
    border-bottom: 1px solid var(--wf-border-subtle);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .wf-sidebar-title {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--wf-text-muted);
  }

  .wf-sidebar-search {
    padding: 8px 12px;
    border-bottom: 1px solid var(--wf-border-subtle);
  }

  .wf-search-input {
    width: 100%;
    padding: 7px 10px 7px 32px;
    background: var(--wf-surface);
    border: 1px solid var(--wf-border);
    color: var(--wf-text);
    font-size: 12px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: 10px center;
  }

  .wf-search-input:focus { border-color: var(--wf-accent); }
  .wf-search-input::placeholder { color: var(--wf-text-muted); }

  .wf-handler-groups {
    flex: 1;
    overflow-y: auto;
    padding: 4px 0;
  }

  .wf-handler-group { margin-bottom: 1px; }

  .wf-handler-group-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    cursor: pointer;
    user-select: none;
    transition: background 0.1s;
  }

  .wf-handler-group-header:hover { background: var(--wf-hover); }

  .wf-handler-group-chevron {
    width: 16px;
    height: 16px;
    color: var(--wf-text-muted);
    transition: transform 0.15s;
    flex-shrink: 0;
  }

  .wf-handler-group.open .wf-handler-group-chevron { transform: rotate(90deg); }

  .wf-handler-group-label {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wf-text-secondary);
  }

  .wf-handler-group-count {
    font-family: var(--font-mono);
    font-size: 9px;
    color: var(--wf-text-muted);
    margin-left: auto;
    background: var(--wf-surface);
    padding: 1px 6px;
  }

  .wf-handler-group-items {
    display: none;
    padding: 0 0 4px 0;
  }

  .wf-handler-group.open .wf-handler-group-items { display: block; }

  .wf-handler-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 14px 5px 36px;
    cursor: pointer;
    transition: background 0.1s;
    position: relative;
  }

  .wf-handler-item:hover { background: var(--wf-accent-dim); }

  .wf-handler-item-icon {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .wf-handler-item-icon svg { width: 14px; height: 14px; }

  .wf-handler-item-name {
    font-size: 11px;
    font-family: var(--font-mono);
    color: var(--wf-text);
    font-weight: 400;
  }

  .wf-handler-item-add {
    opacity: 0;
    font-family: var(--font-display);
    font-size: 9px;
    color: var(--wf-accent);
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    transition: opacity 0.1s;
    position: absolute;
    right: 14px;
  }

  .wf-handler-item:hover .wf-handler-item-add { opacity: 1; }

  .wf-type-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .wf-dot-trigger { background: var(--wf-trigger-color); }
  .wf-dot-action { background: var(--wf-action-color); }
  .wf-dot-condition { background: var(--wf-condition-color); }
  .wf-dot-delay { background: var(--wf-delay-color); }
  .wf-dot-halt { background: var(--wf-halt-color); }

  /* ================================================================ */
  /*  CONTENT AREA                                                     */
  /* ================================================================ */
  .wf-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    position: relative;
  }

  .wf-canvas {
    flex: 1;
    overflow-y: auto;
    background: var(--wf-surface);
    position: relative;
  }

  .dark .wf-canvas {
    background:
      radial-gradient(circle at 50% 30%, rgba(5, 150, 105, 0.02), transparent 60%),
      var(--wf-surface);
  }

  .wf-step-list {
    max-width: 700px;
    margin: 0 auto;
    padding: 32px 24px 120px;
  }

  /* ================================================================ */
  /*  STEP NODE CARDS                                                  */
  /* ================================================================ */
  .wf-node {
    width: var(--wf-node-w);
    margin: 0 auto;
    background: var(--wf-panel);
    border: 1px solid var(--wf-border);
    position: relative;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
    animation: wfNodeIn 0.2s ease both;
  }

  .wf-node:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
    transform: translateY(-2px);
  }

  .wf-node.selected {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
  }

  .wf-node-accent {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
  }

  .wf-node-accent-trigger { background: var(--wf-trigger-color); }
  .wf-node-accent-action { background: var(--wf-action-color); }
  .wf-node-accent-condition { background: var(--wf-condition-color); }
  .wf-node-accent-delay { background: var(--wf-delay-color); }
  .wf-node-accent-halt { background: var(--wf-halt-color); }

  .wf-node-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px 0 16px;
  }

  .wf-node-type-label {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  .wf-node-subcategory {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wf-text-muted);
  }

  .wf-node-body {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 12px 10px 16px;
  }

  .wf-node-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .wf-node-icon svg { width: 16px; height: 16px; }

  .wf-node-info {
    flex: 1;
    min-width: 0;
  }

  .wf-node-name {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    color: var(--wf-text);
    line-height: 1.3;
  }

  .wf-node-desc {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 400;
    color: var(--wf-text-muted);
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* ================================================================ */
  /*  CONNECTORS                                                       */
  /* ================================================================ */
  .wf-connector {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 4px 0;
    position: relative;
  }

  .wf-connector-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 1.5px solid var(--wf-line-color);
    background: var(--wf-panel);
    z-index: 1;
  }

  .wf-connector-line {
    width: var(--wf-line-width);
    height: 16px;
    background: var(--wf-line-color);
  }

  .wf-add-btn {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid var(--wf-line-color);
    background: var(--wf-panel);
    color: var(--wf-line-color);
    cursor: pointer;
    transition: all 0.15s;
    z-index: 1;
    border-radius: 50%;
  }

  .wf-add-btn:hover {
    border-color: var(--wf-accent);
    color: var(--wf-accent);
    background: var(--wf-accent-dim);
    transform: scale(1.15);
  }

  .wf-add-btn svg { width: 12px; height: 12px; }

  /* ================================================================ */
  /*  CONDITION BRANCHING                                              */
  /* ================================================================ */
  .wf-branch-junction {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 4px 0;
  }

  .wf-branch-labels {
    display: flex;
    justify-content: center;
    gap: 0;
    position: relative;
    width: 100%;
    max-width: 620px;
    margin: 0 auto;
  }

  .wf-branch-h-line {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    height: var(--wf-line-width);
    background: var(--wf-line-color);
    width: 50%;
  }

  .wf-branch-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 2px 12px;
    background: var(--wf-panel);
    border: 1px solid var(--wf-border);
    z-index: 1;
    position: relative;
  }

  .wf-branch-label-no { color: var(--wf-branch-no); }
  .wf-branch-label-yes { color: var(--wf-branch-yes); }

  .wf-branch-columns {
    display: flex;
    gap: 24px;
    justify-content: center;
    width: 100%;
    max-width: 700px;
    margin: 0 auto;
  }

  .wf-branch-col {
    flex: 1;
    max-width: 320px;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .wf-branch-col .wf-node {
    width: 100%;
  }

  .wf-branch-v-line {
    width: var(--wf-line-width);
    height: 20px;
    background: var(--wf-line-color);
  }

  /* ================================================================ */
  /*  CONFIG PANEL                                                     */
  /* ================================================================ */
  .wf-config {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: var(--wf-config-w);
    background: var(--wf-panel);
    border-left: 1px solid var(--wf-border);
    z-index: 50;
    transform: translateX(100%);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
  }

  .wf-config.open { transform: translateX(0); }

  .wf-config-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--wf-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    background: var(--wf-panel);
    z-index: 1;
  }

  .wf-config-title {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wf-text);
  }

  .wf-config-close {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    color: var(--wf-text-muted);
    cursor: pointer;
    transition: all 0.15s;
  }

  .wf-config-close:hover { background: var(--wf-hover); color: var(--wf-text); }

  .wf-config-section {
    padding: 16px;
    border-bottom: 1px solid var(--wf-border-subtle);
  }

  .wf-config-section-title {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--wf-text-muted);
    margin-bottom: 12px;
  }

  .wf-config-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 12px;
  }

  .wf-config-row:last-child { margin-bottom: 0; }

  .wf-config-label {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 500;
    color: var(--wf-text-secondary);
  }

  .wf-config-input {
    padding: 7px 10px;
    background: var(--wf-surface);
    border: 1px solid var(--wf-border);
    color: var(--wf-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    width: 100%;
  }

  .wf-config-input:focus { border-color: var(--wf-accent); }

  .wf-config-select {
    padding: 7px 10px;
    background: var(--wf-surface);
    border: 1px solid var(--wf-border);
    color: var(--wf-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    cursor: pointer;
    width: 100%;
  }

  .wf-config-select:focus { border-color: var(--wf-accent); }

  .wf-config-textarea {
    padding: 7px 10px;
    background: var(--wf-surface);
    border: 1px solid var(--wf-border);
    color: var(--wf-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    width: 100%;
    min-height: 60px;
    resize: vertical;
  }

  .wf-config-textarea:focus { border-color: var(--wf-accent); }

  .wf-config-delete {
    margin: 16px;
    padding: 8px 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 1px solid rgba(220, 38, 38, 0.3);
    background: rgba(220, 38, 38, 0.06);
    color: var(--red);
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    transition: all 0.15s;
  }

  .wf-config-delete:hover {
    background: rgba(220, 38, 38, 0.12);
    border-color: var(--red);
  }

  .wf-config-delete svg { width: 14px; height: 14px; }

  /* Node type badge in config */
  .wf-config-type-badge {
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

  /* ================================================================ */
  /*  STATUS BAR                                                       */
  /* ================================================================ */
  .wf-statusbar {
    height: 28px;
    background: var(--wf-panel);
    border-top: 1px solid var(--wf-border);
    display: flex;
    align-items: center;
    padding: 0 14px;
    gap: 16px;
    font-size: 10px;
    color: var(--wf-text-muted);
    font-family: var(--font-mono);
    flex-shrink: 0;
  }

  .wf-statusbar-item {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .wf-statusbar-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--green);
  }

  .wf-statusbar-right {
    margin-left: auto;
    display: flex;
    gap: 16px;
  }

  .wf-statusbar-key {
    display: inline-flex;
    padding: 0 4px;
    background: var(--wf-surface);
    border: 1px solid var(--wf-border-subtle);
    font-size: 9px;
    line-height: 1.4;
  }

  /* ================================================================ */
  /*  TOAST                                                            */
  /* ================================================================ */
  .wf-toast {
    position: fixed;
    bottom: 48px;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: var(--navy);
    border: 1px solid var(--wf-border);
    padding: 8px 16px;
    font-size: 11px;
    font-family: var(--font-mono);
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
    z-index: 200;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
  }

  .wf-toast.visible {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }

  .wf-toast-icon { color: var(--green); }

  /* ================================================================ */
  /*  ADD STEP MODAL                                                   */
  /* ================================================================ */
  .wf-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    z-index: 150;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: wfFadeIn 0.15s ease;
  }

  .wf-modal {
    width: 480px;
    max-height: 520px;
    background: var(--wf-panel);
    border: 1px solid var(--wf-border);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    animation: wfModalIn 0.2s ease;
  }

  .wf-modal-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--wf-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .wf-modal-title {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wf-text);
  }

  .wf-modal-close {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    color: var(--wf-text-muted);
    cursor: pointer;
    transition: all 0.15s;
  }

  .wf-modal-close:hover { background: var(--wf-hover); color: var(--wf-text); }

  .wf-modal-search {
    padding: 8px 16px;
    border-bottom: 1px solid var(--wf-border-subtle);
  }

  .wf-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
  }

  .wf-modal-group-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wf-text-muted);
    padding: 8px 16px 4px;
  }

  .wf-modal-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    cursor: pointer;
    transition: background 0.1s;
  }

  .wf-modal-item:hover { background: var(--wf-accent-dim); }

  .wf-modal-item-icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .wf-modal-item-icon svg { width: 14px; height: 14px; }

  .wf-modal-item-info { flex: 1; min-width: 0; }

  .wf-modal-item-name {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--wf-text);
  }

  .wf-modal-item-desc {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--wf-text-muted);
  }

  /* ================================================================ */
  /*  RUNS TAB                                                         */
  /* ================================================================ */
  .wf-runs {
    padding: 24px;
    max-width: 900px;
    margin: 0 auto;
    width: 100%;
  }

  .wf-runs-table-wrap {
    background: var(--wf-panel);
    border: 1px solid var(--wf-border);
    overflow: hidden;
    box-shadow: var(--wf-shadow);
  }

  .wf-runs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }

  .wf-runs-table thead th {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--wf-text-muted);
    background: var(--wf-surface);
    padding: 10px 16px;
    text-align: left;
    border-bottom: 1px solid var(--wf-border);
  }

  .wf-runs-table tbody td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--wf-border-subtle);
    color: var(--wf-text);
    vertical-align: middle;
  }

  .wf-runs-table tbody tr:last-child td { border-bottom: none; }
  .wf-runs-table tbody tr:hover { background: var(--wf-hover); }

  .wf-run-id {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--blue);
    font-weight: 400;
    letter-spacing: 0.2px;
  }

  .wf-run-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 8px;
  }

  .wf-run-badge-success { background: rgba(5, 150, 105, 0.1); color: var(--green); }
  .wf-run-badge-failed { background: rgba(220, 38, 38, 0.1); color: var(--red); }

  .wf-run-mono {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--wf-text-secondary);
  }

  .wf-runs-empty {
    padding: 48px;
    text-align: center;
    color: var(--wf-text-muted);
    font-family: var(--font-mono);
    font-size: 12px;
  }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes wfFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  @keyframes wfNodeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
  }

  @keyframes wfModalIn {
    from { opacity: 0; transform: scale(0.96) translateY(8px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
  }

  .wf-main { animation: wfFadeIn 0.3s ease-out; }
  .wf-sidebar { animation: wfFadeIn 0.3s ease-out 0.05s both; }
  .wf-canvas { animation: wfFadeIn 0.3s ease-out 0.1s both; }
</style>

<div
  x-data="workflowEditor()"
  @keydown.window="handleKeydown($event)"
>
  {{-- TOP BAR --}}
  <div class="wf-topbar">
    <div class="wf-topbar-left">
      <div class="wf-topbar-brand">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/></svg>
        Workflows
      </div>
      <div class="wf-topbar-sep"></div>
      <div style="display:flex;align-items:center;gap:10px;">
        <input
          type="text"
          class="wf-workflow-name"
          :value="workflow.name"
          @input="workflow.name = $event.target.value; isDirty = true"
          spellcheck="false"
        />
        <span class="wf-status-badge" :class="{ 'draft': workflow.status === 'draft' }" x-text="workflow.status"></span>
      </div>
    </div>

    <div class="wf-topbar-center">
      <button class="wf-topbar-tab" :class="{ 'active': activeTab === 'editor' }" @click="activeTab = 'editor'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/></svg>
        Editor
      </button>
      <button class="wf-topbar-tab" :class="{ 'active': activeTab === 'runs' }" @click="activeTab = 'runs'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Runs
      </button>
    </div>

    <div class="wf-topbar-actions">
      <span class="wf-version" x-text="'v' + workflow.version + ' \u00b7 ' + workflow.lastSaved"></span>
      <button class="wf-btn" @click="discardChanges()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Discard
      </button>
      <button class="wf-btn wf-btn-primary" @click="publishWorkflow()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Publish
      </button>
    </div>
  </div>

  {{-- ALERT BAR --}}
  <div class="wf-alert-bar" x-show="isDirty" x-transition>
    <div class="wf-alert-left">
      <svg class="wf-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
      This workflow has unpublished changes
    </div>
    <div class="wf-alert-actions">
      <button class="wf-alert-btn wf-alert-btn-outline" @click="discardChanges()">Discard changes</button>
      <button class="wf-alert-btn wf-alert-btn-fill" @click="publishWorkflow()">Publish changes</button>
    </div>
  </div>

  {{-- MAIN LAYOUT --}}
  <div class="wf-main" :style="'height: calc(100vh - var(--wf-topbar-h)' + (isDirty ? ' - 45px' : '') + ')'">

    {{-- SIDEBAR --}}
    <div class="wf-sidebar" x-show="activeTab === 'editor'">
      <div class="wf-sidebar-header">
        <span class="wf-sidebar-title">Step Library</span>
      </div>
      <div class="wf-sidebar-search">
        <input
          type="text"
          class="wf-search-input"
          placeholder="Search handlers..."
          x-model="paletteSearch"
          x-ref="paletteSearchInput"
        />
      </div>
      <div class="wf-handler-groups">
        <template x-for="(group, gi) in filteredHandlerGroups" :key="group.category">
          <div class="wf-handler-group" :class="{ 'open': paletteSearch || gi < 3 }">
            <div class="wf-handler-group-header" @click="$el.parentElement.classList.toggle('open')">
              <svg class="wf-handler-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
              <span class="wf-handler-group-label" x-text="group.category"></span>
              <span class="wf-handler-group-count" x-text="group.handlers.length"></span>
            </div>
            <div class="wf-handler-group-items">
              <template x-for="handler in group.handlers" :key="handler.id">
                <div class="wf-handler-item" @click="addNodeFromPalette(handler)">
                  <div class="wf-type-dot" :class="'wf-dot-' + handler.type"></div>
                  <span class="wf-handler-item-name" x-text="handler.name"></span>
                  <span class="wf-handler-item-add">Add</span>
                </div>
              </template>
            </div>
          </div>
        </template>
      </div>
    </div>

    {{-- CONTENT --}}
    <div class="wf-content">
      {{-- EDITOR VIEW --}}
      <template x-if="activeTab === 'editor'">
        <div class="wf-canvas">
          <div class="wf-step-list">
            {{-- Main flow nodes --}}
            <template x-for="(node, ni) in mainFlowNodes" :key="node.id">
              <div>
                {{-- Node card --}}
                <div
                  class="wf-node"
                  :class="{ 'selected': selectedNodeId === node.id }"
                  @click.stop="selectNode(node.id)"
                >
                  <div class="wf-node-accent" :class="'wf-node-accent-' + node.type"></div>
                  <div class="wf-node-header">
                    <span class="wf-node-type-label" :style="'color: var(--wf-' + node.type + '-color)'" x-text="node.type.toUpperCase()"></span>
                    <span class="wf-node-subcategory" x-text="node.subcategory"></span>
                  </div>
                  <div class="wf-node-body">
                    <div class="wf-node-icon" :style="'background: var(--wf-' + node.type + '-bg)'" x-html="node.icon"></div>
                    <div class="wf-node-info">
                      <div class="wf-node-name" x-text="node.name"></div>
                      <div class="wf-node-desc" x-text="node.description"></div>
                    </div>
                  </div>
                </div>

                {{-- Condition branching --}}
                <template x-if="node.type === 'condition'">
                  <div>
                    {{-- Junction --}}
                    <div class="wf-branch-junction">
                      <div class="wf-connector-dot"></div>
                      <div class="wf-connector-line"></div>
                    </div>
                    <div class="wf-branch-labels">
                      <div class="wf-branch-h-line"></div>
                      <div style="display:flex;width:100%;justify-content:center;gap:40%;">
                        <span class="wf-branch-label wf-branch-label-no">No</span>
                        <span class="wf-branch-label wf-branch-label-yes">Yes</span>
                      </div>
                    </div>

                    {{-- Branch columns --}}
                    <div class="wf-branch-columns" style="margin-top:8px;">
                      {{-- NO branch --}}
                      <div class="wf-branch-col">
                        <template x-for="(bnode, bi) in getBranchNodes(node.id, 'no')" :key="bnode.id">
                          <div style="width:100%;">
                            <div class="wf-branch-v-line" style="margin:0 auto;"></div>
                            <div
                              class="wf-node"
                              :class="{ 'selected': selectedNodeId === bnode.id }"
                              @click.stop="selectNode(bnode.id)"
                            >
                              <div class="wf-node-accent" :class="'wf-node-accent-' + bnode.type"></div>
                              <div class="wf-node-header">
                                <span class="wf-node-type-label" :style="'color: var(--wf-' + bnode.type + '-color)'" x-text="bnode.type.toUpperCase()"></span>
                                <span class="wf-node-subcategory" x-text="bnode.subcategory"></span>
                              </div>
                              <div class="wf-node-body">
                                <div class="wf-node-icon" :style="'background: var(--wf-' + bnode.type + '-bg)'" x-html="bnode.icon"></div>
                                <div class="wf-node-info">
                                  <div class="wf-node-name" x-text="bnode.name"></div>
                                  <div class="wf-node-desc" x-text="bnode.description"></div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </template>
                        <div class="wf-connector" style="width:100%;">
                          <div class="wf-connector-line"></div>
                          <div class="wf-add-btn" @click.stop="openAddStep(node.id, 'no')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                          </div>
                        </div>
                      </div>

                      {{-- YES branch --}}
                      <div class="wf-branch-col">
                        <template x-for="(bnode, bi) in getBranchNodes(node.id, 'yes')" :key="bnode.id">
                          <div style="width:100%;">
                            <div class="wf-branch-v-line" style="margin:0 auto;"></div>
                            <div
                              class="wf-node"
                              :class="{ 'selected': selectedNodeId === bnode.id }"
                              @click.stop="selectNode(bnode.id)"
                            >
                              <div class="wf-node-accent" :class="'wf-node-accent-' + bnode.type"></div>
                              <div class="wf-node-header">
                                <span class="wf-node-type-label" :style="'color: var(--wf-' + bnode.type + '-color)'" x-text="bnode.type.toUpperCase()"></span>
                                <span class="wf-node-subcategory" x-text="bnode.subcategory"></span>
                              </div>
                              <div class="wf-node-body">
                                <div class="wf-node-icon" :style="'background: var(--wf-' + bnode.type + '-bg)'" x-html="bnode.icon"></div>
                                <div class="wf-node-info">
                                  <div class="wf-node-name" x-text="bnode.name"></div>
                                  <div class="wf-node-desc" x-text="bnode.description"></div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </template>
                        <div class="wf-connector" style="width:100%;">
                          <div class="wf-connector-line"></div>
                          <div class="wf-add-btn" @click.stop="openAddStep(node.id, 'yes')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </template>

                {{-- Regular connector (non-condition) --}}
                <template x-if="node.type !== 'condition'">
                  <div class="wf-connector">
                    <div class="wf-connector-dot"></div>
                    <div class="wf-connector-line"></div>
                    <div class="wf-add-btn" @click.stop="openAddStep(node.id, null)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </div>
                    <div class="wf-connector-line"></div>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </div>
      </template>

      {{-- RUNS VIEW --}}
      <template x-if="activeTab === 'runs'">
        <div class="wf-canvas">
          <div class="wf-runs">
            <div class="wf-runs-table-wrap">
              <table class="wf-runs-table">
                <thead>
                  <tr>
                    <th>Run ID</th>
                    <th>Trigger</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Duration</th>
                    <th>Steps</th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="run in mockRuns" :key="run.id">
                    <tr>
                      <td><span class="wf-run-id" x-text="run.id"></span></td>
                      <td x-text="run.trigger"></td>
                      <td>
                        <span class="wf-run-badge" :class="run.status === 'Success' ? 'wf-run-badge-success' : 'wf-run-badge-failed'">
                          <span style="width:5px;height:5px;border-radius:50%;background:currentColor;"></span>
                          <span x-text="run.status"></span>
                        </span>
                      </td>
                      <td><span class="wf-run-mono" x-text="run.started"></span></td>
                      <td><span class="wf-run-mono" x-text="run.duration"></span></td>
                      <td><span class="wf-run-mono" x-text="run.steps"></span></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>

      {{-- CONFIG PANEL --}}
      <div class="wf-config" :class="{ 'open': configPanelOpen && selectedNode }">
        <template x-if="selectedNode">
          <div>
            <div class="wf-config-header">
              <span class="wf-config-title">Step Configuration</span>
              <button class="wf-config-close" @click="closeConfig()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
              </button>
            </div>

            <div class="wf-config-section">
              <div class="wf-config-section-title">Step Info</div>
              <div style="margin-bottom:10px;">
                <span
                  class="wf-config-type-badge"
                  :style="'background: var(--wf-' + selectedNode.type + '-bg); color: var(--wf-' + selectedNode.type + '-color)'"
                  x-text="selectedNode.type.toUpperCase() + ' \u00b7 ' + selectedNode.subcategory"
                ></span>
              </div>
              <div class="wf-config-row">
                <label class="wf-config-label">Name</label>
                <input class="wf-config-input" :value="selectedNode.name" @input="selectedNode.name = $event.target.value; isDirty = true" />
              </div>
              <div class="wf-config-row">
                <label class="wf-config-label">Description</label>
                <textarea class="wf-config-textarea" :value="selectedNode.description" @input="selectedNode.description = $event.target.value; isDirty = true"></textarea>
              </div>
            </div>

            <template x-if="selectedHandler && selectedHandler.configSchema.length > 0">
              <div class="wf-config-section">
                <div class="wf-config-section-title">Configuration</div>
                <template x-for="field in selectedHandler.configSchema" :key="field.key">
                  <div class="wf-config-row">
                    <label class="wf-config-label" x-text="field.label"></label>
                    <template x-if="field.type === 'select'">
                      <select class="wf-config-select" @change="selectedNode.config[field.key] = $event.target.value; isDirty = true">
                        <template x-for="opt in field.options" :key="opt">
                          <option :value="opt" :selected="selectedNode.config[field.key] === opt" x-text="opt"></option>
                        </template>
                      </select>
                    </template>
                    <template x-if="field.type === 'text'">
                      <input class="wf-config-input" :value="selectedNode.config[field.key] || ''" @input="selectedNode.config[field.key] = $event.target.value; isDirty = true" />
                    </template>
                    <template x-if="field.type === 'textarea'">
                      <textarea class="wf-config-textarea" :value="selectedNode.config[field.key] || ''" @input="selectedNode.config[field.key] = $event.target.value; isDirty = true"></textarea>
                    </template>
                    <template x-if="field.type === 'number'">
                      <input class="wf-config-input" type="number" :value="selectedNode.config[field.key] || ''" @input="selectedNode.config[field.key] = $event.target.value; isDirty = true" />
                    </template>
                  </div>
                </template>
              </div>
            </template>

            <div class="wf-config-section">
              <div class="wf-config-section-title">Failure Mode</div>
              <div class="wf-config-row">
                <label class="wf-config-label">On failure</label>
                <select class="wf-config-select" @change="selectedNode.config._failureMode = $event.target.value; isDirty = true">
                  <option value="halt" :selected="(selectedNode.config._failureMode || 'halt') === 'halt'">Halt workflow</option>
                  <option value="skip" :selected="selectedNode.config._failureMode === 'skip'">Skip and continue</option>
                  <option value="retry" :selected="selectedNode.config._failureMode === 'retry'">Retry (3 attempts)</option>
                </select>
              </div>
            </div>

            <template x-if="selectedNode.type !== 'trigger'">
              <button class="wf-config-delete" @click="deleteNode(selectedNode.id)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                Delete Step
              </button>
            </template>
          </div>
        </template>
      </div>

      {{-- STATUS BAR --}}
      <div class="wf-statusbar">
        <div class="wf-statusbar-item">
          <div class="wf-statusbar-dot"></div>
          <span x-text="totalNodeCount + ' steps'"></span>
        </div>
        <div class="wf-statusbar-item" x-text="workflow.trigger"></div>
        <div class="wf-statusbar-right">
          <div class="wf-statusbar-item">
            <span class="wf-statusbar-key">Del</span> delete
          </div>
          <div class="wf-statusbar-item">
            <span class="wf-statusbar-key">/</span> search
          </div>
          <div class="wf-statusbar-item">
            <span class="wf-statusbar-key">Esc</span> close
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ADD STEP MODAL --}}
  <template x-if="addStepModalOpen">
    <div class="wf-modal-overlay" @click.self="addStepModalOpen = false" @keydown.escape.window="addStepModalOpen = false">
      <div class="wf-modal">
        <div class="wf-modal-header">
          <span class="wf-modal-title">Add Step</span>
          <button class="wf-modal-close" @click="addStepModalOpen = false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
          </button>
        </div>
        <div class="wf-modal-search">
          <input
            type="text"
            class="wf-search-input"
            placeholder="Search handlers..."
            x-model="modalSearch"
            x-ref="modalSearchInput"
          />
        </div>
        <div class="wf-modal-body">
          <template x-for="group in filteredModalHandlers" :key="group.category">
            <div>
              <div class="wf-modal-group-label" x-text="group.category"></div>
              <template x-for="handler in group.handlers" :key="handler.id">
                <div class="wf-modal-item" @click="addNode(handler)">
                  <div class="wf-modal-item-icon" :style="'background: var(--wf-' + handler.type + '-bg)'" x-html="handler.icon"></div>
                  <div class="wf-modal-item-info">
                    <div class="wf-modal-item-name" x-text="handler.name"></div>
                    <div class="wf-modal-item-desc" x-text="handler.description"></div>
                  </div>
                </div>
              </template>
            </div>
          </template>
        </div>
      </div>
    </div>
  </template>

  {{-- TOAST --}}
  <div class="wf-toast" :class="{ 'visible': toastVisible }">
    <span class="wf-toast-icon">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    </span>
    <span x-text="toastMessage"></span>
  </div>
</div>

@verbatim
<script>
function workflowEditor() {
  const icons = {
    user: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>',
    webhook: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 16.98h1a2 2 0 0 0 1.78-2.88l-5.78-10.2a2 2 0 0 0-3.56 0L6.44 14.1A2 2 0 0 0 8.22 17h1"/><path d="m9 12 3 3 3-3"/></svg>',
    play: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
    gitBranch: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="6" x2="6" y1="3" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></svg>',
    clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
    stop: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><rect width="6" height="6" x="9" y="9"/></svg>',
    arrowRight: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>',
    mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
    bell: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
    users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    activity: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
    fileText: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/></svg>',
    globe: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
    zap: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
  };

  const handlerRegistry = [
    {
      category: 'Triggers',
      handlers: [
        { id: 'domain_event', type: 'trigger', name: 'Domain Event', subcategory: 'User Activity', icon: icons.zap, description: 'Fires when a system event occurs', configSchema: [
          { key: 'event', label: 'Event Name', type: 'select', options: ['member.created', 'opportunity.created', 'opportunity.status_changed', 'invoice.issued', 'invoice.paid'] },
          { key: 'filter', label: 'Filter Expression', type: 'text' }
        ]},
        { id: 'scheduled', type: 'trigger', name: 'Scheduled', subcategory: 'Schedule', icon: icons.calendar, description: 'Runs on a cron schedule', configSchema: [
          { key: 'cron', label: 'Cron Expression', type: 'text' },
          { key: 'timezone', label: 'Timezone', type: 'select', options: ['UTC', 'Europe/London', 'America/New_York', 'America/Los_Angeles'] }
        ]},
        { id: 'inbound_webhook', type: 'trigger', name: 'Inbound Webhook', subcategory: 'External', icon: icons.webhook, description: 'Triggered by external HTTP POST', configSchema: [
          { key: 'secret', label: 'Signing Secret', type: 'text' },
          { key: 'method', label: 'HTTP Method', type: 'select', options: ['POST', 'PUT'] }
        ]},
        { id: 'manual_trigger', type: 'trigger', name: 'Manual Trigger', subcategory: 'Manual', icon: icons.play, description: 'Manually started by a user', configSchema: [] },
      ]
    },
    {
      category: 'Logic',
      handlers: [
        { id: 'if_else', type: 'condition', name: 'If / Else', subcategory: 'Conditions', icon: icons.gitBranch, description: 'Branch based on a condition', configSchema: [
          { key: 'field', label: 'Field to Check', type: 'text' },
          { key: 'operator', label: 'Operator', type: 'select', options: ['equals', 'not_equals', 'contains', 'greater_than', 'less_than', 'is_empty', 'is_not_empty'] },
          { key: 'value', label: 'Value', type: 'text' }
        ]},
        { id: 'wait_delay', type: 'delay', name: 'Wait / Delay', subcategory: 'Timing', icon: icons.clock, description: 'Pause execution for a duration', configSchema: [
          { key: 'duration', label: 'Duration', type: 'number' },
          { key: 'unit', label: 'Unit', type: 'select', options: ['minutes', 'hours', 'days'] }
        ]},
        { id: 'halt', type: 'halt', name: 'Halt', subcategory: 'Control', icon: icons.stop, description: 'Stop the workflow immediately', configSchema: [
          { key: 'reason', label: 'Halt Reason', type: 'text' }
        ]},
      ]
    },
    {
      category: 'Opportunities',
      handlers: [
        { id: 'change_status', type: 'action', name: 'Change Status', subcategory: 'Opportunities', icon: icons.arrowRight, description: 'Update opportunity status', configSchema: [
          { key: 'status', label: 'New Status', type: 'select', options: ['Quotation', 'Confirmed', 'Active', 'Returned', 'Closed Won', 'Closed Lost'] }
        ]},
        { id: 'assign_owner', type: 'action', name: 'Assign Owner', subcategory: 'Opportunities', icon: icons.user, description: 'Assign opportunity to a user', configSchema: [
          { key: 'user', label: 'Assign To', type: 'select', options: ['Round Robin', 'Specific User', 'Team Lead'] }
        ]},
      ]
    },
    {
      category: 'Communication',
      handlers: [
        { id: 'send_email', type: 'action', name: 'Send Email', subcategory: 'Messaging', icon: icons.mail, description: 'Send email using a template', configSchema: [
          { key: 'template', label: 'Email Template', type: 'select', options: ['Welcome Email', 'VIP Onboarding Email', 'Quote Follow-Up', 'Invoice Reminder', 'Custom'] },
          { key: 'to', label: 'Recipient', type: 'select', options: ['Trigger Contact', 'Opportunity Owner', 'Custom Email'] }
        ]},
        { id: 'notify_team', type: 'action', name: 'Notify Team', subcategory: 'Notifications', icon: icons.bell, description: 'Send internal team notification', configSchema: [
          { key: 'channel', label: 'Channel', type: 'select', options: ['In-App', 'Email', 'Both'] },
          { key: 'message', label: 'Message', type: 'textarea' }
        ]},
      ]
    },
    {
      category: 'CRM',
      handlers: [
        { id: 'create_activity', type: 'action', name: 'Create Activity', subcategory: 'CRM', icon: icons.activity, description: 'Log an activity against a member', configSchema: [
          { key: 'activity_type', label: 'Activity Type', type: 'select', options: ['Note', 'Call', 'Meeting', 'Task'] },
          { key: 'description', label: 'Description', type: 'textarea' }
        ]},
        { id: 'add_to_audience', type: 'action', name: 'Add to Audience', subcategory: 'User Management', icon: icons.users, description: 'Add contact to an audience segment', configSchema: [
          { key: 'audience', label: 'Audience', type: 'select', options: ['Main Audience', 'VIP Audience', 'Newsletter', 'Leads'] }
        ]},
      ]
    },
    {
      category: 'Invoicing',
      handlers: [
        { id: 'generate_invoice', type: 'action', name: 'Generate Invoice', subcategory: 'Finance', icon: icons.fileText, description: 'Create invoice from opportunity', configSchema: [
          { key: 'due_days', label: 'Payment Terms (days)', type: 'number' },
          { key: 'auto_send', label: 'Auto-Send', type: 'select', options: ['Yes', 'No'] }
        ]},
      ]
    },
    {
      category: 'External',
      handlers: [
        { id: 'http_request', type: 'action', name: 'HTTP Request', subcategory: 'Integration', icon: icons.globe, description: 'Make an outbound HTTP request', configSchema: [
          { key: 'url', label: 'URL', type: 'text' },
          { key: 'method', label: 'Method', type: 'select', options: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] },
          { key: 'body', label: 'Request Body (JSON)', type: 'textarea' }
        ]},
        { id: 'fire_webhook', type: 'action', name: 'Fire Webhook', subcategory: 'Webhooks', icon: icons.zap, description: 'Dispatch a webhook event', configSchema: [
          { key: 'event', label: 'Webhook Event', type: 'text' },
          { key: 'payload', label: 'Payload Template', type: 'textarea' }
        ]},
      ]
    },
  ];

  let nextId = 100;

  return {
    workflow: {
      name: 'New User Marketing Signup',
      description: 'When a new user registers, add to audience and send welcome email',
      status: 'draft',
      trigger: 'member.created',
      lastSaved: 'Saved 2m ago',
      version: 1,
      runCount: 12,
    },

    nodes: [
      {
        id: 1, type: 'trigger', handlerId: 'domain_event',
        name: 'User registered', subcategory: 'User Activity',
        icon: icons.user, description: 'User registered on the platform',
        config: { event: 'member.created', filter: '' },
        parentId: null, branch: null, order: 0,
      },
      {
        id: 2, type: 'condition', handlerId: 'if_else',
        name: 'If / else', subcategory: 'Conditions',
        icon: icons.gitBranch, description: 'If user is VIP',
        config: { field: 'member.tags', operator: 'contains', value: 'VIP' },
        parentId: null, branch: null, order: 1,
      },
      {
        id: 3, type: 'action', handlerId: 'add_to_audience',
        name: 'Add to audience', subcategory: 'User Management',
        icon: icons.users, description: 'Audience = Main Audience',
        config: { audience: 'Main Audience' },
        parentId: 2, branch: 'no', order: 0,
      },
      {
        id: 4, type: 'action', handlerId: 'send_email',
        name: 'Send email', subcategory: 'Messaging',
        icon: icons.mail, description: 'Template = Welcome Email',
        config: { template: 'Welcome Email', to: 'Trigger Contact' },
        parentId: 2, branch: 'no', order: 1,
      },
      {
        id: 5, type: 'action', handlerId: 'add_to_audience',
        name: 'Add to audience', subcategory: 'User Management',
        icon: icons.users, description: 'Audience = VIP Audience',
        config: { audience: 'VIP Audience' },
        parentId: 2, branch: 'yes', order: 0,
      },
      {
        id: 6, type: 'action', handlerId: 'send_email',
        name: 'Send email', subcategory: 'Messaging',
        icon: icons.mail, description: 'Template = VIP Onboarding Email',
        config: { template: 'VIP Onboarding Email', to: 'Trigger Contact' },
        parentId: 2, branch: 'yes', order: 1,
      },
    ],

    selectedNodeId: null,
    configPanelOpen: false,
    paletteSearch: '',
    modalSearch: '',
    activeTab: 'editor',
    insertTargetId: null,
    insertTargetBranch: null,
    addStepModalOpen: false,
    isDirty: true,
    toastMessage: '',
    toastVisible: false,
    toastTimeout: null,

    handlerRegistry: handlerRegistry,

    mockRuns: [
      { id: 'WFR-0012', trigger: 'member.created', status: 'Success', started: '2026-03-05 14:32:01', duration: '1.2s', steps: '4/4' },
      { id: 'WFR-0011', trigger: 'member.created', status: 'Success', started: '2026-03-05 13:18:44', duration: '0.8s', steps: '4/4' },
      { id: 'WFR-0010', trigger: 'member.created', status: 'Failed', started: '2026-03-05 11:05:22', duration: '3.1s', steps: '2/4' },
      { id: 'WFR-0009', trigger: 'member.created', status: 'Success', started: '2026-03-04 22:41:03', duration: '1.0s', steps: '4/4' },
      { id: 'WFR-0008', trigger: 'member.created', status: 'Success', started: '2026-03-04 19:12:55', duration: '0.9s', steps: '4/4' },
    ],

    get mainFlowNodes() {
      return this.nodes
        .filter(n => n.branch === null)
        .sort((a, b) => a.order - b.order);
    },

    get totalNodeCount() {
      return this.nodes.length;
    },

    get selectedNode() {
      if (!this.selectedNodeId) return null;
      return this.nodes.find(n => n.id === this.selectedNodeId) || null;
    },

    get selectedHandler() {
      if (!this.selectedNode) return null;
      for (const group of this.handlerRegistry) {
        const h = group.handlers.find(h => h.id === this.selectedNode.handlerId);
        if (h) return h;
      }
      return null;
    },

    get filteredHandlerGroups() {
      const q = this.paletteSearch.toLowerCase().trim();
      if (!q) return this.handlerRegistry;
      return this.handlerRegistry.map(g => ({
        ...g,
        handlers: g.handlers.filter(h =>
          h.name.toLowerCase().includes(q) ||
          h.description.toLowerCase().includes(q) ||
          h.subcategory.toLowerCase().includes(q)
        )
      })).filter(g => g.handlers.length > 0);
    },

    get filteredModalHandlers() {
      const q = this.modalSearch.toLowerCase().trim();
      const registry = this.handlerRegistry.map(g => ({
        ...g,
        handlers: g.handlers.filter(h => h.type !== 'trigger')
      })).filter(g => g.handlers.length > 0);

      if (!q) return registry;
      return registry.map(g => ({
        ...g,
        handlers: g.handlers.filter(h =>
          h.name.toLowerCase().includes(q) ||
          h.description.toLowerCase().includes(q) ||
          h.subcategory.toLowerCase().includes(q)
        )
      })).filter(g => g.handlers.length > 0);
    },

    getBranchNodes(conditionId, branch) {
      return this.nodes
        .filter(n => n.parentId === conditionId && n.branch === branch)
        .sort((a, b) => a.order - b.order);
    },

    selectNode(id) {
      if (this.selectedNodeId === id) {
        this.selectedNodeId = null;
        this.configPanelOpen = false;
      } else {
        this.selectedNodeId = id;
        this.configPanelOpen = true;
      }
    },

    closeConfig() {
      this.configPanelOpen = false;
      this.selectedNodeId = null;
    },

    openAddStep(afterId, branch) {
      this.insertTargetId = afterId;
      this.insertTargetBranch = branch;
      this.addStepModalOpen = true;
      this.modalSearch = '';
      this.$nextTick(() => {
        if (this.$refs.modalSearchInput) this.$refs.modalSearchInput.focus();
      });
    },

    addNode(handler) {
      const newNode = {
        id: nextId++,
        type: handler.type,
        handlerId: handler.id,
        name: handler.name,
        subcategory: handler.subcategory,
        icon: handler.icon,
        description: handler.description,
        config: {},
        parentId: this.insertTargetBranch !== null ? this.insertTargetId : null,
        branch: this.insertTargetBranch,
        order: 0,
      };

      if (this.insertTargetBranch !== null) {
        const branchNodes = this.getBranchNodes(this.insertTargetId, this.insertTargetBranch);
        newNode.order = branchNodes.length;
        newNode.parentId = this.insertTargetId;
      } else {
        const targetNode = this.nodes.find(n => n.id === this.insertTargetId);
        if (targetNode) {
          const afterOrder = targetNode.order;
          this.nodes
            .filter(n => n.branch === null && n.order > afterOrder)
            .forEach(n => n.order++);
          newNode.order = afterOrder + 1;
        }
      }

      this.nodes.push(newNode);
      this.addStepModalOpen = false;
      this.isDirty = true;
      this.showToast('Added: ' + handler.name);
    },

    addNodeFromPalette(handler) {
      const mainNodes = this.mainFlowNodes;
      const lastMainNode = mainNodes[mainNodes.length - 1];
      this.insertTargetId = lastMainNode ? lastMainNode.id : null;
      this.insertTargetBranch = null;
      this.addNode(handler);
    },

    deleteNode(id) {
      const node = this.nodes.find(n => n.id === id);
      if (!node || node.type === 'trigger') return;

      if (node.type === 'condition') {
        this.nodes = this.nodes.filter(n => n.id !== id && n.parentId !== id);
      } else {
        this.nodes = this.nodes.filter(n => n.id !== id);
      }

      if (this.selectedNodeId === id) {
        this.selectedNodeId = null;
        this.configPanelOpen = false;
      }

      this.isDirty = true;
      this.showToast('Step deleted');
    },

    discardChanges() {
      this.isDirty = false;
      this.showToast('Changes discarded');
    },

    publishWorkflow() {
      this.workflow.status = 'active';
      this.workflow.version++;
      this.workflow.lastSaved = 'Just now';
      this.isDirty = false;
      this.showToast('Workflow published \u2014 v' + this.workflow.version);
    },

    showToast(msg) {
      this.toastMessage = msg;
      this.toastVisible = true;
      if (this.toastTimeout) clearTimeout(this.toastTimeout);
      this.toastTimeout = setTimeout(() => { this.toastVisible = false; }, 2500);
    },

    handleKeydown(e) {
      if (e.key === 'Escape') {
        if (this.addStepModalOpen) {
          this.addStepModalOpen = false;
        } else if (this.configPanelOpen) {
          this.closeConfig();
        }
        return;
      }

      if ((e.key === 'Delete' || e.key === 'Backspace') && this.selectedNodeId && !e.target.closest('input, textarea, select')) {
        e.preventDefault();
        this.deleteNode(this.selectedNodeId);
        return;
      }

      if ((e.metaKey || e.ctrlKey) && e.key === 's') {
        e.preventDefault();
        this.workflow.lastSaved = 'Just now';
        this.isDirty = false;
        this.showToast('Workflow saved');
        return;
      }

      if (e.key === '/' && !e.target.closest('input, textarea, select')) {
        e.preventDefault();
        if (this.$refs.paletteSearchInput) this.$refs.paletteSearchInput.focus();
        return;
      }

      if (e.key === '+' && !e.target.closest('input, textarea, select')) {
        e.preventDefault();
        const mainNodes = this.mainFlowNodes;
        const last = mainNodes[mainNodes.length - 1];
        if (last) this.openAddStep(last.id, null);
      }
    },
  };
}
</script>
@endverbatim
