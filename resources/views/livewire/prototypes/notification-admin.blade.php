<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Notification Admin')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  NOTIFICATION ADMIN TOKENS — maps to brand system in app.css     */
  /* ================================================================ */
  :root {
    --na-bg: var(--content-bg);
    --na-panel: var(--card-bg);
    --na-surface: var(--base);
    --na-border: var(--card-border);
    --na-border-subtle: var(--grey-border);
    --na-text: var(--text-primary);
    --na-text-secondary: var(--text-secondary);
    --na-text-muted: var(--text-muted);
    --na-accent: var(--green);
    --na-accent-dim: var(--green-muted);
    --na-hover: rgba(0, 0, 0, 0.03);
    --na-shadow: var(--shadow-card);
    --na-shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.08);
    --na-blue: var(--blue);
    --na-blue-bg: rgba(37, 99, 235, 0.08);
    --na-green: var(--green);
    --na-green-bg: rgba(5, 150, 105, 0.08);
    --na-red: var(--red);
    --na-red-bg: rgba(220, 38, 38, 0.06);
    --na-amber: var(--amber);
    --na-amber-bg: rgba(217, 119, 6, 0.08);
    --na-violet: var(--violet);
    --na-violet-bg: rgba(124, 58, 237, 0.08);
    --na-grey: var(--grey);
    --na-grey-light: var(--grey-light);
    --na-sky: var(--sky);
    --na-cyan: var(--cyan);
    --na-rose: var(--rose);
    --na-provider-bg: var(--white);
    --na-pill-off-bg: rgba(0, 0, 0, 0.04);
    --na-pill-off-text: var(--grey-light);
    --na-code-bg: rgba(0, 0, 0, 0.04);
    --na-editor-bg: var(--white);
    --na-preview-bg: var(--base);
    --na-merge-bg: rgba(37, 99, 235, 0.06);
    --na-merge-text: var(--blue);
  }

  .dark {
    --na-bg: var(--content-bg);
    --na-panel: var(--card-bg);
    --na-surface: var(--navy-mid);
    --na-border: var(--card-border);
    --na-border-subtle: #283040;
    --na-text: var(--text-primary);
    --na-text-secondary: var(--text-secondary);
    --na-text-muted: var(--text-muted);
    --na-accent: var(--green);
    --na-accent-dim: rgba(5, 150, 105, 0.12);
    --na-hover: rgba(255, 255, 255, 0.04);
    --na-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --na-shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.4);
    --na-blue-bg: rgba(37, 99, 235, 0.15);
    --na-green-bg: rgba(5, 150, 105, 0.12);
    --na-red-bg: rgba(220, 38, 38, 0.12);
    --na-amber-bg: rgba(217, 119, 6, 0.12);
    --na-violet-bg: rgba(124, 58, 237, 0.15);
    --na-provider-bg: var(--navy-mid);
    --na-pill-off-bg: rgba(255, 255, 255, 0.06);
    --na-pill-off-text: #475569;
    --na-code-bg: rgba(255, 255, 255, 0.06);
    --na-editor-bg: var(--navy-mid);
    --na-preview-bg: var(--navy-light);
    --na-merge-bg: rgba(37, 99, 235, 0.12);
    --na-merge-text: var(--blue-light);
  }

  /* ================================================================ */
  /*  PAGE SHELL                                                       */
  /* ================================================================ */
  .na-page {
    display: flex;
    flex-direction: column;
    flex: 1 1 0;
    min-height: 0;
    overflow-y: auto;
    font-family: var(--font-mono);
    font-size: 12px;
    line-height: 1.6;
    color: var(--na-text);
    -webkit-font-smoothing: antialiased;
    padding: 24px;
    gap: 20px;
  }

  /* ================================================================ */
  /*  PAGE HEADER                                                      */
  /* ================================================================ */
  .na-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-shrink: 0;
  }

  .na-header-left { display: flex; flex-direction: column; gap: 2px; }

  .na-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--na-text);
    margin: 0;
  }

  .na-subtitle {
    font-size: 12px;
    color: var(--na-text-muted);
  }

  /* ================================================================ */
  /*  TAB BAR                                                          */
  /* ================================================================ */
  .na-tabs {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--na-border);
    flex-shrink: 0;
    margin-top: 4px;
  }

  .na-tab {
    padding: 8px 18px;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.02em;
    color: var(--na-text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: color 0.15s, border-color 0.15s;
    user-select: none;
    margin-bottom: -1px;
  }

  .na-tab:hover { color: var(--na-text-secondary); }
  .na-tab.active {
    color: var(--na-accent);
    border-bottom-color: var(--na-accent);
  }

  /* ================================================================ */
  /*  CHANNEL PROVIDERS STRIP                                          */
  /* ================================================================ */
  .na-providers {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding: 2px 0 4px;
    flex-shrink: 0;
    scrollbar-width: thin;
  }

  .na-providers::-webkit-scrollbar { height: 4px; }
  .na-providers::-webkit-scrollbar-track { background: transparent; }
  .na-providers::-webkit-scrollbar-thumb { background: var(--na-border); border-radius: 2px; }

  .na-provider-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: var(--na-provider-bg);
    border: 1px solid var(--na-border);
    border-radius: 6px;
    min-width: 200px;
    flex-shrink: 0;
    box-shadow: var(--na-shadow);
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .na-provider-card:hover {
    border-color: var(--na-accent);
    box-shadow: var(--na-shadow-lg);
  }

  .na-provider-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: var(--na-surface);
    border: 1px solid var(--na-border-subtle);
  }

  .na-provider-icon svg { width: 16px; height: 16px; color: var(--na-text-secondary); }

  .na-provider-info { flex: 1; min-width: 0; }
  .na-provider-name {
    font-weight: 600;
    font-size: 12px;
    color: var(--na-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .na-provider-driver {
    font-size: 10px;
    color: var(--na-text-muted);
    font-family: var(--font-mono);
  }

  .na-provider-status {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
  }

  .na-status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .na-status-dot.connected { background: var(--na-green); box-shadow: 0 0 6px rgba(5, 150, 105, 0.4); }
  .na-status-dot.untested { background: var(--na-amber); box-shadow: 0 0 6px rgba(217, 119, 6, 0.4); }
  .na-status-dot.failing { background: var(--na-red); box-shadow: 0 0 6px rgba(220, 38, 38, 0.4); }

  .na-provider-test-btn {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 3px 10px;
    border: 1px solid var(--na-border);
    border-radius: 4px;
    background: transparent;
    color: var(--na-text-secondary);
    cursor: pointer;
    transition: all 0.15s;
  }

  .na-provider-test-btn:hover {
    border-color: var(--na-accent);
    color: var(--na-accent);
    background: var(--na-accent-dim);
  }

  .na-provider-add {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 18px;
    border: 1px dashed var(--na-border);
    border-radius: 6px;
    min-width: 160px;
    flex-shrink: 0;
    color: var(--na-text-muted);
    cursor: pointer;
    transition: all 0.15s;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
  }

  .na-provider-add:hover {
    border-color: var(--na-accent);
    color: var(--na-accent);
    background: var(--na-accent-dim);
  }

  .na-provider-add svg { width: 16px; height: 16px; }

  /* ================================================================ */
  /*  NOTIFICATION TYPES TAB                                           */
  /* ================================================================ */
  .na-types-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .na-category {
    background: var(--na-panel);
    border: 1px solid var(--na-border);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--na-shadow);
  }

  .na-category-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    cursor: pointer;
    user-select: none;
    transition: background 0.12s;
  }

  .na-category-header:hover { background: var(--na-hover); }

  .na-category-chevron {
    width: 16px;
    height: 16px;
    color: var(--na-text-muted);
    transition: transform 0.2s;
    flex-shrink: 0;
  }

  .na-category-chevron.expanded { transform: rotate(90deg); }

  .na-category-name {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 700;
    color: var(--na-text);
    letter-spacing: 0.01em;
  }

  .na-category-count {
    font-size: 10px;
    font-family: var(--font-mono);
    color: var(--na-text-muted);
    background: var(--na-surface);
    padding: 1px 8px;
    border-radius: 10px;
    border: 1px solid var(--na-border-subtle);
  }

  .na-category-body {
    border-top: 1px solid var(--na-border-subtle);
  }

  .na-type-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--na-border-subtle);
    transition: background 0.12s;
  }

  .na-type-card:last-child { border-bottom: none; }
  .na-type-card:hover { background: var(--na-hover); }

  .na-type-info { flex: 1; min-width: 0; }
  .na-type-name {
    font-weight: 600;
    font-size: 12px;
    color: var(--na-text);
    margin-bottom: 2px;
  }

  .na-type-desc {
    font-size: 11px;
    color: var(--na-text-muted);
  }

  .na-type-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
  }

  .na-type-recipients {
    font-size: 10px;
    color: var(--na-text-muted);
    max-width: 180px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Audience badges */
  .na-audience {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 2px 8px;
    border-radius: 3px;
    flex-shrink: 0;
    white-space: nowrap;
  }

  .na-audience.internal { background: var(--na-blue-bg); color: var(--na-blue); }
  .na-audience.external { background: var(--na-green-bg); color: var(--na-green); }
  .na-audience.both {
    background: linear-gradient(135deg, var(--na-blue-bg) 50%, var(--na-green-bg) 50%);
    color: var(--na-blue);
  }

  /* Channel pills */
  .na-channels {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
  }

  .na-channel-pill {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 3px 8px;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.15s;
    user-select: none;
    border: 1px solid transparent;
  }

  .na-channel-pill.off {
    background: var(--na-pill-off-bg);
    color: var(--na-pill-off-text);
    border-color: transparent;
  }

  .na-channel-pill.on.in_app { background: var(--na-violet-bg); color: var(--na-violet); border-color: rgba(124, 58, 237, 0.2); }
  .na-channel-pill.on.email { background: var(--na-blue-bg); color: var(--na-blue); border-color: rgba(37, 99, 235, 0.2); }
  .na-channel-pill.on.sms { background: var(--na-amber-bg); color: var(--na-amber); border-color: rgba(217, 119, 6, 0.2); }
  .na-channel-pill.on.slack { background: var(--na-green-bg); color: var(--na-green); border-color: rgba(5, 150, 105, 0.2); }

  /* Toggle switch */
  .na-toggle {
    width: 34px;
    height: 18px;
    border-radius: 9px;
    background: var(--na-pill-off-bg);
    border: 1px solid var(--na-border);
    cursor: pointer;
    position: relative;
    flex-shrink: 0;
    transition: all 0.2s;
  }

  .na-toggle.enabled {
    background: var(--na-accent);
    border-color: var(--na-accent);
  }

  .na-toggle-knob {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: white;
    position: absolute;
    top: 2px;
    left: 2px;
    transition: transform 0.2s;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
  }

  .na-toggle.enabled .na-toggle-knob { transform: translateX(16px); }

  /* ================================================================ */
  /*  COMMUNICATION LOG TAB                                            */
  /* ================================================================ */
  .na-log-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--na-panel);
    border: 1px solid var(--na-border);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--na-shadow);
  }

  .na-log-table th {
    padding: 10px 14px;
    text-align: left;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--na-text-muted);
    background: var(--table-header-bg);
    border-bottom: 1px solid var(--na-border);
    white-space: nowrap;
  }

  .na-log-table td {
    padding: 10px 14px;
    font-size: 12px;
    color: var(--na-text);
    border-bottom: 1px solid var(--na-border-subtle);
    white-space: nowrap;
  }

  .na-log-table tr:last-child td { border-bottom: none; }
  .na-log-table tr:hover td { background: var(--table-row-hover); }

  .na-log-timestamp {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--na-text-muted);
  }

  .na-log-channel-icon {
    width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 3px;
  }

  .na-log-channel-icon svg { width: 12px; height: 12px; }
  .na-log-channel-icon.email { color: var(--na-blue); background: var(--na-blue-bg); }
  .na-log-channel-icon.sms { color: var(--na-amber); background: var(--na-amber-bg); }
  .na-log-channel-icon.slack { color: var(--na-green); background: var(--na-green-bg); }
  .na-log-channel-icon.in_app { color: var(--na-violet); background: var(--na-violet-bg); }

  .na-log-status {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 8px;
    border-radius: 3px;
    display: inline-block;
  }

  .na-log-status.sent { background: var(--na-green-bg); color: var(--na-green); }
  .na-log-status.delivered { background: var(--na-blue-bg); color: var(--na-blue); }
  .na-log-status.failed { background: var(--na-red-bg); color: var(--na-red); }
  .na-log-status.queued { background: var(--na-pill-off-bg); color: var(--na-grey); }
  .na-log-status.opened { background: var(--na-violet-bg); color: var(--na-violet); }
  .na-log-status.bounced { background: var(--na-amber-bg); color: var(--na-amber); }

  .na-log-entity {
    color: var(--na-blue);
    font-weight: 500;
    cursor: pointer;
    transition: color 0.12s;
  }

  .na-log-entity:hover { color: var(--na-accent); }

  /* ================================================================ */
  /*  TEMPLATE EDITOR TAB                                              */
  /* ================================================================ */
  .na-template-layout {
    display: grid;
    grid-template-columns: 280px 1fr 1fr;
    gap: 16px;
    min-height: 500px;
  }

  @media (max-width: 1100px) {
    .na-template-layout {
      grid-template-columns: 1fr;
      grid-template-rows: auto 1fr 1fr;
    }
  }

  .na-template-sidebar {
    background: var(--na-panel);
    border: 1px solid var(--na-border);
    border-radius: 8px;
    overflow-y: auto;
    box-shadow: var(--na-shadow);
  }

  .na-template-sidebar-header {
    padding: 12px 14px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--na-text-muted);
    border-bottom: 1px solid var(--na-border-subtle);
    position: sticky;
    top: 0;
    background: var(--na-panel);
    z-index: 2;
  }

  .na-template-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid var(--na-border-subtle);
    transition: background 0.12s;
  }

  .na-template-item:hover { background: var(--na-hover); }
  .na-template-item.active {
    background: var(--na-accent-dim);
    border-left: 3px solid var(--na-accent);
    padding-left: 11px;
  }

  .na-template-item-name {
    font-weight: 600;
    font-size: 12px;
    color: var(--na-text);
  }

  .na-template-item-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    color: var(--na-text-muted);
  }

  .na-template-item-channel {
    font-family: var(--font-display);
    font-size: 8px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 1px 5px;
    border-radius: 2px;
  }

  .na-template-item-channel.email { background: var(--na-blue-bg); color: var(--na-blue); }
  .na-template-item-channel.sms { background: var(--na-amber-bg); color: var(--na-amber); }
  .na-template-item-channel.slack { background: var(--na-green-bg); color: var(--na-green); }

  /* Editor panel */
  .na-editor-panel {
    display: flex;
    flex-direction: column;
    background: var(--na-panel);
    border: 1px solid var(--na-border);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--na-shadow);
  }

  .na-editor-toolbar {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 8px 12px;
    border-bottom: 1px solid var(--na-border-subtle);
    background: var(--na-surface);
    flex-shrink: 0;
  }

  .na-toolbar-btn {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: var(--na-text-secondary);
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.12s;
  }

  .na-toolbar-btn:hover {
    background: var(--na-hover);
    color: var(--na-text);
  }

  .na-toolbar-btn svg { width: 14px; height: 14px; }

  .na-toolbar-sep {
    width: 1px;
    height: 18px;
    background: var(--na-border-subtle);
    margin: 0 6px;
  }

  .na-toolbar-merge-btn {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    padding: 4px 10px;
    border: 1px solid var(--na-border);
    border-radius: 4px;
    background: transparent;
    color: var(--na-text-secondary);
    cursor: pointer;
    transition: all 0.12s;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-left: auto;
  }

  .na-toolbar-merge-btn:hover {
    border-color: var(--na-merge-text);
    color: var(--na-merge-text);
    background: var(--na-merge-bg);
  }

  .na-toolbar-merge-btn svg { width: 12px; height: 12px; }

  /* Merge field browser */
  .na-merge-browser {
    border-bottom: 1px solid var(--na-border-subtle);
    background: var(--na-surface);
    max-height: 200px;
    overflow-y: auto;
    padding: 10px 14px;
  }

  .na-merge-group-title {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--na-text-muted);
    padding: 4px 0;
    margin-top: 6px;
  }

  .na-merge-group-title:first-child { margin-top: 0; }

  .na-merge-fields {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 2px 0;
  }

  .na-merge-tag {
    font-family: var(--font-mono);
    font-size: 10px;
    padding: 2px 8px;
    background: var(--na-merge-bg);
    color: var(--na-merge-text);
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.12s;
    border: 1px solid transparent;
  }

  .na-merge-tag:hover {
    border-color: var(--na-merge-text);
    background: var(--na-blue-bg);
  }

  /* Textarea */
  .na-editor-textarea {
    flex: 1;
    width: 100%;
    padding: 14px;
    font-family: var(--font-mono);
    font-size: 12px;
    line-height: 1.7;
    color: var(--na-text);
    background: var(--na-editor-bg);
    border: none;
    outline: none;
    resize: none;
    min-height: 300px;
  }

  .na-editor-textarea::placeholder { color: var(--na-text-muted); }

  /* Preview panel */
  .na-preview-panel {
    display: flex;
    flex-direction: column;
    background: var(--na-panel);
    border: 1px solid var(--na-border);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--na-shadow);
  }

  .na-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border-bottom: 1px solid var(--na-border-subtle);
    background: var(--na-surface);
    flex-shrink: 0;
  }

  .na-preview-title {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--na-text-muted);
  }

  .na-preview-body {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background: var(--na-preview-bg);
    font-size: 13px;
    line-height: 1.7;
    color: var(--na-text);
  }

  .na-preview-body strong { font-weight: 700; }

  .na-preview-body .na-preview-field {
    background: var(--na-merge-bg);
    color: var(--na-merge-text);
    padding: 1px 4px;
    border-radius: 2px;
    font-family: var(--font-mono);
    font-size: 11px;
  }

  /* ================================================================ */
  /*  FILTER / SEARCH BAR                                              */
  /* ================================================================ */
  .na-filter-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
  }

  .na-search-input {
    flex: 1;
    max-width: 300px;
    padding: 7px 12px 7px 32px;
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--na-text);
    background: var(--na-panel);
    border: 1px solid var(--na-border);
    border-radius: 6px;
    outline: none;
    transition: border-color 0.15s;
  }

  .na-search-input:focus { border-color: var(--na-accent); }
  .na-search-input::placeholder { color: var(--na-text-muted); }

  .na-search-wrap {
    position: relative;
    flex: 1;
    max-width: 300px;
  }

  .na-search-wrap svg {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 14px;
    height: 14px;
    color: var(--na-text-muted);
    pointer-events: none;
  }

  /* ================================================================ */
  /*  UTILITY                                                          */
  /* ================================================================ */
  .na-fade-enter { animation: naFadeIn 0.2s ease; }

  @keyframes naFadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .na-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: var(--na-text-muted);
    font-size: 13px;
    gap: 8px;
  }

  .na-empty-state svg { width: 32px; height: 32px; opacity: 0.3; }
