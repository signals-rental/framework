<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.editor')] #[Title('Workflow Editor — Timeline')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  WORKFLOW TIMELINE TOKENS — maps to brand system in app.css      */
  /* ================================================================ */
  :root {
    --wt-bg: var(--content-bg);
    --wt-panel: var(--card-bg);
    --wt-surface: var(--base);
    --wt-border: var(--card-border);
    --wt-border-subtle: var(--grey-border);
    --wt-text: var(--text-primary);
    --wt-text-secondary: var(--text-secondary);
    --wt-text-muted: var(--text-muted);
    --wt-accent: var(--green);
    --wt-accent-dim: var(--green-muted);
    --wt-hover: rgba(0, 0, 0, 0.04);
    --wt-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    --wt-trigger-color: var(--violet);
    --wt-trigger-bg: rgba(124, 58, 237, 0.08);
    --wt-action-color: var(--blue);
    --wt-action-bg: rgba(37, 99, 235, 0.06);
    --wt-condition-color: var(--amber);
    --wt-condition-bg: rgba(217, 119, 6, 0.06);
    --wt-delay-color: var(--cyan);
    --wt-delay-bg: rgba(8, 145, 178, 0.06);
    --wt-halt-color: var(--red);
    --wt-halt-bg: rgba(220, 38, 38, 0.06);
    --wt-branch-yes: var(--green);
    --wt-branch-no: var(--red);
    --wt-line-color: var(--grey-light);
    --wt-line-width: 2px;
    --wt-topbar-h: 52px;
    --wt-sidebar-w: 280px;
    --wt-config-w: 340px;
    --wt-card-w: 420px;
    --wt-dot-size: 12px;
    --wt-rail-offset: 80px;
    --wt-connector-len: 32px;
  }

  .dark {
    --wt-bg: var(--content-bg);
    --wt-panel: var(--card-bg);
    --wt-surface: var(--navy-mid);
    --wt-border: var(--card-border);
    --wt-border-subtle: #283040;
    --wt-text: var(--text-primary);
    --wt-text-secondary: var(--text-secondary);
    --wt-text-muted: var(--text-muted);
    --wt-accent: var(--green);
    --wt-accent-dim: rgba(5, 150, 105, 0.12);
    --wt-hover: rgba(255, 255, 255, 0.06);
    --wt-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --wt-trigger-bg: rgba(124, 58, 237, 0.15);
    --wt-action-bg: rgba(37, 99, 235, 0.12);
    --wt-condition-bg: rgba(217, 119, 6, 0.12);
    --wt-delay-bg: rgba(8, 145, 178, 0.12);
    --wt-halt-bg: rgba(220, 38, 38, 0.12);
  }

  /* ================================================================ */
  /*  TOP BAR                                                          */
  /* ================================================================ */
  .wt-topbar {
    height: var(--wt-topbar-h);
    background: var(--navy);
    border-bottom: 1px solid var(--wt-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    z-index: 100;
    position: relative;
  }

  .wt-topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .wt-topbar-brand {
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

  .wt-topbar-brand:hover { color: #ffffff; }
  .wt-topbar-brand svg { opacity: 0.5; }

  .wt-topbar-sep {
    width: 1px;
    height: 20px;
    background: rgba(255, 255, 255, 0.12);
  }

  .wt-workflow-name {
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

  .wt-workflow-name:hover { border-color: rgba(255, 255, 255, 0.15); }
  .wt-workflow-name:focus { border-color: var(--green); background: rgba(255, 255, 255, 0.06); }

  .wt-status-badge {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 3px 10px;
    background: rgba(5, 150, 105, 0.15);
    color: var(--green);
  }

  .wt-status-badge.draft {
    background: rgba(217, 119, 6, 0.15);
    color: var(--amber);
  }

  .wt-topbar-center {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: stretch;
    height: 100%;
  }

  .wt-topbar-tab {
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

  .wt-topbar-tab:hover { color: #ffffff; }
  .wt-topbar-tab.active { color: #ffffff; border-bottom-color: var(--wt-accent); }
  .wt-topbar-tab svg { width: 14px; height: 14px; opacity: 0.5; }
  .wt-topbar-tab.active svg { opacity: 0.8; }

  .wt-topbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .wt-version {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--grey);
    padding: 0 8px;
  }

  .wt-btn {
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

  .wt-btn:hover { background: rgba(255, 255, 255, 0.1); color: #ffffff; }
  .wt-btn svg { width: 14px; height: 14px; }

  .wt-btn-primary {
    background: var(--green);
    color: #ffffff;
    border-color: var(--green);
  }

  .wt-btn-primary:hover {
    background: #06b07a;
    border-color: #06b07a;
    color: #ffffff;
  }

  /* ================================================================ */
  /*  ALERT BAR                                                        */
  /* ================================================================ */
  .wt-alert-bar {
    background: var(--blue-pale);
    border-bottom: 1px solid rgba(37, 99, 235, 0.12);
    padding: 10px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-shrink: 0;
  }

  .wt-alert-left {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--wt-text);
  }

  .wt-alert-icon {
    width: 20px;
    height: 20px;
    color: var(--blue);
    flex-shrink: 0;
  }

  .wt-alert-actions {
    display: flex;
    gap: 8px;
  }

  .wt-alert-btn {
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

  .wt-alert-btn-outline {
    border: 1px solid var(--wt-border);
    background: var(--wt-panel);
    color: var(--wt-text-secondary);
  }

  .wt-alert-btn-outline:hover { border-color: var(--wt-text-secondary); color: var(--wt-text); }

  .wt-alert-btn-fill {
    border: 1px solid var(--green);
    background: var(--green);
    color: #ffffff;
  }

  .wt-alert-btn-fill:hover { background: #06b07a; border-color: #06b07a; }

  .dark .wt-alert-bar {
    background: rgba(37, 99, 235, 0.08);
    border-bottom-color: rgba(37, 99, 235, 0.15);
  }

  /* ================================================================ */
  /*  MAIN LAYOUT                                                      */
  /* ================================================================ */
  .wt-main {
    display: flex;
    flex: 1;
    min-height: 0;
    overflow: hidden;
  }

  /* ================================================================ */
  /*  SIDEBAR: Handler Palette                                         */
  /* ================================================================ */
  .wt-sidebar {
    width: var(--wt-sidebar-w);
    min-width: var(--wt-sidebar-w);
    background: var(--wt-panel);
    border-right: 1px solid var(--wt-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .wt-sidebar-header {
    padding: 12px 14px;
    border-bottom: 1px solid var(--wt-border-subtle);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .wt-sidebar-title {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--wt-text-muted);
  }

  .wt-sidebar-search {
    padding: 8px 12px;
    border-bottom: 1px solid var(--wt-border-subtle);
  }

  .wt-search-input {
    width: 100%;
    padding: 7px 10px 7px 32px;
    background: var(--wt-surface);
    border: 1px solid var(--wt-border);
    color: var(--wt-text);
    font-size: 12px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: 10px center;
  }

  .wt-search-input:focus { border-color: var(--wt-accent); }
  .wt-search-input::placeholder { color: var(--wt-text-muted); }

  .wt-handler-groups {
    flex: 1;
    overflow-y: auto;
    padding: 4px 0;
  }

  .wt-handler-group { margin-bottom: 1px; }

  .wt-handler-group-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    cursor: pointer;
    user-select: none;
    transition: background 0.1s;
  }

  .wt-handler-group-header:hover { background: var(--wt-hover); }

  .wt-handler-group-chevron {
    width: 16px;
    height: 16px;
    color: var(--wt-text-muted);
    transition: transform 0.15s;
    flex-shrink: 0;
  }

  .wt-handler-group.open .wt-handler-group-chevron { transform: rotate(90deg); }

  .wt-handler-group-label {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wt-text-secondary);
  }

  .wt-handler-group-count {
    font-family: var(--font-mono);
    font-size: 9px;
    color: var(--wt-text-muted);
    margin-left: auto;
    background: var(--wt-surface);
    padding: 1px 6px;
  }

  .wt-handler-group-items {
    display: none;
    padding: 0 0 4px 0;
  }

  .wt-handler-group.open .wt-handler-group-items { display: block; }

  .wt-handler-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 14px 5px 36px;
    cursor: pointer;
    transition: background 0.1s;
    position: relative;
  }

  .wt-handler-item:hover { background: var(--wt-accent-dim); }

  .wt-handler-item-icon {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .wt-handler-item-icon svg { width: 14px; height: 14px; }

  .wt-handler-item-name {
    font-size: 11px;
    font-family: var(--font-mono);
    color: var(--wt-text);
    font-weight: 400;
  }

  .wt-handler-item-add {
    opacity: 0;
    font-family: var(--font-display);
    font-size: 9px;
    color: var(--wt-accent);
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    transition: opacity 0.1s;
    position: absolute;
    right: 14px;
  }

  .wt-handler-item:hover .wt-handler-item-add { opacity: 1; }

  .wt-type-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .wt-dot-trigger { background: var(--wt-trigger-color); }
  .wt-dot-action { background: var(--wt-action-color); }
  .wt-dot-condition { background: var(--wt-condition-color); }
  .wt-dot-delay { background: var(--wt-delay-color); }
  .wt-dot-halt { background: var(--wt-halt-color); }

  /* ================================================================ */
  /*  CONTENT AREA                                                     */
  /* ================================================================ */
  .wt-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    position: relative;
  }

  .wt-canvas {
    flex: 1;
    overflow-y: auto;
    background: var(--wt-surface);
    position: relative;
  }

  .dark .wt-canvas {
    background:
      radial-gradient(circle at 50% 30%, rgba(5, 150, 105, 0.02), transparent 60%),
      var(--wt-surface);
  }

  /* ================================================================ */
  /*  TIMELINE LAYOUT                                                  */
  /* ================================================================ */
  .wt-timeline {
    max-width: 800px;
    margin: 0 auto;
    padding: 32px 24px 120px;
    position: relative;
  }

  .wt-timeline-step {
    position: relative;
    padding-left: var(--wt-rail-offset);
    padding-bottom: 0;
    animation: wtStepIn 0.3s ease both;
  }

  .wt-timeline-step:nth-child(1) { animation-delay: 0s; }
  .wt-timeline-step:nth-child(2) { animation-delay: 0.05s; }
  .wt-timeline-step:nth-child(3) { animation-delay: 0.1s; }
  .wt-timeline-step:nth-child(4) { animation-delay: 0.15s; }
  .wt-timeline-step:nth-child(5) { animation-delay: 0.2s; }
  .wt-timeline-step:nth-child(6) { animation-delay: 0.25s; }
  .wt-timeline-step:nth-child(7) { animation-delay: 0.3s; }
  .wt-timeline-step:nth-child(8) { animation-delay: 0.35s; }

  /* Vertical rail line */
  .wt-timeline-step::before {
    content: '';
    position: absolute;
    left: calc(var(--wt-rail-offset) - 1px);
    top: 0;
    bottom: 0;
    width: var(--wt-line-width);
    background: var(--wt-line-color);
    opacity: 0.4;
  }

  .wt-timeline-step:first-child::before {
    top: 18px;
  }

  .wt-timeline-step:last-child::before {
    bottom: auto;
    height: 18px;
  }

  /* Timeline dot */
  .wt-timeline-dot {
    position: absolute;
    left: calc(var(--wt-rail-offset) - 7px);
    top: 12px;
    width: var(--wt-dot-size);
    height: var(--wt-dot-size);
    border-radius: 50%;
    z-index: 2;
    transition: transform 0.15s, box-shadow 0.15s;
  }

  .wt-timeline-step:hover .wt-timeline-dot {
    transform: scale(1.25);
    box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.15);
  }

  .wt-timeline-dot-trigger { background: var(--wt-trigger-color); }
  .wt-timeline-dot-action { background: var(--wt-action-color); }
  .wt-timeline-dot-condition { background: var(--wt-condition-color); }
  .wt-timeline-dot-delay { background: var(--wt-delay-color); }
  .wt-timeline-dot-halt { background: var(--wt-halt-color); }

  /* Step number label (left of dot) */
  .wt-step-number {
    position: absolute;
    left: 0;
    top: 13px;
    width: calc(var(--wt-rail-offset) - 20px);
    text-align: right;
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--wt-text-muted);
    line-height: 1;
    user-select: none;
  }

  /* Horizontal connector from dot to card */
  .wt-timeline-connector {
    position: absolute;
    left: calc(var(--wt-rail-offset) + 5px);
    top: 17px;
    width: var(--wt-connector-len);
    height: var(--wt-line-width);
    background: var(--wt-line-color);
    opacity: 0.4;
  }

  /* Card area */
  .wt-timeline-card-area {
    margin-left: calc(var(--wt-connector-len) + 8px);
    padding-top: 0;
    padding-bottom: 24px;
  }

  /* ================================================================ */
  /*  STEP NODE CARDS (timeline variant — wider)                       */
  /* ================================================================ */
  .wt-node {
    width: var(--wt-card-w);
    max-width: 100%;
    background: var(--wt-panel);
    border: 1px solid var(--wt-border);
    position: relative;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
  }

  .wt-node:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
    transform: translateY(-1px);
  }

  .wt-node.selected {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
  }

  .wt-node-accent {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
  }

  .wt-node-accent-trigger { background: var(--wt-trigger-color); }
  .wt-node-accent-action { background: var(--wt-action-color); }
  .wt-node-accent-condition { background: var(--wt-condition-color); }
  .wt-node-accent-delay { background: var(--wt-delay-color); }
  .wt-node-accent-halt { background: var(--wt-halt-color); }

  .wt-node-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px 0 16px;
  }

  .wt-node-type-label {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  .wt-node-subcategory {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wt-text-muted);
  }

  .wt-node-body {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 12px 10px 16px;
  }

  .wt-node-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .wt-node-icon svg { width: 16px; height: 16px; }

  .wt-node-info {
    flex: 1;
    min-width: 0;
  }

  .wt-node-name {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    color: var(--wt-text);
    line-height: 1.3;
  }

  .wt-node-desc {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 400;
    color: var(--wt-text-muted);
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Time annotation (optional right side of card) */
  .wt-node-time {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--wt-text-muted);
    padding: 0 12px 8px 16px;
  }

  /* ================================================================ */
  /*  ADD STEP BUTTON (on timeline rail)                               */
  /* ================================================================ */
  .wt-timeline-add-wrap {
    position: relative;
    padding-left: var(--wt-rail-offset);
    padding-bottom: 24px;
  }

  /* Rail continuation line for add buttons */
  .wt-timeline-add-wrap::before {
    content: '';
    position: absolute;
    left: calc(var(--wt-rail-offset) - 1px);
    top: 0;
    bottom: 0;
    width: var(--wt-line-width);
    background: var(--wt-line-color);
    opacity: 0.4;
  }

  .wt-add-step-btn {
    position: relative;
    left: -12px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 12px 4px 4px;
    background: var(--wt-panel);
    border: 1px dashed var(--wt-line-color);
    color: var(--wt-text-muted);
    cursor: pointer;
    transition: all 0.15s;
    z-index: 1;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .wt-add-step-btn:hover {
    border-color: var(--wt-accent);
    color: var(--wt-accent);
    background: var(--wt-accent-dim);
  }

  .wt-add-step-btn svg { width: 14px; height: 14px; }

  /* ================================================================ */
  /*  BRANCH VISUALIZATION (timeline splits)                           */
  /* ================================================================ */
  .wt-branch-container {
    position: relative;
    padding-left: var(--wt-rail-offset);
    padding-bottom: 12px;
  }

  /* Continuation of the main rail behind branches */
  .wt-branch-container::before {
    content: '';
    position: absolute;
    left: calc(var(--wt-rail-offset) - 1px);
    top: 0;
    bottom: 0;
    width: var(--wt-line-width);
    background: var(--wt-line-color);
    opacity: 0.2;
  }

  .wt-branch-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 8px 0;
    margin-left: calc(var(--wt-connector-len) + 8px);
  }

  .wt-branch-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 2px 12px;
    background: var(--wt-panel);
    border: 1px solid var(--wt-border);
    z-index: 1;
    position: relative;
  }

  .wt-branch-label-no { color: var(--wt-branch-no); }
  .wt-branch-label-yes { color: var(--wt-branch-yes); }

  .wt-branch-h-line {
    flex: 1;
    height: var(--wt-line-width);
    background: var(--wt-line-color);
    opacity: 0.3;
  }

  .wt-branch-columns {
    display: flex;
    gap: 24px;
    margin-left: calc(var(--wt-connector-len) + 8px);
  }

  .wt-branch-col {
    flex: 1;
    max-width: 340px;
  }

  .wt-branch-col-step {
    position: relative;
    padding-left: 24px;
    padding-bottom: 16px;
  }

  /* Branch sub-rail */
  .wt-branch-col-step::before {
    content: '';
    position: absolute;
    left: 5px;
    top: 0;
    bottom: 0;
    width: var(--wt-line-width);
    background: var(--wt-line-color);
    opacity: 0.3;
  }

  .wt-branch-col-step:last-child::before {
    bottom: auto;
    height: 18px;
  }

  .wt-branch-dot {
    position: absolute;
    left: 0;
    top: 12px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    z-index: 2;
  }

  .wt-branch-col .wt-node {
    width: 100%;
  }

  .wt-branch-add-wrap {
    position: relative;
    padding-left: 24px;
    padding-bottom: 4px;
  }

  .wt-branch-add-wrap::before {
    content: '';
    position: absolute;
    left: 5px;
    top: 0;
    height: 12px;
    width: var(--wt-line-width);
    background: var(--wt-line-color);
    opacity: 0.3;
  }

  .wt-branch-add-btn {
    position: relative;
    left: -6px;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px 3px 3px;
    background: var(--wt-panel);
    border: 1px dashed var(--wt-line-color);
    color: var(--wt-text-muted);
    cursor: pointer;
    transition: all 0.15s;
    z-index: 1;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .wt-branch-add-btn:hover {
    border-color: var(--wt-accent);
    color: var(--wt-accent);
    background: var(--wt-accent-dim);
  }

  .wt-branch-add-btn svg { width: 12px; height: 12px; }

  /* ================================================================ */
  /*  CONFIG PANEL                                                     */
  /* ================================================================ */
  .wt-config {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: var(--wt-config-w);
    background: var(--wt-panel);
    border-left: 1px solid var(--wt-border);
    z-index: 50;
    transform: translateX(100%);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
  }

  .wt-config.open { transform: translateX(0); }

  .wt-config-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--wt-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    background: var(--wt-panel);
    z-index: 1;
  }

  .wt-config-title {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wt-text);
  }

  .wt-config-close {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    color: var(--wt-text-muted);
    cursor: pointer;
    transition: all 0.15s;
  }

  .wt-config-close:hover { background: var(--wt-hover); color: var(--wt-text); }

  .wt-config-section {
    padding: 16px;
    border-bottom: 1px solid var(--wt-border-subtle);
  }

  .wt-config-section-title {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--wt-text-muted);
    margin-bottom: 12px;
  }

  .wt-config-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 12px;
  }

  .wt-config-row:last-child { margin-bottom: 0; }

  .wt-config-label {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 500;
    color: var(--wt-text-secondary);
  }

  .wt-config-input {
    padding: 7px 10px;
    background: var(--wt-surface);
    border: 1px solid var(--wt-border);
    color: var(--wt-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    width: 100%;
  }

  .wt-config-input:focus { border-color: var(--wt-accent); }

  .wt-config-select {
    padding: 7px 10px;
    background: var(--wt-surface);
    border: 1px solid var(--wt-border);
    color: var(--wt-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    cursor: pointer;
    width: 100%;
  }

  .wt-config-select:focus { border-color: var(--wt-accent); }

  .wt-config-textarea {
    padding: 7px 10px;
    background: var(--wt-surface);
    border: 1px solid var(--wt-border);
    color: var(--wt-text);
    font-size: 11px;
    font-family: var(--font-mono);
    outline: none;
    transition: border-color 0.15s;
    width: 100%;
    min-height: 60px;
    resize: vertical;
  }

  .wt-config-textarea:focus { border-color: var(--wt-accent); }

  .wt-config-delete {
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

  .wt-config-delete:hover {
    background: rgba(220, 38, 38, 0.12);
    border-color: var(--red);
  }

  .wt-config-delete svg { width: 14px; height: 14px; }

  .wt-config-type-badge {
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
  .wt-statusbar {
    height: 28px;
    background: var(--wt-panel);
    border-top: 1px solid var(--wt-border);
    display: flex;
    align-items: center;
    padding: 0 14px;
    gap: 16px;
    font-size: 10px;
    color: var(--wt-text-muted);
    font-family: var(--font-mono);
    flex-shrink: 0;
  }

  .wt-statusbar-item {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .wt-statusbar-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--green);
  }

  .wt-statusbar-right {
    margin-left: auto;
    display: flex;
    gap: 16px;
  }

  .wt-statusbar-key {
    display: inline-flex;
    padding: 0 4px;
    background: var(--wt-surface);
    border: 1px solid var(--wt-border-subtle);
    font-size: 9px;
    line-height: 1.4;
  }

  /* ================================================================ */
  /*  TOAST                                                            */
  /* ================================================================ */
  .wt-toast {
    position: fixed;
    bottom: 48px;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: var(--navy);
    border: 1px solid var(--wt-border);
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

  .wt-toast.visible {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }

  .wt-toast-icon { color: var(--green); }

  /* ================================================================ */
  /*  ADD STEP MODAL                                                   */
  /* ================================================================ */
  .wt-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    z-index: 150;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: wtFadeIn 0.15s ease;
  }

  .wt-modal {
    width: 480px;
    max-height: 520px;
    background: var(--wt-panel);
    border: 1px solid var(--wt-border);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    animation: wtModalIn 0.2s ease;
  }

  .wt-modal-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--wt-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .wt-modal-title {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wt-text);
  }

  .wt-modal-close {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    color: var(--wt-text-muted);
    cursor: pointer;
    transition: all 0.15s;
  }

  .wt-modal-close:hover { background: var(--wt-hover); color: var(--wt-text); }

  .wt-modal-search {
    padding: 8px 16px;
    border-bottom: 1px solid var(--wt-border-subtle);
  }

  .wt-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
  }

  .wt-modal-group-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wt-text-muted);
    padding: 8px 16px 4px;
  }

  .wt-modal-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    cursor: pointer;
    transition: background 0.1s;
  }

  .wt-modal-item:hover { background: var(--wt-accent-dim); }

  .wt-modal-item-icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .wt-modal-item-icon svg { width: 14px; height: 14px; }

  .wt-modal-item-info { flex: 1; min-width: 0; }

  .wt-modal-item-name {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--wt-text);
  }

  .wt-modal-item-desc {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--wt-text-muted);
  }

  /* ================================================================ */
  /*  RUNS TAB                                                         */
  /* ================================================================ */
  .wt-runs {
    padding: 24px;
    max-width: 900px;
    margin: 0 auto;
    width: 100%;
  }

  .wt-runs-table-wrap {
    background: var(--wt-panel);
    border: 1px solid var(--wt-border);
    overflow: hidden;
    box-shadow: var(--wt-shadow);
  }

  .wt-runs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }

  .wt-runs-table thead th {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--wt-text-muted);
    background: var(--wt-surface);
    padding: 10px 16px;
    text-align: left;
    border-bottom: 1px solid var(--wt-border);
  }

  .wt-runs-table tbody td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--wt-border-subtle);
    color: var(--wt-text);
    vertical-align: middle;
  }

  .wt-runs-table tbody tr:last-child td { border-bottom: none; }
  .wt-runs-table tbody tr:hover { background: var(--wt-hover); }

  .wt-run-id {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--blue);
    font-weight: 400;
    letter-spacing: 0.2px;
  }

  .wt-run-badge {
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

  .wt-run-badge-success { background: rgba(5, 150, 105, 0.1); color: var(--green); }
  .wt-run-badge-failed { background: rgba(220, 38, 38, 0.1); color: var(--red); }

  .wt-run-mono {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--wt-text-secondary);
  }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes wtFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  @keyframes wtStepIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes wtModalIn {
    from { opacity: 0; transform: scale(0.96) translateY(8px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
  }

  .wt-main { animation: wtFadeIn 0.3s ease-out; }
  .wt-sidebar { animation: wtFadeIn 0.3s ease-out 0.05s both; }
  .wt-canvas { animation: wtFadeIn 0.3s ease-out 0.1s both; }
</style>

<div
  x-data="workflowEditorTimeline()"
  @keydown.window="handleKeydown($event)"
>
  {{-- TOP BAR --}}
  <div class="wt-topbar">
    <div class="wt-topbar-left">
      <div class="wt-topbar-brand">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/></svg>
        Workflows
      </div>
      <div class="wt-topbar-sep"></div>
      <div style="display:flex;align-items:center;gap:10px;">
        <input
          type="text"
          class="wt-workflow-name"
          :value="workflow.name"
          @input="workflow.name = $event.target.value; isDirty = true"
          spellcheck="false"
        />
        <span class="wt-status-badge" :class="{ 'draft': workflow.status === 'draft' }" x-text="workflow.status"></span>
      </div>
    </div>

    <div class="wt-topbar-center">
      <button class="wt-topbar-tab" :class="{ 'active': activeTab === 'editor' }" @click="activeTab = 'editor'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/></svg>
        Editor
      </button>
      <button class="wt-topbar-tab" :class="{ 'active': activeTab === 'runs' }" @click="activeTab = 'runs'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Runs
      </button>
    </div>

    <div class="wt-topbar-actions">
      <span class="wt-version" x-text="'v' + workflow.version + ' \u00b7 ' + workflow.lastSaved"></span>
      <button class="wt-btn" @click="discardChanges()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Discard
      </button>
      <button class="wt-btn wt-btn-primary" @click="publishWorkflow()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Publish
      </button>
    </div>
  </div>

  {{-- ALERT BAR --}}
  <div class="wt-alert-bar" x-show="isDirty" x-transition>
    <div class="wt-alert-left">
      <svg class="wt-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
      This workflow has unpublished changes
    </div>
    <div class="wt-alert-actions">
      <button class="wt-alert-btn wt-alert-btn-outline" @click="discardChanges()">Discard changes</button>
      <button class="wt-alert-btn wt-alert-btn-fill" @click="publishWorkflow()">Publish changes</button>
    </div>
  </div>

  {{-- MAIN LAYOUT --}}
  <div class="wt-main" :style="'height: calc(100vh - var(--wt-topbar-h)' + (isDirty ? ' - 45px' : '') + ')'">

    {{-- SIDEBAR --}}
    <div class="wt-sidebar" x-show="activeTab === 'editor'">
      <div class="wt-sidebar-header">
        <span class="wt-sidebar-title">Step Library</span>
      </div>
      <div class="wt-sidebar-search">
        <input
          type="text"
          class="wt-search-input"
          placeholder="Search handlers..."
          x-model="paletteSearch"
          x-ref="paletteSearchInput"
        />
      </div>
      <div class="wt-handler-groups">
        <template x-for="(group, gi) in filteredHandlerGroups" :key="group.category">
          <div class="wt-handler-group" :class="{ 'open': paletteSearch || gi < 3 }">
            <div class="wt-handler-group-header" @click="$el.parentElement.classList.toggle('open')">
              <svg class="wt-handler-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
              <span class="wt-handler-group-label" x-text="group.category"></span>
              <span class="wt-handler-group-count" x-text="group.handlers.length"></span>
            </div>
            <div class="wt-handler-group-items">
              <template x-for="handler in group.handlers" :key="handler.id">
                <div class="wt-handler-item" @click="addNodeFromPalette(handler)">
                  <div class="wt-type-dot" :class="'wt-dot-' + handler.type"></div>
                  <span class="wt-handler-item-name" x-text="handler.name"></span>
                  <span class="wt-handler-item-add">Add</span>
                </div>
              </template>
            </div>
          </div>
        </template>
      </div>
    </div>

    {{-- CONTENT --}}
    <div class="wt-content">
      {{-- EDITOR VIEW --}}
      <template x-if="activeTab === 'editor'">
        <div class="wt-canvas">
          <div class="wt-timeline">
            {{-- Main flow nodes --}}
            <template x-for="(node, ni) in mainFlowNodes" :key="node.id">
              <div>
                {{-- Timeline step --}}
                <div class="wt-timeline-step">
                  {{-- Step number --}}
                  <span class="wt-step-number" x-text="'Step ' + (ni + 1)"></span>

                  {{-- Timeline dot --}}
                  <div class="wt-timeline-dot" :class="'wt-timeline-dot-' + node.type"></div>

                  {{-- Horizontal connector --}}
                  <div class="wt-timeline-connector"></div>

                  {{-- Card area --}}
                  <div class="wt-timeline-card-area">
                    <div
                      class="wt-node"
                      :class="{ 'selected': selectedNodeId === node.id }"
                      @click.stop="selectNode(node.id)"
                    >
                      <div class="wt-node-accent" :class="'wt-node-accent-' + node.type"></div>
                      <div class="wt-node-header">
                        <span class="wt-node-type-label" :style="'color: var(--wt-' + node.type + '-color)'" x-text="node.type.toUpperCase()"></span>
                        <span class="wt-node-subcategory" x-text="node.subcategory"></span>
                      </div>
                      <div class="wt-node-body">
                        <div class="wt-node-icon" :style="'background: var(--wt-' + node.type + '-bg)'" x-html="node.icon"></div>
                        <div class="wt-node-info">
                          <div class="wt-node-name" x-text="node.name"></div>
                          <div class="wt-node-desc" x-text="node.description"></div>
                        </div>
                      </div>
                      <template x-if="node.timeAnnotation">
                        <div class="wt-node-time" x-text="node.timeAnnotation"></div>
                      </template>
                    </div>
                  </div>
                </div>

                {{-- Condition branching --}}
                <template x-if="node.type === 'condition'">
                  <div class="wt-branch-container">
                    {{-- Branch header with NO / YES labels --}}
                    <div class="wt-branch-header">
                      <span class="wt-branch-label wt-branch-label-no">No</span>
                      <div class="wt-branch-h-line"></div>
                      <span class="wt-branch-label wt-branch-label-yes">Yes</span>
                    </div>

                    {{-- Branch columns --}}
                    <div class="wt-branch-columns">
                      {{-- NO branch --}}
                      <div class="wt-branch-col">
                        <template x-for="(bnode, bi) in getBranchNodes(node.id, 'no')" :key="bnode.id">
                          <div class="wt-branch-col-step">
                            <div class="wt-branch-dot" :class="'wt-timeline-dot-' + bnode.type"></div>
                            <div
                              class="wt-node"
                              :class="{ 'selected': selectedNodeId === bnode.id }"
                              @click.stop="selectNode(bnode.id)"
                            >
                              <div class="wt-node-accent" :class="'wt-node-accent-' + bnode.type"></div>
                              <div class="wt-node-header">
                                <span class="wt-node-type-label" :style="'color: var(--wt-' + bnode.type + '-color)'" x-text="bnode.type.toUpperCase()"></span>
                                <span class="wt-node-subcategory" x-text="bnode.subcategory"></span>
                              </div>
                              <div class="wt-node-body">
                                <div class="wt-node-icon" :style="'background: var(--wt-' + bnode.type + '-bg)'" x-html="bnode.icon"></div>
                                <div class="wt-node-info">
                                  <div class="wt-node-name" x-text="bnode.name"></div>
                                  <div class="wt-node-desc" x-text="bnode.description"></div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </template>
                        <div class="wt-branch-add-wrap">
                          <button class="wt-branch-add-btn" @click.stop="openAddStep(node.id, 'no')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add
                          </button>
                        </div>
                      </div>

                      {{-- YES branch --}}
                      <div class="wt-branch-col">
                        <template x-for="(bnode, bi) in getBranchNodes(node.id, 'yes')" :key="bnode.id">
                          <div class="wt-branch-col-step">
                            <div class="wt-branch-dot" :class="'wt-timeline-dot-' + bnode.type"></div>
                            <div
                              class="wt-node"
                              :class="{ 'selected': selectedNodeId === bnode.id }"
                              @click.stop="selectNode(bnode.id)"
                            >
                              <div class="wt-node-accent" :class="'wt-node-accent-' + bnode.type"></div>
                              <div class="wt-node-header">
                                <span class="wt-node-type-label" :style="'color: var(--wt-' + bnode.type + '-color)'" x-text="bnode.type.toUpperCase()"></span>
                                <span class="wt-node-subcategory" x-text="bnode.subcategory"></span>
                              </div>
                              <div class="wt-node-body">
                                <div class="wt-node-icon" :style="'background: var(--wt-' + bnode.type + '-bg)'" x-html="bnode.icon"></div>
                                <div class="wt-node-info">
                                  <div class="wt-node-name" x-text="bnode.name"></div>
                                  <div class="wt-node-desc" x-text="bnode.description"></div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </template>
                        <div class="wt-branch-add-wrap">
                          <button class="wt-branch-add-btn" @click.stop="openAddStep(node.id, 'yes')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </template>

                {{-- Add step button between regular steps --}}
                <template x-if="node.type !== 'condition'">
                  <div class="wt-timeline-add-wrap">
                    <button class="wt-add-step-btn" @click.stop="openAddStep(node.id, null)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                      Add step
                    </button>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </div>
      </template>

      {{-- RUNS VIEW --}}
      <template x-if="activeTab === 'runs'">
        <div class="wt-canvas">
          <div class="wt-runs">
            <div class="wt-runs-table-wrap">
              <table class="wt-runs-table">
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
                      <td><span class="wt-run-id" x-text="run.id"></span></td>
                      <td x-text="run.trigger"></td>
                      <td>
                        <span class="wt-run-badge" :class="run.status === 'Success' ? 'wt-run-badge-success' : 'wt-run-badge-failed'">
                          <span style="width:5px;height:5px;border-radius:50%;background:currentColor;"></span>
                          <span x-text="run.status"></span>
                        </span>
                      </td>
                      <td><span class="wt-run-mono" x-text="run.started"></span></td>
                      <td><span class="wt-run-mono" x-text="run.duration"></span></td>
                      <td><span class="wt-run-mono" x-text="run.steps"></span></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>

      {{-- CONFIG PANEL --}}
      <div class="wt-config" :class="{ 'open': configPanelOpen && selectedNode }">
        <template x-if="selectedNode">
          <div>
            <div class="wt-config-header">
              <span class="wt-config-title">Step Configuration</span>
              <button class="wt-config-close" @click="closeConfig()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
              </button>
            </div>

            <div class="wt-config-section">
              <div class="wt-config-section-title">Step Info</div>
              <div style="margin-bottom:10px;">
                <span
                  class="wt-config-type-badge"
                  :style="'background: var(--wt-' + selectedNode.type + '-bg); color: var(--wt-' + selectedNode.type + '-color)'"
                  x-text="selectedNode.type.toUpperCase() + ' \u00b7 ' + selectedNode.subcategory"
                ></span>
              </div>
              <div class="wt-config-row">
                <label class="wt-config-label">Name</label>
                <input class="wt-config-input" :value="selectedNode.name" @input="selectedNode.name = $event.target.value; isDirty = true" />
              </div>
              <div class="wt-config-row">
                <label class="wt-config-label">Description</label>
                <textarea class="wt-config-textarea" :value="selectedNode.description" @input="selectedNode.description = $event.target.value; isDirty = true"></textarea>
              </div>
            </div>

            <template x-if="selectedHandler && selectedHandler.configSchema.length > 0">
              <div class="wt-config-section">
                <div class="wt-config-section-title">Configuration</div>
                <template x-for="field in selectedHandler.configSchema" :key="field.key">
                  <div class="wt-config-row">
                    <label class="wt-config-label" x-text="field.label"></label>
                    <template x-if="field.type === 'select'">
                      <select class="wt-config-select" @change="selectedNode.config[field.key] = $event.target.value; isDirty = true">
                        <template x-for="opt in field.options" :key="opt">
                          <option :value="opt" :selected="selectedNode.config[field.key] === opt" x-text="opt"></option>
                        </template>
                      </select>
                    </template>
                    <template x-if="field.type === 'text'">
                      <input class="wt-config-input" :value="selectedNode.config[field.key] || ''" @input="selectedNode.config[field.key] = $event.target.value; isDirty = true" />
                    </template>
                    <template x-if="field.type === 'textarea'">
                      <textarea class="wt-config-textarea" :value="selectedNode.config[field.key] || ''" @input="selectedNode.config[field.key] = $event.target.value; isDirty = true"></textarea>
                    </template>
                    <template x-if="field.type === 'number'">
                      <input class="wt-config-input" type="number" :value="selectedNode.config[field.key] || ''" @input="selectedNode.config[field.key] = $event.target.value; isDirty = true" />
                    </template>
                  </div>
                </template>
              </div>
            </template>

            <div class="wt-config-section">
              <div class="wt-config-section-title">Failure Mode</div>
              <div class="wt-config-row">
                <label class="wt-config-label">On failure</label>
                <select class="wt-config-select" @change="selectedNode.config._failureMode = $event.target.value; isDirty = true">
                  <option value="halt" :selected="(selectedNode.config._failureMode || 'halt') === 'halt'">Halt workflow</option>
                  <option value="skip" :selected="selectedNode.config._failureMode === 'skip'">Skip and continue</option>
                  <option value="retry" :selected="selectedNode.config._failureMode === 'retry'">Retry (3 attempts)</option>
                </select>
              </div>
            </div>

            <template x-if="selectedNode.type !== 'trigger'">
              <button class="wt-config-delete" @click="deleteNode(selectedNode.id)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                Delete Step
              </button>
            </template>
          </div>
        </template>
      </div>

      {{-- STATUS BAR --}}
      <div class="wt-statusbar">
        <div class="wt-statusbar-item">
          <div class="wt-statusbar-dot"></div>
          <span x-text="totalNodeCount + ' steps'"></span>
        </div>
        <div class="wt-statusbar-item" x-text="workflow.trigger"></div>
        <div class="wt-statusbar-item" style="font-family: var(--font-display); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--violet);">Timeline</div>
        <div class="wt-statusbar-right">
          <div class="wt-statusbar-item">
            <span class="wt-statusbar-key">Del</span> delete
          </div>
          <div class="wt-statusbar-item">
            <span class="wt-statusbar-key">/</span> search
          </div>
          <div class="wt-statusbar-item">
            <span class="wt-statusbar-key">Esc</span> close
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ADD STEP MODAL --}}
  <template x-if="addStepModalOpen">
    <div class="wt-modal-overlay" @click.self="addStepModalOpen = false" @keydown.escape.window="addStepModalOpen = false">
      <div class="wt-modal">
        <div class="wt-modal-header">
          <span class="wt-modal-title">Add Step</span>
          <button class="wt-modal-close" @click="addStepModalOpen = false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
          </button>
        </div>
        <div class="wt-modal-search">
          <input
            type="text"
            class="wt-search-input"
            placeholder="Search handlers..."
            x-model="modalSearch"
            x-ref="modalSearchInput"
          />
        </div>
        <div class="wt-modal-body">
          <template x-for="group in filteredModalHandlers" :key="group.category">
            <div>
              <div class="wt-modal-group-label" x-text="group.category"></div>
              <template x-for="handler in group.handlers" :key="handler.id">
                <div class="wt-modal-item" @click="addNode(handler)">
                  <div class="wt-modal-item-icon" :style="'background: var(--wt-' + handler.type + '-bg)'" x-html="handler.icon"></div>
                  <div class="wt-modal-item-info">
                    <div class="wt-modal-item-name" x-text="handler.name"></div>
                    <div class="wt-modal-item-desc" x-text="handler.description"></div>
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
  <div class="wt-toast" :class="{ 'visible': toastVisible }">
    <span class="wt-toast-icon">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    </span>
    <span x-text="toastMessage"></span>
  </div>
