<style>
  /* ================================================================ */
  /*  REFERENCE PAGE LAYOUT                                            */
  /*  All s-* component CSS is now in resources/css/components.css     */
  /*  and globally available via app.css. Only ref-* demo classes here. */
  /* ================================================================ */

  .ref-page {
    padding: 0;
    font-family: var(--font-mono);
    font-size: 12px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
  }

  .ref-container { max-width: 100%; margin: 0 auto; width: 100%; }

  .ref-title {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--text-primary);
    margin-bottom: 4px;
  }

  .ref-subtitle {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin-bottom: 32px;
  }

  .ref-section {
    margin-bottom: 48px;
  }

  .ref-section-title {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-primary);
    padding-bottom: 10px;
    border-bottom: 2px solid var(--green);
    margin-bottom: 20px;
  }

  .ref-subsection {
    margin-bottom: 28px;
  }

  .ref-subsection-title {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin-bottom: 12px;
  }

  .ref-demo {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    padding: 20px;
    margin-bottom: 8px;
    box-shadow: var(--shadow-card);
  }

  .ref-note {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--text-muted);
    margin-bottom: 16px;
  }

  .ref-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  .ref-col { display: flex; flex-direction: column; gap: 8px; }
  .ref-spacer { height: 12px; }

  /* On-page index */
  .ref-index {
    border: 1px solid var(--card-border);
    background: var(--card-bg);
    padding: 20px 24px 16px;
    margin-bottom: 48px;
    box-shadow: var(--shadow-card);
  }

  .ref-index-title {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin-bottom: 16px;
  }

  .ref-index-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 20px 32px;
  }

  .ref-index-group-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-primary);
    margin-bottom: 6px;
    padding-bottom: 4px;
    border-bottom: 1px solid var(--green);
  }

  .ref-index-group a {
    display: block;
    font-family: var(--font-sans);
    font-size: 12px;
    color: var(--text-secondary);
    text-decoration: none;
    padding: 2px 0;
    transition: color 0.12s;
  }

  .ref-index-group a:hover { color: var(--green); }

</style>

