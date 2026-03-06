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

</style>

<div class="ref-page">
  <div class="ref-container">

    {{-- ============================================================ --}}
    {{--  HEADER                                                       --}}
    {{-- ============================================================ --}}
    <div class="ref-title">Component Reference</div>
    <div class="ref-subtitle">Signals Framework — Canonical Component Library</div>

    {{-- ============================================================ --}}
    {{--  1. PAGE HEADER                                               --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">1. Page Header</div>
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
      <div class="ref-section-title">2. Buttons</div>

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
        <div class="ref-subsection-title">Small Variant</div>
        <div class="ref-demo">
          <div class="ref-row">
            <button class="s-btn s-btn-sm">Small Default</button>
            <button class="s-btn s-btn-sm s-btn-primary">Small Primary</button>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  3. TOOLBAR CHIPS                                             --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">3. Toolbar Chips</div>
      <div class="ref-note">Toggle chips for toolbar filters. Add .on class for active state.</div>
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

    {{-- ============================================================ --}}
    {{--  4. BADGES                                                    --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">4. Badges</div>
      <div class="ref-note">Use .s-badge with a color modifier. Optional .s-badge-dot for status dot.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <span class="s-badge s-badge-green"><span class="s-badge-dot"></span> Active</span>
          <span class="s-badge s-badge-amber"><span class="s-badge-dot"></span> Pending</span>
          <span class="s-badge s-badge-red"><span class="s-badge-dot"></span> Overdue</span>
          <span class="s-badge s-badge-blue"><span class="s-badge-dot"></span> Processing</span>
          <span class="s-badge s-badge-violet"><span class="s-badge-dot"></span> Archived</span>
        </div>
        <div class="ref-spacer"></div>
        <div class="ref-row">
          <span class="s-badge s-badge-green">Confirmed</span>
          <span class="s-badge s-badge-amber">Draft</span>
          <span class="s-badge s-badge-red">Cancelled</span>
          <span class="s-badge s-badge-blue">In Progress</span>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  5. STATUS CELLS                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">5. Status Cells</div>
      <div class="ref-note">Compact inline status for use inside table cells. Slightly smaller than badges.</div>
      <div class="ref-demo">
        <div class="ref-row">
          <span class="s-status s-status-green"><span class="s-status-dot"></span> Confirmed</span>
          <span class="s-status s-status-amber"><span class="s-status-dot"></span> Provisional</span>
          <span class="s-status s-status-red"><span class="s-status-dot"></span> Cancelled</span>
          <span class="s-status s-status-blue"><span class="s-status-dot"></span> Sub-hired</span>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  6. TABS                                                      --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">6. Tabs</div>
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
      <div class="ref-section-title">7. Table</div>
      <div class="ref-note">Use .s-table-wrap > .s-table. Mono font for IDs/refs/dates/amounts. Use .s-cell-link for clickable refs.</div>
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

    {{-- ============================================================ --}}
    {{--  8. CHECKBOX                                                  --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">8. Checkbox</div>
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
      <div class="ref-section-title">9. Search Input</div>
      <div class="ref-demo">
        <div style="max-width: 300px;">
          <x-signals.search placeholder="Search products..." />
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  10. TOOLBAR                                                  --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">10. Toolbar</div>
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
      <div class="ref-section-title">11. Dropdown / Popover</div>
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
      <div class="ref-section-title">12. Availability Bar</div>
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
      <div class="ref-section-title">13. Product Cell</div>
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
      <div class="ref-section-title">14. Card</div>
      <div class="ref-note">Generic card container. Use .s-card-header + .s-card-body for structured content.</div>
      <div class="ref-demo">
        <div style="max-width: 400px;">
          <x-signals.card title="Recent Activity">
            <x-slot:headerActions>
              <button class="s-btn s-btn-sm s-btn-ghost">View All</button>
            </x-slot:headerActions>
            <p style="color: var(--text-secondary); font-size: 12px;">Card body content goes here. Use for settings panels, detail views, widgets, etc.</p>
          </x-signals.card>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  15. TOAST                                                    --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">15. Toast Notification</div>
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
      <div class="ref-section-title">16. Save Indicator</div>
      <div class="ref-note">Inline pulsing indicator for autosave confirmation. Typically positioned top-right.</div>
      <div class="ref-demo">
        <x-signals.save-indicator text="Saved" />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  17. KEYBOARD HINTS                                           --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">17. Keyboard Hints</div>
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
      <div class="ref-section-title">18. Bulk Action Bar</div>
      <div class="ref-note">Fixed bottom bar when items are selected. Add .visible class to show.</div>
      <div class="ref-demo">
        <x-signals.bulk-bar :count="3" style="position: relative; opacity: 1; transform: none; pointer-events: all; left: 0;">
          <div class="s-bulk-sep"></div>
          <button class="s-bulk-action">Edit Status</button>
          <button class="s-bulk-action">Export</button>
          <button class="s-bulk-action">Duplicate</button>
          <button class="s-bulk-action s-bulk-action-danger">Delete</button>
        </x-signals.bulk-bar>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  19. SUMMARY BAR                                              --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">19. Summary Bar</div>
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
      <div class="ref-section-title">20. Legend / Key</div>
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
      <div class="ref-section-title">21. Tooltip</div>
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
      <div class="ref-section-title">22. Empty State</div>
      <div class="ref-demo">
        <x-signals.empty icon="&#x1f50d;" title="No results found" description="Try adjusting your search or filter criteria" />
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  23. SECTION LABEL                                            --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">23. Section Label</div>
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
      <div class="ref-section-title">24. Color Palette</div>
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
      <div class="ref-section-title">25. Typography</div>
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
      <div class="ref-section-title">26. Version Tree</div>
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
      <div class="ref-section-title">27. Panel</div>
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
      <div class="ref-section-title">28. Event Stream</div>
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
      <div class="ref-section-title">29. State Diagram</div>
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
      <div class="ref-section-title">30. Stat Cards</div>
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
      <div class="ref-section-title">31. Button Dropdown</div>
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
      <div class="ref-section-title">32. Multi-Pane</div>
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
      <div class="ref-section-title">33. Toggle</div>
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
      <div class="ref-section-title">34. Sidebar</div>
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
      <div class="ref-section-title">35. Column Config</div>
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
      <div class="ref-section-title">36. Unsaved Bar</div>
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
      <div class="ref-section-title">37. Toggle Row</div>
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
      <div class="ref-section-title">38. Colour Picker</div>
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
      <div class="ref-section-title">39. Upload Zone</div>
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
      <div class="ref-section-title">40. Matrix</div>
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
      <div class="ref-section-title">41. Status Badge</div>
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
      <div class="ref-section-title">42. Stars</div>
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
      <div class="ref-section-title">43. JSON Viewer</div>
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
      <div class="ref-section-title">44. Parsed View</div>
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
      <div class="ref-section-title">45. Form Section</div>
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
      <div class="ref-section-title">46. Multiplier Table</div>
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
      <div class="ref-section-title">47. Strategy Selector</div>
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
      <div class="ref-section-title">48. Bar Chart</div>
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
      <div class="ref-section-title">49. Qty Bar</div>
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
      <div class="ref-section-title">50. Dispatch Urgency</div>
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
      <div class="ref-section-title">51. Grid Table</div>
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
      <div class="ref-section-title">52. WYSIWYG</div>
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
      <div class="ref-section-title">53. Viz Buttons</div>
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
      <div class="ref-section-title">54. Report Sidebar</div>
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
      <div class="ref-section-title">55. Pin</div>
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
      <div class="ref-section-title">56. Collapsible</div>
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
      <div class="ref-section-title">57. Zoom Controls</div>
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
      <div class="ref-section-title">58. Stepper</div>
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
      <div class="ref-section-title">59. Dropzone</div>
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
      <div class="ref-section-title">60. Format Selector</div>
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
      <div class="ref-section-title">61. Progress Bar</div>
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
    {{--  62. BULK ACTION BAR                                          --}}
    {{-- ============================================================ --}}
    <div class="ref-section">
      <div class="ref-section-title">62. Bulk Action Bar (Icons)</div>
      <div class="ref-note">Icon-enhanced bulk action bar variant. Uses .s-bulk-btn with SVG icons instead of text-only .s-bulk-action buttons (see #18).</div>
      <div class="ref-demo" style="padding: 0; overflow: hidden;">
        <x-signals.bulk-bar :count="3">
          <button class="s-bulk-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export
          </button>
          <button class="s-bulk-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </button>
          <button class="s-bulk-btn s-bulk-btn-danger">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
            Delete
          </button>
        </x-signals.bulk-bar>
      </div>
    </div>
  </div>
</div>