</style>

<div class="na-page" x-data="notificationAdmin()">

  {{-- ============================================================ --}}
  {{-- PAGE HEADER                                                    --}}
  {{-- ============================================================ --}}
  <div class="na-header">
    <div class="na-header-left">
      <h1 class="na-title">Notification Admin</h1>
      <span class="na-subtitle">Configure notification types, channel providers, and message templates</span>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{-- TAB BAR                                                        --}}
  {{-- ============================================================ --}}
  <div class="na-tabs">
    <template x-for="tab in tabs" :key="tab.id">
      <div class="na-tab"
           :class="{ 'active': activeTab === tab.id }"
           @click="activeTab = tab.id"
           x-text="tab.label"></div>
    </template>
  </div>

  {{-- ============================================================ --}}
  {{-- CHANNEL PROVIDERS STRIP                                        --}}
  {{-- ============================================================ --}}
  <div class="na-providers">
    <template x-for="provider in providers" :key="provider.id">
      <div class="na-provider-card">
        <div class="na-provider-icon" x-html="icons[provider.icon]"></div>
        <div class="na-provider-info">
          <div class="na-provider-name" x-text="provider.name"></div>
          <div class="na-provider-driver" x-text="provider.driver"></div>
        </div>
        <div class="na-provider-status">
          <span class="na-status-dot" :class="provider.status"></span>
          <button class="na-provider-test-btn" @click="testProvider(provider)">Test</button>
        </div>
      </div>
    </template>
    <div class="na-provider-add" @click="addProvider()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Provider
    </div>
  </div>

  {{-- ============================================================ --}}
  {{-- NOTIFICATION TYPES TAB                                         --}}
  {{-- ============================================================ --}}
  <div x-show="activeTab === 'types'" x-cloak class="na-fade-enter">
    {{-- Search --}}
    <div class="na-filter-bar" style="margin-bottom: 12px;">
      <div class="na-search-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" class="na-search-input" placeholder="Search notification types..." x-model="typeSearch" style="max-width: 100%;">
      </div>
    </div>

    <div class="na-types-container">
      <template x-for="category in filteredCategories()" :key="category.id">
        <div class="na-category">
          <div class="na-category-header" @click="toggleCategory(category.id)">
            <svg class="na-category-chevron" :class="{ 'expanded': !collapsedCategories[category.id] }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
            <span class="na-category-name" x-text="category.name"></span>
            <span class="na-category-count" x-text="categoryTypeCount(category.id) + ' types'"></span>
          </div>
          <div class="na-category-body" x-show="!collapsedCategories[category.id]" x-collapse>
            <template x-for="type in filteredTypes(category.id)" :key="type.key">
              <div class="na-type-card">
                <div class="na-type-info">
                  <div class="na-type-name" x-text="type.name"></div>
                  <div class="na-type-desc" x-text="type.description"></div>
                </div>
                <div class="na-type-meta">
                  <span class="na-type-recipients" x-text="type.recipients"></span>
                  <span class="na-audience" :class="type.audience" x-text="type.audience === 'both' ? 'Int + Ext' : type.audience"></span>
                  <div class="na-channels">
                    <template x-for="ch in allChannels" :key="ch">
                      <span class="na-channel-pill"
                            :class="[ch, type.channels.includes(ch) ? 'on' : 'off']"
                            @click="toggleChannel(type.key, ch)"
                            x-text="channelLabel(ch)"></span>
                    </template>
                  </div>
                  <div class="na-toggle" :class="{ 'enabled': type.enabled }" @click="type.enabled = !type.enabled">
                    <div class="na-toggle-knob"></div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </template>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{-- COMMUNICATION LOG TAB                                          --}}
  {{-- ============================================================ --}}
  <div x-show="activeTab === 'log'" x-cloak class="na-fade-enter">
    <div class="na-filter-bar" style="margin-bottom: 12px;">
      <div class="na-search-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" class="na-search-input" placeholder="Search logs..." x-model="logSearch" style="max-width: 100%;">
      </div>
    </div>

    <table class="na-log-table">
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>Type</th>
          <th>Recipient</th>
          <th>Channel</th>
          <th>Status</th>
          <th>Entity</th>
        </tr>
      </thead>
      <tbody>
        <template x-for="entry in filteredLogs()" :key="entry.id">
          <tr>
            <td><span class="na-log-timestamp" x-text="entry.timestamp"></span></td>
            <td x-text="entry.type"></td>
            <td x-text="entry.recipient"></td>
            <td>
              <span class="na-log-channel-icon" :class="entry.channel" x-html="channelIcon(entry.channel)"></span>
            </td>
            <td><span class="na-log-status" :class="entry.status" x-text="entry.status"></span></td>
            <td><span class="na-log-entity" x-text="entry.entity"></span></td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>

  {{-- ============================================================ --}}
  {{-- TEMPLATE EDITOR TAB                                            --}}
  {{-- ============================================================ --}}
  <div x-show="activeTab === 'templates'" x-cloak class="na-fade-enter">
    <div class="na-template-layout">
      {{-- Template list sidebar --}}
      <div class="na-template-sidebar">
        <div class="na-template-sidebar-header">Templates</div>
        <template x-for="tpl in templates" :key="tpl.id">
          <div class="na-template-item"
               :class="{ 'active': activeTemplate === tpl.id }"
               @click="activeTemplate = tpl.id">
            <span class="na-template-item-name" x-text="tpl.name"></span>
            <div class="na-template-item-meta">
              <span class="na-template-item-channel" :class="tpl.channel" x-text="tpl.channel"></span>
              <span x-text="tpl.event"></span>
            </div>
          </div>
        </template>
      </div>

      {{-- Editor panel --}}
      <div class="na-editor-panel">
        <div class="na-editor-toolbar">
          <button class="na-toolbar-btn" title="Bold" @click="insertFormat('**')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/></svg>
          </button>
          <button class="na-toolbar-btn" title="Italic" @click="insertFormat('_')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
          </button>
          <div class="na-toolbar-sep"></div>
          <button class="na-toolbar-merge-btn" @click="showMergeFields = !showMergeFields">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5"/><path d="M8 3H3v5"/><path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3"/><path d="m15 9 6-6"/></svg>
            Merge Fields
          </button>
        </div>

        {{-- Merge field browser --}}
        <div class="na-merge-browser" x-show="showMergeFields" x-collapse>
          <template x-for="group in mergeFieldGroups" :key="group.label">
            <div>
              <div class="na-merge-group-title" x-text="group.label"></div>
              <div class="na-merge-fields">
                <template x-for="field in group.fields" :key="field">
                  <span class="na-merge-tag" @click="insertMergeField(field)" x-text="'{{ ' + field + ' }}'"></span>
                </template>
              </div>
            </div>
          </template>
        </div>

        <textarea class="na-editor-textarea"
                  x-ref="editor"
                  :value="currentTemplateBody()"
                  @input="updateTemplateBody($event.target.value)"
                  placeholder="Write your template body..."></textarea>
      </div>

      {{-- Preview panel --}}
      <div class="na-preview-panel">
        <div class="na-preview-header">
          <span class="na-preview-title">Preview</span>
        </div>
        <div class="na-preview-body" x-html="renderedPreview()"></div>
      </div>
    </div>
  </div>

