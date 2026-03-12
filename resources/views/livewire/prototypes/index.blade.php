<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('UI Prototypes')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  :root {
    --pi-bg: var(--content-bg);
    --pi-panel: var(--card-bg);
    --pi-border: var(--card-border);
    --pi-text: var(--text-primary);
    --pi-text-secondary: var(--text-secondary);
    --pi-text-muted: var(--text-muted);
    --pi-accent: var(--green);
    --pi-accent-dim: var(--green-muted);
    --pi-hover: rgba(0, 0, 0, 0.03);
    --pi-shadow: var(--shadow-card);
  }

  .dark {
    --pi-bg: var(--content-bg);
    --pi-panel: var(--card-bg);
    --pi-border: var(--card-border);
    --pi-text: var(--text-primary);
    --pi-text-secondary: var(--text-secondary);
    --pi-text-muted: var(--text-muted);
    --pi-accent: var(--green);
    --pi-accent-dim: rgba(5, 150, 105, 0.12);
    --pi-hover: rgba(255, 255, 255, 0.06);
    --pi-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
  }

  .pi-page {
    min-height: 100vh;
    background: var(--pi-bg);
    padding: 32px;
  }

  .pi-container {
    max-width: 1100px;
    margin: 0 auto;
  }

  .pi-header {
    margin-bottom: 32px;
  }

  .pi-title {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--pi-text);
  }

  .pi-subtitle {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--pi-text-muted);
    margin-top: 4px;
  }

  .pi-count {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--pi-accent);
    margin-top: 8px;
  }

  .pi-section {
    margin-bottom: 28px;
  }

  .pi-section-title {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--pi-text-muted);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--pi-border);
  }

  .pi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
  }

  @media (max-width: 900px) {
    .pi-grid { grid-template-columns: repeat(2, 1fr); }
  }

  @media (max-width: 600px) {
    .pi-grid { grid-template-columns: 1fr; }
  }

  .pi-card {
    background: var(--pi-panel);
    border: 1px solid var(--pi-border);
    padding: 16px 18px;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: all 0.15s ease;
    box-shadow: var(--pi-shadow);
  }

  .pi-card:hover {
    border-color: var(--pi-accent);
    background: var(--pi-hover);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  }

  .dark .pi-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
  }

  .pi-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }

  .pi-card-name {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 700;
    color: var(--pi-text);
    letter-spacing: -0.01em;
  }

  .pi-card-badge {
    font-family: var(--font-mono);
    font-size: 9px;
    padding: 2px 7px;
    background: var(--pi-accent-dim);
    color: var(--pi-accent);
    white-space: nowrap;
  }

  .pi-card-desc {
    font-family: var(--font-display);
    font-size: 11px;
    color: var(--pi-text-muted);
    line-height: 1.5;
  }

  .pi-card-path {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--pi-text-secondary);
    opacity: 0.6;
    margin-top: 2px;
  }

  .pi-card:hover .pi-card-path {
    color: var(--pi-accent);
    opacity: 1;
  }

  .pi-card-arrow {
    width: 14px;
    height: 14px;
    color: var(--pi-text-muted);
    opacity: 0;
    transition: all 0.15s ease;
    flex-shrink: 0;
  }

  .pi-card:hover .pi-card-arrow {
    opacity: 1;
    color: var(--pi-accent);
    transform: translateX(2px);
  }

  @keyframes piFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .pi-card {
    animation: piFadeIn 0.25s ease both;
  }

  .pi-card:nth-child(1) { animation-delay: 0s; }
  .pi-card:nth-child(2) { animation-delay: 0.03s; }
  .pi-card:nth-child(3) { animation-delay: 0.06s; }
  .pi-card:nth-child(4) { animation-delay: 0.09s; }
  .pi-card:nth-child(5) { animation-delay: 0.12s; }
  .pi-card:nth-child(6) { animation-delay: 0.15s; }
</style>

