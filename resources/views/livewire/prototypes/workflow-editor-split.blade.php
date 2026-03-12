<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.editor')] #[Title('Workflow Editor — Split')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  WORKFLOW SPLIT TOKENS — maps to brand system in app.css         */
  /* ================================================================ */
  :root {
    --ws-bg: var(--content-bg);
    --ws-panel: var(--card-bg);
    --ws-surface: var(--base);
    --ws-border: var(--card-border);
    --ws-border-subtle: var(--grey-border);
    --ws-text: var(--text-primary);
    --ws-text-secondary: var(--text-secondary);
    --ws-text-muted: var(--text-muted);
    --ws-accent: var(--green);
    --ws-accent-dim: var(--green-muted);
    --ws-hover: rgba(0, 0, 0, 0.04);
    --ws-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    --ws-code-bg: var(--base);
    --ws-code-gutter: #f0efe9;
    --ws-trigger-color: var(--violet);
    --ws-trigger-bg: rgba(124, 58, 237, 0.08);
    --ws-action-color: var(--blue);
    --ws-action-bg: rgba(37, 99, 235, 0.06);
    --ws-condition-color: var(--amber);
    --ws-condition-bg: rgba(217, 119, 6, 0.06);
    --ws-delay-color: var(--cyan);
    --ws-delay-bg: rgba(8, 145, 178, 0.06);
    --ws-halt-color: var(--red);
    --ws-halt-bg: rgba(220, 38, 38, 0.06);
    --ws-branch-yes: var(--green);
    --ws-branch-no: var(--red);
    --ws-line-color: var(--grey-light);
    --ws-line-width: 1.5px;
    --ws-topbar-h: 52px;
    --ws-node-w: 320px;
  }

  .dark {
    --ws-bg: var(--content-bg);
    --ws-panel: var(--card-bg);
    --ws-surface: var(--navy-mid);
    --ws-border: var(--card-border);
    --ws-border-subtle: #283040;
    --ws-text: var(--text-primary);
    --ws-text-secondary: var(--text-secondary);
    --ws-text-muted: var(--text-muted);
    --ws-accent: var(--green);
    --ws-accent-dim: rgba(5, 150, 105, 0.12);
    --ws-hover: rgba(255, 255, 255, 0.06);
    --ws-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --ws-code-bg: var(--navy);
    --ws-code-gutter: rgba(255, 255, 255, 0.04);
    --ws-trigger-bg: rgba(124, 58, 237, 0.15);
    --ws-action-bg: rgba(37, 99, 235, 0.12);
    --ws-condition-bg: rgba(217, 119, 6, 0.12);
    --ws-delay-bg: rgba(8, 145, 178, 0.12);
    --ws-halt-bg: rgba(220, 38, 38, 0.12);
  }

  /* ================================================================ */
  /*  TOP BAR                                                          */
  /* ================================================================ */
  .ws-topbar {
    height: var(--ws-topbar-h);
    background: var(--navy);
    border-bottom: 1px solid var(--ws-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    z-index: 100;
    position: relative;
  }

  .ws-topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .ws-topbar-brand {
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

  .ws-topbar-brand:hover { color: #ffffff; }
  .ws-topbar-brand svg { opacity: 0.5; }

  .ws-topbar-sep {
    width: 1px;
    height: 20px;
    background: rgba(255, 255, 255, 0.12);
  }

  .ws-workflow-name {
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

  .ws-workflow-name:hover { border-color: rgba(255, 255, 255, 0.15); }
  .ws-workflow-name:focus { border-color: var(--green); background: rgba(255, 255, 255, 0.06); }

  .ws-status-badge {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 3px 10px;
    background: rgba(5, 150, 105, 0.15);
    color: var(--green);
  }

  .ws-status-badge.draft {
    background: rgba(217, 119, 6, 0.15);
    color: var(--amber);
  }

  .ws-topbar-center {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: stretch;
    height: 100%;
  }

  .ws-topbar-tab {
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

  .ws-topbar-tab:hover { color: #ffffff; }
  .ws-topbar-tab.active { color: #ffffff; border-bottom-color: var(--ws-accent); }
  .ws-topbar-tab svg { width: 14px; height: 14px; opacity: 0.5; }
  .ws-topbar-tab.active svg { opacity: 0.8; }

  .ws-topbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .ws-version {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--grey);
    padding: 0 8px;
  }

  .ws-btn {
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

  .ws-btn:hover { background: rgba(255, 255, 255, 0.1); color: #ffffff; }
  .ws-btn svg { width: 14px; height: 14px; }

  .ws-btn-primary {
    background: var(--green);
    color: #ffffff;
    border-color: var(--green);
  }

  .ws-btn-primary:hover {
    background: #06b07a;
    border-color: #06b07a;
    color: #ffffff;
  }

  /* ================================================================ */
  /*  MAIN LAYOUT                                                      */
  /* ================================================================ */
  .ws-main {
    display: flex;
    height: calc(100vh - var(--ws-topbar-h));
    overflow: hidden;
  }

  /* ================================================================ */
  /*  LEFT PANE: Visual Step List                                      */
  /* ================================================================ */
  .ws-visual-pane {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 400px;
    overflow: hidden;
  }

  .ws-canvas {
    flex: 1;
    overflow-y: auto;
    background: var(--ws-surface);
    position: relative;
  }

  .dark .ws-canvas {
    background:
      radial-gradient(circle at 50% 30%, rgba(5, 150, 105, 0.02), transparent 60%),
      var(--ws-surface);
  }

  .ws-step-list {
    max-width: 700px;
    margin: 0 auto;
    padding: 32px 24px 120px;
  }

  /* ================================================================ */
  /*  STEP NODE CARDS                                                  */
  /* ================================================================ */
  .ws-node {
    width: var(--ws-node-w);
    margin: 0 auto;
    background: var(--ws-panel);
    border: 1px solid var(--ws-border);
    position: relative;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
    animation: wsNodeIn 0.2s ease both;
  }

  .ws-node:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
    transform: translateY(-2px);
  }

  .ws-node.selected {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
  }

  .ws-node-accent {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
  }

  .ws-node-accent-trigger { background: var(--ws-trigger-color); }
  .ws-node-accent-action { background: var(--ws-action-color); }
  .ws-node-accent-condition { background: var(--ws-condition-color); }
  .ws-node-accent-delay { background: var(--ws-delay-color); }
  .ws-node-accent-halt { background: var(--ws-halt-color); }

  .ws-node-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px 0 16px;
  }

  .ws-node-type-label {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  .ws-node-subcategory {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ws-text-muted);
  }

  .ws-node-body {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 12px 10px 16px;
  }

  .ws-node-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .ws-node-icon svg { width: 16px; height: 16px; }

  .ws-node-info {
    flex: 1;
    min-width: 0;
  }

  .ws-node-name {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    color: var(--ws-text);
    line-height: 1.3;
  }

  .ws-node-desc {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 400;
    color: var(--ws-text-muted);
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* ================================================================ */
  /*  CONNECTORS                                                       */
  /* ================================================================ */
  .ws-connector {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 4px 0;
    position: relative;
  }

  .ws-connector-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 1.5px solid var(--ws-line-color);
    background: var(--ws-panel);
    z-index: 1;
  }

  .ws-connector-line {
    width: var(--ws-line-width);
    height: 16px;
    background: var(--ws-line-color);
  }

  .ws-add-btn {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid var(--ws-line-color);
    background: var(--ws-panel);
    color: var(--ws-line-color);
    cursor: pointer;
    transition: all 0.15s;
    z-index: 1;
    border-radius: 50%;
  }

  .ws-add-btn:hover {
    border-color: var(--ws-accent);
    color: var(--ws-accent);
    background: var(--ws-accent-dim);
    transform: scale(1.15);
  }

  .ws-add-btn svg { width: 12px; height: 12px; }

  /* ================================================================ */
  /*  CONDITION BRANCHING                                              */
  /* ================================================================ */
  .ws-branch-junction {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 4px 0;
  }

  .ws-branch-labels {
    display: flex;
    justify-content: center;
    gap: 0;
    position: relative;
    width: 100%;
    max-width: 620px;
    margin: 0 auto;
  }

  .ws-branch-h-line {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    height: var(--ws-line-width);
    background: var(--ws-line-color);
    width: 50%;
  }

  .ws-branch-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 2px 12px;
    background: var(--ws-panel);
    border: 1px solid var(--ws-border);
    z-index: 1;
    position: relative;
  }

  .ws-branch-label-no { color: var(--ws-branch-no); }
  .ws-branch-label-yes { color: var(--ws-branch-yes); }

  .ws-branch-columns {
    display: flex;
    gap: 24px;
    justify-content: center;
    width: 100%;
    max-width: 700px;
    margin: 0 auto;
  }

  .ws-branch-col {
    flex: 1;
    max-width: 320px;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .ws-branch-col .ws-node {
    width: 100%;
  }

  .ws-branch-v-line {
    width: var(--ws-line-width);
    height: 20px;
    background: var(--ws-line-color);
  }

  /* ================================================================ */
  /*  RESIZE HANDLE                                                    */
  /* ================================================================ */
  .ws-resize-handle {
    width: 5px;
    background: var(--ws-border);
    cursor: col-resize;
    flex-shrink: 0;
    transition: background 0.15s;
    position: relative;
    z-index: 10;
  }

  .ws-resize-handle:hover,
  .ws-resize-handle.dragging {
    background: var(--ws-accent);
  }

  /* ================================================================ */
  /*  RIGHT PANE: Code / YAML                                          */
  /* ================================================================ */
  .ws-code-pane {
    width: 40%;
    min-width: 300px;
    display: flex;
    flex-direction: column;
    background: var(--ws-code-bg);
    overflow: hidden;
  }

  .ws-code-toolbar {
    height: 40px;
    background: var(--ws-panel);
    border-bottom: 1px solid var(--ws-border);
    display: flex;
    align-items: center;
    padding: 0 12px;
    gap: 8px;
    flex-shrink: 0;
  }

  .ws-code-label {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ws-text-muted);
  }

  .ws-format-toggle {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 0;
  }

  .ws-format-btn {
    padding: 4px 12px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    border: 1px solid var(--ws-border);
    background: var(--ws-surface);
    color: var(--ws-text-muted);
    transition: all 0.15s;
  }

  .ws-format-btn + .ws-format-btn {
    border-left: none;
  }

  .ws-format-btn:hover {
    color: var(--ws-text-secondary);
  }

  .ws-format-btn.active {
    background: var(--ws-accent);
    color: #ffffff;
    border-color: var(--ws-accent);
  }

  .ws-format-btn.active + .ws-format-btn {
    border-left-color: var(--ws-accent);
  }

  .ws-copy-btn {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--ws-border);
    background: var(--ws-surface);
    color: var(--ws-text-muted);
    cursor: pointer;
    transition: all 0.15s;
  }

  .ws-copy-btn:hover { background: var(--ws-hover); color: var(--ws-text); }
  .ws-copy-btn svg { width: 14px; height: 14px; }

  .ws-code-editor {
    flex: 1;
    overflow: auto;
    padding: 0;
    position: relative;
  }

  .ws-code-lines {
    display: flex;
    min-height: 100%;
  }

  .ws-line-numbers {
    padding: 16px 0;
    text-align: right;
    min-width: 48px;
    color: var(--ws-text-muted);
    font-family: var(--font-mono);
    font-size: 11px;
    line-height: 1.7;
    user-select: none;
    background: var(--ws-code-gutter);
    position: sticky;
    left: 0;
    z-index: 1;
    border-right: 1px solid var(--ws-border-subtle);
    padding-right: 12px;
  }

  .ws-line-numbers span { display: block; }

  .ws-code-content {
    flex: 1;
    padding: 16px;
    font-family: var(--font-mono);
    font-size: 11px;
    line-height: 1.7;
    color: var(--ws-text);
    white-space: pre;
    overflow-x: auto;
    tab-size: 2;
  }

  /* Syntax highlighting — YAML light */
  .ws-hl-key { color: #3b72b8; }
  .ws-hl-string { color: #2d7a4f; }
  .ws-hl-comment { color: var(--ws-text-muted); font-style: italic; }
  .ws-hl-number { color: #b83e3e; }
  .ws-hl-bool { color: #9333ea; }
  .ws-hl-dash { color: var(--ws-text-muted); }
  .ws-hl-bracket { color: var(--ws-text-muted); }

  /* Syntax highlighting — YAML dark */
  .dark .ws-hl-key { color: #5b8fd4; }
  .dark .ws-hl-string { color: #4aba82; }
  .dark .ws-hl-number { color: #d45454; }
  .dark .ws-hl-bool { color: #a78bfa; }

  /* ================================================================ */
  /*  STATUS BAR                                                       */
  /* ================================================================ */
  .ws-statusbar {
    height: 28px;
    background: var(--ws-panel);
    border-top: 1px solid var(--ws-border);
    display: flex;
    align-items: center;
    padding: 0 14px;
    gap: 16px;
    font-size: 10px;
    color: var(--ws-text-muted);
    font-family: var(--font-mono);
    flex-shrink: 0;
  }

  .ws-statusbar-item {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .ws-statusbar-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--green);
  }

  .ws-statusbar-right {
    margin-left: auto;
    display: flex;
    gap: 16px;
  }

  .ws-statusbar-key {
    display: inline-flex;
    padding: 0 4px;
    background: var(--ws-surface);
    border: 1px solid var(--ws-border-subtle);
    font-size: 9px;
    line-height: 1.4;
  }

  /* ================================================================ */
  /*  CONFIG PANEL (slide-over inside visual pane)                     */
  /* ================================================================ */
  .ws-config {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 340px;
    background: var(--ws-panel);
    border-left: 1px solid var(--ws-border);
    z-index: 50;
    transform: translateX(100%);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
  }

  .ws-config.open { transform: translateX(0); }

  .ws-config-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--ws-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    background: var(--ws-panel);
    z-index: 1;
  }

  .ws-config-title {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ws-text);
  }

  .ws-config-close {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    color: var(--ws-text-muted);
    cursor: pointer;
    transition: all 0.15s;
  }

  .ws-config-close:hover { background: var(--ws-hover); color: var(--ws-text); }

  .ws-config-section {
    padding: 16px;
    border-bottom: 1px solid var(--ws-border-subtle);
  }

  .ws-config-section-title {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ws-text-muted);
    margin-bottom: 12px;
  }

  .ws-config-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 12px;
  }

  .ws-config-row:last-child { margin-bottom: 0; }

  .ws-config-label {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 500;
    color: var(--ws-text-secondary);
  }

  .ws-config-input {
    padding: 7px 10px;
    background: var(--ws-surface);
    border: 1px solid var(--ws-border);
    color: var(--ws-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    width: 100%;
  }

  .ws-config-input:focus { border-color: var(--ws-accent); }

  .ws-config-select {
    padding: 7px 10px;
    background: var(--ws-surface);
    border: 1px solid var(--ws-border);
    color: var(--ws-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    cursor: pointer;
    width: 100%;
  }

  .ws-config-select:focus { border-color: var(--ws-accent); }

  .ws-config-textarea {
    padding: 7px 10px;
    background: var(--ws-surface);
    border: 1px solid var(--ws-border);
    color: var(--ws-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    width: 100%;
    min-height: 60px;
    resize: vertical;
  }

  .ws-config-textarea:focus { border-color: var(--ws-accent); }

  .ws-config-type-badge {
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

  .ws-config-delete {
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

  .ws-config-delete:hover {
    background: rgba(220, 38, 38, 0.12);
    border-color: var(--red);
  }

  .ws-config-delete svg { width: 14px; height: 14px; }

  /* ================================================================ */
  /*  ADD STEP MODAL                                                   */
  /* ================================================================ */
  .ws-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    z-index: 150;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: wsFadeIn 0.15s ease;
  }

  .ws-modal {
    width: 480px;
    max-height: 520px;
    background: var(--ws-panel);
    border: 1px solid var(--ws-border);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    animation: wsModalIn 0.2s ease;
  }

  .ws-modal-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--ws-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .ws-modal-title {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ws-text);
  }

  .ws-modal-close {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    color: var(--ws-text-muted);
    cursor: pointer;
    transition: all 0.15s;
  }

  .ws-modal-close:hover { background: var(--ws-hover); color: var(--ws-text); }

  .ws-modal-search {
    padding: 8px 16px;
    border-bottom: 1px solid var(--ws-border-subtle);
  }

  .ws-search-input {
    width: 100%;
    padding: 7px 10px 7px 32px;
    background: var(--ws-surface);
    border: 1px solid var(--ws-border);
    color: var(--ws-text);
    font-size: 12px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: 10px center;
  }

  .ws-search-input:focus { border-color: var(--ws-accent); }
  .ws-search-input::placeholder { color: var(--ws-text-muted); }

  .ws-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
  }

  .ws-modal-group-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ws-text-muted);
    padding: 8px 16px 4px;
  }

  .ws-modal-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    cursor: pointer;
    transition: background 0.1s;
  }

  .ws-modal-item:hover { background: var(--ws-accent-dim); }

  .ws-modal-item-icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .ws-modal-item-icon svg { width: 14px; height: 14px; }

  .ws-modal-item-info { flex: 1; min-width: 0; }

  .ws-modal-item-name {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--ws-text);
  }

  .ws-modal-item-desc {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--ws-text-muted);
  }

  .ws-type-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .ws-dot-trigger { background: var(--ws-trigger-color); }
  .ws-dot-action { background: var(--ws-action-color); }
  .ws-dot-condition { background: var(--ws-condition-color); }
  .ws-dot-delay { background: var(--ws-delay-color); }
  .ws-dot-halt { background: var(--ws-halt-color); }

  /* ================================================================ */
  /*  RUNS TAB                                                         */
  /* ================================================================ */
  .ws-runs {
    padding: 24px;
    max-width: 900px;
    margin: 0 auto;
    width: 100%;
  }

  .ws-runs-table-wrap {
    background: var(--ws-panel);
    border: 1px solid var(--ws-border);
    overflow: hidden;
    box-shadow: var(--ws-shadow);
  }

  .ws-runs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }

  .ws-runs-table thead th {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--ws-text-muted);
    background: var(--ws-surface);
    padding: 10px 16px;
    text-align: left;
    border-bottom: 1px solid var(--ws-border);
  }

  .ws-runs-table tbody td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--ws-border-subtle);
    color: var(--ws-text);
    vertical-align: middle;
  }

  .ws-runs-table tbody tr:last-child td { border-bottom: none; }
  .ws-runs-table tbody tr:hover { background: var(--ws-hover); }

  .ws-run-id {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--blue);
    font-weight: 400;
    letter-spacing: 0.2px;
  }

  .ws-run-badge {
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

  .ws-run-badge-success { background: rgba(5, 150, 105, 0.1); color: var(--green); }
  .ws-run-badge-failed { background: rgba(220, 38, 38, 0.1); color: var(--red); }

  .ws-run-mono {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ws-text-secondary);
  }

  /* ================================================================ */
  /*  TOAST                                                            */
  /* ================================================================ */
  .ws-toast {
    position: fixed;
    bottom: 48px;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: var(--navy);
    border: 1px solid var(--ws-border);
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

  .ws-toast.visible {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }

  .ws-toast-icon { color: var(--green); }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes wsFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes wsNodeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
  }

  @keyframes wsModalIn {
    from { opacity: 0; transform: scale(0.96) translateY(8px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
  }

  .ws-main { animation: wsFadeIn 0.3s ease-out; }
  .ws-visual-pane { animation: wsFadeIn 0.3s ease-out 0.05s both; }
  .ws-code-pane { animation: wsFadeIn 0.3s ease-out 0.1s both; }
</style>

<div
  x-data="workflowEditorSplit()"
  @keydown.window="handleKeydown($event)"
>
  {{-- TOP BAR --}}
  <div class="ws-topbar">
    <div class="ws-topbar-left">
      <div class="ws-topbar-brand">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/></svg>
        Workflows
      </div>
      <div class="ws-topbar-sep"></div>
      <div style="display:flex;align-items:center;gap:10px;">
        <input
          type="text"
          class="ws-workflow-name"
          :value="workflow.name"
          @input="workflow.name = $event.target.value; isDirty = true"
          spellcheck="false"
        />
        <span class="ws-status-badge" :class="{ 'draft': workflow.status === 'draft' }" x-text="workflow.status"></span>
      </div>
    </div>

    <div class="ws-topbar-center">
      <button class="ws-topbar-tab" :class="{ 'active': activeView === 'visual' }" @click="activeView = 'visual'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Visual
      </button>
      <button class="ws-topbar-tab" :class="{ 'active': activeView === 'yaml' }" @click="activeView = 'yaml'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
        YAML
      </button>
    </div>

    <div class="ws-topbar-actions">
      <span class="ws-version" x-text="'v' + workflow.version + ' \u00b7 ' + workflow.lastSaved"></span>
      <button class="ws-btn" @click="discardChanges()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Discard
      </button>
      <button class="ws-btn ws-btn-primary" @click="publishWorkflow()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Publish
      </button>
    </div>
  </div>

  {{-- MAIN LAYOUT --}}
  <div class="ws-main" x-ref="splitContainer">

    {{-- LEFT: Visual Step List --}}
    <div class="ws-visual-pane" x-show="activeView === 'visual' || activeView === 'yaml'">
      {{-- EDITOR VIEW --}}
      <template x-if="activeTab === 'editor'">
        <div class="ws-canvas" style="position:relative;">
          <div class="ws-step-list">
            {{-- Main flow nodes --}}
            <template x-for="(node, ni) in mainFlowNodes" :key="node.id">
              <div>
                {{-- Node card --}}
                <div
                  class="ws-node"
                  :class="{ 'selected': selectedNodeId === node.id }"
                  @click.stop="selectNode(node.id)"
                >
                  <div class="ws-node-accent" :class="'ws-node-accent-' + node.type"></div>
                  <div class="ws-node-header">
                    <span class="ws-node-type-label" :style="'color: var(--ws-' + node.type + '-color)'" x-text="node.type.toUpperCase()"></span>
                    <span class="ws-node-subcategory" x-text="node.subcategory"></span>
                  </div>
                  <div class="ws-node-body">
                    <div class="ws-node-icon" :style="'background: var(--ws-' + node.type + '-bg)'" x-html="node.icon"></div>
                    <div class="ws-node-info">
                      <div class="ws-node-name" x-text="node.name"></div>
                      <div class="ws-node-desc" x-text="node.description"></div>
                    </div>
                  </div>
                </div>

                {{-- Condition branching --}}
                <template x-if="node.type === 'condition'">
                  <div>
                    <div class="ws-branch-junction">
                      <div class="ws-connector-dot"></div>
                      <div class="ws-connector-line"></div>
                    </div>
                    <div class="ws-branch-labels">
                      <div class="ws-branch-h-line"></div>
                      <div style="display:flex;width:100%;justify-content:center;gap:40%;">
                        <span class="ws-branch-label ws-branch-label-no">No</span>
                        <span class="ws-branch-label ws-branch-label-yes">Yes</span>
                      </div>
                    </div>

                    <div class="ws-branch-columns" style="margin-top:8px;">
                      {{-- NO branch --}}
                      <div class="ws-branch-col">
                        <template x-for="(bnode, bi) in getBranchNodes(node.id, 'no')" :key="bnode.id">
                          <div style="width:100%;">
                            <div class="ws-branch-v-line" style="margin:0 auto;"></div>
                            <div
                              class="ws-node"
                              :class="{ 'selected': selectedNodeId === bnode.id }"
                              @click.stop="selectNode(bnode.id)"
                            >
                              <div class="ws-node-accent" :class="'ws-node-accent-' + bnode.type"></div>
                              <div class="ws-node-header">
                                <span class="ws-node-type-label" :style="'color: var(--ws-' + bnode.type + '-color)'" x-text="bnode.type.toUpperCase()"></span>
                                <span class="ws-node-subcategory" x-text="bnode.subcategory"></span>
                              </div>
                              <div class="ws-node-body">
                                <div class="ws-node-icon" :style="'background: var(--ws-' + bnode.type + '-bg)'" x-html="bnode.icon"></div>
                                <div class="ws-node-info">
                                  <div class="ws-node-name" x-text="bnode.name"></div>
                                  <div class="ws-node-desc" x-text="bnode.description"></div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </template>
                        <div class="ws-connector" style="width:100%;">
                          <div class="ws-connector-line"></div>
                          <div class="ws-add-btn" @click.stop="openAddStep(node.id, 'no')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                          </div>
                        </div>
                      </div>

                      {{-- YES branch --}}
                      <div class="ws-branch-col">
                        <template x-for="(bnode, bi) in getBranchNodes(node.id, 'yes')" :key="bnode.id">
                          <div style="width:100%;">
                            <div class="ws-branch-v-line" style="margin:0 auto;"></div>
                            <div
                              class="ws-node"
                              :class="{ 'selected': selectedNodeId === bnode.id }"
                              @click.stop="selectNode(bnode.id)"
                            >
                              <div class="ws-node-accent" :class="'ws-node-accent-' + bnode.type"></div>
                              <div class="ws-node-header">
                                <span class="ws-node-type-label" :style="'color: var(--ws-' + bnode.type + '-color)'" x-text="bnode.type.toUpperCase()"></span>
                                <span class="ws-node-subcategory" x-text="bnode.subcategory"></span>
                              </div>
                              <div class="ws-node-body">
                                <div class="ws-node-icon" :style="'background: var(--ws-' + bnode.type + '-bg)'" x-html="bnode.icon"></div>
                                <div class="ws-node-info">
                                  <div class="ws-node-name" x-text="bnode.name"></div>
                                  <div class="ws-node-desc" x-text="bnode.description"></div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </template>
                        <div class="ws-connector" style="width:100%;">
                          <div class="ws-connector-line"></div>
                          <div class="ws-add-btn" @click.stop="openAddStep(node.id, 'yes')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </template>

                {{-- Regular connector (non-condition) --}}
                <template x-if="node.type !== 'condition'">
                  <div class="ws-connector">
                    <div class="ws-connector-dot"></div>
                    <div class="ws-connector-line"></div>
                    <div class="ws-add-btn" @click.stop="openAddStep(node.id, null)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </div>
                    <div class="ws-connector-line"></div>
                  </div>
                </template>
              </div>
            </template>
          </div>

          {{-- CONFIG PANEL --}}
          <div class="ws-config" :class="{ 'open': configPanelOpen && selectedNode }">
            <template x-if="selectedNode">
              <div>
                <div class="ws-config-header">
                  <span class="ws-config-title">Step Configuration</span>
                  <button class="ws-config-close" @click="closeConfig()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                  </button>
                </div>

                <div class="ws-config-section">
                  <div class="ws-config-section-title">Step Info</div>
                  <div style="margin-bottom:10px;">
                    <span
                      class="ws-config-type-badge"
                      :style="'background: var(--ws-' + selectedNode.type + '-bg); color: var(--ws-' + selectedNode.type + '-color)'"
                      x-text="selectedNode.type.toUpperCase() + ' \u00b7 ' + selectedNode.subcategory"
                    ></span>
                  </div>
                  <div class="ws-config-row">
                    <label class="ws-config-label">Name</label>
                    <input class="ws-config-input" :value="selectedNode.name" @input="selectedNode.name = $event.target.value; isDirty = true" />
                  </div>
                  <div class="ws-config-row">
                    <label class="ws-config-label">Description</label>
                    <textarea class="ws-config-textarea" :value="selectedNode.description" @input="selectedNode.description = $event.target.value; isDirty = true"></textarea>
                  </div>
                </div>

                <template x-if="selectedHandler && selectedHandler.configSchema.length > 0">
                  <div class="ws-config-section">
                    <div class="ws-config-section-title">Configuration</div>
                    <template x-for="field in selectedHandler.configSchema" :key="field.key">
                      <div class="ws-config-row">
                        <label class="ws-config-label" x-text="field.label"></label>
                        <template x-if="field.type === 'select'">
                          <select class="ws-config-select" @change="selectedNode.config[field.key] = $event.target.value; isDirty = true">
                            <template x-for="opt in field.options" :key="opt">
                              <option :value="opt" :selected="selectedNode.config[field.key] === opt" x-text="opt"></option>
                            </template>
                          </select>
                        </template>
                        <template x-if="field.type === 'text'">
                          <input class="ws-config-input" :value="selectedNode.config[field.key] || ''" @input="selectedNode.config[field.key] = $event.target.value; isDirty = true" />
                        </template>
                        <template x-if="field.type === 'textarea'">
                          <textarea class="ws-config-textarea" :value="selectedNode.config[field.key] || ''" @input="selectedNode.config[field.key] = $event.target.value; isDirty = true"></textarea>
                        </template>
                        <template x-if="field.type === 'number'">
                          <input class="ws-config-input" type="number" :value="selectedNode.config[field.key] || ''" @input="selectedNode.config[field.key] = $event.target.value; isDirty = true" />
                        </template>
                      </div>
                    </template>
                  </div>
                </template>

                <div class="ws-config-section">
                  <div class="ws-config-section-title">Failure Mode</div>
                  <div class="ws-config-row">
                    <label class="ws-config-label">On failure</label>
                    <select class="ws-config-select" @change="selectedNode.config._failureMode = $event.target.value; isDirty = true">
                      <option value="halt" :selected="(selectedNode.config._failureMode || 'halt') === 'halt'">Halt workflow</option>
                      <option value="skip" :selected="selectedNode.config._failureMode === 'skip'">Skip and continue</option>
                      <option value="retry" :selected="selectedNode.config._failureMode === 'retry'">Retry (3 attempts)</option>
                    </select>
                  </div>
                </div>

                <template x-if="selectedNode.type !== 'trigger'">
                  <button class="ws-config-delete" @click="deleteNode(selectedNode.id)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Delete Step
                  </button>
                </template>
              </div>
            </template>
          </div>
        </div>
      </template>

      {{-- RUNS VIEW --}}
      <template x-if="activeTab === 'runs'">
        <div class="ws-canvas">
          <div class="ws-runs">
            <div class="ws-runs-table-wrap">
              <table class="ws-runs-table">
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
                      <td><span class="ws-run-id" x-text="run.id"></span></td>
                      <td x-text="run.trigger"></td>
                      <td>
                        <span class="ws-run-badge" :class="run.status === 'Success' ? 'ws-run-badge-success' : 'ws-run-badge-failed'">
                          <span style="width:5px;height:5px;border-radius:50%;background:currentColor;"></span>
                          <span x-text="run.status"></span>
                        </span>
                      </td>
                      <td><span class="ws-run-mono" x-text="run.started"></span></td>
                      <td><span class="ws-run-mono" x-text="run.duration"></span></td>
                      <td><span class="ws-run-mono" x-text="run.steps"></span></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>
    </div>

    {{-- RESIZE HANDLE --}}
    <div class="ws-resize-handle" x-ref="resizeHandle"></div>

    {{-- RIGHT: YAML/JSON Code Pane --}}
    <div class="ws-code-pane" x-ref="codePane">
      <div class="ws-code-toolbar">
        <span class="ws-code-label">Configuration</span>
        <div class="ws-format-toggle">
          <button class="ws-format-btn" :class="{ 'active': codeFormat === 'yaml' }" @click="codeFormat = 'yaml'">YAML</button>
          <button class="ws-format-btn" :class="{ 'active': codeFormat === 'json' }" @click="codeFormat = 'json'">JSON</button>
        </div>
        <button class="ws-copy-btn" @click="copyCode()" title="Copy to clipboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
        </button>
      </div>
      <div class="ws-code-editor">
        <div class="ws-code-lines">
          <div class="ws-line-numbers" x-ref="lineNumbers"></div>
          <div class="ws-code-content" x-ref="codeContent"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- STATUS BAR --}}
  <div class="ws-statusbar">
    <div class="ws-statusbar-item">
      <div class="ws-statusbar-dot"></div>
      <span x-text="totalNodeCount + ' steps'"></span>
    </div>
    <div class="ws-statusbar-item" x-text="workflow.trigger"></div>
    <div class="ws-statusbar-item" x-text="codeFormat.toUpperCase()"></div>
    <div class="ws-statusbar-right">
      <div class="ws-statusbar-item">
        <span class="ws-statusbar-key">Del</span> delete
      </div>
      <div class="ws-statusbar-item">
        <span class="ws-statusbar-key">+</span> add step
      </div>
      <div class="ws-statusbar-item">
        <span class="ws-statusbar-key">Esc</span> close
      </div>
    </div>
  </div>

  {{-- ADD STEP MODAL --}}
  <template x-if="addStepModalOpen">
    <div class="ws-modal-overlay" @click.self="addStepModalOpen = false" @keydown.escape.window="addStepModalOpen = false">
      <div class="ws-modal">
        <div class="ws-modal-header">
          <span class="ws-modal-title">Add Step</span>
          <button class="ws-modal-close" @click="addStepModalOpen = false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
          </button>
        </div>
        <div class="ws-modal-search">
          <input
            type="text"
            class="ws-search-input"
            placeholder="Search handlers..."
            x-model="modalSearch"
            x-ref="modalSearchInput"
          />
        </div>
        <div class="ws-modal-body">
          <template x-for="group in filteredModalHandlers" :key="group.category">
            <div>
              <div class="ws-modal-group-label" x-text="group.category"></div>
              <template x-for="handler in group.handlers" :key="handler.id">
                <div class="ws-modal-item" @click="addNode(handler)">
                  <div class="ws-modal-item-icon" :style="'background: var(--ws-' + handler.type + '-bg)'" x-html="handler.icon"></div>
                  <div class="ws-modal-item-info">
                    <div class="ws-modal-item-name" x-text="handler.name"></div>
                    <div class="ws-modal-item-desc" x-text="handler.description"></div>
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
  <div class="ws-toast" :class="{ 'visible': toastVisible }">
    <span class="ws-toast-icon">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    </span>
    <span x-text="toastMessage"></span>
  </div>