<div class="ref-page">
  <div class="ref-container">

    {{-- ============================================================ --}}
    {{--  HEADER                                                       --}}
    {{-- ============================================================ --}}
    <div class="ref-title">Component Reference</div>
    <div class="ref-subtitle">Signals Framework — Canonical Component Library</div>

    {{-- ============================================================ --}}
    {{--  ON-PAGE INDEX                                                --}}
    {{-- ============================================================ --}}
    <div class="ref-index">
      <div class="ref-index-title">On This Page</div>
      <div class="ref-index-grid">
        <div class="ref-index-group">
          <div class="ref-index-group-label">Layout</div>
          <a href="#page-header">Page Header</a>
          <a href="#panel">Panel</a>
          <a href="#multi-pane">Multi-Pane</a>
          <a href="#sidebar">Sidebar</a>
          <a href="#form-section">Form Section</a>
          <a href="#card">Card</a>
          <a href="#card-variants">Card Variants</a>
          <a href="#collapsible">Collapsible</a>
          <a href="#accordion">Accordion</a>
        </div>
        <div class="ref-index-group">
          <div class="ref-index-group-label">Navigation</div>
          <a href="#tabs">Tabs</a>
          <a href="#toolbar">Toolbar</a>
          <a href="#quick-filters">Quick Filters</a>
          <a href="#breadcrumb">Breadcrumb</a>
          <a href="#pagination">Pagination</a>
          <a href="#stepper">Stepper</a>
        </div>
        <div class="ref-index-group">
          <div class="ref-index-group-label">Buttons</div>
          <a href="#buttons">Buttons</a>
          <a href="#loading-button">Loading Button</a>
          <a href="#split-button">Split Button</a>
          <a href="#copy-button">Copy Button</a>
          <a href="#button-dropdown">Button Dropdown</a>
          <a href="#bulk-action-bar">Bulk Action Bar</a>
        </div>
        <div class="ref-index-group">
          <div class="ref-index-group-label">Form Inputs</div>
          <a href="#field">Field</a>
          <a href="#search-input">Search Input</a>
          <a href="#checkbox">Checkbox</a>
          <a href="#toggle">Toggle</a>
          <a href="#toggle-row">Toggle Row</a>
          <a href="#tag-input">Tag Input</a>
          <a href="#combobox">Combobox</a>
          <a href="#number-input">Number Input</a>
          <a href="#range-slider">Range Slider</a>
          <a href="#inline-edit">Inline Edit</a>
          <a href="#calendar">Calendar</a>
          <a href="#date-picker">Date Picker</a>
          <a href="#datetime-input">Datetime Input</a>
          <a href="#otp-input">OTP Input</a>
          <a href="#colour-picker">Colour Picker</a>
          <a href="#upload-zone">Upload Zone</a>
          <a href="#dropzone">Dropzone</a>
          <a href="#matrix">Matrix</a>
          <a href="#stars">Stars</a>
          <a href="#wysiwyg">WYSIWYG</a>
          <a href="#format-selector">Format Selector</a>
          <a href="#strategy-selector">Strategy Selector</a>
        </div>
        <div class="ref-index-group">
          <div class="ref-index-group-label">Data Display</div>
          <a href="#table">Table</a>
          <a href="#data-list">Data List</a>
          <a href="#status-cells">Status Cells</a>
          <a href="#badges">Badges</a>
          <a href="#toolbar-chips">Toolbar Chips</a>
          <a href="#status-badge">Status Badge</a>
          <a href="#avatar">Avatar</a>
          <a href="#sparkline">Sparkline</a>
          <a href="#datetime-display">Datetime Display</a>
          <a href="#stat-cards">Stat Cards</a>
          <a href="#bar-chart">Bar Chart</a>
          <a href="#qty-bar">Qty Bar</a>
          <a href="#grid-table">Grid Table</a>
          <a href="#kanban-board">Kanban Board</a>
          <a href="#tree-view">Tree View</a>
          <a href="#timeline">Timeline</a>
          <a href="#product-cell">Product Cell</a>
          <a href="#availability-bar">Availability Bar</a>
          <a href="#photo-gallery">Photo Gallery</a>
        </div>
        <div class="ref-index-group">
          <div class="ref-index-group-label">Overlays</div>
          <a href="#modal">Modal</a>
          <a href="#drawer">Drawer</a>
          <a href="#confirmation-dialog">Confirmation Dialog</a>
          <a href="#popover">Popover</a>
          <a href="#tooltip">Tooltip</a>
          <a href="#simple-tooltip">Simple Tooltip</a>
          <a href="#command-palette">Command Palette</a>
          <a href="#dropdown">Dropdown</a>
          <a href="#column-config">Column Config</a>
          <a href="#notification-center">Notification Center</a>
        </div>
        <div class="ref-index-group">
          <div class="ref-index-group-label">Feedback</div>
          <a href="#alert-banner">Alert / Banner</a>
          <a href="#toast-notification">Toast</a>
          <a href="#save-indicator">Save Indicator</a>
          <a href="#skeleton">Skeleton</a>
          <a href="#spinner">Spinner</a>
          <a href="#progress-bar">Progress Bar</a>
          <a href="#empty-state">Empty State</a>
          <a href="#unsaved-bar">Unsaved Bar</a>
        </div>
        <div class="ref-index-group">
          <div class="ref-index-group-label">Reference</div>
          <a href="#color-palette">Color Palette</a>
          <a href="#typography">Typography</a>
          <a href="#keyboard-hints">Keyboard Hints</a>
          <a href="#section-label">Section Label</a>
          <a href="#legend">Legend / Key</a>
          <a href="#summary-bar">Summary Bar</a>
          <a href="#version-tree">Version Tree</a>
          <a href="#event-stream">Event Stream</a>
          <a href="#state-diagram">State Diagram</a>
          <a href="#json-viewer">JSON Viewer</a>
          <a href="#parsed-view">Parsed View</a>
          <a href="#multiplier-table">Multiplier Table</a>
          <a href="#viz-buttons">Viz Buttons</a>
          <a href="#dispatch-urgency">Dispatch Urgency</a>
          <a href="#report-sidebar">Report Sidebar</a>
          <a href="#pin">Pin</a>
          <a href="#zoom-controls">Zoom Controls</a>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  1. PAGE HEADER                                               --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Page Header</div>
      <div class="ref-note">Use .s-page-header for the top of every list/detail page. Breadcrumb + title + metadata left, actions right.</div>
      <div class="ref-demo">
        <x-signals.page-header title="Opportunity Grid" style="padding: 0;">
          <x-slot:breadcrumbs>
            <a href="#">Opportunities</a>
            <span>/</span>
            <span>All Items</span>
          </x-slot:breadcrumbs>
          <x-slot:meta>
            <span class="s-badge s-badge-green"><span class="s-badge-dot"></span> Active</span>
            <span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4m8-4v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
              15 Jan - 28 Feb 2026
            </span>
            <span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              Acme Corp
            </span>
          </x-slot:meta>
          <x-slot:actions>
            <button class="s-btn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Export
            </button>
            <button class="s-btn s-btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              New Opportunity
            </button>
          </x-slot:actions>
        </x-signals.page-header>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  2. BUTTONS                                                   --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Buttons</div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Standard Variants</div>
        <div class="ref-demo">
          <div class="ref-row">
            <button class="s-btn">Default</button>
            <button class="s-btn s-btn-primary">Primary</button>
            <button class="s-btn s-btn-danger">Danger</button>
            <button class="s-btn s-btn-ghost">Ghost</button>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">With Icons</div>
        <div class="ref-demo">
          <div class="ref-row">
            <button class="s-btn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Add Item
            </button>
            <button class="s-btn s-btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              Save
            </button>
            <button class="s-btn s-btn-danger">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
              Delete
            </button>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Sizes</div>
        <div class="ref-demo">
          <div class="ref-row">
            <button class="s-btn s-btn-sm">Small</button>
            <button class="s-btn">Default</button>
            <button class="s-btn s-btn-lg">Large</button>
          </div>
          <div class="ref-spacer"></div>
          <div class="ref-row">
            <button class="s-btn s-btn-sm s-btn-primary">Small Primary</button>
            <button class="s-btn s-btn-primary">Default Primary</button>
            <button class="s-btn s-btn-lg s-btn-primary">Large Primary</button>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Outline Variants</div>
        <div class="ref-demo">
          <div class="ref-row">
            <button class="s-btn s-btn-outline-green">Green</button>
            <button class="s-btn s-btn-outline-blue">Blue</button>
            <button class="s-btn s-btn-outline-amber">Amber</button>
            <button class="s-btn s-btn-outline-red">Red</button>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Icon-Only & Block</div>
        <div class="ref-demo">
          <div class="ref-row">
            <button class="s-btn s-btn-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
            <button class="s-btn s-btn-icon s-btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </button>
            <button class="s-btn s-btn-sm s-btn-icon s-btn-ghost">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
            </button>
          </div>
          <div class="ref-spacer"></div>
          <div style="max-width: 300px;">
            <button class="s-btn s-btn-block s-btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              Full Width Button
            </button>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Disabled & Loading</div>
        <div class="ref-demo">
          <div class="ref-row">
            <button class="s-btn" disabled>Disabled</button>
            <button class="s-btn s-btn-primary" disabled>Disabled Primary</button>
            <button class="s-btn s-btn-primary s-btn-loading">
              <span class="s-btn-text">Saving</span>
              <span class="s-btn-spinner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.48-8.48l2.83-2.83M2 12h4m12 0h4m-3.93 7.07l-2.83-2.83M7.76 7.76L4.93 4.93"/></svg></span>
            </button>
            <button class="s-btn s-btn-loading">
              <span class="s-btn-text">Loading</span>
              <span class="s-btn-spinner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.48-8.48l2.83-2.83M2 12h4m12 0h4m-3.93 7.07l-2.83-2.83M7.76 7.76L4.93 4.93"/></svg></span>
            </button>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  3. TOOLBAR CHIPS                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Toolbar Chips</div>
      <div class="ref-note">Toggle chips for toolbar filters. Add .on class for active state.</div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Standard</div>
        <div class="ref-demo">
          <div class="ref-row">
            <span class="s-chip on">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
              Active
            </span>
            <span class="s-chip">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              Cancelled
            </span>
            <span class="s-chip">Quotation</span>
            <span class="s-chip on">Confirmed</span>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Color Variants</div>
        <div class="ref-demo">
          <div class="ref-row">
            <span class="s-chip s-chip-green on">Green</span>
            <span class="s-chip s-chip-amber on">Amber</span>
            <span class="s-chip s-chip-red on">Red</span>
            <span class="s-chip s-chip-blue on">Blue</span>
            <span class="s-chip s-chip-violet on">Violet</span>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">With Count & Removable</div>
        <div class="ref-demo">
          <div class="ref-row">
            <span class="s-chip on">Equipment <span class="s-chip-count">92</span></span>
            <span class="s-chip on">Services <span class="s-chip-count">34</span></span>
            <span class="s-chip on">
              Audio
              <span class="s-chip-remove">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </span>
            </span>
            <span class="s-chip on">
              Lighting
              <span class="s-chip-remove">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </span>
            </span>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  4. BADGES                                                    --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Badges</div>
      <div class="ref-note">Use .s-badge with a color modifier. Optional .s-badge-dot for status dot.</div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">All Colors (with dot)</div>
        <div class="ref-demo">
          <div class="ref-row">
            <span class="s-badge s-badge-green"><span class="s-badge-dot"></span> Active</span>
            <span class="s-badge s-badge-amber"><span class="s-badge-dot"></span> Pending</span>
            <span class="s-badge s-badge-red"><span class="s-badge-dot"></span> Overdue</span>
            <span class="s-badge s-badge-blue"><span class="s-badge-dot"></span> Processing</span>
            <span class="s-badge s-badge-violet"><span class="s-badge-dot"></span> Archived</span>
            <span class="s-badge s-badge-cyan"><span class="s-badge-dot"></span> Syncing</span>
            <span class="s-badge s-badge-navy"><span class="s-badge-dot"></span> System</span>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Without Dot</div>
        <div class="ref-demo">
          <div class="ref-row">
            <span class="s-badge s-badge-green">Confirmed</span>
            <span class="s-badge s-badge-amber">Draft</span>
            <span class="s-badge s-badge-red">Cancelled</span>
            <span class="s-badge s-badge-blue">In Progress</span>
            <span class="s-badge s-badge-violet">Archived</span>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Lifecycle</div>
        <div class="ref-demo">
          <div class="ref-row">
            <span class="s-badge s-badge-draft">Draft</span>
            <span class="s-badge s-badge-quote">Quotation</span>
            <span class="s-badge s-badge-order">Order</span>
            <span class="s-badge s-badge-cyan">Processing</span>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Outline & Count</div>
        <div class="ref-demo">
          <div class="ref-row">
            <span class="s-badge s-badge-outline s-badge-green">Outline Green</span>
            <span class="s-badge s-badge-outline s-badge-blue">Outline Blue</span>
            <span class="s-badge s-badge-outline s-badge-red">Outline Red</span>
            <span class="s-badge s-badge-outline s-badge-amber">Outline Amber</span>
          </div>
          <div class="ref-spacer"></div>
          <div class="ref-row">
            <span class="s-badge s-badge-green s-badge-count">5</span>
            <span class="s-badge s-badge-red s-badge-count">12</span>
            <span class="s-badge s-badge-blue s-badge-count">99+</span>
            <span class="s-badge s-badge-amber s-badge-count">3</span>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  5. STATUS CELLS                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Status Cells</div>
      <div class="ref-note">Compact inline status for use inside table cells. Slightly smaller than badges.</div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">All Colors</div>
        <div class="ref-demo">
          <div class="ref-row">
            <span class="s-status s-status-green"><span class="s-status-dot"></span> Confirmed</span>
            <span class="s-status s-status-amber"><span class="s-status-dot"></span> Provisional</span>
            <span class="s-status s-status-red"><span class="s-status-dot"></span> Cancelled</span>
            <span class="s-status s-status-blue"><span class="s-status-dot"></span> Sub-hired</span>
            <span class="s-status s-status-violet"><span class="s-status-dot"></span> Archived</span>
            <span class="s-status s-status-cyan"><span class="s-status-dot"></span> Syncing</span>
            <span class="s-status s-status-navy"><span class="s-status-dot"></span> System</span>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Outline Variant</div>
        <div class="ref-demo">
          <div class="ref-row">
            <span class="s-status s-status-outline s-status-green"><span class="s-status-dot"></span> Active</span>
            <span class="s-status s-status-outline s-status-amber"><span class="s-status-dot"></span> Warning</span>
            <span class="s-status s-status-outline s-status-red"><span class="s-status-dot"></span> Error</span>
            <span class="s-status s-status-outline s-status-blue"><span class="s-status-dot"></span> Info</span>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  6. TABS                                                      --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Tabs</div>
      <div class="ref-note">Underline tabs. Add .on class for active. Optional .s-tab-count for counts.</div>
      <div class="ref-demo">
        <x-signals.tabs>
          <button class="s-tab on">All Items <span class="s-tab-count">148</span></button>
          <button class="s-tab">Equipment <span class="s-tab-count">92</span></button>
          <button class="s-tab">Services <span class="s-tab-count">34</span></button>
          <button class="s-tab">Accessories <span class="s-tab-count">22</span></button>
        </x-signals.tabs>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  7. TABLE                                                     --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Table</div>
      <div class="ref-note">Use .s-table-wrap > .s-table. Mono font for IDs/refs/dates/amounts. Use .s-cell-link for clickable refs.</div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Standard</div>
        <div class="ref-demo" style="padding: 0; overflow: hidden;">
          <x-signals.table-wrap style="border: none; box-shadow: none;">
            <table class="s-table">
              <thead>
                <tr>
                  <th class="s-col-check"><x-signals.checkbox /></th>
                  <th class="sortable">Reference</th>
                  <th class="sortable">Subject</th>
                  <th class="sortable">Member</th>
                  <th class="sortable">Status</th>
                  <th class="sortable" style="text-align: right;">Total</th>
                  <th class="sortable">Starts</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="s-col-check"><x-signals.checkbox /></td>
                  <td><a class="s-cell-link" href="#">OPP-2026-0148</a></td>
                  <td>Summer Festival Main Stage</td>
                  <td>Acme Events Ltd</td>
                  <td><span class="s-status s-status-green"><span class="s-status-dot"></span> Confirmed</span></td>
                  <td class="s-cell-amount">12,450.00</td>
                  <td class="s-cell-mono">15 Mar 2026</td>
                </tr>
                <tr>
                  <td class="s-col-check"><x-signals.checkbox /></td>
                  <td><a class="s-cell-link" href="#">OPP-2026-0147</a></td>
                  <td>Corporate Awards Dinner</td>
                  <td>Globex Corporation</td>
                  <td><span class="s-status s-status-amber"><span class="s-status-dot"></span> Provisional</span></td>
                  <td class="s-cell-amount">8,200.00</td>
                  <td class="s-cell-mono">22 Mar 2026</td>
                </tr>
                <tr class="selected">
                  <td class="s-col-check"><x-signals.checkbox :checked="true" /></td>
                  <td><a class="s-cell-link" href="#">OPP-2026-0146</a></td>
                  <td>Wedding Reception — Smith</td>
                  <td>Jane Smith</td>
                  <td><span class="s-status s-status-red"><span class="s-status-dot"></span> Cancelled</span></td>
                  <td class="s-cell-amount">3,800.00</td>
                  <td class="s-cell-mono">28 Mar 2026</td>
                </tr>
              </tbody>
            </table>
          </x-signals.table-wrap>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Striped</div>
        <div class="ref-demo" style="padding: 0; overflow: hidden;">
          <x-signals.table-wrap style="border: none; box-shadow: none;">
            <table class="s-table s-table-striped">
              <thead>
                <tr><th>SKU</th><th>Product</th><th>Category</th><th style="text-align: right;">Qty</th></tr>
              </thead>
              <tbody>
                <tr><td class="s-cell-mono">MIC-SM58</td><td>Shure SM58</td><td>Audio</td><td class="s-cell-amount">24</td></tr>
                <tr><td class="s-cell-mono">SPK-EON615</td><td>JBL EON615</td><td>Audio</td><td class="s-cell-amount">12</td></tr>
                <tr><td class="s-cell-mono">LGT-PAR64</td><td>PAR64 LED</td><td>Lighting</td><td class="s-cell-amount">48</td></tr>
                <tr><td class="s-cell-mono">CAB-XLR5</td><td>XLR Cable 5m</td><td>Cables</td><td class="s-cell-amount">100</td></tr>
                <tr><td class="s-cell-mono">STD-MIC</td><td>Mic Stand</td><td>Stands</td><td class="s-cell-amount">30</td></tr>
              </tbody>
            </table>
          </x-signals.table-wrap>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Compact & Bordered</div>
        <div class="ref-demo" style="padding: 0; overflow: hidden;">
          <x-signals.table-wrap style="border: none; box-shadow: none;">
            <table class="s-table s-table-compact s-table-bordered">
              <thead>
                <tr><th>Day</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th></tr>
              </thead>
              <tbody>
                <tr><td class="s-cell-mono">PA System</td><td class="s-cell-amount">2</td><td class="s-cell-amount">4</td><td class="s-cell-amount">4</td><td class="s-cell-amount">2</td><td class="s-cell-amount">0</td></tr>
                <tr><td class="s-cell-mono">Lighting Rig</td><td class="s-cell-amount">1</td><td class="s-cell-amount">1</td><td class="s-cell-amount">3</td><td class="s-cell-amount">3</td><td class="s-cell-amount">1</td></tr>
                <tr><td class="s-cell-mono">Staging</td><td class="s-cell-amount">0</td><td class="s-cell-amount">1</td><td class="s-cell-amount">1</td><td class="s-cell-amount">0</td><td class="s-cell-amount">0</td></tr>
              </tbody>
            </table>
          </x-signals.table-wrap>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Inline Editing & Group Row</div>
        <div class="ref-demo" style="padding: 0; overflow: hidden;">
          <x-signals.table-wrap style="border: none; box-shadow: none;">
            <table class="s-table">
              <thead>
                <tr><th>Item</th><th style="text-align: right;">Qty</th><th style="text-align: right;">Rate</th><th style="text-align: right;">Total</th></tr>
              </thead>
              <tbody>
                <tr class="s-table-group-row"><td colspan="4">Audio Equipment</td></tr>
                <tr><td>Shure SM58 Microphone</td><td class="s-cell-edit"><input type="text" value="4"></td><td class="s-cell-amount">45.00</td><td class="s-cell-amount">180.00</td></tr>
                <tr><td>JBL EON615 Speaker</td><td class="s-cell-edit"><input type="text" value="2"></td><td class="s-cell-amount">120.00</td><td class="s-cell-amount">240.00</td></tr>
                <tr class="s-table-group-row"><td colspan="4">Lighting</td></tr>
                <tr><td>PAR64 LED</td><td class="s-cell-edit"><input type="text" value="8"></td><td class="s-cell-amount">25.00</td><td class="s-cell-amount">200.00</td></tr>
              </tbody>
            </table>
          </x-signals.table-wrap>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Sortable Headers with Indicators</div>
        <div class="ref-demo" style="padding: 0; overflow: hidden;">
          <x-signals.table-wrap style="border: none; box-shadow: none;">
            <table class="s-table">
              <thead>
                <tr>
                  <th class="s-col-check"><x-signals.checkbox /></th>
                  <th class="sortable sort-asc">Reference <span class="s-sort-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg></span></th>
                  <th class="sortable">Subject <span class="s-sort-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></span></th>
                  <th class="sortable">Category <span class="s-sort-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></span></th>
                  <th class="sortable">Status <span class="s-sort-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></span></th>
                  <th class="sortable sort-desc" style="text-align: right;">Total <span class="s-sort-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></span></th>
                  <th class="sortable">Date <span class="s-sort-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></span></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="s-col-check"><x-signals.checkbox /></td>
                  <td><a class="s-cell-link" href="#">OPP-2026-0148</a></td>
                  <td>Summer Festival Main Stage</td>
                  <td>Events</td>
                  <td><span class="s-status s-status-green"><span class="s-status-dot"></span> Confirmed</span></td>
                  <td class="s-cell-amount">12,450.00</td>
                  <td class="s-cell-mono">15 Mar 2026</td>
                </tr>
                <tr>
                  <td class="s-col-check"><x-signals.checkbox /></td>
                  <td><a class="s-cell-link" href="#">OPP-2026-0149</a></td>
                  <td>Corporate Awards Dinner</td>
                  <td>Corporate</td>
                  <td><span class="s-status s-status-amber"><span class="s-status-dot"></span> Provisional</span></td>
                  <td class="s-cell-amount">8,200.00</td>
                  <td class="s-cell-mono">22 Mar 2026</td>
                </tr>
                <tr>
                  <td class="s-col-check"><x-signals.checkbox /></td>
                  <td><a class="s-cell-link" href="#">OPP-2026-0150</a></td>
                  <td>Outdoor Cinema Screening</td>
                  <td>Entertainment</td>
                  <td><span class="s-status s-status-blue"><span class="s-status-dot"></span> Quotation</span></td>
                  <td class="s-cell-amount">4,680.00</td>
                  <td class="s-cell-mono">01 Apr 2026</td>
                </tr>
              </tbody>
            </table>
          </x-signals.table-wrap>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Filter Row</div>
        <div class="ref-demo" style="padding: 0; overflow: hidden;">
          <x-signals.table-wrap style="border: none; box-shadow: none;">
            <table class="s-table">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Product</th>
                  <th>Category</th>
                  <th>Status</th>
                  <th style="text-align: right;">Stock</th>
                </tr>
                <tr class="s-table-filter">
                  <td><input type="text" placeholder="SKU..."></td>
                  <td><input type="text" placeholder="Product name..."></td>
                  <td>
                    <select>
                      <option value="">All</option>
                      <option>Audio</option>
                      <option>Lighting</option>
                      <option>Staging</option>
                      <option>Cables</option>
                    </select>
                  </td>
                  <td>
                    <select>
                      <option value="">All</option>
                      <option>Active</option>
                      <option>Low Stock</option>
                      <option>Out of Stock</option>
                    </select>
                  </td>
                  <td></td>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="s-cell-mono">MIC-SM58</td>
                  <td>Shure SM58</td>
                  <td>Audio</td>
                  <td><span class="s-status s-status-green"><span class="s-status-dot"></span> Active</span></td>
                  <td class="s-cell-amount">24</td>
                </tr>
                <tr>
                  <td class="s-cell-mono">SPK-EON615</td>
                  <td>JBL EON615</td>
                  <td>Audio</td>
                  <td><span class="s-status s-status-green"><span class="s-status-dot"></span> Active</span></td>
                  <td class="s-cell-amount">12</td>
                </tr>
                <tr>
                  <td class="s-cell-mono">LGT-PAR64</td>
                  <td>PAR64 LED</td>
                  <td>Lighting</td>
                  <td><span class="s-status s-status-amber"><span class="s-status-dot"></span> Low Stock</span></td>
                  <td class="s-cell-amount">3</td>
                </tr>
                <tr>
                  <td class="s-cell-mono">STG-4X8</td>
                  <td>Stage Deck 4x8</td>
                  <td>Staging</td>
                  <td><span class="s-status s-status-red"><span class="s-status-dot"></span> Out of Stock</span></td>
                  <td class="s-cell-amount">0</td>
                </tr>
              </tbody>
            </table>
          </x-signals.table-wrap>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">With Footer Totals</div>
        <div class="ref-demo" style="padding: 0; overflow: hidden;">
          <x-signals.table-wrap style="border: none; box-shadow: none;">
            <table class="s-table">
              <thead>
                <tr><th>Item</th><th style="text-align: right;">Qty</th><th style="text-align: right;">Rate</th><th style="text-align: right;">Discount</th><th style="text-align: right;">Total</th></tr>
              </thead>
              <tbody>
                <tr><td>PA System (d&b E12)</td><td class="s-cell-amount">1</td><td class="s-cell-amount">850.00</td><td class="s-cell-amount">0.00</td><td class="s-cell-amount">850.00</td></tr>
                <tr><td>Stage Monitor (Wedge)</td><td class="s-cell-amount">4</td><td class="s-cell-amount">65.00</td><td class="s-cell-amount">-26.00</td><td class="s-cell-amount">234.00</td></tr>
                <tr><td>Radio Mic (Sennheiser)</td><td class="s-cell-amount">6</td><td class="s-cell-amount">45.00</td><td class="s-cell-amount">0.00</td><td class="s-cell-amount">270.00</td></tr>
                <tr><td>XLR Cable 10m</td><td class="s-cell-amount">12</td><td class="s-cell-amount">5.00</td><td class="s-cell-amount">0.00</td><td class="s-cell-amount">60.00</td></tr>
              </tbody>
              <tfoot>
                <tr class="s-table-footer">
                  <td colspan="3"></td>
                  <td style="text-align: right;">Subtotal</td>
                  <td class="s-cell-amount">1,414.00</td>
                </tr>
                <tr class="s-table-footer">
                  <td colspan="3"></td>
                  <td style="text-align: right;">VAT (20%)</td>
                  <td class="s-cell-amount">282.80</td>
                </tr>
                <tr class="s-table-footer">
                  <td colspan="3"></td>
                  <td style="text-align: right; font-weight: 700;">Total</td>
                  <td class="s-cell-amount" style="font-weight: 700;">1,696.80</td>
                </tr>
              </tfoot>
            </table>
          </x-signals.table-wrap>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  8. CHECKBOX                                                  --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Checkbox</div>
      <div class="ref-note">Custom checkbox. Add .checked class for checked state.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <x-signals.checkbox />
          <x-signals.checkbox :checked="true" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  9. SEARCH INPUT                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Search Input</div>
      <div class="ref-note">Use <code>&lt;x-signals.search&gt;</code> everywhere. Mono font, green focus border. For toolbars, tables, and standalone use.</div>
      <div class="ref-demo" style="display: flex; flex-direction: column; gap: 12px; max-width: 360px;">
        <x-signals.search placeholder="Search products..." />
        <x-signals.search placeholder="Filter members..." />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  10. TOOLBAR                                                  --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Toolbar</div>
      <div class="ref-note">Toolbar sits between page header and content. Use .s-toolbar-sep for dividers, .s-toolbar-right for right-aligned items.</div>
      <div class="ref-demo" style="padding: 0;">
        <x-signals.toolbar>
          <span class="s-chip on">All</span>
          <span class="s-chip">Equipment</span>
          <span class="s-chip">Services</span>
          <div class="s-toolbar-sep"></div>
          <span class="s-chip on">Active</span>
          <span class="s-chip">Archived</span>
          <x-slot:right>
            <x-signals.search placeholder="Filter..." />
            <button class="s-btn s-btn-sm">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/></svg>
              Columns
            </button>
          </x-slot:right>
        </x-signals.toolbar>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  11. DROPDOWN                                                 --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Dropdown / Popover</div>
      <div class="ref-note">Use .s-dropdown inside a relative container. Shown via Alpine x-show or display toggle.</div>
      <div class="ref-demo">
        <div style="position: relative; display: inline-block;">
          <x-signals.dropdown style="position: relative; display: block;">
            <div class="s-dropdown-group">Status</div>
            <div class="s-dropdown-item">
              <span class="s-status-dot" style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a;"></span>
              Confirmed
            </div>
            <div class="s-dropdown-item">
              <span class="s-status-dot" style="width: 6px; height: 6px; border-radius: 50%; background: var(--amber);"></span>
              Provisional
            </div>
            <hr class="s-dropdown-sep">
            <div class="s-dropdown-item">
              <span class="s-status-dot" style="width: 6px; height: 6px; border-radius: 50%; background: var(--red);"></span>
              Cancelled
            </div>
          </x-signals.dropdown>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  12. AVAILABILITY BAR                                         --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Availability Bar</div>
      <div class="ref-note">Compact availability indicator for table cells. Fill width = percentage available.</div>
      <div class="ref-demo">
        <div class="ref-col">
          <x-signals.avail label="20 / 20" :percent="100" color="green" />
          <x-signals.avail label="12 / 20" :percent="60" color="amber" />
          <x-signals.avail label="3 / 20" :percent="15" color="red" />
          <x-signals.avail label="0 / 20" :percent="0" color="red">
            <span class="s-conflict">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              Shortage
            </span>
          </x-signals.avail>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  13. PRODUCT CELL                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Product Cell</div>
      <div class="ref-note">Used in tables to show a product with thumbnail, name, and SKU.</div>
      <div class="ref-demo">
        <div class="ref-col" style="gap: 12px;">
          <x-signals.product-cell name="Shure SM58 Microphone" sku="SKU-MIC-SM58">
            <x-slot:thumb>&#x1f3a4;</x-slot:thumb>
          </x-signals.product-cell>
          <x-signals.product-cell name="JBL EON615 Speaker" sku="SKU-SPK-EON615">
            <x-slot:thumb>&#x1f50a;</x-slot:thumb>
          </x-signals.product-cell>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  14. CARD                                                     --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Card</div>
      <div class="ref-note">Generic card container. Use .s-card-header + .s-card-body for structured content.</div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Standard with Header Actions</div>
        <div class="ref-demo">
          <div style="max-width: 400px;">
            <x-signals.card title="Recent Activity">
              <x-slot:headerActions>
                <button class="s-btn s-btn-sm s-btn-ghost">View All</button>
              </x-slot:headerActions>
              <p style="color: var(--text-secondary); font-size: 12px; margin: 0;">Card body content goes here. Use for settings panels, detail views, widgets, etc.</p>
            </x-signals.card>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">With Footer</div>
        <div class="ref-demo">
          <div style="max-width: 400px;">
            <x-signals.card title="Confirm Dispatch">
              <p style="color: var(--text-secondary); font-size: 13px; margin: 0; font-family: var(--font-sans);">12 items will be dispatched to Acme Events Ltd. This action cannot be undone.</p>
              <x-slot:footer>
                <button class="s-btn s-btn-sm">Cancel</button>
                <button class="s-btn s-btn-sm s-btn-primary">Dispatch</button>
              </x-slot:footer>
            </x-signals.card>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Cover Image & Horizontal</div>
        <div class="ref-demo" style="display: flex; gap: 16px; flex-wrap: wrap;">
          <div class="s-card" style="width: 200px;">
            <div class="s-card-cover" style="background: linear-gradient(135deg, var(--s-green-bg), var(--s-blue-bg)); display: flex; align-items: center; justify-content: center; font-size: 32px;">🎤</div>
            <div class="s-card-body">
              <span class="s-card-title" style="display: block; margin-bottom: 4px;">Audio Pack</span>
              <span style="font-family: var(--font-sans); font-size: 12px; color: var(--text-muted);">8 items · £450/day</span>
            </div>
          </div>
          <div class="s-card s-card-horizontal" style="width: 360px;">
            <div class="s-card-cover" style="background: linear-gradient(135deg, var(--s-amber-bg), var(--s-violet-bg)); display: flex; align-items: center; justify-content: center; font-size: 28px;">📦</div>
            <div class="s-card-body">
              <span class="s-card-title" style="display: block; margin-bottom: 4px;">Staging Package</span>
              <span style="font-family: var(--font-sans); font-size: 12px; color: var(--text-muted);">24 items across 3 categories. Includes assembly.</span>
            </div>
          </div>
        </div>
      </div>

      <div class="ref-subsection">
        <div class="ref-subsection-title">Stat Card</div>
        <div class="ref-demo">
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; max-width: 600px;">
            <div class="s-card">
              <div class="s-card-body" style="text-align: center; padding: 16px;">
                <div style="font-family: var(--font-mono); font-size: 28px; font-weight: 700; color: var(--green);">148</div>
                <div style="font-family: var(--font-display); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); margin-top: 4px;">Active Opportunities</div>
              </div>
            </div>
            <div class="s-card">
              <div class="s-card-body" style="text-align: center; padding: 16px;">
                <div style="font-family: var(--font-mono); font-size: 28px; font-weight: 700; color: var(--blue);">£52.4k</div>
                <div style="font-family: var(--font-display); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); margin-top: 4px;">Revenue This Month</div>
              </div>
            </div>
            <div class="s-card">
              <div class="s-card-body" style="text-align: center; padding: 16px;">
                <div style="font-family: var(--font-mono); font-size: 28px; font-weight: 700; color: var(--amber);">23</div>
                <div style="font-family: var(--font-display); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); margin-top: 4px;">Pending Quotes</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  15. TOAST                                                    --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Toast Notification</div>
      <div class="ref-note">Fixed-position toast. Add .visible class to show. Typically used for save confirmations.</div>
      <div class="ref-demo">
        <x-signals.toast message="Changes saved successfully" style="position: relative; opacity: 1; transform: none; pointer-events: all; left: 0;">
          <x-slot:icon>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          </x-slot:icon>
        </x-signals.toast>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  16. SAVE INDICATOR                                           --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Save Indicator</div>
      <div class="ref-note">Inline pulsing indicator for autosave confirmation. Typically positioned top-right.</div>
      <div class="ref-demo">
        <x-signals.save-indicator text="Saved" />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  17. KEYBOARD HINTS                                           --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Keyboard Hints</div>
      <div class="ref-demo">
        <div class="ref-row">
          <span class="s-kbd-hint"><span class="s-kbd">Esc</span> Close</span>
          <span class="s-kbd-hint"><span class="s-kbd">&#8984;S</span> Save</span>
          <span class="s-kbd-hint"><span class="s-kbd">&#8593;&#8595;</span> Navigate</span>
          <span class="s-kbd-hint"><span class="s-kbd">Enter</span> Select</span>
          <span class="s-kbd-hint"><span class="s-kbd">Tab</span> Next Cell</span>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  18. BULK ACTION BAR                                          --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Bulk Action Bar</div>
      <div class="ref-note">Fixed bottom bar when items are selected. Uses .s-bulk-btn with SVG icons. Add .visible class to show.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.bulk-bar :count="3">
          <button class="s-bulk-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Status
          </button>
          <button class="s-bulk-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export
          </button>
          <button class="s-bulk-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="0"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            Duplicate
          </button>
          <button class="s-bulk-btn s-bulk-btn-danger">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            Delete
          </button>
        </x-signals.bulk-bar>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  19. SUMMARY BAR                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Summary Bar</div>
      <div class="ref-note">Compact footer showing aggregate stats. Fixed at bottom of scrollable content.</div>
      <div class="ref-demo" style="padding: 0;">
        <x-signals.summary-bar>
          <span>Products: <span class="s-summary-val">24</span></span>
          <span>Available: <span class="s-summary-ok">18</span></span>
          <span>Low Stock: <span class="s-summary-alert">4</span></span>
          <span>Out of Stock: <span class="s-summary-alert">2</span></span>
        </x-signals.summary-bar>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  20. LEGEND                                                   --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Legend / Key</div>
      <div class="ref-note">Use below availability matrices or any color-coded visualisation.</div>
      <div class="ref-demo" style="padding: 0;">
        <x-signals.legend label="Key:">
          <span class="s-legend-item"><span class="s-legend-swatch s-legend-ok"></span> Available</span>
          <span class="s-legend-item"><span class="s-legend-swatch s-legend-warn"></span> Low</span>
          <span class="s-legend-item"><span class="s-legend-swatch s-legend-crit"></span> Out of Stock</span>
          <span class="s-legend-item"><span class="s-legend-swatch s-legend-na"></span> N/A</span>
        </x-signals.legend>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  21. TOOLTIP                                                  --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Tooltip</div>
      <div class="ref-note">Fixed-position tooltip shown on hover. Position via JS. Add .visible class to show.</div>
      <div class="ref-demo">
        <x-signals.tooltip title="Shure SM58" style="position: relative; opacity: 1; pointer-events: all; display: inline-block;">
          <div class="s-tooltip-row"><span class="s-tooltip-label">Stock</span> <span class="s-tooltip-val">20</span></div>
          <div class="s-tooltip-row"><span class="s-tooltip-label">Allocated</span> <span class="s-tooltip-val">14</span></div>
          <hr class="s-tooltip-sep">
          <div class="s-tooltip-row"><span class="s-tooltip-label">Available</span> <span class="s-tooltip-val" style="color: #16a34a;">6</span></div>
        </x-signals.tooltip>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  22. EMPTY STATE                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Empty State</div>
      <div class="ref-demo">
        <x-signals.empty icon="&#x1f50d;" title="No results found" description="Try adjusting your search or filter criteria" />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  23. SECTION LABEL                                            --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Section Label</div>
      <div class="ref-note">Mono uppercase label used for grouping content. Consistent across all views.</div>
      <div class="ref-demo">
        <div class="ref-col" style="gap: 12px;">
          <span class="s-section-label">General Settings</span>
          <span class="s-section-label">Notification Channels</span>
          <span class="s-section-label">Custom Fields</span>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  24. COLOR PALETTE                                            --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Color Palette</div>
      <div class="ref-note">All status/semantic colors available. These map to --s-{color}-bg and --s-{color}-bdr tokens.</div>
      <div class="ref-demo">
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px;">
          <div style="text-align: center;">
            <div style="height: 40px; background: var(--green); margin-bottom: 4px;"></div>
            <span class="s-section-label">Green</span>
          </div>
          <div style="text-align: center;">
            <div style="height: 40px; background: var(--amber); margin-bottom: 4px;"></div>
            <span class="s-section-label">Amber</span>
          </div>
          <div style="text-align: center;">
            <div style="height: 40px; background: var(--red); margin-bottom: 4px;"></div>
            <span class="s-section-label">Red</span>
          </div>
          <div style="text-align: center;">
            <div style="height: 40px; background: var(--blue); margin-bottom: 4px;"></div>
            <span class="s-section-label">Blue</span>
          </div>
          <div style="text-align: center;">
            <div style="height: 40px; background: var(--violet); margin-bottom: 4px;"></div>
            <span class="s-section-label">Violet</span>
          </div>
        </div>
        <div class="ref-spacer"></div>
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px;">
          <div style="text-align: center;">
            <div style="height: 40px; background: var(--navy); margin-bottom: 4px;"></div>
            <span class="s-section-label">Navy</span>
          </div>
          <div style="text-align: center;">
            <div style="height: 40px; background: var(--navy-mid); margin-bottom: 4px;"></div>
            <span class="s-section-label">Navy Mid</span>
          </div>
          <div style="text-align: center;">
            <div style="height: 40px; background: var(--navy-light); margin-bottom: 4px;"></div>
            <span class="s-section-label">Navy Light</span>
          </div>
          <div style="text-align: center;">
            <div style="height: 40px; background: var(--grey); margin-bottom: 4px;"></div>
            <span class="s-section-label">Grey</span>
          </div>
          <div style="text-align: center;">
            <div style="height: 40px; background: var(--grey-light); margin-bottom: 4px;"></div>
            <span class="s-section-label">Grey Light</span>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  25. TYPOGRAPHY                                               --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Typography</div>
      <div class="ref-demo">
        <div class="ref-col" style="gap: 16px;">
          <div>
            <div style="font-family: var(--font-display); font-size: 20px; font-weight: 700; letter-spacing: -0.03em; color: var(--text-primary);">Page Title — Chakra Petch 700</div>
            <div class="s-section-label" style="margin-top: 2px;">font-family: var(--font-display); font-size: 20px; font-weight: 700;</div>
          </div>
          <div>
            <div style="font-family: var(--font-display); font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-primary);">Section Title — Chakra Petch 700 Uppercase</div>
            <div class="s-section-label" style="margin-top: 2px;">font-family: var(--font-display); font-size: 14px; font-weight: 700; text-transform: uppercase;</div>
          </div>
          <div>
            <div style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-primary);">Button / Tab Label — Chakra Petch 600</div>
            <div class="s-section-label" style="margin-top: 2px;">font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase;</div>
          </div>
          <div>
            <div style="font-family: var(--font-sans); font-size: 13px; color: var(--text-primary);">Body / Data Text — System Sans 400</div>
            <div class="s-section-label" style="margin-top: 2px;">font-family: var(--font-sans); font-size: 13px;</div>
          </div>
          <div>
            <div style="font-family: var(--font-mono); font-size: 9px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted);">Label / Section — Martian Mono 500 Uppercase</div>
            <div class="s-section-label" style="margin-top: 2px;">font-family: var(--font-mono); font-size: 9px; font-weight: 500; text-transform: uppercase;</div>
          </div>
        </div>
      </div>
    </div>


    {{-- ============================================================ --}}
    {{--  26. VERSION TREE                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Version Tree</div>
      <div class="ref-note">Hierarchical quote version tree with active/selected states. From opportunity-lifecycle.</div>
      <div class="ref-demo">
        <x-signals.version-tree>
          <div class="s-vt-node s-vt-node-active">
            <div class="s-vt-node-header">
              <span class="s-vt-version-label">v3</span>
              <span class="s-vt-version-name">Final revision</span>
              <span class="s-vt-confirmed-marker">CONFIRMED</span>
              <span class="s-vt-version-total">$12,450.00</span>
            </div>
            <div class="s-vt-version-date">15 Mar 2026</div>
          </div>
          <div class="s-vt-children">
            <div class="s-vt-node">
              <div class="s-vt-node-header">
                <span class="s-vt-version-label">v2</span>
                <span class="s-vt-version-name">Added lighting</span>
                <span class="s-vt-version-total">$10,800.00</span>
              </div>
              <div class="s-vt-version-date">10 Mar 2026</div>
            </div>
            <div class="s-vt-node">
              <div class="s-vt-node-header">
                <span class="s-vt-version-label">v1</span>
                <span class="s-vt-version-name">Initial quote</span>
                <span class="s-vt-version-total">$8,200.00</span>
              </div>
              <div class="s-vt-version-date">05 Mar 2026</div>
            </div>
          </div>
        </x-signals.version-tree>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  27. PANEL                                                    --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Panel</div>
      <div class="ref-note">Card panel with header/title/body. From opportunity-lifecycle.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.panel title="Event Stream" style="border: none; box-shadow: none;">
          <x-slot:headerActions>
            <button class="s-btn s-btn-sm">View All</button>
          </x-slot:headerActions>
          <span style="font-size: 12px; color: var(--text-muted);">Panel body content goes here.</span>
        </x-signals.panel>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  28. EVENT STREAM                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Event Stream</div>
      <div class="ref-note">Timeline-style event rows with color-coded left borders. From opportunity-lifecycle.</div>
      <div class="ref-demo" style="padding: 0;">
        <x-signals.event-row name="OpportunityCreated" actor="Sarah Chen" time="2 min ago" border="create" />
        <x-signals.event-row name="StatusChanged" actor="System" time="5 min ago" border="status">
          <x-slot:payload><span class="s-es-payload-key">from:</span> Draft  <span class="s-es-payload-key">to:</span> Quotation</x-slot:payload>
        </x-signals.event-row>
        <x-signals.event-row name="ItemAdded" actor="Mike Ross" time="1 hour ago" border="update" />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  29. STATE DIAGRAM                                            --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">State Diagram</div>
      <div class="ref-note">Three-column state machine visualization. From opportunity-lifecycle.</div>
      <div class="ref-demo">
        <x-signals.state-diagram>
          <div class="s-sm-column s-sm-column-draft">
            <div class="s-sm-col-header s-sm-col-header-draft">Draft</div>
            <div class="s-sm-col-body">
              <div class="s-sm-status-box">
                <div class="s-sm-status-name">Draft</div>
                <div class="s-sm-status-value">new</div>
              </div>
            </div>
          </div>
          <div class="s-sm-column s-sm-column-quote">
            <div class="s-sm-col-header s-sm-col-header-quote">Quotation</div>
            <div class="s-sm-col-body">
              <div class="s-sm-status-box s-sm-status-box-active">
                <div class="s-sm-status-name">Provisional</div>
                <div class="s-sm-status-value">quotation</div>
              </div>
            </div>
          </div>
          <div class="s-sm-column s-sm-column-order">
            <div class="s-sm-col-header s-sm-col-header-order">Order</div>
            <div class="s-sm-col-body">
              <div class="s-sm-status-box">
                <div class="s-sm-status-name">Confirmed</div>
                <div class="s-sm-status-value">order</div>
              </div>
            </div>
          </div>
        </x-signals.state-diagram>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  30. STAT CARDS                                               --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Stat Cards</div>
      <div class="ref-note">Icon + label + value stat cards in a 4-column grid. From field-registry.</div>
      <div class="ref-demo">
        <x-signals.stat-grid>
          <x-signals.stat-card label="Total Fields" value="142" color="blue">
            <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></x-slot:icon>
          </x-signals.stat-card>
          <x-signals.stat-card label="Core" value="98" color="green">
            <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></x-slot:icon>
          </x-signals.stat-card>
          <x-signals.stat-card label="Computed" value="28" color="amber">
            <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></x-slot:icon>
          </x-signals.stat-card>
          <x-signals.stat-card label="Custom" value="16" color="violet">
            <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></x-slot:icon>
          </x-signals.stat-card>
        </x-signals.stat-grid>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  31. BUTTON DROPDOWN                                          --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Button Dropdown</div>
      <div class="ref-note">Custom styled select element with dropdown arrow. From field-registry.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <select class="s-select">
            <option>Opportunity</option>
            <option>Member</option>
            <option>Product</option>
            <option>Invoice</option>
          </select>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  32. MULTI-PANE                                               --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Multi-Pane</div>
      <div class="ref-note">Sidebar + editor layout. Active item is green. From permissions.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.multi-pane style="gap: 0;">
          <x-slot:sidebar>
            <div class="s-multi-pane-sidebar-header">
              <span class="s-multi-pane-sidebar-title">Roles</span>
            </div>
            <div class="s-multi-pane-item active">
              <span class="s-multi-pane-item-name">Admin</span>
            </div>
            <div class="s-multi-pane-item">
              <span class="s-multi-pane-item-name">Manager</span>
            </div>
            <div class="s-multi-pane-item">
              <span class="s-multi-pane-item-name">Operator</span>
            </div>
          </x-slot:sidebar>
          <span style="font-size: 12px; color: var(--text-muted);">Editor content area</span>
        </x-signals.multi-pane>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  33. TOGGLE                                                   --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Toggle</div>
      <div class="ref-note">Toggle switch. Add .on class for active state. From permissions.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <x-signals.toggle />
          <x-signals.toggle :on="true" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  34. SIDEBAR                                                  --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Sidebar</div>
      <div class="ref-note">Navigation sidebar with grouped items and badges. From custom-views.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.sidebar title="Views" style="width: 100%; max-width: 280px; border: none; box-shadow: none;">
          <div class="s-sidebar-group-label">System</div>
          <div class="s-sidebar-item active">
            <span class="s-sidebar-item-name">All Opportunities</span>
            <span class="s-sidebar-item-badge s-sidebar-item-badge-system">System</span>
          </div>
          <div class="s-sidebar-item">
            <span class="s-sidebar-item-name">Active Orders</span>
            <span class="s-sidebar-item-badge s-sidebar-item-badge-system">System</span>
          </div>
          <div class="s-sidebar-group-label">Personal</div>
          <div class="s-sidebar-item">
            <span class="s-sidebar-item-name">My Quotes</span>
            <span class="s-sidebar-item-badge s-sidebar-item-badge-personal">Personal</span>
          </div>
        </x-signals.sidebar>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  35. COLUMN CONFIG                                            --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Column Config</div>
      <div class="ref-note">Draggable column config with grip handles and toggles. From custom-views.</div>
      <div class="ref-demo">
        <x-signals.column-config title="Visible Columns" style="max-width: 400px;">
          <div class="s-column-config-item">
            <svg class="s-column-config-handle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            <span class="s-column-config-name">Reference</span>
            <x-signals.toggle :on="true" style="margin-left: auto;" />
          </div>
          <div class="s-column-config-item">
            <svg class="s-column-config-handle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            <span class="s-column-config-name">Subject</span>
            <x-signals.toggle :on="true" style="margin-left: auto;" />
          </div>
          <div class="s-column-config-item">
            <svg class="s-column-config-handle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            <span class="s-column-config-name">Status</span>
            <x-signals.toggle style="margin-left: auto;" />
          </div>
        </x-signals.column-config>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  36. UNSAVED BAR                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Unsaved Bar</div>
      <div class="ref-note">Fixed notification bar for unsaved changes. Navy background, pulsing amber dot. From settings-admin.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.unsaved-bar>
          <button class="s-btn s-btn-sm" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); color: #fff;">Discard</button>
          <button class="s-btn s-btn-sm s-btn-primary">Save Changes</button>
        </x-signals.unsaved-bar>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  37. TOGGLE ROW                                               --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Toggle Row</div>
      <div class="ref-note">Label + description + toggle in a row. From settings-admin.</div>
      <div class="ref-demo">
        <x-signals.toggle-row label="Enable two-factor authentication" description="Require 2FA for all admin accounts">
          <x-signals.toggle :on="true" />
        </x-signals.toggle-row>
        <x-signals.toggle-row label="Allow public registration" description="Users can create their own accounts">
          <x-signals.toggle />
        </x-signals.toggle-row>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  38. COLOUR PICKER                                            --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Colour Picker</div>
      <div class="ref-note">Swatch + hex value display. From settings-admin.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <x-signals.colour-picker color="#059669" />
          <x-signals.colour-picker color="#2563eb" />
          <x-signals.colour-picker color="#0f172a" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  39. UPLOAD ZONE                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Upload Zone</div>
      <div class="ref-note">Small dashed upload area for logos/icons. From settings-admin.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <x-signals.upload-zone label="Upload logo">
            <x-slot:icon><svg class="s-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></x-slot:icon>
          </x-signals.upload-zone>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  40. MATRIX                                                   --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Matrix</div>
      <div class="ref-note">Grid table for tax rates, permission matrices, etc. From settings-admin.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.matrix style="border: none;">
          <table>
            <thead>
              <tr>
                <th style="text-align: left;">Region</th>
                <th>Standard</th>
                <th>Reduced</th>
                <th>Zero</th>
              </tr>
            </thead>
            <tbody>
              <tr><td style="text-align: left; font-family: var(--font-display); font-weight: 600; color: var(--text-primary);">United Kingdom</td><td>20%</td><td>5%</td><td>0%</td></tr>
              <tr><td style="text-align: left; font-family: var(--font-display); font-weight: 600; color: var(--text-primary);">Germany</td><td>19%</td><td>7%</td><td>0%</td></tr>
              <tr><td style="text-align: left; font-family: var(--font-display); font-weight: 600; color: var(--text-primary);">France</td><td>20%</td><td>5.5%</td><td>0%</td></tr>
            </tbody>
          </table>
        </x-signals.matrix>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  41. STATUS BADGE                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Status Badge</div>
      <div class="ref-note">Compact status indicators (lighter weight than s-badge). From plugin-system.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <span class="s-status-badge s-status-active">Active</span>
          <span class="s-status-badge s-status-disabled">Disabled</span>
          <span class="s-status-badge s-status-error">Error</span>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  42. STARS                                                    --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Stars</div>
      <div class="ref-note">Star rating display. From plugin-system.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <x-signals.stars :rating="4" :max="5" />
          <span style="font-size: 11px; color: var(--text-muted);">4.0 / 5</span>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  43. JSON VIEWER                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">JSON Viewer</div>
      <div class="ref-note">Syntax-highlighted JSON display on dark background. From plugin-system.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.json-viewer>{
  <span class="s-json-key">"name"</span>: <span class="s-json-string">"signals/analytics"</span>,
  <span class="s-json-key">"version"</span>: <span class="s-json-string">"1.2.0"</span>,
  <span class="s-json-key">"enabled"</span>: <span class="s-json-bool">true</span>,
  <span class="s-json-key">"priority"</span>: <span class="s-json-number">10</span>,
  <span class="s-json-key">"config"</span>: <span class="s-json-null">null</span>
}</x-signals.json-viewer>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  44. PARSED VIEW                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Parsed View</div>
      <div class="ref-note">Collapsible key-value sections on dark background. From plugin-system.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.parsed-view title="Parsed View">
          <div class="s-parsed-section">
            <div class="s-parsed-section-header">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              <span class="s-parsed-section-name">Metadata</span>
            </div>
            <div class="s-parsed-section-body">
              <div class="s-parsed-kv">
                <span class="s-parsed-kv-key">Name</span>
                <span class="s-parsed-kv-val">signals/analytics</span>
                <span class="s-parsed-kv-key">Version</span>
                <span class="s-parsed-kv-val">1.2.0</span>
                <span class="s-parsed-kv-key">Author</span>
                <span class="s-parsed-kv-val">Signals Team</span>
              </div>
            </div>
          </div>
        </x-signals.parsed-view>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  45. FORM SECTION                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Form Section</div>
      <div class="ref-note">Panel with header icon, title and body. From rate-engine.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.form-section title="Period Settings" style="border: none; box-shadow: none;">
          <x-slot:icon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </x-slot:icon>
          <span style="font-size: 12px; color: var(--text-muted);">Form fields go here.</span>
        </x-signals.form-section>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  46. MULTIPLIER TABLE                                         --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Multiplier Table</div>
      <div class="ref-note">Editable table with input cells for rate multipliers. From rate-engine.</div>
      <div class="ref-demo">
        <table class="s-multiplier-table" style="max-width: 500px;">
          <thead>
            <tr>
              <th>Period</th>
              <th>Days</th>
              <th style="text-align: right;">Multiplier</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="font-family: var(--font-display); font-size: 12px; font-weight: 600; color: var(--text-primary);">1 Day</td>
              <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);">1</td>
              <td style="text-align: right;"><input class="s-multiplier-input" type="text" value="1.00"></td>
            </tr>
            <tr>
              <td style="font-family: var(--font-display); font-size: 12px; font-weight: 600; color: var(--text-primary);">2 Days</td>
              <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);">2</td>
              <td style="text-align: right;"><input class="s-multiplier-input" type="text" value="1.75"></td>
            </tr>
            <tr>
              <td style="font-family: var(--font-display); font-size: 12px; font-weight: 600; color: var(--text-primary);">1 Week</td>
              <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);">7</td>
              <td style="text-align: right;"><input class="s-multiplier-input" type="text" value="3.00"></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  47. STRATEGY SELECTOR                                        --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Strategy Selector</div>
      <div class="ref-note">Radio-style card selector with icons. From rate-engine.</div>
      <div class="ref-demo">
        <div class="s-strategy-grid">
          <x-signals.strategy-card name="Period" description="Charge per time period" :selected="true">
            <x-slot:icon><div class="s-strategy-icon s-strategy-icon-period"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></x-slot:icon>
          </x-signals.strategy-card>
          <x-signals.strategy-card name="Usage" description="Charge per use">
            <x-slot:icon><div class="s-strategy-icon s-strategy-icon-usage"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div></x-slot:icon>
          </x-signals.strategy-card>
          <x-signals.strategy-card name="Fixed" description="One-time charge">
            <x-slot:icon><div class="s-strategy-icon s-strategy-icon-fixed"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></x-slot:icon>
          </x-signals.strategy-card>
          <x-signals.strategy-card name="Hybrid" description="Combined strategy">
            <x-slot:icon><div class="s-strategy-icon s-strategy-icon-hybrid"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg></div></x-slot:icon>
          </x-signals.strategy-card>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  48. BAR CHART                                                --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Bar Chart</div>
      <div class="ref-note">Horizontal bar chart with labels. From shortage-resolution.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.bar-chart style="border: none;">
          <div class="s-bar-row">
            <span class="s-bar-label">PA Systems</span>
            <div class="s-bar-track"><div class="s-bar-fill s-bar-fill-red" style="width: 75%;"><span class="s-bar-value">12</span></div></div>
          </div>
          <div class="s-bar-row">
            <span class="s-bar-label">Stage Decks</span>
            <div class="s-bar-track"><div class="s-bar-fill s-bar-fill-amber" style="width: 50%;"><span class="s-bar-value">8</span></div></div>
          </div>
          <div class="s-bar-row">
            <span class="s-bar-label">Cables</span>
            <div class="s-bar-track"><div class="s-bar-fill s-bar-fill-green" style="width: 25%;"><span class="s-bar-value">4</span></div></div>
          </div>
        </x-signals.bar-chart>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  49. QTY BAR                                                  --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Qty Bar</div>
      <div class="ref-note">Quantity availability indicator. Green fill on red background. From shortage-resolution.</div>
      <div class="ref-demo">
        <div style="max-width: 200px;">
          <x-signals.qty-bar label="6 of 10 available" :percent="60" />
          <div class="ref-spacer"></div>
          <x-signals.qty-bar label="2 of 10 available" :percent="20" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  50. DISPATCH URGENCY                                         --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Dispatch Urgency</div>
      <div class="ref-note">Color-coded urgency text. From shortage-resolution.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <span class="s-dispatch-urgency s-dispatch-critical">Tomorrow</span>
          <span class="s-dispatch-urgency s-dispatch-warning">3 days</span>
          <span class="s-dispatch-urgency s-dispatch-normal">2 weeks</span>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  51. GRID TABLE                                               --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Grid Table</div>
      <div class="ref-note">CSS Grid-based table layout (not HTML table). From shortage-resolution.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.grid-table style="border: none;">
          <div class="s-grid-table-header" style="grid-template-columns: 1fr 100px 100px;">
            <div class="s-grid-th">Product</div>
            <div class="s-grid-th">Qty</div>
            <div class="s-grid-th">Status</div>
          </div>
          <div class="s-grid-table-row" style="grid-template-columns: 1fr 100px 100px;">
            <div class="s-grid-td" style="font-weight: 600;">JBL VTX V25-II</div>
            <div class="s-grid-td s-grid-td-mono">12</div>
            <div class="s-grid-td"><span class="s-badge s-badge-red">Shortage</span></div>
          </div>
          <div class="s-grid-table-row" style="grid-template-columns: 1fr 100px 100px;">
            <div class="s-grid-td" style="font-weight: 600;">Chauvet Rogue R2</div>
            <div class="s-grid-td s-grid-td-mono">8</div>
            <div class="s-grid-td"><span class="s-badge s-badge-green">Available</span></div>
          </div>
        </x-signals.grid-table>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  52. WYSIWYG                                                  --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">WYSIWYG</div>
      <div class="ref-note">Toolbar with icon buttons + textarea. From notification-admin.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.editor placeholder="Enter template content..." style="border: none;">
          <x-slot:toolbar>
            <button class="s-toolbar-btn" title="Bold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/></svg></button>
            <button class="s-toolbar-btn" title="Italic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg></button>
            <div class="s-toolbar-sep"></div>
            <button class="s-toolbar-btn" title="Link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></button>
            <button class="s-toolbar-btn" title="List"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></button>
          </x-slot:toolbar>
        </x-signals.editor>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  53. VIZ BUTTONS                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Viz Buttons</div>
      <div class="ref-note">Toggle button group for switching visualization types. From reporting.</div>
      <div class="ref-demo">
        <x-signals.viz-btns>
          <button class="s-viz-btn active" title="Table"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg></button>
          <button class="s-viz-btn" title="Bar Chart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></button>
          <button class="s-viz-btn" title="Line Chart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></button>
          <button class="s-viz-btn" title="Pie Chart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg></button>
        </x-signals.viz-btns>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  54. REPORT SIDEBAR                                           --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Report Sidebar</div>
      <div class="ref-note">Grouped sidebar list with source badges and pin buttons. From reporting.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.report-sidebar style="width: 100%; max-width: 300px; border: none;">
          <div class="s-report-group-label">Revenue</div>
          <div class="s-report-item active">
            <span class="s-report-name">Revenue by Month</span>
            <span class="s-report-source">Opp</span>
            <button class="s-pin-btn pinned"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></button>
          </div>
          <div class="s-report-item">
            <span class="s-report-name">Revenue by Category</span>
            <span class="s-report-source">Opp</span>
            <button class="s-pin-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></button>
          </div>
          <div class="s-report-group-label">Inventory</div>
          <div class="s-report-item">
            <span class="s-report-name">Stock Utilisation</span>
            <span class="s-report-source">Inv</span>
            <button class="s-pin-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></button>
          </div>
        </x-signals.report-sidebar>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  55. PIN                                                      --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Pin</div>
      <div class="ref-note">Pin/favourite toggle button. Amber when active. From reporting.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <button class="s-pin-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></button>
          <button class="s-pin-btn pinned"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></button>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  56. COLLAPSIBLE                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Collapsible</div>
      <div class="ref-note">Accordion panel with chevron indicator. From reporting.</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.collapsible label="Report Builder" :open="true" style="border: none; box-shadow: none;">
          <span style="font-size: 12px; color: var(--text-muted);">Collapsible content goes here.</span>
        </x-signals.collapsible>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  57. ZOOM CONTROLS                                            --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Zoom Controls</div>
      <div class="ref-note">Zoom in/out with level display.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <x-signals.zoom level="100%" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  58. STEPPER                                                  --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Stepper</div>
      <div class="ref-note">Multi-step progress indicator with done/active/pending states. From import-export.</div>
      <div class="ref-demo">
        <x-signals.stepper style="padding-bottom: 32px;">
          <div class="s-stepper-stage">
            <div class="s-stepper-circle done">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
              <span class="s-stepper-label">Upload</span>
            </div>
            <div class="s-stepper-line done"></div>
          </div>
          <div class="s-stepper-stage">
            <div class="s-stepper-circle active">
              2
              <span class="s-stepper-label">Map Fields</span>
            </div>
            <div class="s-stepper-line"></div>
          </div>
          <div class="s-stepper-stage">
            <div class="s-stepper-circle">
              3
              <span class="s-stepper-label">Validate</span>
            </div>
            <div class="s-stepper-line"></div>
          </div>
          <div class="s-stepper-stage">
            <div class="s-stepper-circle">
              4
              <span class="s-stepper-label">Import</span>
            </div>
          </div>
        </x-signals.stepper>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  59. DROPZONE                                                 --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Dropzone</div>
      <div class="ref-note">Drag-and-drop file upload area. From import-export.</div>
      <div class="ref-demo">
        <x-signals.dropzone hint="Supports CSV, XLSX, JSON (max 50MB)">
          <x-slot:icon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          </x-slot:icon>
        </x-signals.dropzone>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  60. FORMAT SELECTOR                                          --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Format Selector</div>
      <div class="ref-note">Card-based format/option selector. From import-export.</div>
      <div class="ref-demo">
        <div class="s-format-cards">
          <x-signals.format-card icon="CSV" label="CSV File" :selected="true" />
          <x-signals.format-card icon="XLS" label="Excel" />
          <x-signals.format-card icon="JSON" label="JSON" />
          <x-signals.format-card icon="PDF" label="PDF" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  61. PROGRESS BAR                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Progress Bar</div>
      <div class="ref-note">Horizontal progress bar with label and percentage. From import-export.</div>
      <div class="ref-demo">
        <div style="max-width: 400px;">
          <x-signals.progress label="Importing records..." :percent="68" />
          <div class="ref-spacer"></div>
          <x-signals.progress label="Validating fields" :percent="100" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  63. AVATAR                                                   --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Avatar</div>
      <div class="ref-note">User avatar with image, initials, color, and status indicator. Use <code>&lt;x-signals.avatar&gt;</code> Blade component.</div>
      <div class="ref-subsection">
        <div class="ref-subsection-title">Sizes</div>
        <div class="ref-demo" style="display: flex; align-items: center; gap: 12px;">
          <x-signals.avatar initials="XS" size="xs" color="green" />
          <x-signals.avatar initials="SM" size="sm" color="blue" />
          <x-signals.avatar initials="MD" size="md" color="amber" />
          <x-signals.avatar initials="LG" size="lg" color="violet" />
        </div>
      </div>
      <div class="ref-subsection">
        <div class="ref-subsection-title">Indicators</div>
        <div class="ref-demo" style="display: flex; align-items: center; gap: 12px;">
          <x-signals.avatar initials="ON" size="md" color="green" indicator="online" />
          <x-signals.avatar initials="AW" size="md" color="amber" indicator="away" />
          <x-signals.avatar initials="OF" size="md" color="navy" indicator="offline" />
        </div>
      </div>
      <div class="ref-subsection">
        <div class="ref-subsection-title">Avatar Group</div>
        <div class="ref-demo">
          <x-signals.avatar-group>
            <x-signals.avatar initials="AB" size="sm" color="green" />
            <x-signals.avatar initials="CD" size="sm" color="blue" />
            <x-signals.avatar initials="EF" size="sm" color="amber" />
            <x-signals.avatar initials="+3" size="sm" color="navy" />
          </x-signals.avatar-group>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  64. LOADING BUTTON                                           --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Loading Button</div>
      <div class="ref-note">Add <code>.s-btn-loading</code> to any <code>.s-btn</code> to show a spinner. CSS-only modifier.</div>
      <div class="ref-demo" style="display: flex; gap: 12px; align-items: center;">
        <button class="s-btn s-btn-primary s-btn-loading">
          <span class="s-btn-text">Saving</span>
          <span class="s-btn-spinner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.48-8.48l2.83-2.83M2 12h4m12 0h4m-3.93 7.07l-2.83-2.83M7.76 7.76L4.93 4.93"/></svg></span>
        </button>
        <button class="s-btn s-btn-loading">
          <span class="s-btn-text">Loading</span>
          <span class="s-btn-spinner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.48-8.48l2.83-2.83M2 12h4m12 0h4m-3.93 7.07l-2.83-2.83M7.76 7.76L4.93 4.93"/></svg></span>
        </button>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  65. SPLIT BUTTON                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Split Button</div>
      <div class="ref-note">Button with dropdown for secondary actions. Uses <code>&lt;x-signals.split-button&gt;</code>.</div>
      <div class="ref-demo" style="display: flex; gap: 12px;">
        <x-signals.split-button label="Save" variant="primary">
          <div class="s-dropdown-item">Save as Draft</div>
          <div class="s-dropdown-item">Save & Close</div>
          <hr class="s-dropdown-sep">
          <div class="s-dropdown-item">Discard</div>
        </x-signals.split-button>
        <x-signals.split-button label="Actions">
          <div class="s-dropdown-item">Duplicate</div>
          <div class="s-dropdown-item">Archive</div>
        </x-signals.split-button>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  66. PAGINATION                                               --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Pagination</div>
      <div class="ref-note">Page navigation with numbered buttons, ellipsis, and per-page selector. CSS demo below; use <code>&lt;x-signals.pagination&gt;</code> with a Laravel paginator.</div>
      <div class="ref-demo">
        <div class="s-pagination">
          <button class="s-pagination-btn" disabled>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button class="s-pagination-btn active">1</button>
          <button class="s-pagination-btn">2</button>
          <button class="s-pagination-btn">3</button>
          <span class="s-pagination-ellipsis">&hellip;</span>
          <button class="s-pagination-btn">12</button>
          <button class="s-pagination-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
          <span class="s-pagination-info">1&ndash;25 of 300</span>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  67. CARD VARIANTS                                            --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Card Variants</div>
      <div class="ref-note">Card footer slot and horizontal layout. Extends existing <code>&lt;x-signals.card&gt;</code>.</div>
      <div class="ref-subsection">
        <div class="ref-subsection-title">Card with Footer</div>
        <div class="ref-demo">
          <x-signals.card title="Confirm Action" style="max-width: 360px;">
            <p style="margin: 0; font-family: var(--font-sans); font-size: 13px; color: var(--text-secondary);">Are you sure you want to proceed?</p>
            <x-slot:footer>
              <button class="s-btn s-btn-sm">Cancel</button>
              <button class="s-btn s-btn-sm s-btn-primary">Confirm</button>
            </x-slot:footer>
          </x-signals.card>
        </div>
      </div>
      <div class="ref-subsection">
        <div class="ref-subsection-title">Horizontal Card</div>
        <div class="ref-demo">
          <div class="s-card s-card-horizontal" style="max-width: 440px;">
            <div class="s-card-cover" style="background: var(--s-green-bg); display: flex; align-items: center; justify-content: center; font-size: 28px;">📦</div>
            <div class="s-card-body">
              <span class="s-card-title" style="margin-bottom: 6px; display: block;">Equipment Package</span>
              <p style="margin: 0; font-family: var(--font-sans); font-size: 12px; color: var(--text-muted);">24 items across 3 categories</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  68. SIMPLE TOOLTIP                                           --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Simple Tooltip</div>
      <div class="ref-note">Lightweight hover tooltip. Uses <code>&lt;x-signals.tip&gt;</code>. Pure CSS, no Alpine.</div>
      <div class="ref-demo" style="display: flex; gap: 24px; align-items: center; padding: 32px 24px;">
        <x-signals.tip text="Top tooltip" position="top">
          <button class="s-btn s-btn-sm">Top</button>
        </x-signals.tip>
        <x-signals.tip text="Bottom tooltip" position="bottom">
          <button class="s-btn s-btn-sm">Bottom</button>
        </x-signals.tip>
        <x-signals.tip text="Left tooltip" position="left">
          <button class="s-btn s-btn-sm">Left</button>
        </x-signals.tip>
        <x-signals.tip text="Right tooltip" position="right">
          <button class="s-btn s-btn-sm">Right</button>
        </x-signals.tip>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  69. SKELETON                                                 --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Skeleton</div>
      <div class="ref-note">Loading placeholder with shimmer animation. Use <code>&lt;x-signals.skeleton&gt;</code>.</div>
      <div class="ref-demo" style="display: flex; gap: 24px; align-items: flex-start;">
        <div style="flex: 1;">
          <x-signals.skeleton type="text" :lines="3" />
        </div>
        <x-signals.skeleton type="circle" />
        <x-signals.skeleton type="btn" />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  70. COMMAND PALETTE                                          --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Command Palette</div>
      <div class="ref-note">Full-screen overlay with blurred backdrop. Press <code>Cmd+K</code> to open, or click the button below. Uses <code>&lt;x-signals.command-palette&gt;</code>.</div>
      <div class="ref-demo">
        <x-signals.command-palette id="demo-cmd">
          <div class="s-command-group">Navigation</div>
          <div class="s-command-item active">
            <svg class="s-command-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            <span class="s-command-item-label">Dashboard</span>
            <span class="s-command-item-hint">⌘D</span>
          </div>
          <div class="s-command-item">
            <svg class="s-command-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/></svg>
            <span class="s-command-item-label">Members</span>
            <span class="s-command-item-hint">⌘M</span>
          </div>
          <div class="s-command-group">Actions</div>
          <div class="s-command-item">
            <svg class="s-command-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span class="s-command-item-label">New Opportunity</span>
            <span class="s-command-item-hint">⌘N</span>
          </div>
          <div class="s-command-item">
            <svg class="s-command-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
            <span class="s-command-item-label">Products</span>
            <span class="s-command-item-hint">⌘P</span>
          </div>
        </x-signals.command-palette>
        <div style="font-family: var(--font-sans); font-size: 12px; color: var(--text-muted);">
          Press <span class="s-kbd">⌘K</span> to open the full-screen command palette with blurred backdrop.
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  71. BREADCRUMB                                               --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Breadcrumb</div>
      <div class="ref-note">Breadcrumb navigation. Uses existing <code>.s-breadcrumb</code> CSS via <code>&lt;x-signals.breadcrumb&gt;</code>.</div>
      <div class="ref-demo">
        <x-signals.breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => '#'],
            ['label' => 'Opportunities', 'href' => '#'],
            ['label' => 'OPP-2026-0148'],
        ]" />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  72. CALENDAR                                                 --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Calendar</div>
      <div class="ref-note">Mini month calendar with day selection, event dots, range selection, and adjacent month navigation. Uses <code>&lt;x-signals.calendar&gt;</code> with Alpine.</div>
      <div class="ref-demo" style="display: flex; flex-wrap: wrap; gap: 24px; align-items: flex-start;">
        {{-- Single date selection with events --}}
        <div>
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 8px;">Single Date + Events</div>
          <x-signals.calendar selected="2026-03-08" :events="['2026-03-05', '2026-03-12', '2026-03-20']" />
        </div>
        {{-- Date range selection --}}
        <div>
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 8px;">Date Range</div>
          <x-signals.calendar range-start="2026-03-10" range-end="2026-03-18" />
        </div>
        {{-- Adjacent month navigation --}}
        <div>
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 8px;">Adjacent Month Nav</div>
          <x-signals.calendar :show-adjacent-months="true" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  73. SPINNER                                                  --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Spinner</div>
      <div class="ref-note">SVG-based loading spinner. CSS-only — no border-radius needed.</div>
      <div class="ref-demo" style="display: flex; align-items: center; gap: 16px;">
        <svg class="s-spinner s-spinner-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.48-8.48l2.83-2.83M2 12h4m12 0h4m-3.93 7.07l-2.83-2.83M7.76 7.76L4.93 4.93"/></svg>
        <svg class="s-spinner s-spinner-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.48-8.48l2.83-2.83M2 12h4m12 0h4m-3.93 7.07l-2.83-2.83M7.76 7.76L4.93 4.93"/></svg>
        <svg class="s-spinner s-spinner-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.48-8.48l2.83-2.83M2 12h4m12 0h4m-3.93 7.07l-2.83-2.83M7.76 7.76L4.93 4.93"/></svg>
        <svg class="s-spinner s-spinner-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.48-8.48l2.83-2.83M2 12h4m12 0h4m-3.93 7.07l-2.83-2.83M7.76 7.76L4.93 4.93"/></svg>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  74. POPOVER                                                  --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Popover</div>
      <div class="ref-note">Click-triggered floating panel with title, body, and optional footer. Uses <code>&lt;x-signals.popover&gt;</code>.</div>
      <div class="ref-demo" style="padding: 40px 24px;">
        <x-signals.popover position="bottom" title="Stock Details">
          <x-slot:trigger>
            <button class="s-btn s-btn-sm">Click me</button>
          </x-slot:trigger>
          <p style="margin: 0; font-size: 12px;">Item has 16 units available across 2 stores.</p>
          <x-slot:footer>
            <button class="s-btn s-btn-sm s-btn-primary">View Details</button>
          </x-slot:footer>
        </x-signals.popover>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  75. DATE PICKER                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Date Picker</div>
      <div class="ref-note">Date input with calendar dropdown, range mode, and optional time picker. Uses <code>&lt;x-signals.datepicker&gt;</code>. Composes the Calendar component.</div>
      <div class="ref-demo" style="display: flex; flex-direction: column; gap: 16px; padding-bottom: 40px;">
        {{-- Single date --}}
        <div>
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 6px;">Single Date</div>
          <x-signals.datepicker value="2026-03-08" placeholder="Pick a date" />
        </div>
        {{-- Date with time picker --}}
        <div>
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 6px;">Date + Time</div>
          <x-signals.datepicker value="2026-03-08" :show-time="true" placeholder="Pick date & time" />
        </div>
        {{-- Date range --}}
        <div>
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 6px;">Date Range</div>
          <x-signals.datepicker :range="true" range-start="2026-03-10" range-end="2026-03-18" placeholder="Select range" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  76. FIELD                                                    --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Field</div>
      <div class="ref-note">Form field wrapper with label, help text, and error state. Uses <code>&lt;x-signals.field&gt;</code> with <code>s-input</code>, <code>s-textarea</code>, <code>s-checkbox</code>, <code>s-radio</code> classes.</div>
      <div class="ref-demo" style="display: flex; gap: 32px; flex-wrap: wrap;">
        {{-- Standard inputs --}}
        <div style="flex: 1; min-width: 280px; max-width: 360px;">
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 12px;">Standard Inputs</div>
          <x-signals.field label="Full Name" :required="true">
            <input type="text" class="s-input" placeholder="Jane Smith">
          </x-signals.field>
          <x-signals.field label="Email Address" help="We'll never share your email." :required="true">
            <input type="email" class="s-input" placeholder="user@example.com">
          </x-signals.field>
          <x-signals.field label="Password">
            <input type="password" class="s-input" placeholder="Enter password">
          </x-signals.field>
          <x-signals.field label="Category" :required="true">
            <select class="s-input">
              <option value="">Select a category...</option>
              <option>Lighting</option>
              <option>Audio</option>
              <option>Staging</option>
              <option>Power</option>
            </select>
          </x-signals.field>
          <x-signals.field label="Notes">
            <textarea class="s-textarea" placeholder="Additional notes..." rows="3"></textarea>
          </x-signals.field>
        </div>
        {{-- Checkbox, radio, toggle --}}
        <div style="flex: 1; min-width: 280px; max-width: 360px;">
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 12px;">Checkbox, Radio &amp; Toggle</div>
          <x-signals.field label="Store">
            <div class="s-field-options" x-data="{ store: 'main' }">
              <label class="s-field-option" x-on:click="store = 'main'">
                <div class="s-radio" x-bind:class="store === 'main' && 'checked'"></div>
                Main Warehouse
              </label>
              <label class="s-field-option" x-on:click="store = 'north'">
                <div class="s-radio" x-bind:class="store === 'north' && 'checked'"></div>
                North Depot
              </label>
              <label class="s-field-option" x-on:click="store = 'south'">
                <div class="s-radio" x-bind:class="store === 'south' && 'checked'"></div>
                South Depot
              </label>
            </div>
          </x-signals.field>
          <x-signals.field label="Options">
            <div class="s-field-options" x-data="{ delivery: true, signature: false }">
              <label class="s-field-option" x-on:click="delivery = !delivery">
                <div class="s-checkbox" x-bind:class="delivery && 'checked'">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                Include delivery
              </label>
              <label class="s-field-option" x-on:click="signature = !signature">
                <div class="s-checkbox" x-bind:class="signature && 'checked'">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                Require signature
              </label>
            </div>
          </x-signals.field>
          <x-signals.field label="Notifications" help="Choose which notifications to receive.">
            <div class="s-field-options" x-data="{ email: true, sms: false, inapp: true }">
              <label class="s-field-option" x-on:click="email = !email">
                <div class="s-checkbox" x-bind:class="email && 'checked'">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                Email
              </label>
              <label class="s-field-option" x-on:click="sms = !sms">
                <div class="s-checkbox" x-bind:class="sms && 'checked'">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                SMS
              </label>
              <label class="s-field-option" x-on:click="inapp = !inapp">
                <div class="s-checkbox" x-bind:class="inapp && 'checked'">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                In-app
              </label>
            </div>
          </x-signals.field>
          <x-signals.field label="Company Name" error="This field is required." :required="true">
            <input type="text" class="s-input">
          </x-signals.field>
          <x-signals.field label="Disabled Field">
            <input type="text" class="s-input" disabled value="Read only value">
          </x-signals.field>
        </div>
        {{-- Input groups --}}
        <div style="flex: 1; min-width: 280px; max-width: 360px;">
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 12px;">Input Groups</div>
          <x-signals.field label="Website">
            <div class="s-input-group">
              <span class="s-input-prefix">https://</span>
              <input type="text" class="s-input" placeholder="example.com">
            </div>
          </x-signals.field>
          <x-signals.field label="Price">
            <div class="s-input-group">
              <span class="s-input-prefix">&pound;</span>
              <input type="number" class="s-input" placeholder="0.00" step="0.01">
              <span class="s-input-suffix">GBP</span>
            </div>
          </x-signals.field>
          <x-signals.field label="Weight">
            <div class="s-input-group">
              <input type="number" class="s-input" placeholder="0">
              <span class="s-input-suffix">kg</span>
            </div>
          </x-signals.field>
          <x-signals.field label="Quantity">
            <input type="number" class="s-input" placeholder="0" min="0" step="1">
          </x-signals.field>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  77. MODAL                                                    --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Modal</div>
      <div class="ref-note">Full-page overlay dialog with blur backdrop, focus trap, and escape-to-close. Uses <code>&lt;x-signals.modal&gt;</code>. Click buttons below to open live modals.</div>
      <div class="ref-demo" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        {{-- Small modal trigger --}}
        <div x-data>
          <button class="s-btn s-btn-sm" x-on:click="$dispatch('open-modal', 'ref-modal-sm')">Small Modal</button>
        </div>
        {{-- Medium modal trigger --}}
        <div x-data>
          <button class="s-btn s-btn-sm s-btn-primary" x-on:click="$dispatch('open-modal', 'ref-modal-md')">Medium Modal</button>
        </div>
        {{-- Large modal trigger --}}
        <div x-data>
          <button class="s-btn s-btn-sm s-btn-danger" x-on:click="$dispatch('open-modal', 'ref-modal-lg')">Large Modal</button>
        </div>
      </div>
      {{-- Small modal --}}
      <x-signals.modal name="ref-modal-sm" title="Delete Item" size="sm">
        <p style="margin: 0;">Are you sure you want to delete this item? This action cannot be undone.</p>
        <x-slot:footer>
          <button class="s-btn s-btn-sm" x-on:click="$dispatch('close-modal', 'ref-modal-sm')">Cancel</button>
          <button class="s-btn s-btn-sm s-btn-danger" x-on:click="$dispatch('close-modal', 'ref-modal-sm')">Delete</button>
        </x-slot:footer>
      </x-signals.modal>
      {{-- Medium modal --}}
      <x-signals.modal name="ref-modal-md" title="Edit Opportunity" size="md">
        <div style="display: flex; flex-direction: column; gap: 12px;">
          <x-signals.field label="Subject" :required="true">
            <input type="text" class="s-input" value="Summer Festival 2026">
          </x-signals.field>
          <x-signals.field label="Status">
            <select class="s-input">
              <option>Quotation</option>
              <option selected>Confirmed</option>
              <option>Cancelled</option>
            </select>
          </x-signals.field>
        </div>
        <x-slot:footer>
          <button class="s-btn s-btn-sm" x-on:click="$dispatch('close-modal', 'ref-modal-md')">Cancel</button>
          <button class="s-btn s-btn-sm s-btn-primary" x-on:click="$dispatch('close-modal', 'ref-modal-md')">Save Changes</button>
        </x-slot:footer>
      </x-signals.modal>
      {{-- Large modal --}}
      <x-signals.modal name="ref-modal-lg" title="Bulk Import Preview" size="lg">
        <p style="margin: 0 0 12px;">Review the following records before importing:</p>
        <table class="s-table s-table-compact" style="width: 100%;">
          <thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td>Acme Events Ltd</td><td>info@acme.co.uk</td><td>Organisation</td><td><span class="s-status s-status-green">Ready</span></td></tr>
            <tr><td>Jane Cooper</td><td>jane@cooper.com</td><td>Contact</td><td><span class="s-status s-status-green">Ready</span></td></tr>
            <tr><td>Sound Warehouse</td><td>hello@soundwh.com</td><td>Organisation</td><td><span class="s-status s-status-amber">Duplicate?</span></td></tr>
          </tbody>
        </table>
        <x-slot:footer>
          <button class="s-btn s-btn-sm" x-on:click="$dispatch('close-modal', 'ref-modal-lg')">Cancel</button>
          <button class="s-btn s-btn-sm s-btn-primary" x-on:click="$dispatch('close-modal', 'ref-modal-lg')">Import 3 Records</button>
        </x-slot:footer>
      </x-signals.modal>
    </div>

    {{-- ============================================================ --}}
    {{--  78. OTP INPUT                                                --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">OTP Input</div>
      <div class="ref-note">Multi-digit code input with auto-advance, backspace navigation, and paste support. Uses <code>&lt;x-signals.otp-input&gt;</code>.</div>
      <div class="ref-demo" style="display: flex; flex-direction: column; gap: 16px; align-items: flex-start;">
        <x-signals.otp-input :length="6" :separator="true" />
        <x-signals.otp-input :length="4" />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  79. TIMELINE                                                 --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Timeline</div>
      <div class="ref-note">Vertical timeline with colored dots and content. Uses <code>&lt;x-signals.timeline&gt;</code> and <code>&lt;x-signals.timeline-item&gt;</code>.</div>
      <div class="ref-demo" style="max-width: 400px;">
        <x-signals.timeline>
          <x-signals.timeline-item color="green" title="Order Confirmed" meta="08 Mar 2026 · 14:30">
            Order #OPP-2026-0148 confirmed by admin.
          </x-signals.timeline-item>
          <x-signals.timeline-item color="blue" title="Items Dispatched" meta="07 Mar 2026 · 09:15">
            12 items dispatched via courier.
          </x-signals.timeline-item>
          <x-signals.timeline-item color="amber" title="Quote Sent" meta="05 Mar 2026 · 11:00">
            Quote emailed to client.
          </x-signals.timeline-item>
          <x-signals.timeline-item title="Created" meta="04 Mar 2026 · 16:45">
            Opportunity created.
          </x-signals.timeline-item>
        </x-signals.timeline>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  80. DATETIME DISPLAY                                         --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Datetime Display</div>
      <div class="ref-note">Formatted date/time display in mono font. Uses <code>&lt;x-signals.datetime&gt;</code>.</div>
      <div class="ref-demo" style="display: flex; flex-direction: column; gap: 12px;">
        <x-signals.datetime value="2026-03-08 14:30:00" :showIcon="true" />
        <x-signals.datetime value="2026-03-08 14:30:00" size="sm" :showTime="false" />
        <x-signals.datetime value="2026-03-08 14:30:00" size="lg" :showIcon="true" :relative="true" />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  81. DATETIME INPUT                                           --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Datetime Input</div>
      <div class="ref-note">Combined date picker + time input. Uses <code>&lt;x-signals.datetime-input&gt;</code>. Composes the Date Picker with built-in time picker.</div>
      <div class="ref-demo" style="display: flex; flex-direction: column; gap: 16px; padding-bottom: 40px;">
        {{-- With time --}}
        <div>
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 6px;">Date + Time</div>
          <x-signals.datetime-input value="2026-03-08 14:30:00" />
        </div>
        {{-- Date only --}}
        <div>
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 6px;">Date Only (no time)</div>
          <x-signals.datetime-input value="2026-03-08" :show-time="false" placeholder="Select date" />
        </div>
        {{-- Empty --}}
        <div>
          <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 6px;">Empty State</div>
          <x-signals.datetime-input placeholder="Choose date & time" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  82. ALERT / BANNER                                           --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Alert / Banner</div>
      <div class="ref-note">System messages, validation summaries, status banners. Use <code>&lt;x-signals.alert&gt;</code>. Supports info/success/warning/danger types.</div>
      <div class="ref-demo" style="display: flex; flex-direction: column; gap: 12px;">
        <x-signals.alert type="info" title="Information">
          Your import is being processed. This may take a few minutes.
        </x-signals.alert>
        <x-signals.alert type="success" title="Success">
          Opportunity #1042 has been confirmed and the client notified.
        </x-signals.alert>
        <x-signals.alert type="warning" title="Low Stock Warning">
          3 items are below minimum stock levels. Review allocations.
        </x-signals.alert>
        <x-signals.alert type="danger" title="Payment Failed">
          Invoice #INV-2026-089 payment was declined. Please retry.
        </x-signals.alert>
      </div>
      <div class="ref-note">Dismissible variant:</div>
      <div class="ref-demo">
        <x-signals.alert type="info" title="Tip" :dismissible="true">
          You can customise columns by clicking the gear icon in the toolbar.
        </x-signals.alert>
      </div>
      <div class="ref-note">Banner (full-width, no side borders) — add <code>class="s-banner"</code>:</div>
      <div class="ref-demo" style="padding: 0;">
        <x-signals.alert type="warning" class="s-banner">
          Scheduled maintenance tonight at 22:00 UTC. Expect brief downtime.
        </x-signals.alert>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  83. DRAWER                                                   --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Drawer</div>
      <div class="ref-note">Slide-over panel for detail views, quick-edit forms, and filters. Uses <code>&lt;x-signals.drawer&gt;</code>. Click buttons to open.</div>
      <div class="ref-demo" style="display: flex; flex-wrap: wrap; gap: 10px;">
        <div x-data>
          <button class="s-btn s-btn-sm" x-on:click="$dispatch('open-drawer', 'ref-drawer-right')">Right Drawer (md)</button>
        </div>
        <div x-data>
          <button class="s-btn s-btn-sm" x-on:click="$dispatch('open-drawer', 'ref-drawer-left')">Left Drawer (sm)</button>
        </div>
      </div>
      <x-signals.drawer name="ref-drawer-right" title="Member Details" side="right" size="md">
        <x-signals.data-list :items="[
            ['label' => 'Name', 'value' => 'Acme Events Ltd'],
            ['label' => 'Email', 'value' => 'info@acme-events.co.uk'],
            ['label' => 'Type', 'value' => 'Organisation'],
            ['label' => 'Phone', 'value' => '+44 20 7946 0958'],
            ['label' => 'Status', 'value' => 'Active'],
        ]" />
        <x-slot:footer>
          <button class="s-btn s-btn-sm" x-on:click="$dispatch('close-drawer', 'ref-drawer-right')">Close</button>
          <button class="s-btn s-btn-sm s-btn-primary" x-on:click="$dispatch('close-drawer', 'ref-drawer-right')">Edit Member</button>
        </x-slot:footer>
      </x-signals.drawer>
      <x-signals.drawer name="ref-drawer-left" title="Filters" side="left" size="sm">
        <div style="display: flex; flex-direction: column; gap: 12px;">
          <x-signals.field label="Status">
            <select class="s-input"><option>All</option><option>Active</option><option>Archived</option></select>
          </x-signals.field>
          <x-signals.field label="Date Range">
            <input type="text" class="s-input" value="Last 30 days">
          </x-signals.field>
        </div>
        <x-slot:footer>
          <button class="s-btn s-btn-sm" x-on:click="$dispatch('close-drawer', 'ref-drawer-left')">Reset</button>
          <button class="s-btn s-btn-sm s-btn-primary" x-on:click="$dispatch('close-drawer', 'ref-drawer-left')">Apply Filters</button>
        </x-slot:footer>
      </x-signals.drawer>
    </div>

    {{-- ============================================================ --}}
    {{--  84. TAG INPUT                                                --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Tag Input</div>
      <div class="ref-note">Multi-value tokenised input for tags, categories, recipients. Uses <code>&lt;x-signals.tag-input&gt;</code>. Type and press Enter, or select from suggestions.</div>
      <div class="ref-demo" style="max-width: 400px;">
        <x-signals.tag-input
          name="tags"
          :value="['Lighting', 'Sound']"
          placeholder="Add category..."
          :suggestions="['Lighting', 'Sound', 'Staging', 'Video', 'Power', 'Rigging', 'Furniture', 'Transport']"
        />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  85. COMBOBOX                                                 --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Combobox</div>
      <div class="ref-note">Searchable select / autocomplete. Uses <code>&lt;x-signals.combobox&gt;</code>. Type to filter, arrow keys to navigate, Enter to select.</div>
      <div class="ref-demo" style="max-width: 300px;">
        <x-signals.combobox
          name="member_id"
          placeholder="Search members..."
          :options="[
              ['value' => '1', 'label' => 'Acme Events Ltd', 'group' => 'Organisations'],
              ['value' => '2', 'label' => 'Sound Warehouse', 'group' => 'Organisations'],
              ['value' => '3', 'label' => 'Jane Cooper', 'group' => 'Contacts'],
              ['value' => '4', 'label' => 'Marcus Webb', 'group' => 'Contacts'],
              ['value' => '5', 'label' => 'Festival Hire Co', 'group' => 'Organisations'],
          ]"
        />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  86. CONFIRMATION DIALOG                                      --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Confirmation Dialog</div>
      <div class="ref-note">Lightweight destructive action confirmation. Uses <code>&lt;x-signals.confirm&gt;</code>. Reuses modal backdrop and sizing.</div>
      <div class="ref-demo" style="display: flex; flex-wrap: wrap; gap: 10px;">
        <div x-data>
          <button class="s-btn s-btn-sm s-btn-danger" x-on:click="$dispatch('open-confirm', 'ref-confirm-danger')">Delete Item</button>
        </div>
        <div x-data>
          <button class="s-btn s-btn-sm" x-on:click="$dispatch('open-confirm', 'ref-confirm-warning')">Archive Record</button>
        </div>
      </div>
      <x-signals.confirm name="ref-confirm-danger" title="Delete Opportunity?" message="This will permanently delete the opportunity and all associated line items. This action cannot be undone." confirmLabel="Delete" type="danger" />
      <x-signals.confirm name="ref-confirm-warning" title="Archive Record?" message="This record will be moved to the archive and hidden from active views." confirmLabel="Archive" type="warning" />
    </div>

    {{-- ============================================================ --}}
    {{--  87. COPY BUTTON                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Copy Button</div>
      <div class="ref-note">One-click copy for API keys, share links, reference numbers. Uses <code>&lt;x-signals.copy-btn&gt;</code>.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <x-signals.copy-btn text="sk_live_abc123def456" label="Copy API Key" />
          <x-signals.copy-btn text="OPP-2026-1042" label="OPP-2026-1042" />
          <x-signals.copy-btn text="https://app.signals.rent/share/abc123" label="Copy Link" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  88. ACCORDION                                                --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Accordion</div>
      <div class="ref-note">Grouped collapsible sections with mutual exclusion. Uses <code>&lt;x-signals.accordion&gt;</code> + <code>&lt;x-signals.accordion-item&gt;</code>.</div>
      <div class="ref-subsection">
        <div class="ref-subsection-title">Single open (default)</div>
        <div class="ref-demo" style="padding: 0; overflow: hidden;">
          <x-signals.accordion>
            <x-signals.accordion-item id="company" label="Company Settings">
              Configure your company name, logo, address, and contact details.
            </x-signals.accordion-item>
            <x-signals.accordion-item id="billing" label="Billing & Invoicing">
              Set default payment terms, tax rates, and invoice numbering sequences.
            </x-signals.accordion-item>
            <x-signals.accordion-item id="notifications" label="Notification Preferences">
              Choose which email and in-app notifications you receive.
            </x-signals.accordion-item>
          </x-signals.accordion>
        </div>
      </div>
      <div class="ref-subsection">
        <div class="ref-subsection-title">Multiple open</div>
        <div class="ref-demo" style="padding: 0; overflow: hidden;">
          <x-signals.accordion :multiple="true">
            <x-signals.accordion-item id="faq1" label="How do I create an opportunity?">
              Navigate to Opportunities and click the + New Opportunity button.
            </x-signals.accordion-item>
            <x-signals.accordion-item id="faq2" label="How do I generate an invoice?">
              Open an opportunity, click Actions, then select Issue Invoice.
            </x-signals.accordion-item>
          </x-signals.accordion>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  89. INLINE EDIT                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Inline Edit</div>
      <div class="ref-note">Click-to-edit fields. Uses <code>&lt;x-signals.inline-edit&gt;</code>. Click the value to edit, Enter to save, Escape to cancel.</div>
      <div class="ref-demo">
        <div class="ref-col" style="gap: 12px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-family: var(--font-display); font-size: 12px; color: var(--text-muted); width: 80px;">Subject</span>
            <x-signals.inline-edit value="Summer Festival 2026" name="subject" />
          </div>
          <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-family: var(--font-display); font-size: 12px; color: var(--text-muted); width: 80px;">Quantity</span>
            <x-signals.inline-edit value="12" name="quantity" type="number" />
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  90. NUMBER INPUT                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Number Input</div>
      <div class="ref-note">Stepper-style number input with +/- buttons and min/max bounds. Uses <code>&lt;x-signals.number-input&gt;</code>.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <div>
            <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 6px;">Default (step 1)</div>
            <x-signals.number-input :value="5" :min="0" :max="20" name="qty" />
          </div>
          <div>
            <div style="font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 6px;">Step 5, min 0, max 100</div>
            <x-signals.number-input :value="25" :min="0" :max="100" :step="5" name="bulk_qty" />
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  91. RANGE SLIDER                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Range Slider</div>
      <div class="ref-note">Drag-based range selection. Uses <code>&lt;x-signals.range-slider&gt;</code>.</div>
      <div class="ref-demo" style="max-width: 400px;">
        <x-signals.range-slider :min="0" :max="500" :value="150" :step="10" name="price_filter" />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  92. QUICK FILTERS                                            --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Quick Filters</div>
      <div class="ref-note">Horizontal chip bar for one-click filter presets. Uses <code>&lt;x-signals.quick-filters&gt;</code>. Uses <code>wire:click</code> for Livewire integration.</div>
      <div class="ref-demo">
        <x-signals.quick-filters
          :filters="[
              ['label' => 'All', 'value' => 'all', 'count' => 142],
              ['label' => 'Active', 'value' => 'active', 'count' => 89],
              ['label' => 'Confirmed', 'value' => 'confirmed', 'count' => 34],
              ['label' => 'Archived', 'value' => 'archived', 'count' => 19],
          ]"
          active="active"
        />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  93. NOTIFICATION CENTER                                      --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Notification Center</div>
      <div class="ref-note">Bell-icon dropdown with grouped notifications. Uses <code>&lt;x-signals.notification-center&gt;</code>. Real-time updates via Reverb in production.</div>
      <div class="ref-demo">
        <x-signals.notification-center :count="3">
          <x-slot:headerActions>
            <button>Mark all read</button>
          </x-slot:headerActions>
          <div class="s-notif-center-item unread">
            <div class="s-notif-center-item-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="s-notif-center-item-content">
              <strong>Opportunity Confirmed</strong>
              <span>OPP-2026-1042 "Summer Festival" confirmed by client</span>
            </div>
            <span class="s-notif-center-item-time">2m ago</span>
          </div>
          <div class="s-notif-center-item unread">
            <div class="s-notif-center-item-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2Z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <div class="s-notif-center-item-content">
              <strong>New Message</strong>
              <span>Jane Cooper sent you a message about the PA system</span>
            </div>
            <span class="s-notif-center-item-time">15m ago</span>
          </div>
          <div class="s-notif-center-item">
            <div class="s-notif-center-item-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="s-notif-center-item-content">
              <strong>Stock Alert</strong>
              <span>Martin guitars below minimum level (2 remaining)</span>
            </div>
            <span class="s-notif-center-item-time">1h ago</span>
          </div>
          <x-slot:footer>
            <a href="#">View all notifications</a>
          </x-slot:footer>
        </x-signals.notification-center>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  94. KANBAN BOARD                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Kanban Board</div>
      <div class="ref-note">Columnar card layout for pipeline views. Uses <code>&lt;x-signals.kanban&gt;</code>, <code>&lt;x-signals.kanban-column&gt;</code>, <code>&lt;x-signals.kanban-card&gt;</code>.</div>
      <div class="ref-demo" style="overflow-x: auto;">
        <x-signals.kanban>
          <x-signals.kanban-column title="Quotation" :count="3">
            <x-signals.kanban-card>
              <div style="font-family: var(--font-display); font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Summer Festival</div>
              <div style="font-family: var(--font-mono); font-size: 10px; color: var(--text-muted);">OPP-1042 · Acme Events</div>
              <div style="font-family: var(--font-mono); font-size: 11px; color: var(--green); margin-top: 6px;">£12,500.00</div>
            </x-signals.kanban-card>
            <x-signals.kanban-card>
              <div style="font-family: var(--font-display); font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Corporate Launch</div>
              <div style="font-family: var(--font-mono); font-size: 10px; color: var(--text-muted);">OPP-1043 · TechCo</div>
              <div style="font-family: var(--font-mono); font-size: 11px; color: var(--green); margin-top: 6px;">£8,200.00</div>
            </x-signals.kanban-card>
            <x-signals.kanban-card>
              <div style="font-family: var(--font-display); font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Wedding Reception</div>
              <div style="font-family: var(--font-mono); font-size: 10px; color: var(--text-muted);">OPP-1044 · Private</div>
              <div style="font-family: var(--font-mono); font-size: 11px; color: var(--green); margin-top: 6px;">£3,750.00</div>
            </x-signals.kanban-card>
          </x-signals.kanban-column>
          <x-signals.kanban-column title="Confirmed" :count="2">
            <x-signals.kanban-card>
              <div style="font-family: var(--font-display); font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Music Awards</div>
              <div style="font-family: var(--font-mono); font-size: 10px; color: var(--text-muted);">OPP-1038 · MusicCo</div>
              <div style="font-family: var(--font-mono); font-size: 11px; color: var(--green); margin-top: 6px;">£45,000.00</div>
            </x-signals.kanban-card>
            <x-signals.kanban-card>
              <div style="font-family: var(--font-display); font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Trade Show</div>
              <div style="font-family: var(--font-mono); font-size: 10px; color: var(--text-muted);">OPP-1040 · ExpoWorld</div>
              <div style="font-family: var(--font-mono); font-size: 11px; color: var(--green); margin-top: 6px;">£18,900.00</div>
            </x-signals.kanban-card>
          </x-signals.kanban-column>
          <x-signals.kanban-column title="In Progress" :count="1">
            <x-signals.kanban-card>
              <div style="font-family: var(--font-display); font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Product Launch</div>
              <div style="font-family: var(--font-mono); font-size: 10px; color: var(--text-muted);">OPP-1035 · InnovateCo</div>
              <div style="font-family: var(--font-mono); font-size: 11px; color: var(--green); margin-top: 6px;">£22,100.00</div>
            </x-signals.kanban-card>
          </x-signals.kanban-column>
          <x-signals.kanban-column title="Completed" :count="0">
            <div class="s-kanban-placeholder"></div>
          </x-signals.kanban-column>
        </x-signals.kanban>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  95. TREE VIEW                                                --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Tree View</div>
      <div class="ref-note">Hierarchical expandable tree for category browsing. Uses <code>&lt;x-signals.tree-view&gt;</code>. Click arrows to expand/collapse.</div>
      <div class="ref-demo" style="max-width: 350px;">
        <x-signals.tree-view
          :selectable="true"
          :items="[
              ['label' => 'Audio', 'children' => [
                  ['label' => 'Speakers', 'children' => [
                      ['label' => 'Line Array'],
                      ['label' => 'Monitors'],
                      ['label' => 'Subwoofers'],
                  ]],
                  ['label' => 'Mixers'],
                  ['label' => 'Microphones'],
              ]],
              ['label' => 'Lighting', 'children' => [
                  ['label' => 'Moving Heads'],
                  ['label' => 'LED Wash'],
                  ['label' => 'Control'],
              ]],
              ['label' => 'Staging'],
          ]"
        />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  96. DATA LIST                                                --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Data List</div>
      <div class="ref-note">Key-value pair display for detail views. Uses <code>&lt;x-signals.data-list&gt;</code>. Supports horizontal, vertical, and grid layouts.</div>
      <div class="ref-subsection">
        <div class="ref-subsection-title">Horizontal (default)</div>
        <div class="ref-demo">
          <x-signals.data-list :items="[
              ['label' => 'Reference', 'value' => 'OPP-2026-1042'],
              ['label' => 'Subject', 'value' => 'Summer Festival 2026'],
              ['label' => 'Member', 'value' => 'Acme Events Ltd'],
              ['label' => 'Status', 'value' => 'Confirmed'],
              ['label' => 'Total', 'value' => '£12,500.00'],
          ]" />
        </div>
      </div>
      <div class="ref-subsection">
        <div class="ref-subsection-title">Grid layout</div>
        <div class="ref-demo">
          <x-signals.data-list layout="grid" :items="[
              ['label' => 'Company', 'value' => 'Acme Events Ltd'],
              ['label' => 'Email', 'value' => 'info@acme.co.uk'],
              ['label' => 'Phone', 'value' => '+44 20 7946 0958'],
              ['label' => 'Type', 'value' => 'Organisation'],
              ['label' => 'Created', 'value' => '15 Jan 2026'],
              ['label' => 'Last Active', 'value' => '3 days ago'],
          ]" />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  97. SPARKLINE                                                --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Sparkline</div>
      <div class="ref-note">Tiny inline trend line for dashboard cards and table cells. Uses <code>&lt;x-signals.sparkline&gt;</code>. Pure SVG, no chart library.</div>
      <div class="ref-demo">
        <div class="ref-row" style="gap: 24px;">
          <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-family: var(--font-display); font-size: 12px; color: var(--text-muted);">Revenue</span>
            <x-signals.sparkline :data="[12, 18, 15, 22, 28, 25, 32]" color="green" size="md" />
            <span style="font-family: var(--font-mono); font-size: 11px; color: var(--green);">+14%</span>
          </div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-family: var(--font-display); font-size: 12px; color: var(--text-muted);">Returns</span>
            <x-signals.sparkline :data="[8, 12, 10, 14, 11, 9, 6]" color="red" size="md" />
            <span style="font-family: var(--font-mono); font-size: 11px; color: var(--red);">-25%</span>
          </div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-family: var(--font-display); font-size: 12px; color: var(--text-muted);">Utilisation</span>
            <x-signals.sparkline :data="[50, 52, 48, 51, 49, 50, 51]" color="blue" size="sm" />
            <span style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted);">~50%</span>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  98. PHOTO GALLERY                                            --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">Photo Gallery</div>
      <div class="ref-note">Grid of thumbnails with lightbox overlay. Uses <code>&lt;x-signals.gallery&gt;</code>. Click to open lightbox, arrow keys to navigate.</div>
      <div class="ref-demo" style="max-width: 500px;">
        <x-signals.gallery
          :columns="3"
          :images="[
              ['src' => 'https://picsum.photos/seed/sig1/400/400', 'alt' => 'Equipment 1', 'caption' => 'Line array speaker system'],
              ['src' => 'https://picsum.photos/seed/sig2/400/400', 'alt' => 'Equipment 2', 'caption' => 'LED moving head wash'],
              ['src' => 'https://picsum.photos/seed/sig3/400/400', 'alt' => 'Equipment 3', 'caption' => 'Digital mixing console'],
              ['src' => 'https://picsum.photos/seed/sig4/400/400', 'alt' => 'Equipment 4', 'caption' => 'Stage lighting rig'],
              ['src' => 'https://picsum.photos/seed/sig5/400/400', 'alt' => 'Equipment 5', 'caption' => 'Wireless microphone kit'],
              ['src' => 'https://picsum.photos/seed/sig6/400/400', 'alt' => 'Equipment 6', 'caption' => 'Truss and rigging'],
          ]"
        />
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Auto-assign IDs to section titles
    document.querySelectorAll('.ref-section-title').forEach(function (el) {
        var text = el.textContent.trim();
        var slug = text.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-').replace(/-+$/, '').replace(/^-+/, '');
        el.id = slug;
    });

    // Populate the right sidebar TOC
    var toc = document.querySelector('.docs-toc');
    if (!toc) return;

    var titles = document.querySelectorAll('.ref-section-title[id]');
    if (titles.length === 0) return;

    var tocTitle = document.createElement('div');
    tocTitle.className = 'docs-toc-title';
    tocTitle.textContent = 'On This Page';

    var tocNav = document.createElement('nav');
    tocNav.className = 'docs-toc-nav';

    titles.forEach(function (el) {
        var a = document.createElement('a');
        a.href = '#' + el.id;
        a.className = 'docs-toc-link';
        a.textContent = el.textContent.trim();
        tocNav.appendChild(a);
    });

    toc.insertBefore(tocNav, toc.firstChild);
    toc.insertBefore(tocTitle, toc.firstChild);

    // Scrollspy: highlight active TOC link
    var tocLinks = tocNav.querySelectorAll('.docs-toc-link');
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                tocLinks.forEach(function (l) { l.classList.remove('active'); });
                var active = tocNav.querySelector('a[href="#' + entry.target.id + '"]');
                if (active) active.classList.add('active');
            }
        });
    }, { rootMargin: '0px 0px -80% 0px', threshold: 0.1 });

    titles.forEach(function (el) { observer.observe(el); });
});
</script>
