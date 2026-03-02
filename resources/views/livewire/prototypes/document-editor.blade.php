<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.editor')] #[Title('Document Template Editor')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  EDITOR TOKENS — maps to brand system in app.css                 */
  /* ================================================================ */
  :root {
    --editor-bg: var(--content-bg);
    --editor-panel: var(--card-bg);
    --editor-surface: var(--base);
    --editor-border: var(--card-border);
    --editor-border-subtle: var(--grey-border);
    --editor-text: var(--text-primary);
    --editor-text-secondary: var(--text-secondary);
    --editor-text-muted: var(--text-muted);
    --editor-accent: var(--green);
    --editor-accent-dim: var(--green-muted);
    --editor-hover: rgba(0, 0, 0, 0.04);
    --editor-code-bg: var(--base);
    --editor-code-gutter: #f0efe9;
    --editor-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    --topbar-h: 52px;
    --tabbar-h: 40px;
    --sidebar-w: 272px;
  }

  .dark {
    --editor-bg: var(--content-bg);
    --editor-panel: var(--card-bg);
    --editor-surface: var(--navy-mid);
    --editor-border: var(--card-border);
    --editor-border-subtle: #283040;
    --editor-text: var(--text-primary);
    --editor-text-secondary: var(--text-secondary);
    --editor-text-muted: var(--text-muted);
    --editor-accent: var(--green);
    --editor-accent-dim: rgba(5, 150, 105, 0.12);
    --editor-hover: rgba(255, 255, 255, 0.06);
    --editor-code-bg: var(--navy);
    --editor-code-gutter: rgba(255, 255, 255, 0.04);
    --editor-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
  }

  /* ================================================================ */
  /*  TOP BAR                                                          */
  /* ================================================================ */
  .de-topbar {
    height: var(--topbar-h);
    background: var(--navy);
    border-bottom: 1px solid var(--editor-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    z-index: 100;
    position: relative;
  }

  .de-topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .de-topbar-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--grey-light);
  }

  .de-topbar-brand svg { opacity: 0.5; }

  .de-topbar-sep {
    width: 1px;
    height: 20px;
    background: rgba(255, 255, 255, 0.12);
  }

  .de-template-name {
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

  .de-template-name:hover { border-color: rgba(255, 255, 255, 0.15); }
  .de-template-name:focus { border-color: var(--green); background: rgba(255, 255, 255, 0.06); }

  .de-doc-badge {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 3px 10px;
    background: rgba(5, 150, 105, 0.15);
    color: var(--green);
  }

  .de-topbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .de-version {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--grey);
    padding: 0 8px;
  }

  .de-btn {
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

  .de-btn:hover { background: rgba(255, 255, 255, 0.1); color: #ffffff; }
  .de-btn svg { width: 14px; height: 14px; }

  .de-btn-primary {
    background: var(--green);
    color: #ffffff;
    border-color: var(--green);
  }

  .de-btn-primary:hover {
    background: #06b07a;
    border-color: #06b07a;
    color: #ffffff;
  }

  /* ================================================================ */
  /*  MAIN LAYOUT                                                      */
  /* ================================================================ */
  .de-main {
    display: flex;
    height: calc(100vh - var(--topbar-h));
  }

  /* ================================================================ */
  /*  SIDEBAR: Field Browser                                           */
  /* ================================================================ */
  .de-sidebar {
    width: var(--sidebar-w);
    min-width: var(--sidebar-w);
    background: var(--editor-panel);
    border-right: 1px solid var(--editor-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .de-sidebar-header {
    padding: 12px 14px;
    border-bottom: 1px solid var(--editor-border-subtle);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .de-sidebar-title {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--editor-text-muted);
  }

  .de-sidebar-search {
    padding: 8px 12px;
    border-bottom: 1px solid var(--editor-border-subtle);
  }

  .de-search-input {
    width: 100%;
    padding: 7px 10px 7px 32px;
    background: var(--editor-surface);
    border: 1px solid var(--editor-border);
    color: var(--editor-text);
    font-size: 12px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: 10px center;
  }

  .de-search-input:focus { border-color: var(--editor-accent); }
  .de-search-input::placeholder { color: var(--editor-text-muted); }

  .de-field-groups {
    flex: 1;
    overflow-y: auto;
    padding: 4px 0;
  }

  .de-field-group { margin-bottom: 1px; }

  .de-field-group-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    cursor: pointer;
    user-select: none;
    transition: background 0.1s;
  }

  .de-field-group-header:hover { background: var(--editor-hover); }

  .de-field-group-chevron {
    width: 16px;
    height: 16px;
    color: var(--editor-text-muted);
    transition: transform 0.15s;
    flex-shrink: 0;
  }

  .de-field-group.open .de-field-group-chevron { transform: rotate(90deg); }

  .de-field-group-label {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--editor-text-secondary);
  }

  .de-field-group-count {
    font-family: var(--font-mono);
    font-size: 9px;
    color: var(--editor-text-muted);
    margin-left: auto;
    background: var(--editor-surface);
    padding: 1px 6px;
  }

  .de-field-group-items {
    display: none;
    padding: 0 0 4px 0;
  }

  .de-field-group.open .de-field-group-items { display: block; }

  .de-field-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 14px 5px 36px;
    cursor: pointer;
    transition: background 0.1s;
    position: relative;
  }

  .de-field-item:hover { background: var(--editor-accent-dim); }

  .de-field-item-name {
    font-size: 11px;
    font-family: var(--font-mono);
    color: var(--editor-text);
    font-weight: 400;
  }

  .de-field-item-type {
    font-size: 9px;
    color: var(--editor-text-muted);
    margin-left: auto;
    font-family: var(--font-mono);
  }

  .de-field-item-insert {
    opacity: 0;
    font-family: var(--font-display);
    font-size: 9px;
    color: var(--editor-accent);
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    transition: opacity 0.1s;
    position: absolute;
    right: 14px;
  }

  .de-field-item:hover .de-field-item-insert { opacity: 1; }
  .de-field-item:hover .de-field-item-type { display: none; }

  .de-type-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .de-type-string { background: var(--blue); }
  .de-type-currency { background: var(--green); }
  .de-type-date { background: var(--amber); }
  .de-type-collection { background: var(--violet); }
  .de-type-boolean { background: var(--red); }
  .de-type-number { background: var(--sky); }

  /* ================================================================ */
  /*  EDITOR AREA                                                      */
  /* ================================================================ */
  .de-editor-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
  }

  .de-tabs {
    height: var(--tabbar-h);
    background: var(--editor-panel);
    border-bottom: 1px solid var(--editor-border);
    display: flex;
    align-items: stretch;
    padding: 0 4px;
    gap: 2px;
  }

  .de-tab {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 14px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--editor-text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.15s;
    user-select: none;
    position: relative;
    top: 1px;
  }

  .de-tab:hover { color: var(--editor-text-secondary); }

  .de-tab.active {
    color: var(--editor-text);
    border-bottom-color: var(--editor-accent);
  }

  .de-tab svg { width: 14px; height: 14px; opacity: 0.5; }
  .de-tab.active svg { opacity: 0.8; }

  .de-tab-divider {
    width: 1px;
    background: var(--editor-border-subtle);
    margin: 8px 4px;
    align-self: stretch;
  }

  /* ================================================================ */
  /*  SPLIT: Code + Preview                                            */
  /* ================================================================ */
  .de-split {
    flex: 1;
    display: flex;
    overflow: hidden;
    position: relative;
  }

  .de-code-pane {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--editor-code-bg);
    overflow: hidden;
    position: relative;
  }

  .de-code-editor {
    flex: 1;
    overflow: auto;
    padding: 0;
    position: relative;
  }

  .de-code-lines {
    display: flex;
    min-height: 100%;
  }

  .de-line-numbers {
    padding: 16px 0;
    text-align: right;
    min-width: 48px;
    color: var(--editor-text-muted);
    font-family: var(--font-mono);
    font-size: 11px;
    line-height: 1.7;
    user-select: none;
    background: var(--editor-code-gutter);
    position: sticky;
    left: 0;
    z-index: 1;
    border-right: 1px solid var(--editor-border-subtle);
    padding-right: 12px;
  }

  .de-line-numbers span { display: block; }

  .de-code-content {
    flex: 1;
    padding: 16px;
    font-family: var(--font-mono);
    font-size: 11px;
    line-height: 1.7;
    color: var(--editor-text);
    white-space: pre;
    overflow-x: auto;
    outline: none;
    tab-size: 2;
    caret-color: var(--editor-accent);
  }

  /* Syntax highlighting — light mode */
  .de-hl-tag { color: #b83e3e; }
  .de-hl-attr { color: #8a6b20; }
  .de-hl-string { color: #2d7a4f; }
  .de-hl-blade { color: #059669; font-weight: 600; }
  .de-hl-blade-var { color: #06855a; }
  .de-hl-comment { color: var(--editor-text-muted); font-style: italic; }
  .de-hl-css-prop { color: #3b72b8; }
  .de-hl-css-val { color: #2d7a4f; }

  /* Syntax highlighting — dark mode */
  .dark .de-hl-tag { color: #d45454; }
  .dark .de-hl-attr { color: #d4a944; }
  .dark .de-hl-string { color: #4aba82; }
  .dark .de-hl-blade { color: #34d399; font-weight: 500; }
  .dark .de-hl-blade-var { color: #6ee7b7; }
  .dark .de-hl-css-prop { color: #5b8fd4; }
  .dark .de-hl-css-val { color: #4aba82; }

  /* ================================================================ */
  /*  RESIZE HANDLE                                                    */
  /* ================================================================ */
  .de-resize-handle {
    width: 5px;
    background: var(--editor-border);
    cursor: col-resize;
    flex-shrink: 0;
    transition: background 0.15s;
    position: relative;
    z-index: 10;
  }

  .de-resize-handle:hover,
  .de-resize-handle.dragging {
    background: var(--editor-accent);
  }

  /* ================================================================ */
  /*  PREVIEW PANE                                                     */
  /* ================================================================ */
  .de-preview {
    width: 50%;
    min-width: 300px;
    display: flex;
    flex-direction: column;
    background: var(--editor-bg);
    overflow: hidden;
  }

  .de-preview-toolbar {
    height: 40px;
    background: var(--editor-panel);
    border-bottom: 1px solid var(--editor-border);
    display: flex;
    align-items: center;
    padding: 0 12px;
    gap: 8px;
    flex-shrink: 0;
  }

  .de-preview-label {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--editor-text-muted);
  }

  .de-zoom {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .de-zoom-btn {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--editor-border);
    background: var(--editor-surface);
    color: var(--editor-text-muted);
    cursor: pointer;
    font-size: 14px;
    font-family: var(--font-mono);
    transition: all 0.15s;
  }

  .de-zoom-btn:hover { background: var(--editor-hover); color: var(--editor-text); }

  .de-zoom-level {
    font-size: 10px;
    color: var(--editor-text-muted);
    font-family: var(--font-mono);
    min-width: 36px;
    text-align: center;
  }

  .de-data-select {
    background: var(--editor-surface);
    border: 1px solid var(--editor-border);
    color: var(--editor-text-secondary);
    font-size: 11px;
    font-family: var(--font-mono);
    padding: 4px 8px;
    outline: none;
    cursor: pointer;
  }

  .de-data-select:focus { border-color: var(--editor-accent); }

  .de-preview-canvas {
    flex: 1;
    overflow: auto;
    display: flex;
    justify-content: center;
    padding: 32px;
    background: var(--editor-bg);
  }

  .dark .de-preview-canvas {
    background:
      radial-gradient(circle at 50% 50%, rgba(5, 150, 105, 0.03), transparent 70%),
      repeating-conic-gradient(var(--navy) 0% 25%, var(--navy-mid) 0% 50%) 0 0 / 20px 20px;
  }

  .de-preview-paper {
    background: #ffffff;
    color: #1a1a1a;
    width: 595px;
    min-height: 842px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08), 0 16px 48px rgba(0,0,0,0.06);
    flex-shrink: 0;
    overflow: hidden;
    transform-origin: top center;
    transition: transform 0.2s;
    position: relative;
    border: 1px solid var(--editor-border);
  }

  .dark .de-preview-paper {
    box-shadow: 0 2px 8px rgba(0,0,0,0.4), 0 20px 60px rgba(0,0,0,0.3);
    border: none;
  }

  .de-preview-inner {
    padding: 48px 40px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 11px;
    line-height: 1.6;
    color: #1a1a1a;
  }

  /* ================================================================ */
  /*  STATUS BAR                                                       */
  /* ================================================================ */
  .de-statusbar {
    height: 28px;
    background: var(--editor-panel);
    border-top: 1px solid var(--editor-border);
    display: flex;
    align-items: center;
    padding: 0 14px;
    gap: 16px;
    font-size: 10px;
    color: var(--editor-text-muted);
    font-family: var(--font-mono);
  }

  .de-statusbar-item {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .de-statusbar-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--green);
  }

  .de-statusbar-right {
    margin-left: auto;
    display: flex;
    gap: 16px;
  }

  /* ================================================================ */
  /*  CONFIG PANEL (slide-over)                                        */
  /* ================================================================ */
  .de-config {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 320px;
    background: var(--editor-panel);
    border-left: 1px solid var(--editor-border);
    z-index: 50;
    transform: translateX(100%);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
  }

  .de-config.open { transform: translateX(0); }

  .de-config-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--editor-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    background: var(--editor-panel);
    z-index: 1;
  }

  .de-config-title {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--editor-text);
  }

  .de-config-close {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    color: var(--editor-text-muted);
    cursor: pointer;
    transition: all 0.15s;
  }

  .de-config-close:hover { background: var(--editor-hover); color: var(--editor-text); }

  .de-config-section {
    padding: 16px;
    border-bottom: 1px solid var(--editor-border-subtle);
  }

  .de-config-section-title {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--editor-text-muted);
    margin-bottom: 12px;
  }

  .de-config-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
  }

  .de-config-row:last-child { margin-bottom: 0; }

  .de-config-label {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--editor-text-secondary);
    width: 80px;
    flex-shrink: 0;
  }

  .de-config-input {
    flex: 1;
    padding: 6px 10px;
    background: var(--editor-surface);
    border: 1px solid var(--editor-border);
    color: var(--editor-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
  }

  .de-config-input:focus { border-color: var(--editor-accent); }

  .de-config-select {
    flex: 1;
    padding: 6px 10px;
    background: var(--editor-surface);
    border: 1px solid var(--editor-border);
    color: var(--editor-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    cursor: pointer;
  }

  .de-config-select:focus { border-color: var(--editor-accent); }

  .de-color-swatch {
    width: 28px;
    height: 28px;
    border: 2px solid var(--editor-border);
    cursor: pointer;
    flex-shrink: 0;
  }

  .de-margin-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
  }

  .de-margin-wrap {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .de-margin-wrap label {
    font-family: var(--font-mono);
    font-size: 9px;
    color: var(--editor-text-muted);
    width: 10px;
    text-transform: uppercase;
    font-weight: 500;
  }

  .de-margin-wrap input {
    width: 100%;
    padding: 5px 6px;
    background: var(--editor-surface);
    border: 1px solid var(--editor-border);
    color: var(--editor-text);
    font-size: 10px;
    font-family: var(--font-mono);
    outline: none;
    text-align: center;
  }

  .de-margin-wrap input:focus { border-color: var(--editor-accent); }

  /* ================================================================ */
  /*  TOAST                                                            */
  /* ================================================================ */
  .de-toast {
    position: fixed;
    bottom: 48px;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: var(--navy);
    border: 1px solid var(--editor-border);
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

  .de-toast.visible {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }

  .de-toast-icon { color: var(--green); }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes deFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .de-main { animation: deFadeIn 0.3s ease-out; }
  .de-sidebar { animation: deFadeIn 0.3s ease-out 0.05s both; }
  .de-code-pane { animation: deFadeIn 0.3s ease-out 0.1s both; }
  .de-preview { animation: deFadeIn 0.3s ease-out 0.15s both; }
</style>

<div
  x-data="documentEditor()"
  x-init="init()"
>
  {{-- TOP BAR --}}
  <div class="de-topbar">
    <div class="de-topbar-left">
      <div class="de-topbar-brand">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
        Templates
      </div>
      <div class="de-topbar-sep"></div>
      <div style="display:flex;align-items:center;gap:10px;">
        <input type="text" class="de-template-name" value="Standard Quote" spellcheck="false" />
        <span class="de-doc-badge">Quote</span>
      </div>
    </div>
    <div class="de-topbar-actions">
      <span class="de-version">v3 · Saved 2m ago</span>
      <button class="de-btn" @click="configOpen = !configOpen">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
        Page Setup
      </button>
      <button class="de-btn" @click="showToast('Preview rendered with sample data')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
        Preview
      </button>
      <button class="de-btn de-btn-primary" @click="showToast('Template saved — v4')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
        Save
      </button>
    </div>
  </div>

  {{-- MAIN LAYOUT --}}
  <div class="de-main">

    {{-- SIDEBAR --}}
    <div class="de-sidebar" x-show="sidebarOpen" x-transition>
      <div class="de-sidebar-header">
        <span class="de-sidebar-title">Available Fields</span>
      </div>
      <div class="de-sidebar-search">
        <input type="text" class="de-search-input" placeholder="Search fields…" x-model="searchQuery" />
      </div>
      <div class="de-field-groups">
        <template x-for="(group, gi) in filteredGroups" :key="group.group">
          <div class="de-field-group" :class="{ 'open': searchQuery || gi < 3 }">
            <div class="de-field-group-header" @click="$el.parentElement.classList.toggle('open')">
              <svg class="de-field-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
              <span class="de-field-group-label" x-text="group.group"></span>
              <span class="de-field-group-count" x-text="group.fields.length"></span>
            </div>
            <div class="de-field-group-items">
              <template x-for="field in group.fields" :key="field.name">
                <div class="de-field-item" @click="insertField(field.name)" :title="field.label">
                  <div class="de-type-dot" :class="'de-type-' + field.type"></div>
                  <span class="de-field-item-name" x-text="field.name"></span>
                  <span class="de-field-item-type" x-text="field.type"></span>
                  <span class="de-field-item-insert">Insert</span>
                </div>
              </template>
            </div>
          </div>
        </template>
      </div>
    </div>

    {{-- EDITOR AREA --}}
    <div class="de-editor-area">
      <div class="de-tabs">
        <template x-for="tab in [{id:'body',label:'Body',icon:'doc'},{id:'header',label:'Header',icon:'header'},{id:'footer',label:'Footer',icon:'footer'}]" :key="tab.id">
          <div class="de-tab" :class="{ 'active': activeTab === tab.id }" @click="switchTab(tab.id)">
            <svg x-show="tab.icon === 'doc'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
            <svg x-show="tab.icon === 'header'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/></svg>
            <svg x-show="tab.icon === 'footer'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 15h18"/></svg>
            <span x-text="tab.label"></span>
          </div>
        </template>
        <div class="de-tab-divider"></div>
        <div class="de-tab" :class="{ 'active': activeTab === 'css' }" @click="switchTab('css')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          Styles
        </div>
      </div>

      <div class="de-split" x-ref="editorSplit">
        {{-- Code Editor --}}
        <div class="de-code-pane">
          <div class="de-code-editor">
            <div class="de-code-lines">
              <div class="de-line-numbers" x-ref="lineNumbers"></div>
              <div
                class="de-code-content"
                contenteditable="true"
                spellcheck="false"
                x-ref="codeContent"
              ></div>
            </div>
          </div>
        </div>

        {{-- Resize Handle --}}
        <div class="de-resize-handle" x-ref="resizeHandle"></div>

        {{-- Preview --}}
        <div class="de-preview" x-ref="previewPane">
          <div class="de-preview-toolbar">
            <span class="de-preview-label">Live Preview</span>
            <select class="de-data-select" @change="showToast('Rendering with: ' + $event.target.value)">
              <option>Sample Data</option>
              <option>QUO-2025-0042 — Apex Events Ltd</option>
              <option>QUO-2025-0039 — Mercury Productions</option>
              <option>QUO-2025-0037 — Neon Festival Group</option>
            </select>
            <div class="de-zoom">
              <button class="de-zoom-btn" @click="adjustZoom(-10)">&minus;</button>
              <span class="de-zoom-level" x-text="zoomLevel + '%'"></span>
              <button class="de-zoom-btn" @click="adjustZoom(10)">+</button>
            </div>
          </div>
          <div class="de-preview-canvas">
            <div class="de-preview-paper" :style="'transform: scale(' + (zoomLevel / 100) + ')'">
              <div class="de-preview-inner" x-ref="previewContent"></div>
            </div>
          </div>

          {{-- Config Slide-over --}}
          <div class="de-config" :class="{ 'open': configOpen }">
            <div class="de-config-header">
              <span class="de-config-title">Page Setup</span>
              <button class="de-config-close" @click="configOpen = false">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
              </button>
            </div>

            <div class="de-config-section">
              <div class="de-config-section-title">Page</div>
              <div class="de-config-row">
                <span class="de-config-label">Size</span>
                <select class="de-config-select">
                  <option selected>A4 (210 × 297mm)</option>
                  <option>Letter (8.5 × 11in)</option>
                  <option>A3 (297 × 420mm)</option>
                  <option>Custom</option>
                </select>
              </div>
              <div class="de-config-row">
                <span class="de-config-label">Orientation</span>
                <select class="de-config-select">
                  <option selected>Portrait</option>
                  <option>Landscape</option>
                </select>
              </div>
            </div>

            <div class="de-config-section">
              <div class="de-config-section-title">Margins (mm)</div>
              <div class="de-margin-grid">
                <div class="de-margin-wrap"><label>T</label><input type="number" value="20" /></div>
                <div class="de-margin-wrap"><label>R</label><input type="number" value="15" /></div>
                <div class="de-margin-wrap"><label>B</label><input type="number" value="20" /></div>
                <div class="de-margin-wrap"><label>L</label><input type="number" value="15" /></div>
              </div>
            </div>

            <div class="de-config-section">
              <div class="de-config-section-title">Branding</div>
              <div class="de-config-row">
                <span class="de-config-label">Primary</span>
                <div class="de-color-swatch" style="background: #059669;"></div>
                <input class="de-config-input" value="#059669" style="flex:1" />
              </div>
              <div class="de-config-row">
                <span class="de-config-label">Secondary</span>
                <div class="de-color-swatch" style="background: #334155;"></div>
                <input class="de-config-input" value="#334155" style="flex:1" />
              </div>
              <div class="de-config-row">
                <span class="de-config-label">Font</span>
                <select class="de-config-select">
                  <option selected>System Default</option>
                  <option>Helvetica</option>
                  <option>Georgia</option>
                  <option>Custom (upload)</option>
                </select>
              </div>
              <div class="de-config-row">
                <span class="de-config-label">Logo</span>
                <button class="de-btn" style="flex:1; justify-content:center; border-color: var(--editor-border); background: var(--editor-surface); color: var(--editor-text-secondary);">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                  Upload Logo
                </button>
              </div>
            </div>

            <div class="de-config-section">
              <div class="de-config-section-title">Numbering</div>
              <div class="de-config-row">
                <span class="de-config-label">Prefix</span>
                <input class="de-config-input" value="QUO-" style="flex:1" />
              </div>
              <div class="de-config-row">
                <span class="de-config-label">Padding</span>
                <select class="de-config-select">
                  <option>3 digits (001)</option>
                  <option selected>4 digits (0001)</option>
                  <option>5 digits (00001)</option>
                </select>
              </div>
              <div class="de-config-row">
                <span class="de-config-label">Year</span>
                <select class="de-config-select">
                  <option selected>Yes (QUO-2025-0001)</option>
                  <option>No (QUO-0001)</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Status Bar --}}
      <div class="de-statusbar">
        <div class="de-statusbar-item">
          <div class="de-statusbar-dot"></div>
          Preview synced
        </div>
        <div class="de-statusbar-item">Blade + HTML</div>
        <div class="de-statusbar-right">
          <div class="de-statusbar-item">Ln <span x-text="cursorLine"></span>, Col <span x-text="cursorCol"></span></div>
          <div class="de-statusbar-item">UTF-8</div>
          <div class="de-statusbar-item">A4 Portrait</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Toast --}}
  <div class="de-toast" :class="{ 'visible': toastVisible }">
    <span class="de-toast-icon">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    </span>
    <span x-text="toastMessage"></span>
  </div>
</div>

@verbatim
<script>
function documentEditor() {
  return {
    activeTab: 'body',
    sidebarOpen: true,
    configOpen: false,
    zoomLevel: 75,
    searchQuery: '',
    cursorLine: 1,
    cursorCol: 1,
    toastMessage: '',
    toastVisible: false,
    toastTimeout: null,
    isResizing: false,

    templates: {
      body: `{{-- Quote Template: Standard --}}\n<div class="document-header">\n  <div class="logo-area">\n    @if($store->logo_url)\n      <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="logo" />\n    @endif\n  </div>\n  <div class="doc-info">\n    <h1>QUOTATION</h1>\n    <table class="meta-table">\n      <tr>\n        <td class="label">Quote No.</td>\n        <td>{{ $document->number }}</td>\n      </tr>\n      <tr>\n        <td class="label">Date</td>\n        <td>{{ $document->date->format('d M Y') }}</td>\n      </tr>\n      <tr>\n        <td class="label">Valid Until</td>\n        <td>{{ $document->due_date->format('d M Y') }}</td>\n      </tr>\n    </table>\n  </div>\n</div>\n\n<div class="addresses">\n  <div class="address-block">\n    <h3>From</h3>\n    <p class="company">{{ $store->name }}</p>\n    <p>{{ $store->address }}</p>\n    <p>{{ $store->phone }}</p>\n    <p>{{ $store->email }}</p>\n  </div>\n  <div class="address-block">\n    <h3>To</h3>\n    <p class="company">{{ $member->company_name }}</p>\n    <p>{{ $member->name }}</p>\n    <p>{{ $member->billing_address }}</p>\n    <p>{{ $member->email }}</p>\n  </div>\n</div>\n\n<div class="event-details">\n  <h3>{{ $opportunity->subject }}</h3>\n  <p class="dates">\n    {{ $opportunity->starts_at->format('d M Y') }} \u2014\n    {{ $opportunity->ends_at->format('d M Y') }}\n    <span class="days">({{ $opportunity->rental_days }} days)</span>\n  </p>\n</div>\n\n<table class="items-table">\n  <thead>\n    <tr>\n      <th class="item-name">Item</th>\n      <th class="item-qty">Qty</th>\n      <th class="item-rate">Unit Rate</th>\n      <th class="item-disc">Discount</th>\n      <th class="item-total">Total</th>\n    </tr>\n  </thead>\n  <tbody>\n    @foreach($items as $item)\n    <tr>\n      <td class="item-name">\n        <strong>{{ $item->product_name }}</strong>\n        @if($item->description)\n          <br><span class="desc">{{ $item->description }}</span>\n        @endif\n      </td>\n      <td class="item-qty">{{ $item->quantity }}</td>\n      <td class="item-rate">\u00a3{{ number_format($item->unit_charge, 2) }}</td>\n      <td class="item-disc">\n        @if($item->discount > 0)\n          -\u00a3{{ number_format($item->discount, 2) }}\n        @else\n          \u2014\n        @endif\n      </td>\n      <td class="item-total">\u00a3{{ number_format($item->total_charge, 2) }}</td>\n    </tr>\n    @endforeach\n  </tbody>\n</table>\n\n<div class="totals">\n  <table class="totals-table">\n    <tr>\n      <td>Subtotal</td>\n      <td>\u00a3{{ number_format($opportunity->charge_total, 2) }}</td>\n    </tr>\n    <tr>\n      <td>VAT (20%)</td>\n      <td>\u00a3{{ number_format($opportunity->tax_total, 2) }}</td>\n    </tr>\n    <tr class="grand-total">\n      <td>Total</td>\n      <td>\u00a3{{ number_format($opportunity->grand_total, 2) }}</td>\n    </tr>\n  </table>\n</div>\n\n@if($document->terms)\n<div class="terms">\n  <h4>Terms & Conditions</h4>\n  <p>{{ $document->terms }}</p>\n</div>\n@endif`,

      header: `{{-- Repeating page header --}}\n<div class="page-header">\n  <span class="header-company">{{ $store->name }}</span>\n  <span class="header-doc">{{ $document->number }}</span>\n</div>`,

      footer: `{{-- Repeating page footer --}}\n<div class="page-footer">\n  <div class="footer-left">\n    {{ $store->name }} \u00b7 {{ $store->company_number }}\n    \u00b7 VAT: {{ $store->vat_number }}\n  </div>\n  <div class="footer-right">\n    Page <span class="page-num"></span>\n  </div>\n</div>`,

      css: `.document-header {\n  display: flex;\n  justify-content: space-between;\n  align-items: flex-start;\n  margin-bottom: 32px;\n  padding-bottom: 24px;\n  border-bottom: 2px solid var(--primary-colour, #059669);\n}\n\n.logo { max-height: 48px; max-width: 180px; }\n\n.doc-info h1 {\n  font-size: 28px;\n  font-weight: 700;\n  letter-spacing: 0.08em;\n  color: var(--primary-colour, #059669);\n  margin-bottom: 12px;\n  text-align: right;\n}\n\n.meta-table td {\n  padding: 2px 0;\n  font-size: 11px;\n}\n.meta-table .label {\n  color: #888;\n  padding-right: 16px;\n  text-align: right;\n}\n\n.addresses {\n  display: flex;\n  gap: 40px;\n  margin-bottom: 28px;\n}\n\n.address-block h3 {\n  font-size: 9px;\n  text-transform: uppercase;\n  letter-spacing: 0.1em;\n  color: #999;\n  margin-bottom: 6px;\n}\n\n.address-block .company {\n  font-weight: 600;\n  font-size: 13px;\n}\n\n.items-table {\n  width: 100%;\n  border-collapse: collapse;\n  margin: 24px 0;\n}\n\n.items-table th {\n  background: #f5f5f2;\n  padding: 8px 12px;\n  font-size: 10px;\n  text-transform: uppercase;\n  letter-spacing: 0.06em;\n  color: #666;\n  text-align: left;\n  border-bottom: 2px solid #e0e0dc;\n}\n\n.items-table td {\n  padding: 10px 12px;\n  border-bottom: 1px solid #f0f0ec;\n  font-size: 11px;\n  vertical-align: top;\n}\n\n.item-qty, .item-rate,\n.item-disc, .item-total { text-align: right; }\n\n.desc { color: #888; font-size: 10px; }\n\n.totals { display: flex; justify-content: flex-end; }\n.totals-table td {\n  padding: 6px 16px;\n  font-size: 12px;\n}\n.totals-table td:last-child { text-align: right; font-weight: 500; }\n.grand-total td {\n  font-weight: 700;\n  font-size: 14px;\n  border-top: 2px solid #1a1a1a;\n  padding-top: 10px;\n}\n\n.terms {\n  margin-top: 40px;\n  padding-top: 16px;\n  border-top: 1px solid #e0e0dc;\n}\n.terms h4 {\n  font-size: 10px;\n  text-transform: uppercase;\n  letter-spacing: 0.06em;\n  color: #999;\n  margin-bottom: 6px;\n}\n.terms p { font-size: 10px; color: #666; }`
    },

    fieldRegistry: [
      { group: 'Opportunity', fields: [
        { name: 'opportunity.subject', type: 'string', label: 'Subject' },
        { name: 'opportunity.reference', type: 'string', label: 'Reference' },
        { name: 'opportunity.status', type: 'string', label: 'Status' },
        { name: 'opportunity.starts_at', type: 'date', label: 'Start Date' },
        { name: 'opportunity.ends_at', type: 'date', label: 'End Date' },
        { name: 'opportunity.charge_total', type: 'currency', label: 'Charge Total' },
        { name: 'opportunity.tax_total', type: 'currency', label: 'Tax Total' },
        { name: 'opportunity.grand_total', type: 'currency', label: 'Grand Total' },
        { name: 'opportunity.description', type: 'string', label: 'Description' },
        { name: 'opportunity.rental_days', type: 'number', label: 'Rental Days' },
      ]},
      { group: 'Customer', fields: [
        { name: 'member.company_name', type: 'string', label: 'Company Name' },
        { name: 'member.name', type: 'string', label: 'Contact Name' },
        { name: 'member.email', type: 'string', label: 'Email' },
        { name: 'member.phone', type: 'string', label: 'Phone' },
        { name: 'member.billing_address', type: 'string', label: 'Billing Address' },
        { name: 'member.delivery_address', type: 'string', label: 'Delivery Address' },
        { name: 'member.vat_number', type: 'string', label: 'VAT Number' },
      ]},
      { group: 'Line Items', fields: [
        { name: 'items', type: 'collection', label: 'All Items (loop)' },
        { name: 'item.product_name', type: 'string', label: 'Product Name' },
        { name: 'item.quantity', type: 'number', label: 'Quantity' },
        { name: 'item.unit_charge', type: 'currency', label: 'Unit Charge' },
        { name: 'item.total_charge', type: 'currency', label: 'Line Total' },
        { name: 'item.discount', type: 'currency', label: 'Discount' },
        { name: 'item.description', type: 'string', label: 'Description' },
      ]},
      { group: 'Store', fields: [
        { name: 'store.name', type: 'string', label: 'Store Name' },
        { name: 'store.address', type: 'string', label: 'Address' },
        { name: 'store.phone', type: 'string', label: 'Phone' },
        { name: 'store.email', type: 'string', label: 'Email' },
        { name: 'store.vat_number', type: 'string', label: 'VAT Number' },
        { name: 'store.company_number', type: 'string', label: 'Company Number' },
        { name: 'store.website', type: 'string', label: 'Website' },
      ]},
      { group: 'Document', fields: [
        { name: 'document.number', type: 'string', label: 'Document Number' },
        { name: 'document.date', type: 'date', label: 'Document Date' },
        { name: 'document.due_date', type: 'date', label: 'Due Date' },
        { name: 'document.terms', type: 'string', label: 'Terms & Conditions' },
        { name: 'document.notes', type: 'string', label: 'Notes' },
      ]},
      { group: 'Helpers', fields: [
        { name: '@foreach($items as $item)', type: 'collection', label: 'Loop Items' },
        { name: '@if($condition)', type: 'boolean', label: 'Conditional' },
        { name: 'now()->format("d/m/Y")', type: 'date', label: "Today's Date" },
        { name: 'number_format($val, 2)', type: 'currency', label: 'Format Number' },
      ]}
    ],

    sampleData: {
      store: { name: 'Apex Audio Ltd', address: '14 Warehouse Lane, Manchester M1 2AB', phone: '0161 234 5678', email: 'hire@apexaudio.co.uk', vat_number: 'GB 123 4567 89', company_number: '09876543' },
      member: { company_name: 'Mercury Productions', name: 'James Chen', email: 'james@mercury.co', billing_address: '88 Camden High Street, London NW1 0LT' },
      opportunity: { subject: 'Alexandra Palace \u2014 New Year\u0027s Eve Gala', starts_at: '28 Dec 2025', ends_at: '02 Jan 2026', charge_total: '14,250.00', tax_total: '2,850.00', grand_total: '17,100.00', rental_days: 5 },
      document: { number: 'QUO-2025-0042', date: '23 Feb 2026', due_date: '09 Mar 2026', terms: 'Payment due within 14 days of invoice date. Equipment remains the property of Apex Audio Ltd. Client is responsible for any damage beyond reasonable wear and tear.' },
      items: [
        { product_name: 'L-Acoustics K2 Line Array (12 elements)', quantity: 2, unit_charge: '1,800.00', discount: 0, total_charge: '3,600.00', description: 'Main PA left/right hangs' },
        { product_name: 'L-Acoustics SB28 Subwoofer', quantity: 8, unit_charge: '180.00', discount: 0, total_charge: '1,440.00', description: 'Ground-stacked sub array' },
        { product_name: 'DiGiCo SD12 Console', quantity: 1, unit_charge: '950.00', discount: 0, total_charge: '950.00', description: 'FOH mixing console with stage rack' },
        { product_name: 'Robe MegaPointe', quantity: 24, unit_charge: '85.00', discount: '204.00', total_charge: '1,836.00', description: '10% volume discount applied' },
        { product_name: 'GrandMA3 Light Console', quantity: 1, unit_charge: '750.00', discount: 0, total_charge: '750.00', description: 'Lighting control with onPC backup' },
        { product_name: 'Barco UDX-4K32 Projector', quantity: 2, unit_charge: '1,400.00', discount: 0, total_charge: '2,800.00', description: '4K laser projector for main screens' },
        { product_name: 'LED Video Wall 2.8mm (per sqm)', quantity: 18, unit_charge: '120.00', discount: 0, total_charge: '2,160.00', description: 'Stage backdrop 6m \u00d7 3m' },
        { product_name: 'Technical Crew (per day)', quantity: 5, unit_charge: '142.80', discount: 0, total_charge: '714.00', description: '3\u00d7 crew for setup, show, derig' },
      ]
    },

    get filteredGroups() {
      const q = this.searchQuery.toLowerCase().trim();
      if (!q) return this.fieldRegistry;
      return this.fieldRegistry.map(g => ({
        ...g,
        fields: g.fields.filter(f =>
          f.name.toLowerCase().includes(q) || f.label.toLowerCase().includes(q)
        )
      })).filter(g => g.fields.length > 0);
    },

    get currentCode() {
      return this.templates[this.activeTab] || '';
    },

    highlightSyntax(code) {
      let h = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      h = h.replace(/({{--.*?--}})/g, '<span class="de-hl-comment">$1</span>');
      h = h.replace(/(@\w+)(\()?/g, '<span class="de-hl-blade">$1</span>$2');
      h = h.replace(/({{.*?}})/g, '<span class="de-hl-blade-var">$1</span>');
      h = h.replace(/(&lt;\/?)([\w-]+)/g, '$1<span class="de-hl-tag">$2</span>');
      h = h.replace(/(class|src|alt|href|style|id)(=)/g, '<span class="de-hl-attr">$1</span>$2');
      h = h.replace(/("(?:[^"\\]|\\.)*?")/g, '<span class="de-hl-string">$1</span>');
      h = h.replace(/([\w-]+)(\s*:\s*)(?!\/\/)/g, '<span class="de-hl-css-prop">$1</span>$2');
      return h;
    },

    renderEditor() {
      const code = this.currentCode;
      const content = this.$refs.codeContent;
      const lineNums = this.$refs.lineNumbers;
      if (!content || !lineNums) return;

      // Safe: renders hardcoded template content with syntax highlighting spans
      content.innerHTML = this.highlightSyntax(code);
      const lines = code.split('\n');
      lineNums.innerHTML = lines.map((_, i) => '<span>' + (i + 1) + '</span>').join('');
    },

    switchTab(tab) {
      this.activeTab = tab;
      this.$nextTick(() => {
        this.renderEditor();
        this.updatePreview();
      });
    },

    insertField(name) {
      let snippet;
      if (name.startsWith('@')) {
        snippet = name;
      } else if (name.includes('(')) {
        snippet = '{{ ' + name + ' }}';
      } else {
        snippet = '{{ $' + name + ' }}';
      }
      this.templates[this.activeTab] += '\n' + snippet;
      this.renderEditor();
      this.showToast('Inserted: ' + snippet);
    },

    updatePreview() {
      const el = this.$refs.previewContent;
      if (!el) return;
      const d = this.sampleData;

      // Safe: all data is hardcoded sample data defined within this component
      el.innerHTML = '<style>' + this.templates.css + '</style>' +
        '<div class="document-header">' +
          '<div class="logo-area">' +
            '<div style="width:140px;height:36px;background:linear-gradient(135deg,#059669,#047857);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:13px;letter-spacing:0.08em;">APEX AUDIO</div>' +
          '</div>' +
          '<div class="doc-info">' +
            '<h1>QUOTATION</h1>' +
            '<table class="meta-table">' +
              '<tr><td class="label">Quote No.</td><td>' + d.document.number + '</td></tr>' +
              '<tr><td class="label">Date</td><td>' + d.document.date + '</td></tr>' +
              '<tr><td class="label">Valid Until</td><td>' + d.document.due_date + '</td></tr>' +
            '</table>' +
          '</div>' +
        '</div>' +
        '<div class="addresses">' +
          '<div class="address-block"><h3>From</h3><p class="company">' + d.store.name + '</p><p>' + d.store.address + '</p><p>' + d.store.phone + '</p><p>' + d.store.email + '</p></div>' +
          '<div class="address-block"><h3>To</h3><p class="company">' + d.member.company_name + '</p><p>' + d.member.name + '</p><p>' + d.member.billing_address + '</p><p>' + d.member.email + '</p></div>' +
        '</div>' +
        '<div style="margin-bottom:20px;">' +
          '<h3 style="font-size:14px;font-weight:600;margin-bottom:4px;">' + d.opportunity.subject + '</h3>' +
          '<p style="color:#666;font-size:11px;">' + d.opportunity.starts_at + ' \u2014 ' + d.opportunity.ends_at + ' <span style="color:#999;margin-left:4px;">(' + d.opportunity.rental_days + ' days)</span></p>' +
        '</div>' +
        '<table class="items-table"><thead><tr><th class="item-name">Item</th><th class="item-qty">Qty</th><th class="item-rate">Unit Rate</th><th class="item-disc">Discount</th><th class="item-total">Total</th></tr></thead><tbody>' +
        d.items.map(function(i) { return '<tr><td class="item-name"><strong>' + i.product_name + '</strong>' + (i.description ? '<br><span class="desc">' + i.description + '</span>' : '') + '</td><td class="item-qty">' + i.quantity + '</td><td class="item-rate">\u00a3' + i.unit_charge + '</td><td class="item-disc">' + (i.discount ? '\u2212\u00a3' + i.discount : '\u2014') + '</td><td class="item-total">\u00a3' + i.total_charge + '</td></tr>'; }).join('') +
        '</tbody></table>' +
        '<div class="totals"><table class="totals-table">' +
          '<tr><td>Subtotal</td><td>\u00a3' + d.opportunity.charge_total + '</td></tr>' +
          '<tr><td>VAT (20%)</td><td>\u00a3' + d.opportunity.tax_total + '</td></tr>' +
          '<tr class="grand-total"><td>Total</td><td>\u00a3' + d.opportunity.grand_total + '</td></tr>' +
        '</table></div>' +
        '<div class="terms"><h4>Terms & Conditions</h4><p>' + d.document.terms + '</p></div>';
    },

    adjustZoom(delta) {
      this.zoomLevel = Math.max(25, Math.min(150, this.zoomLevel + delta));
    },

    showToast(msg) {
      this.toastMessage = msg;
      this.toastVisible = true;
      if (this.toastTimeout) clearTimeout(this.toastTimeout);
      this.toastTimeout = setTimeout(() => { this.toastVisible = false; }, 2500);
    },

    initResize() {
      const handle = this.$refs.resizeHandle;
      const preview = this.$refs.previewPane;
      const split = this.$refs.editorSplit;
      if (!handle || !preview || !split) return;

      handle.addEventListener('mousedown', () => {
        this.isResizing = true;
        handle.classList.add('dragging');
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
      });

      document.addEventListener('mousemove', (e) => {
        if (!this.isResizing) return;
        const rect = split.getBoundingClientRect();
        const w = Math.max(300, Math.min(rect.width - 300, rect.right - e.clientX));
        preview.style.width = w + 'px';
        preview.style.flex = 'none';
      });

      document.addEventListener('mouseup', () => {
        if (!this.isResizing) return;
        this.isResizing = false;
        handle.classList.remove('dragging');
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
      });
    },

    initCursorTracking() {
      const content = this.$refs.codeContent;
      if (!content) return;

      const updateCursor = () => {
        const sel = window.getSelection();
        if (!sel.rangeCount) return;
        const range = sel.getRangeAt(0);
        const preRange = range.cloneRange();
        preRange.selectNodeContents(content);
        preRange.setEnd(range.startContainer, range.startOffset);
        const text = preRange.toString();
        const lines = text.split('\n');
        this.cursorLine = lines.length;
        this.cursorCol = lines[lines.length - 1].length + 1;
      };

      content.addEventListener('keyup', updateCursor);
      content.addEventListener('click', updateCursor);

      content.addEventListener('input', () => {
        this.templates[this.activeTab] = content.textContent;
        this.updatePreview();
      });
    },

    init() {
      this.$nextTick(() => {
        this.renderEditor();
        this.updatePreview();
        this.initResize();
        this.initCursorTracking();
      });
    }
  };
}
</script>
@endverbatim