</div>

@verbatim
<script>
function workflowEditorSplit() {
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
    modalSearch: '',
    activeTab: 'editor',
    activeView: 'visual',
    insertTargetId: null,
    insertTargetBranch: null,
    addStepModalOpen: false,
    isDirty: true,
    codeFormat: 'yaml',
    isResizing: false,
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

    // ================================================================
    //  YAML / JSON generation
    // ================================================================
    buildWorkflowDefinition() {
      const mainNodes = this.mainFlowNodes;
      const def = {
        workflow: {
          name: this.workflow.name,
          description: this.workflow.description,
          status: this.workflow.status,
          version: this.workflow.version,
          trigger: {},
          steps: [],
        }
      };

      for (const node of mainNodes) {
        if (node.type === 'trigger') {
          def.workflow.trigger = {
            handler: node.handlerId,
            name: node.name,
            config: this.cleanConfig(node.config),
          };
        } else if (node.type === 'condition') {
          const condStep = {
            type: 'condition',
            handler: node.handlerId,
            name: node.name,
            description: node.description,
            config: this.cleanConfig(node.config),
            branches: {
              'yes': this.buildBranchSteps(node.id, 'yes'),
              'no': this.buildBranchSteps(node.id, 'no'),
            }
          };
          def.workflow.steps.push(condStep);
        } else {
          def.workflow.steps.push({
            type: node.type,
            handler: node.handlerId,
            name: node.name,
            description: node.description,
            config: this.cleanConfig(node.config),
          });
        }
      }

      return def;
    },

    buildBranchSteps(conditionId, branch) {
      const branchNodes = this.getBranchNodes(conditionId, branch);
      return branchNodes.map(n => ({
        type: n.type,
        handler: n.handlerId,
        name: n.name,
        description: n.description,
        config: this.cleanConfig(n.config),
      }));
    },

    cleanConfig(config) {
      const cleaned = {};
      for (const [key, val] of Object.entries(config)) {
        if (key.startsWith('_')) continue;
        if (val !== '' && val !== null && val !== undefined) {
          cleaned[key] = val;
        }
      }
      return Object.keys(cleaned).length > 0 ? cleaned : undefined;
    },

    get yamlOutput() {
      const def = this.buildWorkflowDefinition();
      return this.toYaml(def, 0);
    },

    get jsonOutput() {
      const def = this.buildWorkflowDefinition();
      return JSON.stringify(def, null, 2);
    },

    toYaml(obj, indent) {
      const pad = '  '.repeat(indent);
      let out = '';

      if (Array.isArray(obj)) {
        if (obj.length === 0) return '[]';
        for (const item of obj) {
          if (typeof item === 'object' && item !== null) {
            out += pad + '-\n';
            const inner = this.toYaml(item, indent + 1);
            out += inner;
          } else {
            out += pad + '- ' + this.yamlValue(item) + '\n';
          }
        }
        return out;
      }

      if (typeof obj === 'object' && obj !== null) {
        const keys = Object.keys(obj);
        for (const key of keys) {
          const val = obj[key];
          if (val === undefined) continue;

          if (val === null) {
            out += pad + key + ': null\n';
          } else if (typeof val === 'object' && !Array.isArray(val)) {
            const innerKeys = Object.keys(val);
            if (innerKeys.length === 0) {
              out += pad + key + ': {}\n';
            } else {
              out += pad + key + ':\n';
              out += this.toYaml(val, indent + 1);
            }
          } else if (Array.isArray(val)) {
            if (val.length === 0) {
              out += pad + key + ': []\n';
            } else {
              out += pad + key + ':\n';
              out += this.toYaml(val, indent + 1);
            }
          } else {
            out += pad + key + ': ' + this.yamlValue(val) + '\n';
          }
        }
        return out;
      }

      return pad + this.yamlValue(obj) + '\n';
    },

    yamlValue(val) {
      if (typeof val === 'string') {
        if (val === '' || val === 'true' || val === 'false' || val === 'null' ||
            val.includes(':') || val.includes('#') || val.includes('{') ||
            val.includes('}') || val.includes('[') || val.includes(']') ||
            val.includes(',') || val.includes('&') || val.includes('*') ||
            val.includes('?') || val.includes('|') || val.includes('>') ||
            val.includes("'") || val.includes('"') || val.includes('%') ||
            val.includes('@') || val.includes('`') || /^\d/.test(val)) {
          return "'" + val.replace(/'/g, "''") + "'";
        }
        return val;
      }
      if (typeof val === 'number') return String(val);
      if (typeof val === 'boolean') return val ? 'true' : 'false';
      if (val === null) return 'null';
      return String(val);
    },

    // ================================================================
    //  Syntax highlighting
    // ================================================================
    highlightYaml(code) {
      let h = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

      // Comments
      h = h.replace(/(#.*)$/gm, '<span class="ws-hl-comment">$1</span>');

      // Array dashes
      h = h.replace(/^(\s*)(- )(.*)$/gm, function(match, spaces, dash, rest) {
        return spaces + '<span class="ws-hl-dash">' + dash + '</span>' + rest;
      });

      // Keys (word followed by colon)
      h = h.replace(/^(\s*)([\w][\w.-]*)(\s*:)/gm, '$1<span class="ws-hl-key">$2</span>$3');

      // Quoted strings (single and double)
      h = h.replace(/('[^']*')/g, '<span class="ws-hl-string">$1</span>');
      h = h.replace(/("[^"]*")/g, '<span class="ws-hl-string">$1</span>');

      // Booleans
      h = h.replace(/:\s+(true|false)\b/g, function(match, val) {
        return ': <span class="ws-hl-bool">' + val + '</span>';
      });

      // Numbers
      h = h.replace(/:\s+(\d+(?:\.\d+)?)\s*$/gm, function(match, num) {
        return ': <span class="ws-hl-number">' + num + '</span>';
      });

      // Null
      h = h.replace(/:\s+(null)\b/g, function(match, val) {
        return ': <span class="ws-hl-bool">' + val + '</span>';
      });

      // Brackets
      h = h.replace(/(\[\]|\{\})/g, '<span class="ws-hl-bracket">$1</span>');

      return h;
    },

    highlightJson(code) {
      let h = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

      // Keys
      h = h.replace(/("[\w.-]+")\s*:/g, '<span class="ws-hl-key">$1</span>:');

      // String values (after colon)
      h = h.replace(/:\s*("(?:[^"\\]|\\.)*")/g, function(match, str) {
        return ': <span class="ws-hl-string">' + str + '</span>';
      });

      // Standalone string values in arrays
      h = h.replace(/(\[\s*\n?\s*|,\s*\n?\s*)("(?:[^"\\]|\\.)*")(?=\s*[,\]])/g, function(match, prefix, str) {
        return prefix + '<span class="ws-hl-string">' + str + '</span>';
      });

      // Numbers
      h = h.replace(/:\s*(\d+(?:\.\d+)?)\b/g, ': <span class="ws-hl-number">$1</span>');

      // Booleans and null
      h = h.replace(/:\s*(true|false|null)\b/g, function(match, val) {
        return ': <span class="ws-hl-bool">' + val + '</span>';
      });

      // Brackets
      h = h.replace(/([{}\[\]])/g, '<span class="ws-hl-bracket">$1</span>');

      return h;
    },

    renderCode() {
      const content = this.$refs.codeContent;
      const lineNums = this.$refs.lineNumbers;
      if (!content || !lineNums) return;

      let code, highlighted;
      if (this.codeFormat === 'yaml') {
        code = this.yamlOutput;
        highlighted = this.highlightYaml(code);
      } else {
        code = this.jsonOutput;
        highlighted = this.highlightJson(code);
      }

      // Safe: renders hardcoded workflow definition data with syntax highlighting spans
      content.innerHTML = highlighted;
      const lines = code.split('\n');
      lineNums.innerHTML = lines.map((_, i) => '<span>' + (i + 1) + '</span>').join('');
    },

    copyCode() {
      const code = this.codeFormat === 'yaml' ? this.yamlOutput : this.jsonOutput;
      navigator.clipboard.writeText(code).then(() => {
        this.showToast('Copied ' + this.codeFormat.toUpperCase() + ' to clipboard');
      }).catch(() => {
        this.showToast('Failed to copy');
      });
    },

    // ================================================================
    //  Resize handle
    // ================================================================
    initResize() {
      const handle = this.$refs.resizeHandle;
      const codePane = this.$refs.codePane;
      const container = this.$refs.splitContainer;
      if (!handle || !codePane || !container) return;

      handle.addEventListener('mousedown', () => {
        this.isResizing = true;
        handle.classList.add('dragging');
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
      });

      document.addEventListener('mousemove', (e) => {
        if (!this.isResizing) return;
        const rect = container.getBoundingClientRect();
        const w = Math.max(300, Math.min(rect.width - 400, rect.right - e.clientX));
        codePane.style.width = w + 'px';
        codePane.style.flex = 'none';
      });

      document.addEventListener('mouseup', () => {
        if (!this.isResizing) return;
        this.isResizing = false;
        handle.classList.remove('dragging');
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
      });
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

      if (e.key === '+' && !e.target.closest('input, textarea, select')) {
        e.preventDefault();
        const mainNodes = this.mainFlowNodes;
        const last = mainNodes[mainNodes.length - 1];
        if (last) this.openAddStep(last.id, null);
      }
    },

    init() {
      this.$nextTick(() => {
        this.renderCode();
        this.initResize();
      });

      this.$watch('nodes', () => {
        this.$nextTick(() => this.renderCode());
      }, { deep: true });

      this.$watch('codeFormat', () => {
        this.$nextTick(() => this.renderCode());
      });

      this.$watch('workflow', () => {
        this.$nextTick(() => this.renderCode());
      }, { deep: true });
    },
  };
}
</script>
@endverbatim