<div class="pi-page">
  <div class="pi-container">

    {{-- ============================================================ --}}
    {{--  HEADER                                                       --}}
    {{-- ============================================================ --}}
    <div class="pi-header">
      <div class="pi-title">UI Prototypes</div>
      <div class="pi-subtitle">Signals Framework — Design Validation</div>
      <div class="pi-count">20 prototypes</div>
    </div>

    {{-- ============================================================ --}}
    {{--  REFERENCE                                                    --}}
    {{-- ============================================================ --}}
    <div class="pi-section">
      <div class="pi-section-title">Reference</div>
      <div class="pi-grid">
        <a href="/prototypes/component-reference" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Component Reference</span>
            <span class="pi-card-badge">canonical</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Single source of truth — all canonical UI components with live examples</div>
          <div class="pi-card-path">/prototypes/component-reference</div>
        </a>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  CORE FRAMEWORK                                               --}}
    {{-- ============================================================ --}}
    <div class="pi-section">
      <div class="pi-section-title">Core Framework</div>
      <div class="pi-grid">
        <a href="/prototypes/opportunity-lifecycle" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Opportunity Lifecycle</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Two-axis state model, event sourcing, version tree, asset assignments</div>
          <div class="pi-card-path">/prototypes/opportunity-lifecycle</div>
        </a>
        <a href="/prototypes/field-registry" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Field Registry</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Schema engine with core, computed, and custom field sources</div>
          <div class="pi-card-path">/prototypes/field-registry</div>
        </a>
        <a href="/prototypes/permissions" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Permissions & Auth</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Four-layer access control with role editor and permission matrix</div>
          <div class="pi-card-path">/prototypes/permissions</div>
        </a>
        <a href="/prototypes/custom-views" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Custom Views</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Saved list configurations with columns, filters, sorting, and visibility</div>
          <div class="pi-card-path">/prototypes/custom-views</div>
        </a>
        <a href="/prototypes/settings-admin" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Settings & Admin</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Company, email, security, taxation, modules, and system health</div>
          <div class="pi-card-path">/prototypes/settings-admin</div>
        </a>
        <a href="/prototypes/plugin-system" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Plugin System</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Composer plugins with manifest viewer, hooks, slots, and marketplace</div>
          <div class="pi-card-path">/prototypes/plugin-system</div>
        </a>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  PRICING & INVENTORY                                          --}}
    {{-- ============================================================ --}}
    <div class="pi-section">
      <div class="pi-section-title">Pricing & Inventory</div>
      <div class="pi-grid">
        <a href="/prototypes/rate-engine" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Rate Engine</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Rate definitions, CRMS presets, multiplier/factor tables, live calculator</div>
          <div class="pi-card-path">/prototypes/rate-engine</div>
        </a>
        <a href="/prototypes/availability" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Availability Engine</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Demand-based availability with timeline visualisation</div>
          <div class="pi-card-path">/prototypes/availability</div>
        </a>
        <a href="/prototypes/availability-opportunity" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Availability — Opportunity</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Opportunity-level availability checking and conflict resolution</div>
          <div class="pi-card-path">/prototypes/availability-opportunity</div>
        </a>
        <a href="/prototypes/shortage-resolution" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Shortage Resolution</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Detection, resolver options, virtual stock, and sub-hire management</div>
          <div class="pi-card-path">/prototypes/shortage-resolution</div>
        </a>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  COMMUNICATION & REPORTING                                    --}}
    {{-- ============================================================ --}}
    <div class="pi-section">
      <div class="pi-section-title">Communication & Reporting</div>
      <div class="pi-grid">
        <a href="/prototypes/notification-admin" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Notification Admin</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Channel providers, notification types, communication log, templates</div>
          <div class="pi-card-path">/prototypes/notification-admin</div>
        </a>
        <a href="/prototypes/reporting" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Reporting Framework</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Report builder with dimensions, measures, charts, and comparisons</div>
          <div class="pi-card-path">/prototypes/reporting</div>
        </a>
        <a href="/prototypes/document-editor" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Document Editor</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Document template editor for quotes, invoices, and delivery notes</div>
          <div class="pi-card-path">/prototypes/document-editor</div>
        </a>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  DATA & MIGRATION                                             --}}
    {{-- ============================================================ --}}
    <div class="pi-section">
      <div class="pi-section-title">Data & Migration</div>
      <div class="pi-grid">
        <a href="/prototypes/import-export" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Import / Export</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">5-stage import pipeline, field mapping, validation, CRMS migration</div>
          <div class="pi-card-path">/prototypes/import-export</div>
        </a>
        <a href="/prototypes/grid" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Data Grid</span>
            <span class="pi-card-badge">auth</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Sortable, filterable data grid component</div>
          <div class="pi-card-path">/prototypes/grid</div>
        </a>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  WORKFLOW ENGINE                                               --}}
    {{-- ============================================================ --}}
    <div class="pi-section">
      <div class="pi-section-title">Workflow Engine</div>
      <div class="pi-grid">
        <a href="/prototypes/workflow-editor-minimal" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Workflow — Minimal</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Streamlined node-based workflow builder</div>
          <div class="pi-card-path">/prototypes/workflow-editor-minimal</div>
        </a>
        <a href="/prototypes/workflow-editor" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Workflow — Full</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Full-featured workflow editor with canvas</div>
          <div class="pi-card-path">/prototypes/workflow-editor</div>
        </a>
        <a href="/prototypes/workflow-editor-split" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Workflow — Split</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Split-panel workflow editor variant</div>
          <div class="pi-card-path">/prototypes/workflow-editor-split</div>
        </a>
        <a href="/prototypes/workflow-editor-timeline" class="pi-card">
          <div class="pi-card-top">
            <span class="pi-card-name">Workflow — Timeline</span>
            <svg class="pi-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </div>
          <div class="pi-card-desc">Timeline-based workflow visualisation</div>
          <div class="pi-card-path">/prototypes/workflow-editor-timeline</div>
        </a>
      </div>
    </div>

  </div>
</div>