</div>

@verbatim
<script>
function notificationAdmin() {
  /* ================================================================ */
  /*  ICONS                                                            */
  /* ================================================================ */
  const icons = {
    mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
    cloud: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>',
    phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
    hash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>',
    bell: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
    messageSquare: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  };

  /* ================================================================ */
  /*  CHANNEL ICONS (small inline)                                     */
  /* ================================================================ */
  function channelIcon(ch) {
    const map = {
      email: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
      sms: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
      slack: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>',
      in_app: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
    };
    return map[ch] || '';
  }

  /* ================================================================ */
  /*  MOCK DATA                                                        */
  /* ================================================================ */

  const providers = [
    { id: 'smtp1', name: 'Office SMTP', driver: 'smtp', status: 'connected', icon: 'mail' },
    { id: 'ses1', name: 'AWS SES', driver: 'ses', status: 'connected', icon: 'cloud' },
    { id: 'twilio1', name: 'Twilio SMS', driver: 'twilio', status: 'untested', icon: 'phone' },
    { id: 'slack1', name: 'Ops Slack', driver: 'slack_webhook', status: 'connected', icon: 'hash' },
  ];

  const categories = [
    { id: 'opportunities', name: 'Opportunities' },
    { id: 'invoicing', name: 'Invoicing' },
    { id: 'stock', name: 'Stock' },
    { id: 'compliance', name: 'Compliance' },
    { id: 'crm', name: 'CRM' },
    { id: 'system', name: 'System' },
  ];

  const notificationTypes = [
    // Opportunities
    { key: 'opportunity.created', category: 'opportunities', name: 'Opportunity Created', description: 'When a new opportunity is created', audience: 'internal', channels: ['in_app', 'email'], recipients: 'Subject owner, Admins', enabled: true },
    { key: 'opportunity.confirmed', category: 'opportunities', name: 'Booking Confirmed', description: 'When an opportunity is confirmed', audience: 'both', channels: ['in_app', 'email', 'sms'], recipients: 'Subject owner, Contact', enabled: true },
    { key: 'opportunity.cancelled', category: 'opportunities', name: 'Booking Cancelled', description: 'When a booking is cancelled', audience: 'both', channels: ['in_app', 'email'], recipients: 'Subject owner, Contact, Admins', enabled: true },
    { key: 'opportunity.late_return', category: 'opportunities', name: 'Late Return Alert', description: 'When equipment is not returned by end date', audience: 'internal', channels: ['in_app', 'email', 'slack'], recipients: 'Subject owner, Store managers', enabled: true },
    // Invoicing
    { key: 'invoice.created', category: 'invoicing', name: 'Invoice Created', description: 'When a new invoice is generated', audience: 'external', channels: ['email'], recipients: 'Contact', enabled: true },
    { key: 'invoice.overdue', category: 'invoicing', name: 'Invoice Overdue', description: 'When an invoice passes its due date', audience: 'both', channels: ['in_app', 'email', 'sms'], recipients: 'Subject owner, Contact, Admins', enabled: true },
    { key: 'invoice.payment_received', category: 'invoicing', name: 'Payment Received', description: 'When payment is recorded against an invoice', audience: 'both', channels: ['in_app', 'email'], recipients: 'Subject owner, Contact', enabled: true },
    // Stock
    { key: 'stock.below_threshold', category: 'stock', name: 'Stock Below Threshold', description: 'When stock quantity drops below minimum', audience: 'internal', channels: ['in_app', 'email', 'slack'], recipients: 'Store managers', enabled: true },
    { key: 'stock.quarantined', category: 'stock', name: 'Item Quarantined', description: 'When a stock item is quarantined', audience: 'internal', channels: ['in_app'], recipients: 'Store managers', enabled: true },
    { key: 'stock.transfer_completed', category: 'stock', name: 'Transfer Completed', description: 'When a stock transfer is completed', audience: 'internal', channels: ['in_app', 'email'], recipients: 'Transfer requester, Destination store', enabled: true },
    // Compliance
    { key: 'inspection.due', category: 'compliance', name: 'Inspection Due', description: 'When an inspection is approaching due date', audience: 'internal', channels: ['in_app', 'email'], recipients: 'Assigned inspector, Store managers', enabled: true },
    { key: 'inspection.failed', category: 'compliance', name: 'Inspection Failed', description: 'When an inspection fails', audience: 'internal', channels: ['in_app', 'email', 'slack'], recipients: 'Store managers, Admins', enabled: true },
    { key: 'inspection.completed', category: 'compliance', name: 'Inspection Completed', description: 'When an inspection is completed', audience: 'internal', channels: ['in_app'], recipients: 'Store managers', enabled: false },
    // CRM
    { key: 'activity.assigned', category: 'crm', name: 'Activity Assigned', description: 'When an activity is assigned to a team member', audience: 'internal', channels: ['in_app', 'email'], recipients: 'Assignee', enabled: true },
    { key: 'activity.overdue', category: 'crm', name: 'Activity Overdue', description: 'When an activity passes its due date', audience: 'internal', channels: ['in_app', 'email'], recipients: 'Assignee, Manager', enabled: true },
    { key: 'discussion.mention', category: 'crm', name: 'Mentioned in Discussion', description: 'When you are mentioned in a discussion', audience: 'internal', channels: ['in_app', 'email'], recipients: 'Mentioned member', enabled: true },
    // System
    { key: 'system.member_invited', category: 'system', name: 'Member Invited', description: 'When a new team member is invited', audience: 'internal', channels: ['email'], recipients: 'Invited member', enabled: true },
    { key: 'system.member_joined', category: 'system', name: 'Member Joined', description: 'When a new team member accepts invite', audience: 'internal', channels: ['in_app'], recipients: 'Admins', enabled: true },
    { key: 'system.import_completed', category: 'system', name: 'Import Completed', description: 'When a data import finishes', audience: 'internal', channels: ['in_app', 'email'], recipients: 'Import initiator', enabled: true },
    { key: 'system.webhook_failing', category: 'system', name: 'Webhook Failing', description: 'When a webhook endpoint has consecutive failures', audience: 'internal', channels: ['in_app', 'email', 'slack'], recipients: 'Admins', enabled: true },
  ];

  const logEntries = [
    { id: 1, timestamp: '2 minutes ago', type: 'Booking Confirmed', recipient: 'sarah.johnson@acme.com', channel: 'email', status: 'delivered', entity: 'Opportunity #1042' },
    { id: 2, timestamp: '8 minutes ago', type: 'Booking Confirmed', recipient: 'Sarah Johnson', channel: 'sms', status: 'sent', entity: 'Opportunity #1042' },
    { id: 3, timestamp: '15 minutes ago', type: 'Invoice Overdue', recipient: 'accounts@deltaevents.co', channel: 'email', status: 'opened', entity: 'Invoice #INV-2087' },
    { id: 4, timestamp: '32 minutes ago', type: 'Stock Below Threshold', recipient: '#ops-alerts', channel: 'slack', status: 'sent', entity: 'LED Par Can 64' },
    { id: 5, timestamp: '1 hour ago', type: 'Late Return Alert', recipient: 'mike.chen@signals.rent', channel: 'in_app', status: 'delivered', entity: 'Opportunity #1038' },
    { id: 6, timestamp: '2 hours ago', type: 'Inspection Failed', recipient: '#ops-alerts', channel: 'slack', status: 'sent', entity: 'Genie GS-1930 #042' },
    { id: 7, timestamp: '2 hours ago', type: 'Payment Received', recipient: 'billing@summit.co.uk', channel: 'email', status: 'bounced', entity: 'Invoice #INV-2081' },
    { id: 8, timestamp: '3 hours ago', type: 'Activity Assigned', recipient: 'james.wu@signals.rent', channel: 'in_app', status: 'delivered', entity: 'Follow-up call' },
    { id: 9, timestamp: '5 hours ago', type: 'Member Invited', recipient: 'new.hire@company.com', channel: 'email', status: 'failed', entity: 'Team invitation' },
    { id: 10, timestamp: '6 hours ago', type: 'Webhook Failing', recipient: 'admin@signals.rent', channel: 'email', status: 'queued', entity: 'https://hooks.example.com/signals' },
  ];

  const templates = [
    { id: 'tpl1', name: 'Booking Confirmation Email', event: 'opportunity.confirmed', channel: 'email', body: 'Hi {{ contact.first_name }},\n\nYour booking **{{ opportunity.subject }}** ({{ opportunity.number }}) has been confirmed.\n\n**Collection:** {{ opportunity.starts_at | date:"d M Y" }} at {{ opportunity.starts_at | time:"H:i" }}\n**Return:** {{ opportunity.ends_at | date:"d M Y" }}\n**Total:** {{ opportunity.charge_total | currency }}\n\nIf you have any questions, please contact us.\n\nThanks,\n{{ company.name }}' },
    { id: 'tpl2', name: 'Invoice Created Email', event: 'invoice.created', channel: 'email', body: 'Hi {{ contact.first_name }},\n\nInvoice **{{ invoice.number }}** has been raised for {{ invoice.total | currency }}.\n\n**Due date:** {{ invoice.due_date | date:"d M Y" }}\n\nPlease arrange payment at your earliest convenience.\n\nRegards,\n{{ company.name }}' },
    { id: 'tpl3', name: 'Late Return SMS', event: 'opportunity.late_return', channel: 'sms', body: 'SIGNALS: Equipment for booking {{ opportunity.number }} was due back {{ opportunity.ends_at | date:"d M Y" }}. Please return ASAP or contact {{ company.phone }}.' },
    { id: 'tpl4', name: 'Invoice Overdue Email', event: 'invoice.overdue', channel: 'email', body: 'Hi {{ contact.first_name }},\n\nThis is a reminder that invoice **{{ invoice.number }}** for {{ invoice.total | currency }} was due on {{ invoice.due_date | date:"d M Y" }}.\n\nPlease arrange payment promptly to avoid further action.\n\nRegards,\n{{ company.name }}' },
    { id: 'tpl5', name: 'Payment Received Email', event: 'invoice.payment_received', channel: 'email', body: 'Hi {{ contact.first_name }},\n\nWe have received your payment of {{ payment.amount | currency }} against invoice **{{ invoice.number }}**.\n\nThank you for your prompt payment.\n\nRegards,\n{{ company.name }}' },
    { id: 'tpl6', name: 'Booking Cancelled Email', event: 'opportunity.cancelled', channel: 'email', body: 'Hi {{ contact.first_name }},\n\nBooking **{{ opportunity.subject }}** ({{ opportunity.number }}) has been cancelled.\n\nIf this was unexpected, please contact us immediately.\n\nRegards,\n{{ company.name }}' },
    { id: 'tpl7', name: 'Inspection Due Reminder', event: 'inspection.due', channel: 'email', body: 'Hi {{ inspector.first_name }},\n\nInspection for **{{ asset.name }}** ({{ asset.serial }}) is due on {{ inspection.due_date | date:"d M Y" }}.\n\nPlease schedule this at your earliest availability.\n\nRegards,\n{{ company.name }}' },
    { id: 'tpl8', name: 'Stock Alert Slack', event: 'stock.below_threshold', channel: 'slack', body: ':warning: **Stock Alert**\n\n*{{ product.name }}* is below minimum threshold.\n\nCurrent: {{ stock.quantity }} | Minimum: {{ stock.minimum }}\nStore: {{ store.name }}' },
  ];

  const mergeFieldGroups = [
    {
      label: 'Opportunity',
      fields: ['opportunity.subject', 'opportunity.number', 'opportunity.starts_at', 'opportunity.ends_at', 'opportunity.charge_total', 'opportunity.status'],
    },
    {
      label: 'Invoice',
      fields: ['invoice.number', 'invoice.total', 'invoice.due_date', 'invoice.status'],
    },
    {
      label: 'Member / Contact',
      fields: ['contact.first_name', 'contact.last_name', 'contact.email', 'contact.phone'],
    },
    {
      label: 'Company',
      fields: ['company.name', 'company.phone', 'company.email', 'company.website'],
    },
    {
      label: 'Product / Asset',
      fields: ['product.name', 'product.sku', 'asset.name', 'asset.serial'],
    },
  ];

  /* Sample merge values for preview */
  const sampleValues = {
    'contact.first_name': 'Sarah',
    'contact.last_name': 'Johnson',
    'contact.email': 'sarah.johnson@acme.com',
    'contact.phone': '+44 7700 900123',
    'opportunity.subject': 'Summer Festival Main Stage',
    'opportunity.number': 'OPP-1042',
    'opportunity.starts_at': '15 Mar 2026',
    'opportunity.ends_at': '18 Mar 2026',
    'opportunity.charge_total': '\u00a312,450.00',
    'opportunity.status': 'Confirmed',
    'invoice.number': 'INV-2087',
    'invoice.total': '\u00a312,450.00',
    'invoice.due_date': '30 Mar 2026',
    'invoice.status': 'Issued',
    'company.name': 'Signals Rentals Ltd',
    'company.phone': '+44 20 7946 0958',
    'company.email': 'hello@signals.rent',
    'company.website': 'signals.rent',
    'product.name': 'LED Par Can 64',
    'product.sku': 'LPC-64',
    'asset.name': 'Genie GS-1930',
    'asset.serial': 'GS1930-042',
    'inspector.first_name': 'James',
    'stock.quantity': '3',
    'stock.minimum': '10',
    'store.name': 'London Warehouse',
    'payment.amount': '\u00a35,000.00',
    'inspection.due_date': '20 Mar 2026',
  };

  /* ================================================================ */
  /*  COMPONENT STATE                                                  */
  /* ================================================================ */
  return {
    icons,
    providers,
    categories,
    notificationTypes,
    logEntries,
    templates,
    mergeFieldGroups,

    tabs: [
      { id: 'types', label: 'Types' },
      { id: 'log', label: 'Communication Log' },
      { id: 'templates', label: 'Templates' },
    ],

    activeTab: 'types',
    allChannels: ['in_app', 'email', 'sms', 'slack'],

    /* Types tab state */
    typeSearch: '',
    collapsedCategories: {},

    /* Log tab state */
    logSearch: '',

    /* Templates tab state */
    activeTemplate: 'tpl1',
    showMergeFields: false,

    /* ============================================================== */
    /*  TYPES TAB METHODS                                              */
    /* ============================================================== */
    toggleCategory(id) {
      this.collapsedCategories[id] = !this.collapsedCategories[id];
    },

    filteredCategories() {
      if (!this.typeSearch) return this.categories;
      const q = this.typeSearch.toLowerCase();
      return this.categories.filter(c =>
        this.notificationTypes.some(t =>
          t.category === c.id &&
          (t.name.toLowerCase().includes(q) || t.description.toLowerCase().includes(q) || t.key.toLowerCase().includes(q))
        )
      );
    },

    filteredTypes(categoryId) {
      let types = this.notificationTypes.filter(t => t.category === categoryId);
      if (this.typeSearch) {
        const q = this.typeSearch.toLowerCase();
        types = types.filter(t =>
          t.name.toLowerCase().includes(q) || t.description.toLowerCase().includes(q) || t.key.toLowerCase().includes(q)
        );
      }
      return types;
    },

    categoryTypeCount(categoryId) {
      return this.filteredTypes(categoryId).length;
    },

    toggleChannel(typeKey, channel) {
      const type = this.notificationTypes.find(t => t.key === typeKey);
      if (!type) return;
      const idx = type.channels.indexOf(channel);
      if (idx >= 0) {
        type.channels.splice(idx, 1);
      } else {
        type.channels.push(channel);
      }
    },

    channelLabel(ch) {
      const labels = { in_app: 'In-App', email: 'Email', sms: 'SMS', slack: 'Slack' };
      return labels[ch] || ch;
    },

    channelIcon,

    /* ============================================================== */
    /*  LOG TAB METHODS                                                */
    /* ============================================================== */
    filteredLogs() {
      if (!this.logSearch) return this.logEntries;
      const q = this.logSearch.toLowerCase();
      return this.logEntries.filter(e =>
        e.type.toLowerCase().includes(q) ||
        e.recipient.toLowerCase().includes(q) ||
        e.entity.toLowerCase().includes(q) ||
        e.status.toLowerCase().includes(q)
      );
    },

    /* ============================================================== */
    /*  TEMPLATE TAB METHODS                                           */
    /* ============================================================== */
    currentTemplateBody() {
      const tpl = this.templates.find(t => t.id === this.activeTemplate);
      return tpl ? tpl.body : '';
    },

    updateTemplateBody(value) {
      const tpl = this.templates.find(t => t.id === this.activeTemplate);
      if (tpl) tpl.body = value;
    },

    insertMergeField(field) {
      const textarea = this.$refs.editor;
      if (!textarea) return;
      const tag = '{{ ' + field + ' }}';
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const text = textarea.value;
      const newText = text.substring(0, start) + tag + text.substring(end);
      this.updateTemplateBody(newText);
      this.$nextTick(() => {
        textarea.value = newText;
        textarea.selectionStart = textarea.selectionEnd = start + tag.length;
        textarea.focus();
      });
    },

    insertFormat(wrapper) {
      const textarea = this.$refs.editor;
      if (!textarea) return;
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const text = textarea.value;
      const selected = text.substring(start, end) || 'text';
      const newText = text.substring(0, start) + wrapper + selected + wrapper + text.substring(end);
      this.updateTemplateBody(newText);
      this.$nextTick(() => {
        textarea.value = newText;
        textarea.selectionStart = start + wrapper.length;
        textarea.selectionEnd = start + wrapper.length + selected.length;
        textarea.focus();
      });
    },

    renderedPreview() {
      const tpl = this.templates.find(t => t.id === this.activeTemplate);
      if (!tpl) return '';

      let body = tpl.body;

      /* Replace merge fields with sample values */
      body = body.replace(/\{\{\s*([^}|]+?)(?:\s*\|\s*[^}]+)?\s*\}\}/g, (match, fieldRaw) => {
        const field = fieldRaw.trim();
        const val = sampleValues[field];
        if (val) {
          return '<span class="na-preview-field">' + this.escapeHtml(val) + '</span>';
        }
        return '<span class="na-preview-field">' + this.escapeHtml(match) + '</span>';
      });

      /* Basic markdown: bold, italic, line breaks */
      body = this.escapeHtml(body);
      /* Undo escaping inside our preview-field spans */
      body = body.replace(/&lt;span class=&quot;na-preview-field&quot;&gt;/g, '<span class="na-preview-field">');
      body = body.replace(/&lt;\/span&gt;/g, '</span>');

      body = body.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
      body = body.replace(/_(.+?)_/g, '<em>$1</em>');
      body = body.replace(/\n/g, '<br>');

      return body;
    },

    escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    },

    /* ============================================================== */
    /*  PROVIDER ACTIONS                                               */
    /* ============================================================== */
    testProvider(provider) {
      const original = provider.status;
      provider.status = 'untested';
      setTimeout(() => { provider.status = 'connected'; }, 1500);
    },

    addProvider() {
      /* No-op in prototype */
    },
  };
}
</script>
@endverbatim
