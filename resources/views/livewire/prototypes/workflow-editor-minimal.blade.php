<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.editor')] #[Title('Workflow Editor — Minimal')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  WORKFLOW MINIMAL TOKENS — maps to brand system in app.css       */
  /* ================================================================ */
  :root {
    --wm-bg: var(--content-bg);
    --wm-panel: var(--card-bg);
    --wm-surface: var(--base);
    --wm-border: var(--card-border);
    --wm-border-subtle: var(--grey-border);
    --wm-text: var(--text-primary);
    --wm-text-secondary: var(--text-secondary);
    --wm-text-muted: var(--text-muted);
    --wm-accent: var(--green);
    --wm-accent-dim: var(--green-muted);
    --wm-hover: rgba(0, 0, 0, 0.04);
    --wm-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    --wm-trigger-color: var(--violet);
    --wm-trigger-bg: rgba(124, 58, 237, 0.08);
    --wm-action-color: var(--blue);
    --wm-action-bg: rgba(37, 99, 235, 0.06);
    --wm-condition-color: var(--amber);
    --wm-condition-bg: rgba(217, 119, 6, 0.06);
    --wm-delay-color: var(--cyan);
    --wm-delay-bg: rgba(8, 145, 178, 0.06);
    --wm-halt-color: var(--red);
    --wm-halt-bg: rgba(220, 38, 38, 0.06);
    --wm-branch-yes: var(--green);
    --wm-branch-no: var(--red);
    --wm-line-color: var(--grey-light);
    --wm-line-width: 1.5px;
    --wm-topbar-h: 52px;
    --wm-node-w: 520px;
  }

  .dark {
    --wm-bg: var(--content-bg);
    --wm-panel: var(--card-bg);
    --wm-surface: var(--navy-mid);
    --wm-border: var(--card-border);
    --wm-border-subtle: #283040;
    --wm-text: var(--text-primary);
    --wm-text-secondary: var(--text-secondary);
    --wm-text-muted: var(--text-muted);
    --wm-accent: var(--green);
    --wm-accent-dim: rgba(5, 150, 105, 0.12);
    --wm-hover: rgba(255, 255, 255, 0.06);
    --wm-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --wm-trigger-bg: rgba(124, 58, 237, 0.15);
    --wm-action-bg: rgba(37, 99, 235, 0.12);
    --wm-condition-bg: rgba(217, 119, 6, 0.12);
    --wm-delay-bg: rgba(8, 145, 178, 0.12);
    --wm-halt-bg: rgba(220, 38, 38, 0.12);
  }

  /* ================================================================ */
  /*  TOP BAR                                                          */
  /* ================================================================ */
  .wm-topbar {
    height: var(--wm-topbar-h);
    background: var(--navy);
    border-bottom: 1px solid var(--wm-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    z-index: 100;
    position: relative;
  }

  .wm-topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .wm-topbar-brand {
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

  .wm-topbar-brand:hover { color: #ffffff; }
  .wm-topbar-brand svg { opacity: 0.5; }

  .wm-topbar-sep {
    width: 1px;
    height: 20px;
    background: rgba(255, 255, 255, 0.12);
  }

  .wm-workflow-name {
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

  .wm-workflow-name:hover { border-color: rgba(255, 255, 255, 0.15); }
  .wm-workflow-name:focus { border-color: var(--green); background: rgba(255, 255, 255, 0.06); }

  .wm-status-badge {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 3px 10px;
    background: rgba(5, 150, 105, 0.15);
    color: var(--green);
  }

  .wm-status-badge.draft {
    background: rgba(217, 119, 6, 0.15);
    color: var(--amber);
  }

  .wm-topbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .wm-version {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--grey);
    padding: 0 8px;
  }

  .wm-btn {
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

  .wm-btn:hover { background: rgba(255, 255, 255, 0.1); color: #ffffff; }
  .wm-btn svg { width: 14px; height: 14px; }

  .wm-btn-primary {
    background: var(--green);
    color: #ffffff;
    border-color: var(--green);
  }

  .wm-btn-primary:hover {
    background: #06b07a;
    border-color: #06b07a;
    color: #ffffff;
  }

  /* ================================================================ */
  /*  CANVAS                                                           */
  /* ================================================================ */
  .wm-canvas {
    flex: 1;
    overflow-y: auto;
    background: var(--wm-surface);
    position: relative;
  }

  .dark .wm-canvas {
    background:
      radial-gradient(circle at 50% 30%, rgba(5, 150, 105, 0.02), transparent 60%),
      var(--wm-surface);
  }

  .wm-step-list {
    max-width: var(--wm-node-w);
    margin: 0 auto;
    padding: 48px 24px 160px;
  }

  /* ================================================================ */
  /*  NODE CARDS — wider, more padding                                 */
  /* ================================================================ */
  .wm-node {
    width: 100%;
    background: var(--wm-panel);
    border: 1px solid var(--wm-border);
    position: relative;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
    animation: wmNodeIn 0.2s ease both;
  }

  .wm-node:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
    transform: translateY(-1px);
  }

  .wm-node.selected {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
  }

  .wm-node-accent {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
  }

  .wm-node-accent-trigger { background: var(--wm-trigger-color); }
  .wm-node-accent-action { background: var(--wm-action-color); }
  .wm-node-accent-condition { background: var(--wm-condition-color); }
  .wm-node-accent-delay { background: var(--wm-delay-color); }
  .wm-node-accent-halt { background: var(--wm-halt-color); }

  .wm-node-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px 0 20px;
  }

  .wm-node-type-row {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .wm-node-type-label {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  .wm-node-subcategory {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wm-text-muted);
  }

  .wm-node-type-sep {
    font-family: var(--font-mono);
    font-size: 9px;
    color: var(--wm-text-muted);
    opacity: 0.5;
  }

  .wm-node-expand-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    background: none;
    border: none;
    color: var(--wm-text-muted);
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 6px;
    transition: color 0.15s, background 0.15s;
  }

  .wm-node-expand-btn:hover {
    color: var(--wm-text-secondary);
    background: var(--wm-hover);
  }

  .wm-node-expand-btn svg {
    width: 14px;
    height: 14px;
    transition: transform 0.2s;
  }

  .wm-node-expand-btn.expanded svg {
    transform: rotate(180deg);
  }

  .wm-node-body {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px 12px 20px;
  }

  .wm-node-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .wm-node-icon svg { width: 18px; height: 18px; }

  .wm-node-info {
    flex: 1;
    min-width: 0;
  }

  .wm-node-name {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 600;
    color: var(--wm-text);
    line-height: 1.3;
  }

  .wm-node-desc {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 400;
    color: var(--wm-text-muted);
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* ================================================================ */
  /*  INLINE CONFIG SECTION                                            */
  /* ================================================================ */
  .wm-inline-config-wrap {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.25s ease;
  }

  .wm-inline-config-wrap.expanded {
    max-height: 600px;
  }

  .wm-inline-config {
    padding: 0 20px 16px 20px;
    border-top: 1px solid var(--wm-border-subtle);
    margin: 0 16px;
  }

  .wm-config-section-title {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--wm-text-muted);
    margin-bottom: 10px;
    margin-top: 14px;
  }

  .wm-config-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 10px;
  }

  .wm-config-row:last-child { margin-bottom: 0; }

  .wm-config-label {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 500;
    color: var(--wm-text-secondary);
  }

  .wm-config-input {
    padding: 7px 10px;
    background: var(--wm-surface);
    border: 1px solid var(--wm-border);
    color: var(--wm-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    width: 100%;
  }

  .wm-config-input:focus { border-color: var(--wm-accent); }

  .wm-config-select {
    padding: 7px 10px;
    background: var(--wm-surface);
    border: 1px solid var(--wm-border);
    color: var(--wm-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    cursor: pointer;
    width: 100%;
  }

  .wm-config-select:focus { border-color: var(--wm-accent); }

  .wm-config-textarea {
    padding: 7px 10px;
    background: var(--wm-surface);
    border: 1px solid var(--wm-border);
    color: var(--wm-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    width: 100%;
    min-height: 52px;
    resize: vertical;
  }

  .wm-config-textarea:focus { border-color: var(--wm-accent); }

  .wm-config-delete-btn {
    margin-top: 12px;
    padding: 7px 14px;
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
    width: 100%;
  }

  .wm-config-delete-btn:hover {
    background: rgba(220, 38, 38, 0.12);
    border-color: var(--red);
  }

  .wm-config-delete-btn svg { width: 14px; height: 14px; }

  /* ================================================================ */
  /*  CONNECTORS                                                       */
  /* ================================================================ */
  .wm-connector {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 4px 0;
    position: relative;
  }

  .wm-connector-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 1.5px solid var(--wm-line-color);
    background: var(--wm-panel);
    z-index: 1;
  }

  .wm-connector-line {
    width: var(--wm-line-width);
    height: 16px;
    background: var(--wm-line-color);
  }

  .wm-add-btn {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid var(--wm-line-color);
    background: var(--wm-panel);
    color: var(--wm-line-color);
    cursor: pointer;
    transition: all 0.15s;
    z-index: 1;
    border-radius: 50%;
  }

  .wm-add-btn:hover {
    border-color: var(--wm-accent);
    color: var(--wm-accent);
    background: var(--wm-accent-dim);
    transform: scale(1.15);
  }

  .wm-add-btn svg { width: 12px; height: 12px; }

  /* ================================================================ */
  /*  INLINE HANDLER PICKER                                            */
  /* ================================================================ */
  .wm-inline-picker-wrap {
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    transition: max-height 0.2s ease, opacity 0.2s ease;
  }

  .wm-inline-picker-wrap.open {
    max-height: 520px;
    opacity: 1;
  }

  .wm-inline-picker {
    width: 100%;
    background: var(--wm-panel);
    border: 1px solid var(--wm-border);
    box-shadow: 0 8px 32px rgba(15, 23, 42, 0.08);
    margin: 4px 0;
    animation: wmPickerIn 0.15s ease both;
  }

  .dark .wm-inline-picker {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  }

  .wm-inline-picker-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border-bottom: 1px solid var(--wm-border-subtle);
  }

  .wm-inline-picker-title {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wm-text-secondary);
  }

  .wm-inline-picker-close {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    color: var(--wm-text-muted);
    cursor: pointer;
    transition: all 0.15s;
  }

  .wm-inline-picker-close:hover { background: var(--wm-hover); color: var(--wm-text); }

  .wm-inline-picker-search {
    padding: 8px 14px;
    border-bottom: 1px solid var(--wm-border-subtle);
  }

  .wm-search-input {
    width: 100%;
    padding: 7px 10px 7px 32px;
    background: var(--wm-surface);
    border: 1px solid var(--wm-border);
    color: var(--wm-text);
    font-size: 12px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: 10px center;
  }

  .wm-search-input:focus { border-color: var(--wm-accent); }
  .wm-search-input::placeholder { color: var(--wm-text-muted); }

  .wm-inline-picker-body {
    max-height: 340px;
    overflow-y: auto;
    padding: 4px 0;
  }

  .wm-picker-group-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wm-text-muted);
    padding: 8px 14px 4px;
  }

  .wm-picker-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 14px;
    cursor: pointer;
    transition: background 0.1s;
  }

  .wm-picker-item:hover { background: var(--wm-accent-dim); }

  .wm-picker-item-icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .wm-picker-item-icon svg { width: 14px; height: 14px; }

  .wm-picker-item-info { flex: 1; min-width: 0; }

  .wm-picker-item-name {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--wm-text);
  }

  .wm-picker-item-desc {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--wm-text-muted);
  }

  .wm-type-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .wm-dot-trigger { background: var(--wm-trigger-color); }
  .wm-dot-action { background: var(--wm-action-color); }
  .wm-dot-condition { background: var(--wm-condition-color); }
  .wm-dot-delay { background: var(--wm-delay-color); }
  .wm-dot-halt { background: var(--wm-halt-color); }

  /* ================================================================ */
  /*  CONDITION BRANCHING                                              */
  /* ================================================================ */
  .wm-branch-junction {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 4px 0;
  }

  .wm-branch-labels {
    display: flex;
    justify-content: center;
    gap: 0;
    position: relative;
    width: 100%;
    max-width: 520px;
    margin: 0 auto;
  }

  .wm-branch-h-line {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    height: var(--wm-line-width);
    background: var(--wm-line-color);
    width: 50%;
  }

  .wm-branch-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 2px 12px;
    background: var(--wm-panel);
    border: 1px solid var(--wm-border);
    z-index: 1;
    position: relative;
  }

  .wm-branch-label-no { color: var(--wm-branch-no); }
  .wm-branch-label-yes { color: var(--wm-branch-yes); }

  .wm-branch-columns {
    display: flex;
    gap: 24px;
    justify-content: center;
    width: 100%;
    max-width: 520px;
    margin: 0 auto;
  }

  .wm-branch-col {
    flex: 1;
    max-width: 240px;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .wm-branch-col .wm-node { width: 100%; }

  .wm-branch-v-line {
    width: var(--wm-line-width);
    height: 20px;
    background: var(--wm-line-color);
  }

  /* ================================================================ */
  /*  FLOATING TOOLBAR                                                 */
  /* ================================================================ */
  .wm-floating-toolbar {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%) translateY(0);
    background: var(--navy);
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 8px 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    z-index: 100;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    animation: wmToolbarIn 0.3s ease 0.2s both;
  }

  .wm-toolbar-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--grey-light);
    white-space: nowrap;
  }

  .wm-toolbar-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--green);
  }

  .wm-toolbar-sep {
    width: 1px;
    height: 14px;
    background: rgba(255, 255, 255, 0.12);
  }

  .wm-toolbar-key {
    display: inline-flex;
    padding: 0 4px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 400;
    line-height: 1.5;
    color: var(--grey);
  }

  /* ================================================================ */
  /*  TOAST                                                            */
  /* ================================================================ */
  .wm-toast {
    position: fixed;
    bottom: 72px;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: var(--navy);
    border: 1px solid var(--wm-border);
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

  .wm-toast.visible {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }

  .wm-toast-icon { color: var(--green); }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes wmNodeIn {
    from { opacity: 0; transform: scale(0.96) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
  }

  @keyframes wmPickerIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes wmToolbarIn {
    from { opacity: 0; transform: translateX(-50%) translateY(16px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
  }

  @keyframes wmFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  .wm-canvas { animation: wmFadeIn 0.3s ease-out 0.1s both; }
</style>

<div
  x-data="workflowEditorMinimal()"
  @keydown.window="handleKeydown($event)"
  style="display:flex;flex-direction:column;height:100vh;"
>
  {{-- TOP BAR — simplified --}}
  <div class="wm-topbar">
    <div class="wm-topbar-left">
      <div class="wm-topbar-brand">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/></svg>
        Workflows
      </div>
      <div class="wm-topbar-sep"></div>
      <div style="display:flex;align-items:center;gap:10px;">
        <input
          type="text"
          class="wm-workflow-name"
          :value="workflow.name"
          @input="workflow.name = $event.target.value; isDirty = true"
          spellcheck="false"
        />
        <span class="wm-status-badge" :class="{ 'draft': workflow.status === 'draft' }" x-text="workflow.status"></span>
      </div>
    </div>

    <div class="wm-topbar-actions">
      <span class="wm-version" x-text="'v' + workflow.version + ' \u00b7 ' + workflow.lastSaved"></span>
      <button class="wm-btn wm-btn-primary" @click="publishWorkflow()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Publish
      </button>
    </div>
  </div>

  {{-- CANVAS — full-width, no sidebar --}}
  <div class="wm-canvas">
    <div class="wm-step-list">
      {{-- Main flow nodes --}}
      <template x-for="(node, ni) in mainFlowNodes" :key="node.id">
        <div>
          {{-- Node card --}}
          <div
            class="wm-node"
            :class="{ 'selected': selectedNodeId === node.id }"
            @click.stop="selectNode(node.id)"
          >
            <div class="wm-node-accent" :class="'wm-node-accent-' + node.type"></div>
            <div class="wm-node-header">
              <div class="wm-node-type-row">
                <span class="wm-node-type-label" :style="'color: var(--wm-' + node.type + '-color)'" x-text="node.type.toUpperCase()"></span>
                <span class="wm-node-type-sep">&middot;</span>
                <span class="wm-node-subcategory" x-text="node.subcategory"></span>
              </div>
              <button
                class="wm-node-expand-btn"
                :class="{ 'expanded': expandedNodeId === node.id }"
                @click.stop="toggleExpand(node.id)"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
            </div>
            <div class="wm-node-body">
              <div class="wm-node-icon" :style="'background: var(--wm-' + node.type + '-bg)'" x-html="node.icon"></div>
              <div class="wm-node-info">
                <div class="wm-node-name" x-text="node.name"></div>
                <div class="wm-node-desc" x-text="node.description"></div>
              </div>
            </div>

            {{-- Inline config section --}}
            <div class="wm-inline-config-wrap" :class="{ 'expanded': expandedNodeId === node.id }">
              <div class="wm-inline-config" @click.stop>
                <div class="wm-config-section-title">Configuration</div>

                {{-- Step info --}}
                <div class="wm-config-row">
                  <label class="wm-config-label">Name</label>
                  <input class="wm-config-input" :value="node.name" @input="node.name = $event.target.value; isDirty = true" />
                </div>
                <div class="wm-config-row">
                  <label class="wm-config-label">Description</label>
                  <input class="wm-config-input" :value="node.description" @input="node.description = $event.target.value; isDirty = true" />
                </div>

                {{-- Handler-specific fields --}}
                <template x-if="getHandler(node.handlerId) && getHandler(node.handlerId).configSchema.length > 0">
                  <div>
                    <div class="wm-config-section-title" style="margin-top:14px;">Settings</div>
                    <template x-for="field in getHandler(node.handlerId).configSchema" :key="field.key">
                      <div class="wm-config-row">
                        <label class="wm-config-label" x-text="field.label"></label>
                        <template x-if="field.type === 'select'">
                          <select class="wm-config-select" @change="node.config[field.key] = $event.target.value; isDirty = true">
                            <template x-for="opt in field.options" :key="opt">
                              <option :value="opt" :selected="node.config[field.key] === opt" x-text="opt"></option>
                            </template>
                          </select>
                        </template>
                        <template x-if="field.type === 'text'">
                          <input class="wm-config-input" :value="node.config[field.key] || ''" @input="node.config[field.key] = $event.target.value; isDirty = true" />
                        </template>
                        <template x-if="field.type === 'textarea'">
                          <textarea class="wm-config-textarea" :value="node.config[field.key] || ''" @input="node.config[field.key] = $event.target.value; isDirty = true"></textarea>
                        </template>
                        <template x-if="field.type === 'number'">
                          <input class="wm-config-input" type="number" :value="node.config[field.key] || ''" @input="node.config[field.key] = $event.target.value; isDirty = true" />
                        </template>
                      </div>
                    </template>
                  </div>
                </template>

                {{-- Failure mode --}}
                <div class="wm-config-section-title" style="margin-top:14px;">Failure Mode</div>
                <div class="wm-config-row">
                  <label class="wm-config-label">On failure</label>
                  <select class="wm-config-select" @change="node.config._failureMode = $event.target.value; isDirty = true">
                    <option value="halt" :selected="(node.config._failureMode || 'halt') === 'halt'">Halt workflow</option>
                    <option value="skip" :selected="node.config._failureMode === 'skip'">Skip and continue</option>
                    <option value="retry" :selected="node.config._failureMode === 'retry'">Retry (3 attempts)</option>
                  </select>
                </div>

                {{-- Delete button --}}
                <template x-if="node.type !== 'trigger'">
                  <button class="wm-config-delete-btn" @click.stop="deleteNode(node.id)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Delete Step
                  </button>
                </template>
              </div>
            </div>
          </div>

          {{-- Condition branching --}}
          <template x-if="node.type === 'condition'">
            <div>
              {{-- Junction --}}
              <div class="wm-branch-junction">
                <div class="wm-connector-dot"></div>
                <div class="wm-connector-line"></div>
              </div>
              <div class="wm-branch-labels">
                <div class="wm-branch-h-line"></div>
                <div style="display:flex;width:100%;justify-content:center;gap:30%;">
                  <span class="wm-branch-label wm-branch-label-no">No</span>
                  <span class="wm-branch-label wm-branch-label-yes">Yes</span>
                </div>
              </div>

              {{-- Branch columns --}}
              <div class="wm-branch-columns" style="margin-top:8px;">
                {{-- NO branch --}}
                <div class="wm-branch-col">
                  <template x-for="(bnode, bi) in getBranchNodes(node.id, 'no')" :key="bnode.id">
                    <div style="width:100%;">
                      <div class="wm-branch-v-line" style="margin:0 auto;"></div>
                      <div
                        class="wm-node"
                        :class="{ 'selected': selectedNodeId === bnode.id }"
                        @click.stop="selectNode(bnode.id)"
                      >
                        <div class="wm-node-accent" :class="'wm-node-accent-' + bnode.type"></div>
                        <div class="wm-node-header">
                          <div class="wm-node-type-row">
                            <span class="wm-node-type-label" :style="'color: var(--wm-' + bnode.type + '-color)'" x-text="bnode.type.toUpperCase()"></span>
                            <span class="wm-node-type-sep">&middot;</span>
                            <span class="wm-node-subcategory" x-text="bnode.subcategory"></span>
                          </div>
                          <button
                            class="wm-node-expand-btn"
                            :class="{ 'expanded': expandedNodeId === bnode.id }"
                            @click.stop="toggleExpand(bnode.id)"
                          >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                          </button>
                        </div>
                        <div class="wm-node-body">
                          <div class="wm-node-icon" :style="'background: var(--wm-' + bnode.type + '-bg)'" x-html="bnode.icon"></div>
                          <div class="wm-node-info">
                            <div class="wm-node-name" x-text="bnode.name"></div>
                            <div class="wm-node-desc" x-text="bnode.description"></div>
                          </div>
                        </div>

                        {{-- Inline config for branch nodes --}}
                        <div class="wm-inline-config-wrap" :class="{ 'expanded': expandedNodeId === bnode.id }">
                          <div class="wm-inline-config" @click.stop>
                            <div class="wm-config-section-title">Configuration</div>
                            <div class="wm-config-row">
                              <label class="wm-config-label">Name</label>
                              <input class="wm-config-input" :value="bnode.name" @input="bnode.name = $event.target.value; isDirty = true" />
                            </div>
                            <div class="wm-config-row">
                              <label class="wm-config-label">Description</label>
                              <input class="wm-config-input" :value="bnode.description" @input="bnode.description = $event.target.value; isDirty = true" />
                            </div>
                            <template x-if="getHandler(bnode.handlerId) && getHandler(bnode.handlerId).configSchema.length > 0">
                              <div>
                                <div class="wm-config-section-title" style="margin-top:14px;">Settings</div>
                                <template x-for="field in getHandler(bnode.handlerId).configSchema" :key="field.key">
                                  <div class="wm-config-row">
                                    <label class="wm-config-label" x-text="field.label"></label>
                                    <template x-if="field.type === 'select'">
                                      <select class="wm-config-select" @change="bnode.config[field.key] = $event.target.value; isDirty = true">
                                        <template x-for="opt in field.options" :key="opt">
                                          <option :value="opt" :selected="bnode.config[field.key] === opt" x-text="opt"></option>
                                        </template>
                                      </select>
                                    </template>
                                    <template x-if="field.type === 'text'">
                                      <input class="wm-config-input" :value="bnode.config[field.key] || ''" @input="bnode.config[field.key] = $event.target.value; isDirty = true" />
                                    </template>
                                    <template x-if="field.type === 'textarea'">
                                      <textarea class="wm-config-textarea" :value="bnode.config[field.key] || ''" @input="bnode.config[field.key] = $event.target.value; isDirty = true"></textarea>
                                    </template>
                                    <template x-if="field.type === 'number'">
                                      <input class="wm-config-input" type="number" :value="bnode.config[field.key] || ''" @input="bnode.config[field.key] = $event.target.value; isDirty = true" />
                                    </template>
                                  </div>
                                </template>
                              </div>
                            </template>
                            <div class="wm-config-section-title" style="margin-top:14px;">Failure Mode</div>
                            <div class="wm-config-row">
                              <label class="wm-config-label">On failure</label>
                              <select class="wm-config-select" @change="bnode.config._failureMode = $event.target.value; isDirty = true">
                                <option value="halt" :selected="(bnode.config._failureMode || 'halt') === 'halt'">Halt workflow</option>
                                <option value="skip" :selected="bnode.config._failureMode === 'skip'">Skip and continue</option>
                                <option value="retry" :selected="bnode.config._failureMode === 'retry'">Retry (3 attempts)</option>
                              </select>
                            </div>
                            <button class="wm-config-delete-btn" @click.stop="deleteNode(bnode.id)">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                              Delete Step
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>
                  {{-- Add button for NO branch --}}
                  <div class="wm-connector" style="width:100%;">
                    <div class="wm-connector-line"></div>
                    <div class="wm-add-btn" @click.stop="openInlinePicker(node.id, 'no')">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </div>
                  </div>
                  {{-- Inline picker for NO branch --}}
                  <div
                    class="wm-inline-picker-wrap"
                    :class="{ 'open': inlinePickerAfterNodeId === node.id && inlinePickerBranch === 'no' }"
                    style="width:100%;"
                  >
                    <div class="wm-inline-picker" x-show="inlinePickerAfterNodeId === node.id && inlinePickerBranch === 'no'">
                      <div class="wm-inline-picker-header">
                        <span class="wm-inline-picker-title">Add Step</span>
                        <button class="wm-inline-picker-close" @click.stop="closeInlinePicker()">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                      </div>
                      <div class="wm-inline-picker-search">
                        <input type="text" class="wm-search-input" placeholder="Search handlers..." x-model="inlinePickerSearch" />
                      </div>
                      <div class="wm-inline-picker-body">
                        <template x-for="group in filteredPickerHandlers" :key="group.category">
                          <div>
                            <div class="wm-picker-group-label" x-text="group.category"></div>
                            <template x-for="handler in group.handlers" :key="handler.id">
                              <div class="wm-picker-item" @click.stop="addNode(handler)">
                                <div class="wm-picker-item-icon" :style="'background: var(--wm-' + handler.type + '-bg)'" x-html="handler.icon"></div>
                                <div class="wm-picker-item-info">
                                  <div class="wm-picker-item-name" x-text="handler.name"></div>
                                  <div class="wm-picker-item-desc" x-text="handler.description"></div>
                                </div>
                              </div>
                            </template>
                          </div>
                        </template>
                      </div>
                    </div>
                  </div>
                </div>

                {{-- YES branch --}}
                <div class="wm-branch-col">
                  <template x-for="(bnode, bi) in getBranchNodes(node.id, 'yes')" :key="bnode.id">
                    <div style="width:100%;">
                      <div class="wm-branch-v-line" style="margin:0 auto;"></div>
                      <div
                        class="wm-node"
                        :class="{ 'selected': selectedNodeId === bnode.id }"
                        @click.stop="selectNode(bnode.id)"
                      >
                        <div class="wm-node-accent" :class="'wm-node-accent-' + bnode.type"></div>
                        <div class="wm-node-header">
                          <div class="wm-node-type-row">
                            <span class="wm-node-type-label" :style="'color: var(--wm-' + bnode.type + '-color)'" x-text="bnode.type.toUpperCase()"></span>
                            <span class="wm-node-type-sep">&middot;</span>
                            <span class="wm-node-subcategory" x-text="bnode.subcategory"></span>
                          </div>
                          <button
                            class="wm-node-expand-btn"
                            :class="{ 'expanded': expandedNodeId === bnode.id }"
                            @click.stop="toggleExpand(bnode.id)"
                          >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                          </button>
                        </div>
                        <div class="wm-node-body">
                          <div class="wm-node-icon" :style="'background: var(--wm-' + bnode.type + '-bg)'" x-html="bnode.icon"></div>
                          <div class="wm-node-info">
                            <div class="wm-node-name" x-text="bnode.name"></div>
                            <div class="wm-node-desc" x-text="bnode.description"></div>
                          </div>
                        </div>

                        {{-- Inline config for YES branch nodes --}}
                        <div class="wm-inline-config-wrap" :class="{ 'expanded': expandedNodeId === bnode.id }">
                          <div class="wm-inline-config" @click.stop>
                            <div class="wm-config-section-title">Configuration</div>
                            <div class="wm-config-row">
                              <label class="wm-config-label">Name</label>
                              <input class="wm-config-input" :value="bnode.name" @input="bnode.name = $event.target.value; isDirty = true" />
                            </div>
                            <div class="wm-config-row">
                              <label class="wm-config-label">Description</label>
                              <input class="wm-config-input" :value="bnode.description" @input="bnode.description = $event.target.value; isDirty = true" />
                            </div>
                            <template x-if="getHandler(bnode.handlerId) && getHandler(bnode.handlerId).configSchema.length > 0">
                              <div>
                                <div class="wm-config-section-title" style="margin-top:14px;">Settings</div>
                                <template x-for="field in getHandler(bnode.handlerId).configSchema" :key="field.key">
                                  <div class="wm-config-row">
                                    <label class="wm-config-label" x-text="field.label"></label>
                                    <template x-if="field.type === 'select'">
                                      <select class="wm-config-select" @change="bnode.config[field.key] = $event.target.value; isDirty = true">
                                        <template x-for="opt in field.options" :key="opt">
                                          <option :value="opt" :selected="bnode.config[field.key] === opt" x-text="opt"></option>
                                        </template>
                                      </select>
                                    </template>
                                    <template x-if="field.type === 'text'">
                                      <input class="wm-config-input" :value="bnode.config[field.key] || ''" @input="bnode.config[field.key] = $event.target.value; isDirty = true" />
                                    </template>
                                    <template x-if="field.type === 'textarea'">
                                      <textarea class="wm-config-textarea" :value="bnode.config[field.key] || ''" @input="bnode.config[field.key] = $event.target.value; isDirty = true"></textarea>
                                    </template>
                                    <template x-if="field.type === 'number'">
                                      <input class="wm-config-input" type="number" :value="bnode.config[field.key] || ''" @input="bnode.config[field.key] = $event.target.value; isDirty = true" />
                                    </template>
                                  </div>
                                </template>
                              </div>
                            </template>
                            <div class="wm-config-section-title" style="margin-top:14px;">Failure Mode</div>
                            <div class="wm-config-row">
                              <label class="wm-config-label">On failure</label>
                              <select class="wm-config-select" @change="bnode.config._failureMode = $event.target.value; isDirty = true">
                                <option value="halt" :selected="(bnode.config._failureMode || 'halt') === 'halt'">Halt workflow</option>
                                <option value="skip" :selected="bnode.config._failureMode === 'skip'">Skip and continue</option>
                                <option value="retry" :selected="bnode.config._failureMode === 'retry'">Retry (3 attempts)</option>
                              </select>
                            </div>
                            <button class="wm-config-delete-btn" @click.stop="deleteNode(bnode.id)">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                              Delete Step
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>
                  {{-- Add button for YES branch --}}
                  <div class="wm-connector" style="width:100%;">
                    <div class="wm-connector-line"></div>
                    <div class="wm-add-btn" @click.stop="openInlinePicker(node.id, 'yes')">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </div>
                  </div>
                  {{-- Inline picker for YES branch --}}
                  <div
                    class="wm-inline-picker-wrap"
                    :class="{ 'open': inlinePickerAfterNodeId === node.id && inlinePickerBranch === 'yes' }"
                    style="width:100%;"
                  >
                    <div class="wm-inline-picker" x-show="inlinePickerAfterNodeId === node.id && inlinePickerBranch === 'yes'">
                      <div class="wm-inline-picker-header">
                        <span class="wm-inline-picker-title">Add Step</span>
                        <button class="wm-inline-picker-close" @click.stop="closeInlinePicker()">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                      </div>
                      <div class="wm-inline-picker-search">
                        <input type="text" class="wm-search-input" placeholder="Search handlers..." x-model="inlinePickerSearch" />
                      </div>
                      <div class="wm-inline-picker-body">
                        <template x-for="group in filteredPickerHandlers" :key="group.category">
                          <div>
                            <div class="wm-picker-group-label" x-text="group.category"></div>
                            <template x-for="handler in group.handlers" :key="handler.id">
                              <div class="wm-picker-item" @click.stop="addNode(handler)">
                                <div class="wm-picker-item-icon" :style="'background: var(--wm-' + handler.type + '-bg)'" x-html="handler.icon"></div>
                                <div class="wm-picker-item-info">
                                  <div class="wm-picker-item-name" x-text="handler.name"></div>
                                  <div class="wm-picker-item-desc" x-text="handler.description"></div>
                                </div>
                              </div>
                            </template>
                          </div>
                        </template>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          {{-- Regular connector (non-condition) --}}
          <template x-if="node.type !== 'condition'">
            <div>
              <div class="wm-connector">
                <div class="wm-connector-dot"></div>
                <div class="wm-connector-line"></div>
                <div class="wm-add-btn" @click.stop="openInlinePicker(node.id, null)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </div>
                <div class="wm-connector-line"></div>
              </div>

              {{-- Inline handler picker --}}
              <div
                class="wm-inline-picker-wrap"
                :class="{ 'open': inlinePickerAfterNodeId === node.id && inlinePickerBranch === null }"
              >
                <div class="wm-inline-picker" x-show="inlinePickerAfterNodeId === node.id && inlinePickerBranch === null">
                  <div class="wm-inline-picker-header">
                    <span class="wm-inline-picker-title">Add Step</span>
                    <button class="wm-inline-picker-close" @click.stop="closeInlinePicker()">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                  </div>
                  <div class="wm-inline-picker-search">
                    <input type="text" class="wm-search-input" placeholder="Search handlers..." x-model="inlinePickerSearch" x-ref="inlinePickerSearchInput" />
                  </div>
                  <div class="wm-inline-picker-body">
                    <template x-for="group in filteredPickerHandlers" :key="group.category">
                      <div>
                        <div class="wm-picker-group-label" x-text="group.category"></div>
                        <template x-for="handler in group.handlers" :key="handler.id">
                          <div class="wm-picker-item" @click.stop="addNode(handler)">
                            <div class="wm-picker-item-icon" :style="'background: var(--wm-' + handler.type + '-bg)'" x-html="handler.icon"></div>
                            <div class="wm-picker-item-info">
                              <div class="wm-picker-item-name" x-text="handler.name"></div>
                              <div class="wm-picker-item-desc" x-text="handler.description"></div>
                            </div>
                          </div>
                        </template>
                      </div>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </template>
    </div>
  </div>

  {{-- FLOATING TOOLBAR --}}
  <div class="wm-floating-toolbar">
    <div class="wm-toolbar-item">
      <div class="wm-toolbar-dot"></div>
      <span x-text="totalNodeCount + ' steps'"></span>
    </div>
    <div class="wm-toolbar-sep"></div>
    <div class="wm-toolbar-item">
      <span class="wm-toolbar-key">Del</span>
      <span>delete</span>
    </div>
    <div class="wm-toolbar-sep"></div>
    <div class="wm-toolbar-item">
      <span class="wm-toolbar-key">+</span>
      <span>add step</span>
    </div>
    <div class="wm-toolbar-sep"></div>
    <div class="wm-toolbar-item">
      <span class="wm-toolbar-key">Esc</span>
      <span>close</span>
    </div>
    <div class="wm-toolbar-sep"></div>
    <div class="wm-toolbar-item">
      <span class="wm-toolbar-key">&#8984;S</span>
      <span>save</span>
    </div>
  </div>

  {{-- TOAST --}}
  <div class="wm-toast" :class="{ 'visible': toastVisible }">
    <span class="wm-toast-icon">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    </span>
    <span x-text="toastMessage"></span>
  </div>
</div>

@verbatim
<script>
function workflowEditorMinimal() {
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

  // Flat handler lookup
  const handlerMap = {};
  for (const group of handlerRegistry) {
    for (const h of group.handlers) {
      handlerMap[h.id] = h;
    }
  }

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
    expandedNodeId: null,
    inlinePickerAfterNodeId: null,
    inlinePickerBranch: null,
    inlinePickerSearch: '',
    isDirty: true,
    toastMessage: '',
    toastVisible: false,
    toastTimeout: null,

    handlerRegistry: handlerRegistry,

    get mainFlowNodes() {
      return this.nodes
        .filter(n => n.branch === null)
        .sort((a, b) => a.order - b.order);
    },

    get totalNodeCount() {
      return this.nodes.length;
    },

    get filteredPickerHandlers() {
      const q = this.inlinePickerSearch.toLowerCase().trim();
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

    getHandler(handlerId) {
      return handlerMap[handlerId] || null;
    },

    getBranchNodes(conditionId, branch) {
      return this.nodes
        .filter(n => n.parentId === conditionId && n.branch === branch)
        .sort((a, b) => a.order - b.order);
    },

    selectNode(id) {
      if (this.selectedNodeId === id) {
        this.selectedNodeId = null;
      } else {
        this.selectedNodeId = id;
      }
    },

    toggleExpand(id) {
      if (this.expandedNodeId === id) {
        this.expandedNodeId = null;
      } else {
        this.expandedNodeId = id;
        this.selectedNodeId = id;
      }
    },

    openInlinePicker(afterId, branch) {
      if (this.inlinePickerAfterNodeId === afterId && this.inlinePickerBranch === branch) {
        this.closeInlinePicker();
        return;
      }
      this.inlinePickerAfterNodeId = afterId;
      this.inlinePickerBranch = branch;
      this.inlinePickerSearch = '';
    },

    closeInlinePicker() {
      this.inlinePickerAfterNodeId = null;
      this.inlinePickerBranch = null;
      this.inlinePickerSearch = '';
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
        parentId: this.inlinePickerBranch !== null ? this.inlinePickerAfterNodeId : null,
        branch: this.inlinePickerBranch,
        order: 0,
      };

      if (this.inlinePickerBranch !== null) {
        const branchNodes = this.getBranchNodes(this.inlinePickerAfterNodeId, this.inlinePickerBranch);
        newNode.order = branchNodes.length;
        newNode.parentId = this.inlinePickerAfterNodeId;
      } else {
        const targetNode = this.nodes.find(n => n.id === this.inlinePickerAfterNodeId);
        if (targetNode) {
          const afterOrder = targetNode.order;
          this.nodes
            .filter(n => n.branch === null && n.order > afterOrder)
            .forEach(n => n.order++);
          newNode.order = afterOrder + 1;
        }
      }

      this.nodes.push(newNode);
      this.closeInlinePicker();
      this.isDirty = true;
      this.expandedNodeId = newNode.id;
      this.selectedNodeId = newNode.id;
      this.showToast('Added: ' + handler.name);
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
      }
      if (this.expandedNodeId === id) {
        this.expandedNodeId = null;
      }

      this.isDirty = true;
      this.showToast('Step deleted');
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
        if (this.inlinePickerAfterNodeId !== null) {
          this.closeInlinePicker();
        } else if (this.expandedNodeId !== null) {
          this.expandedNodeId = null;
        } else if (this.selectedNodeId !== null) {
          this.selectedNodeId = null;
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

      if (e.key === '+' && !e.target.closest('input, textarea, select')) {
        e.preventDefault();
        const mainNodes = this.mainFlowNodes;
        const last = mainNodes[mainNodes.length - 1];
        if (last) this.openInlinePicker(last.id, null);
      }
    },
  };
}
</script>
@endverbatim