</div>

@verbatim
<script>
function workflowEditorTimeline() {
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
        timeAnnotation: 'T+0s',
      },
      {
        id: 2, type: 'condition', handlerId: 'if_else',
        name: 'If / else', subcategory: 'Conditions',
        icon: icons.gitBranch, description: 'If user is VIP',
        config: { field: 'member.tags', operator: 'contains', value: 'VIP' },
        parentId: null, branch: null, order: 1,
        timeAnnotation: 'T+0s',
      },
      {
        id: 3, type: 'action', handlerId: 'add_to_audience',
        name: 'Add to audience', subcategory: 'User Management',
        icon: icons.users, description: 'Audience = Main Audience',
        config: { audience: 'Main Audience' },
        parentId: 2, branch: 'no', order: 0,
        timeAnnotation: null,
      },
      {
        id: 4, type: 'action', handlerId: 'send_email',
        name: 'Send email', subcategory: 'Messaging',
        icon: icons.mail, description: 'Template = Welcome Email',
        config: { template: 'Welcome Email', to: 'Trigger Contact' },
        parentId: 2, branch: 'no', order: 1,
        timeAnnotation: null,
      },
      {
        id: 5, type: 'action', handlerId: 'add_to_audience',
        name: 'Add to audience', subcategory: 'User Management',
        icon: icons.users, description: 'Audience = VIP Audience',
        config: { audience: 'VIP Audience' },
        parentId: 2, branch: 'yes', order: 0,
        timeAnnotation: null,
      },
      {
        id: 6, type: 'action', handlerId: 'send_email',
        name: 'Send email', subcategory: 'Messaging',
        icon: icons.mail, description: 'Template = VIP Onboarding Email',
        config: { template: 'VIP Onboarding Email', to: 'Trigger Contact' },
        parentId: 2, branch: 'yes', order: 1,
        timeAnnotation: null,
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
        timeAnnotation: null,
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
