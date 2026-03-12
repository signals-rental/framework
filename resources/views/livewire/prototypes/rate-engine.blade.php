<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Rate Definitions & Rate Engine')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  RATE ENGINE TOKENS                                               */
  /* ================================================================ */

  :root {
    --re-bg: var(--content-bg);
    --re-panel: var(--card-bg);
    --re-surface: var(--base);
    --re-border: var(--card-border);
    --re-border-subtle: var(--grey-border);
    --re-text: var(--text-primary);
    --re-text-secondary: var(--text-secondary);
    --re-text-muted: var(--text-muted);
    --re-accent: var(--green);
    --re-accent-dim: var(--green-muted);
    --re-hover: rgba(0, 0, 0, 0.04);
    --re-shadow: var(--shadow-card);
    --re-period-color: var(--green);
    --re-period-bg: rgba(5, 150, 105, 0.08);
    --re-usage-color: var(--amber);
    --re-usage-bg: rgba(217, 119, 6, 0.08);
    --re-fixed-color: var(--blue);
    --re-fixed-bg: rgba(37, 99, 235, 0.08);
    --re-hybrid-color: var(--violet);
    --re-hybrid-bg: rgba(124, 58, 237, 0.08);
    --re-green-bg: #ecfdf3; --re-green-bdr: #bbf7d0;
    --re-amber-bg: #fffbeb; --re-amber-bdr: #fde68a;
    --re-blue-bg: #eff6ff; --re-blue-bdr: #bfdbfe;
    --re-violet-bg: #f5f3ff; --re-violet-bdr: #ddd6fe;
    --re-shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.1);
    --re-input-bg: var(--white);
    --re-input-border: var(--grey-border);
  }

  .dark {
    --re-bg: var(--content-bg);
    --re-panel: var(--card-bg);
    --re-surface: var(--navy-mid);
    --re-border: var(--card-border);
    --re-border-subtle: #283040;
    --re-text: var(--text-primary);
    --re-text-secondary: var(--text-secondary);
    --re-text-muted: var(--text-muted);
    --re-accent: var(--green);
    --re-accent-dim: rgba(5, 150, 105, 0.12);
    --re-hover: rgba(255, 255, 255, 0.06);
    --re-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --re-period-bg: rgba(5, 150, 105, 0.15);
    --re-usage-bg: rgba(217, 119, 6, 0.15);
    --re-fixed-bg: rgba(37, 99, 235, 0.15);
    --re-hybrid-bg: rgba(124, 58, 237, 0.15);
    --re-green-bg: rgba(22, 163, 74, 0.15); --re-green-bdr: rgba(22, 163, 74, 0.3);
    --re-amber-bg: rgba(217, 119, 6, 0.15); --re-amber-bdr: rgba(217, 119, 6, 0.3);
    --re-blue-bg: rgba(37, 99, 235, 0.15); --re-blue-bdr: rgba(37, 99, 235, 0.3);
    --re-violet-bg: rgba(124, 58, 237, 0.15); --re-violet-bdr: rgba(124, 58, 237, 0.3);
    --re-shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.4);
    --re-input-bg: var(--navy-mid);
    --re-input-border: #283040;
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */

  .re-page {
    display: flex;
    flex-direction: column;
    flex: 1 1 0;
    min-height: 0;
    overflow-y: auto;
    font-family: var(--font-mono);
    font-size: 12px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
    padding-bottom: 40px;
  }

  /* ================================================================ */
  /*  PAGE HEADER                                                      */
  /* ================================================================ */

  .re-ph {
    padding: 18px 24px 0;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-shrink: 0;
  }

  .re-bc {
    font-size: 11px;
    color: var(--re-text-muted);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .re-bc a { color: var(--re-text-muted); text-decoration: none; }
  .re-bc a:hover { color: var(--re-text-secondary); }

  .re-ph-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--re-text);
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .re-ph-meta {
    font-size: 11px;
    color: var(--re-text-muted);
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 3px;
  }

  .re-ph-meta span { display: flex; align-items: center; gap: 4px; }
  .re-ph-act { display: flex; gap: 8px; align-items: center; }

  /* ================================================================ */
  /*  BADGES & BUTTONS                                                 */
  /* ================================================================ */

  .re-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 3px 10px;
    border-radius: 3px;
  }

  .re-badge-period { background: var(--re-green-bg); color: var(--re-period-color); border: 1px solid var(--re-green-bdr); }
  .re-badge-usage  { background: var(--re-amber-bg); color: var(--re-usage-color); border: 1px solid var(--re-amber-bdr); }
  .re-badge-fixed  { background: var(--re-blue-bg); color: var(--re-fixed-color); border: 1px solid var(--re-blue-bdr); }
  .re-badge-hybrid { background: var(--re-violet-bg); color: var(--re-hybrid-color); border: 1px solid var(--re-violet-bdr); }

  .re-bdot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

  .re-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    border: 1px solid var(--re-border);
    background: var(--re-panel);
    color: var(--re-text-secondary);
    transition: all 0.15s;
    white-space: nowrap;
    box-shadow: var(--re-shadow);
    border-radius: 4px;
  }

  .re-btn:hover { background: var(--re-hover); color: var(--re-text); border-color: var(--navy-light); }
  .re-btn svg { width: 14px; height: 14px; }

  .re-btn-primary {
    background: var(--re-accent);
    color: var(--white);
    border-color: var(--re-accent);
  }

  .re-btn-primary:hover { background: #047857; border-color: #047857; color: var(--white); }

  .re-btn-sm {
    padding: 4px 10px;
    font-size: 10px;
  }

  .re-btn-danger {
    color: var(--red);
    border-color: rgba(220, 38, 38, 0.3);
  }

  .re-btn-danger:hover {
    background: rgba(220, 38, 38, 0.08);
    border-color: var(--red);
    color: var(--red);
  }

  /* ================================================================ */
  /*  PRESET GRID                                                      */
  /* ================================================================ */

  .re-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    padding: 16px 24px;
  }

  .re-card {
    background: var(--re-panel);
    border: 1px solid var(--re-border);
    border-radius: 6px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.15s;
    box-shadow: var(--re-shadow);
    position: relative;
  }

  .re-card:hover {
    border-color: var(--re-accent);
    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.1);
    transform: translateY(-1px);
  }

  .re-card-name {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 700;
    color: var(--re-text);
    margin-bottom: 8px;
    letter-spacing: -0.02em;
  }

  .re-card-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 6px;
  }

  .re-card-period {
    font-size: 11px;
    color: var(--re-text-muted);
  }

  .re-card-indicators {
    display: flex;
    gap: 4px;
  }

  .re-card-ind {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 3px;
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.02em;
  }

  .re-card-ind-m { background: rgba(37, 99, 235, 0.1); color: var(--blue); }
  .re-card-ind-f { background: rgba(124, 58, 237, 0.1); color: var(--violet); }

  /* ================================================================ */
  /*  EDITOR LAYOUT                                                    */
  /* ================================================================ */

  .re-editor {
    padding: 16px 24px;
    display: flex;
    gap: 20px;
  }

  .re-editor-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .re-editor-sidebar {
    width: 380px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  /* ================================================================ */
  /*  SECTIONS                                                         */
  /* ================================================================ */

  .re-section {
    background: var(--re-panel);
    border: 1px solid var(--re-border);
    border-radius: 6px;
    box-shadow: var(--re-shadow);
  }

  .re-section-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--re-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .re-section-title {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--re-text);
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .re-section-title svg { width: 14px; height: 14px; color: var(--re-text-muted); }

  .re-section-body {
    padding: 16px;
  }

  /* ================================================================ */
  /*  FORM ELEMENTS                                                    */
  /* ================================================================ */

  .re-label {
    display: block;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--re-text-secondary);
    margin-bottom: 6px;
  }

  .re-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--re-input-border);
    border-radius: 4px;
    background: var(--re-input-bg);
    color: var(--re-text);
    font-family: var(--font-mono);
    font-size: 12px;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
  }

  .re-input:focus { border-color: var(--re-accent); }

  .re-input::placeholder { color: var(--re-text-muted); }

  .re-textarea {
    resize: vertical;
    min-height: 60px;
  }

  .re-field-group {
    margin-bottom: 14px;
  }

  .re-field-group:last-child { margin-bottom: 0; }

  .re-field-row {
    display: flex;
    gap: 12px;
  }

  .re-field-row > * { flex: 1; }

  /* ================================================================ */
  /*  STRATEGY SELECTOR                                                */
  /* ================================================================ */

  .re-strategies {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
  }

  .re-strategy-card {
    background: var(--re-surface);
    border: 2px solid var(--re-border);
    border-radius: 6px;
    padding: 14px 12px;
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
  }

  .re-strategy-card:hover { border-color: var(--re-text-muted); }

  .re-strategy-card.re-strategy-selected {
    border-color: var(--re-accent);
    background: var(--re-accent-dim);
  }

  .re-strategy-icon {
    width: 32px;
    height: 32px;
    margin: 0 auto 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
  }

  .re-strategy-icon svg { width: 18px; height: 18px; }

  .re-strategy-icon-period { background: var(--re-period-bg); color: var(--re-period-color); }
  .re-strategy-icon-usage  { background: var(--re-usage-bg); color: var(--re-usage-color); }
  .re-strategy-icon-fixed  { background: var(--re-fixed-bg); color: var(--re-fixed-color); }
  .re-strategy-icon-hybrid { background: var(--re-hybrid-bg); color: var(--re-hybrid-color); }

  .re-strategy-name {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 700;
    color: var(--re-text);
    margin-bottom: 4px;
  }

  .re-strategy-desc {
    font-size: 10px;
    color: var(--re-text-muted);
    line-height: 1.4;
  }

  /* ================================================================ */
  /*  RADIO BUTTONS                                                    */
  /* ================================================================ */

  .re-radios {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .re-radio {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: var(--re-surface);
    border: 1px solid var(--re-border);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 500;
    color: var(--re-text-secondary);
  }

  .re-radio:hover { border-color: var(--re-text-muted); }

  .re-radio.re-radio-active {
    border-color: var(--re-accent);
    background: var(--re-accent-dim);
    color: var(--re-accent);
    font-weight: 600;
  }

  .re-radio-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid var(--re-border);
    position: relative;
    flex-shrink: 0;
    transition: all 0.15s;
  }

  .re-radio-active .re-radio-dot {
    border-color: var(--re-accent);
  }

  .re-radio-active .re-radio-dot::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--re-accent);
  }

  /* ================================================================ */
  /*  TOGGLE SWITCH                                                    */
  /* ================================================================ */

  .re-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
  }

  .re-toggle-row + .re-toggle-row {
    border-top: 1px solid var(--re-border-subtle);
  }

  .re-toggle-label {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--re-text);
  }

  .re-toggle {
    width: 36px;
    height: 20px;
    border-radius: 10px;
    background: var(--re-border);
    cursor: pointer;
    position: relative;
    transition: background 0.2s;
    flex-shrink: 0;
  }

  .re-toggle.re-toggle-on { background: var(--re-accent); }

  .re-toggle-knob {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--white);
    transition: transform 0.2s;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
  }

  .re-toggle-on .re-toggle-knob { transform: translateX(16px); }

  /* ================================================================ */
  /*  TABLES                                                           */
  /* ================================================================ */

  .re-table {
    width: 100%;
    border-collapse: collapse;
  }

  .re-table th {
    text-align: left;
    padding: 8px 12px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--re-text-muted);
    background: var(--table-header-bg);
    border-bottom: 1px solid var(--re-border);
  }

  .re-table td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--re-border-subtle);
    color: var(--re-text);
    vertical-align: middle;
  }

  .re-table tbody tr { transition: background 0.08s; }
  .re-table tbody tr:hover { background: var(--table-row-hover); }
  .re-table tbody tr:last-child td { border-bottom: none; }

  .re-table-input {
    width: 100%;
    padding: 5px 8px;
    border: 1px solid var(--re-input-border);
    border-radius: 3px;
    background: var(--re-input-bg);
    color: var(--re-text);
    font-family: var(--font-mono);
    font-size: 11px;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
  }

  .re-table-input:focus { border-color: var(--re-accent); }

  .re-table-actions {
    display: flex;
    gap: 6px;
    padding: 8px 12px;
    border-top: 1px solid var(--re-border-subtle);
  }

  /* ================================================================ */
  /*  TIME OPTIONS                                                     */
  /* ================================================================ */

  .re-time-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .re-time-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--re-input-border);
    border-radius: 4px;
    background: var(--re-input-bg);
    color: var(--re-text);
    font-family: var(--font-mono);
    font-size: 12px;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
  }

  .re-time-input:focus { border-color: var(--re-accent); }

  /* ================================================================ */
  /*  CALCULATOR PANEL                                                 */
  /* ================================================================ */

  .re-calc {
    position: sticky;
    top: 16px;
  }

  .re-calc-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }

  .re-calc-inputs .re-field-group:first-child {
    grid-column: 1 / -1;
  }

  .re-calc-result {
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid var(--re-border-subtle);
  }

  .re-calc-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    font-size: 11px;
    color: var(--re-text-secondary);
  }

  .re-calc-line-label { flex: 1; }
  .re-calc-line-value { font-family: var(--font-mono); text-align: right; }

  .re-calc-subtotal {
    border-top: 1px solid var(--re-border-subtle);
    margin-top: 6px;
    padding-top: 6px;
    font-weight: 600;
    color: var(--re-text);
  }

  .re-calc-total {
    border-top: 2px solid var(--re-border);
    margin-top: 6px;
    padding-top: 8px;
    font-family: var(--font-display);
    font-size: 16px;
    font-weight: 700;
    color: var(--re-text);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .re-calc-total-amount {
    font-size: 18px;
    color: var(--re-accent);
  }

  .re-calc-breakdown-header {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--re-text-muted);
    margin-bottom: 8px;
    margin-top: 12px;
  }

  /* ================================================================ */
  /*  PRODUCT RATES TABLE                                              */
  /* ================================================================ */

  .re-products {
    margin: 0 24px;
  }

  .re-products .re-table td { font-size: 11px; }

  .re-priority-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 600;
    background: var(--re-surface);
    border: 1px solid var(--re-border-subtle);
    color: var(--re-text-muted);
  }

  .re-seasonal-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 9px;
    padding: 2px 6px;
    border-radius: 3px;
    background: var(--re-amber-bg);
    color: var(--re-usage-color);
    border: 1px solid var(--re-amber-bdr);
    font-family: var(--font-display);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-left: 6px;
  }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */

  @keyframes re-fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .re-animate { animation: re-fadeIn 0.2s ease both; }

  .re-card { animation: re-fadeIn 0.2s ease both; }
  .re-card:nth-child(1) { animation-delay: 0.02s; }
  .re-card:nth-child(2) { animation-delay: 0.04s; }
  .re-card:nth-child(3) { animation-delay: 0.06s; }
  .re-card:nth-child(4) { animation-delay: 0.08s; }
  .re-card:nth-child(5) { animation-delay: 0.10s; }
  .re-card:nth-child(6) { animation-delay: 0.12s; }
  .re-card:nth-child(7) { animation-delay: 0.14s; }
  .re-card:nth-child(8) { animation-delay: 0.16s; }
  .re-card:nth-child(9) { animation-delay: 0.18s; }
  .re-card:nth-child(10) { animation-delay: 0.20s; }
  .re-card:nth-child(11) { animation-delay: 0.22s; }
  .re-card:nth-child(12) { animation-delay: 0.24s; }

  /* ================================================================ */
  /*  RESPONSIVE                                                       */
  /* ================================================================ */

  @media (max-width: 1200px) {
    .re-editor { flex-direction: column; }
    .re-editor-sidebar { width: 100%; }
  }

  @media (max-width: 900px) {
    .re-grid { grid-template-columns: repeat(2, 1fr); }
    .re-strategies { grid-template-columns: repeat(2, 1fr); }
  }

  @media (max-width: 600px) {
    .re-grid { grid-template-columns: 1fr; }
    .re-strategies { grid-template-columns: 1fr; }
  }
</style>

<div class="re-page" x-data="rateEngine()">

  {{-- ============================================================ --}}
  {{--  SUBNAV                                                       --}}
  {{-- ============================================================ --}}
  <nav class="app-subnav">
    <div class="flex h-full items-center gap-0">
      <a href="#" class="subnav-link active">Rate Definitions</a>
      <a href="#" class="subnav-link">Rate Engine</a>
      <a href="#" class="subnav-link">Price Lists</a>
    </div>
    <span class="ml-auto font-mono text-[9px] uppercase tracking-[0.06em] text-[var(--text-muted)]">
      12 Presets
    </span>
  </nav>

  {{-- ============================================================ --}}
  {{--  PAGE HEADER                                                  --}}
  {{-- ============================================================ --}}
  <div class="re-ph">
    <div>
      <div class="re-bc">
        <a href="#">Settings</a>
        <span style="color:var(--re-text-muted);font-size:10px">&rsaquo;</span>
        <a href="#" x-show="view === 'editor'" @click.prevent="view = 'grid'; selectedPreset = null">Rate Definitions</a>
        <span x-show="view === 'editor'" style="color:var(--re-text-muted);font-size:10px">&rsaquo;</span>
        <span style="color:var(--re-text);font-weight:500" x-text="view === 'grid' ? 'Rate Definitions' : definition.name"></span>
      </div>
      <div class="re-ph-title">
        <template x-if="view === 'grid'">
          <span>Rate Definitions</span>
        </template>
        <template x-if="view === 'editor'">
          <span x-text="definition.name"></span>
        </template>
        <span class="re-badge" :class="'re-badge-' + (view === 'editor' ? definition.strategy : 'period')">
          <span class="re-bdot"></span>
          <span x-text="view === 'editor' ? strategyLabel(definition.strategy) : 'Rate Engine'"></span>
        </span>
      </div>
      <div class="re-ph-meta">
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
          <span x-text="view === 'grid' ? '12 presets' : strategyLabel(definition.strategy) + ' strategy'"></span>
        </span>
        <span x-show="view === 'editor' && definition.strategy !== 'fixed'">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span x-text="basePeriodLabel(definition.base_period)"></span>
        </span>
      </div>
    </div>
    <div class="re-ph-act">
      <template x-if="view === 'editor'">
        <button class="re-btn" @click="view = 'grid'; selectedPreset = null">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
          Back to Grid
        </button>
      </template>
      <button class="re-btn re-btn-primary" @click="createNew()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Create Rate Definition
      </button>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{--  PRESET GRID VIEW                                             --}}
  {{-- ============================================================ --}}
  <template x-if="view === 'grid'">
    <div class="re-grid">
      <template x-for="(preset, index) in presets" :key="index">
        <div class="re-card" @click="openPreset(index)">
          <div class="re-card-name" x-text="preset.name"></div>
          <div style="margin-bottom:8px">
            <span class="re-badge" :class="'re-badge-' + preset.strategy">
              <span class="re-bdot"></span>
              <span x-text="strategyLabel(preset.strategy)"></span>
            </span>
          </div>
          <div class="re-card-row">
            <span class="re-card-period" x-text="preset.base_period ? basePeriodLabel(preset.base_period) : '\u2014'"></span>
            <div class="re-card-indicators">
              <template x-if="preset.has_multipliers">
                <span class="re-card-ind re-card-ind-m" title="Has Multipliers">M</span>
              </template>
              <template x-if="preset.has_factors">
                <span class="re-card-ind re-card-ind-f" title="Has Factors">F</span>
              </template>
            </div>
          </div>
        </div>
      </template>
    </div>
  </template>

  {{-- ============================================================ --}}
  {{--  EDITOR VIEW                                                  --}}
  {{-- ============================================================ --}}
  <template x-if="view === 'editor'">
    <div>
      <div class="re-editor">

        {{-- ======================================================== --}}
        {{--  MAIN COLUMN                                              --}}
        {{-- ======================================================== --}}
        <div class="re-editor-main">

          {{-- Name & Description --}}
          <div class="re-section re-animate">
            <div class="re-section-header">
              <div class="re-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Definition
              </div>
            </div>
            <div class="re-section-body">
              <div class="re-field-group">
                <label class="re-label">Name</label>
                <input type="text" class="re-input" x-model="definition.name" placeholder="Rate definition name">
              </div>
              <div class="re-field-group">
                <label class="re-label">Description</label>
                <textarea class="re-input re-textarea" x-model="definition.description" placeholder="Optional description of this rate definition"></textarea>
              </div>
            </div>
          </div>

          {{-- Strategy Selector --}}
          <div class="re-section re-animate" style="animation-delay: 0.05s">
            <div class="re-section-header">
              <div class="re-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                Strategy
              </div>
            </div>
            <div class="re-section-body">
              <div class="re-strategies">
                <template x-for="s in strategies" :key="s.id">
                  <div class="re-strategy-card"
                       :class="{ 're-strategy-selected': definition.strategy === s.id }"
                       @click="definition.strategy = s.id">
                    <div class="re-strategy-icon" :class="'re-strategy-icon-' + s.id">
                      <svg x-show="s.id === 'period'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                      <svg x-show="s.id === 'usage'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                      <svg x-show="s.id === 'fixed'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                      <svg x-show="s.id === 'hybrid'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    </div>
                    <div class="re-strategy-name" x-text="s.name"></div>
                    <div class="re-strategy-desc" x-text="s.description"></div>
                  </div>
                </template>
              </div>
            </div>
          </div>

          {{-- Base Period Selector --}}
          <div class="re-section re-animate" style="animation-delay: 0.1s" x-show="definition.strategy !== 'fixed'" x-transition>
            <div class="re-section-header">
              <div class="re-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Base Period
              </div>
            </div>
            <div class="re-section-body">
              <div class="re-radios">
                <template x-for="bp in basePeriods" :key="bp.id">
                  <div class="re-radio"
                       :class="{ 're-radio-active': definition.base_period === bp.id }"
                       @click="definition.base_period = bp.id">
                    <span class="re-radio-dot"></span>
                    <span x-text="bp.label"></span>
                  </div>
                </template>
              </div>
            </div>
          </div>

          {{-- Modifier Toggles --}}
          <div class="re-section re-animate" style="animation-delay: 0.15s">
            <div class="re-section-header">
              <div class="re-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                Modifiers
              </div>
            </div>
            <div class="re-section-body">
              <div class="re-toggle-row">
                <span class="re-toggle-label">Enable Multiplier</span>
                <div class="re-toggle" :class="{ 're-toggle-on': definition.enable_multiplier }" @click="definition.enable_multiplier = !definition.enable_multiplier">
                  <div class="re-toggle-knob"></div>
                </div>
              </div>
              <div class="re-toggle-row">
                <span class="re-toggle-label">Enable Factor</span>
                <div class="re-toggle" :class="{ 're-toggle-on': definition.enable_factor }" @click="definition.enable_factor = !definition.enable_factor">
                  <div class="re-toggle-knob"></div>
                </div>
              </div>
            </div>
          </div>

          {{-- Time Options --}}
          <div class="re-section re-animate" style="animation-delay: 0.2s" x-show="definition.strategy === 'period' || definition.strategy === 'usage'" x-transition>
            <div class="re-section-header">
              <div class="re-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Time Options
              </div>
            </div>
            <div class="re-section-body">
              <div class="re-field-group">
                <label class="re-label">Day Type</label>
                <div class="re-radios">
                  <div class="re-radio" :class="{ 're-radio-active': definition.day_type === '24_hour' }" @click="definition.day_type = '24_hour'">
                    <span class="re-radio-dot"></span>
                    24 Hour
                  </div>
                  <div class="re-radio" :class="{ 're-radio-active': definition.day_type === 'business_hours' }" @click="definition.day_type = 'business_hours'">
                    <span class="re-radio-dot"></span>
                    Business Hours
                  </div>
                </div>
              </div>

              <div x-show="definition.day_type === 'business_hours'" x-transition>
                <div class="re-field-row" style="margin-bottom: 14px">
                  <div class="re-field-group">
                    <label class="re-label">Start Time</label>
                    <input type="time" class="re-input re-time-input" x-model="definition.business_start">
                  </div>
                  <div class="re-field-group">
                    <label class="re-label">End Time</label>
                    <input type="time" class="re-input re-time-input" x-model="definition.business_end">
                  </div>
                </div>
              </div>

              <div class="re-time-grid">
                <div class="re-field-group">
                  <label class="re-label">Rental Days per Week</label>
                  <input type="number" class="re-input" x-model.number="definition.rental_days_per_week" min="1" max="7">
                </div>
                <div class="re-field-group">
                  <label class="re-label">Leeway Minutes</label>
                  <input type="number" class="re-input" x-model.number="definition.leeway_minutes" min="0">
                </div>
                <div class="re-field-group">
                  <label class="re-label">First Day Cut-off</label>
                  <input type="time" class="re-input re-time-input" x-model="definition.first_day_cutoff">
                </div>
                <div class="re-field-group">
                  <label class="re-label">Last Day Cut-off</label>
                  <input type="time" class="re-input re-time-input" x-model="definition.last_day_cutoff">
                </div>
              </div>
            </div>
          </div>

          {{-- Multiplier Table --}}
          <div class="re-section re-animate" style="animation-delay: 0.25s" x-show="definition.enable_multiplier" x-transition>
            <div class="re-section-header">
              <div class="re-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Multiplier Table
              </div>
              <span style="font-size:10px;color:var(--re-text-muted)" x-text="definition.multipliers.length + ' rows'"></span>
            </div>
            <table class="re-table">
              <thead>
                <tr>
                  <th style="width:50%">Period Number</th>
                  <th>Multiplier</th>
                  <th style="width:60px"></th>
                </tr>
              </thead>
              <tbody>
                <template x-for="(row, i) in definition.multipliers" :key="i">
                  <tr>
                    <td>
                      <input type="text" class="re-table-input" x-model="row.period" :placeholder="'Day ' + (i + 1)">
                    </td>
                    <td>
                      <input type="number" class="re-table-input" x-model.number="row.multiplier" step="0.1" min="0">
                    </td>
                    <td style="text-align:center">
                      <button class="re-btn re-btn-sm re-btn-danger" @click="removeMultiplierRow(i)" x-show="definition.multipliers.length > 1">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                      </button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
            <div class="re-table-actions">
              <button class="re-btn re-btn-sm" @click="addMultiplierRow()">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Row
              </button>
            </div>
          </div>

          {{-- Factor Table --}}
          <div class="re-section re-animate" style="animation-delay: 0.3s" x-show="definition.enable_factor" x-transition>
            <div class="re-section-header">
              <div class="re-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                Factor Table
              </div>
              <span style="font-size:10px;color:var(--re-text-muted)" x-text="definition.factors.length + ' rows'"></span>
            </div>
            <table class="re-table">
              <thead>
                <tr>
                  <th>Range From</th>
                  <th>Range To</th>
                  <th>Factor</th>
                  <th style="width:60px"></th>
                </tr>
              </thead>
              <tbody>
                <template x-for="(row, i) in definition.factors" :key="i">
                  <tr>
                    <td>
                      <input type="number" class="re-table-input" x-model.number="row.from" min="1">
                    </td>
                    <td>
                      <input type="text" class="re-table-input" x-model="row.to" placeholder="\u221e">
                    </td>
                    <td>
                      <input type="number" class="re-table-input" x-model.number="row.factor" step="0.1" min="0">
                    </td>
                    <td style="text-align:center">
                      <button class="re-btn re-btn-sm re-btn-danger" @click="removeFactorRow(i)" x-show="definition.factors.length > 1">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                      </button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
            <div class="re-table-actions">
              <button class="re-btn re-btn-sm" @click="addFactorRow()">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Row
              </button>
            </div>
          </div>
        </div>

        {{-- ======================================================== --}}
        {{--  SIDEBAR — Calculator                                     --}}
        {{-- ======================================================== --}}
        <div class="re-editor-sidebar">
          <div class="re-calc">

            {{-- Calculator --}}
            <div class="re-section re-animate" style="animation-delay: 0.1s">
              <div class="re-section-header">
                <div class="re-section-title">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="8" y2="10.01"/><line x1="12" y1="10" x2="12" y2="10.01"/><line x1="16" y1="10" x2="16" y2="10.01"/><line x1="8" y1="14" x2="8" y2="14.01"/><line x1="12" y1="14" x2="12" y2="14.01"/><line x1="16" y1="14" x2="16" y2="14.01"/><line x1="8" y1="18" x2="16" y2="18"/></svg>
                  Rate Calculator
                </div>
              </div>
              <div class="re-section-body">
                <div class="re-calc-inputs">
                  <div class="re-field-group">
                    <label class="re-label">Unit Price</label>
                    <div style="position:relative">
                      <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--re-text-muted);font-size:12px;font-weight:600">&pound;</span>
                      <input type="number" class="re-input" style="padding-left:24px" x-model.number="calc.unit_price" step="0.01" min="0" placeholder="0.00">
                    </div>
                  </div>
                  <div class="re-field-group">
                    <label class="re-label">Start Date</label>
                    <input type="date" class="re-input" x-model="calc.start_date">
                  </div>
                  <div class="re-field-group">
                    <label class="re-label">End Date</label>
                    <input type="date" class="re-input" x-model="calc.end_date">
                  </div>
                  <div class="re-field-group">
                    <label class="re-label">Quantity</label>
                    <input type="number" class="re-input" x-model.number="calc.quantity" min="1">
                  </div>
                </div>

                <div style="margin-top:14px">
                  <button class="re-btn re-btn-primary" style="width:100%;justify-content:center" @click="calculate()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Calculate
                  </button>
                </div>

                {{-- Results --}}
                <div x-show="calc.calculated" x-transition class="re-calc-result">
                  <div class="re-calc-breakdown-header">Breakdown</div>

                  {{-- Line items --}}
                  <template x-for="(line, i) in calc.lines" :key="i">
                    <div class="re-calc-line">
                      <span class="re-calc-line-label" x-text="line.label"></span>
                      <span class="re-calc-line-value" x-text="'£' + line.total.toFixed(2)"></span>
                    </div>
                  </template>

                  {{-- Subtotal --}}
                  <div class="re-calc-line re-calc-subtotal">
                    <span class="re-calc-line-label">Subtotal</span>
                    <span class="re-calc-line-value" x-text="'£' + calc.subtotal.toFixed(2)"></span>
                  </div>

                  {{-- Factor --}}
                  <div class="re-calc-line" x-show="calc.factor_text">
                    <span class="re-calc-line-label" x-text="calc.factor_text"></span>
                    <span class="re-calc-line-value" x-text="'£' + calc.factor_total.toFixed(2)"></span>
                  </div>

                  {{-- Total --}}
                  <div class="re-calc-total">
                    <span>Total</span>
                    <span class="re-calc-total-amount" x-text="'£' + calc.total.toFixed(2)"></span>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ============================================================ --}}
      {{--  PRODUCT RATES TABLE                                          --}}
      {{-- ============================================================ --}}
      <div class="re-products" style="margin-top: 4px">
        <div class="re-section re-animate" style="animation-delay: 0.35s">
          <div class="re-section-header">
            <div class="re-section-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 2 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
              Product Rates Using This Definition
            </div>
            <span style="font-size:10px;color:var(--re-text-muted)">5 rates</span>
          </div>
          <table class="re-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Store</th>
                <th>Transaction Type</th>
                <th style="text-align:right">Price</th>
                <th>Valid From</th>
                <th>Valid To</th>
                <th style="text-align:center">Priority</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="(rate, i) in productRates" :key="i">
                <tr>
                  <td style="font-weight:500" x-text="rate.product"></td>
                  <td x-text="rate.store"></td>
                  <td>
                    <span class="re-badge" :class="rate.type === 'Rental' ? 're-badge-period' : 're-badge-fixed'">
                      <span class="re-bdot"></span>
                      <span x-text="rate.type"></span>
                    </span>
                  </td>
                  <td style="text-align:right;font-family:var(--font-mono)" x-text="'£' + rate.price.toFixed(2)"></td>
                  <td x-text="rate.valid_from || '\u2014'"></td>
                  <td>
                    <span x-text="rate.valid_to || '\u2014'"></span>
                    <span x-show="rate.seasonal" class="re-seasonal-badge">Seasonal</span>
                  </td>
                  <td style="text-align:center">
                    <span class="re-priority-badge" x-text="rate.priority"></span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </template>
</div>

@verbatim
<script>
function rateEngine() {
  return {
    view: 'grid',
    selectedPreset: null,

    /* ============================================================ */
    /*  STRATEGY DEFINITIONS                                         */
    /* ============================================================ */

    strategies: [
      { id: 'period', name: 'Period', description: 'Charges per time period (day, hour, etc.)' },
      { id: 'usage', name: 'Usage', description: 'Charges for actual days used within a period' },
      { id: 'fixed', name: 'Fixed', description: 'One-time charge regardless of duration' },
      { id: 'hybrid', name: 'Hybrid', description: 'Fixed charge plus per-period sub-charges' },
    ],

    basePeriods: [
      { id: 'half_hourly', label: 'Half Hourly' },
      { id: 'hourly', label: 'Hourly' },
      { id: 'daily', label: 'Daily' },
      { id: 'weekly', label: 'Weekly' },
      { id: 'monthly', label: 'Monthly' },
    ],

    /* ============================================================ */
    /*  12 CRMS PRESETS                                               */
    /* ============================================================ */

    presets: [
      {
        name: 'Daily Multiplier and Factor',
        strategy: 'period',
        base_period: 'daily',
        has_multipliers: true,
        has_factors: true,
        multipliers: [
          { period: 'Day 1', multiplier: 1.0 },
          { period: 'Day 2', multiplier: 1.0 },
          { period: 'Day 3', multiplier: 0.8 },
          { period: 'Day 4', multiplier: 0.6 },
          { period: 'Day 5+', multiplier: 0.5 },
        ],
        factors: [
          { from: 1, to: '10', factor: 1.0 },
          { from: 11, to: '50', factor: 0.9 },
          { from: 51, to: '\u221e', factor: 0.8 },
        ],
        description: 'Standard rental rate with declining daily multiplier and quantity-based factor discounts.',
      },
      {
        name: 'Daily Rate',
        strategy: 'period',
        base_period: 'daily',
        has_multipliers: false,
        has_factors: false,
        multipliers: [],
        factors: [],
        description: 'Simple per-day rental charge with no modifiers.',
      },
      {
        name: 'Days Used Rate',
        strategy: 'usage',
        base_period: 'daily',
        has_multipliers: false,
        has_factors: false,
        multipliers: [],
        factors: [],
        description: 'Charges only for the actual number of days equipment is used.',
      },
      {
        name: 'Fixed Rate and Factor',
        strategy: 'fixed',
        base_period: null,
        has_multipliers: false,
        has_factors: true,
        multipliers: [],
        factors: [
          { from: 1, to: '10', factor: 1.0 },
          { from: 11, to: '50', factor: 0.9 },
          { from: 51, to: '\u221e', factor: 0.8 },
        ],
        description: 'One-time fixed charge with quantity-based factor discounts.',
      },
      {
        name: 'Fixed Rate and Subs Days',
        strategy: 'hybrid',
        base_period: 'daily',
        has_multipliers: false,
        has_factors: false,
        multipliers: [],
        factors: [],
        description: 'Fixed charge with additional per-day sub-charges for extended periods.',
      },
      {
        name: 'Fixed Rate',
        strategy: 'fixed',
        base_period: null,
        has_multipliers: false,
        has_factors: false,
        multipliers: [],
        factors: [],
        description: 'Simple one-time charge regardless of rental duration.',
      },
      {
        name: 'Half Hourly Rate',
        strategy: 'period',
        base_period: 'half_hourly',
        has_multipliers: false,
        has_factors: false,
        multipliers: [],
        factors: [],
        description: 'Per half-hour rental charge for short-duration hires.',
      },
      {
        name: 'Hourly Multiplier and Factor',
        strategy: 'period',
        base_period: 'hourly',
        has_multipliers: true,
        has_factors: true,
        multipliers: [
          { period: 'Hour 1', multiplier: 1.0 },
          { period: 'Hour 2', multiplier: 1.0 },
          { period: 'Hour 3', multiplier: 0.8 },
          { period: 'Hour 4', multiplier: 0.6 },
          { period: 'Hour 5+', multiplier: 0.5 },
        ],
        factors: [
          { from: 1, to: '10', factor: 1.0 },
          { from: 11, to: '50', factor: 0.9 },
          { from: 51, to: '\u221e', factor: 0.8 },
        ],
        description: 'Hourly rate with declining multiplier and quantity-based factor discounts.',
      },
      {
        name: 'Hourly Rate',
        strategy: 'period',
        base_period: 'hourly',
        has_multipliers: false,
        has_factors: false,
        multipliers: [],
        factors: [],
        description: 'Simple per-hour rental charge with no modifiers.',
      },
      {
        name: 'Monthly Multiplier and Factor',
        strategy: 'period',
        base_period: 'monthly',
        has_multipliers: true,
        has_factors: true,
        multipliers: [
          { period: 'Month 1', multiplier: 1.0 },
          { period: 'Month 2', multiplier: 1.0 },
          { period: 'Month 3', multiplier: 0.9 },
          { period: 'Month 4', multiplier: 0.8 },
          { period: 'Month 5+', multiplier: 0.7 },
        ],
        factors: [
          { from: 1, to: '10', factor: 1.0 },
          { from: 11, to: '50', factor: 0.9 },
          { from: 51, to: '\u221e', factor: 0.8 },
        ],
        description: 'Monthly rate with declining multiplier and quantity-based factor discounts.',
      },
      {
        name: 'Monthly Rate',
        strategy: 'period',
        base_period: 'monthly',
        has_multipliers: false,
        has_factors: false,
        multipliers: [],
        factors: [],
        description: 'Simple per-month rental charge with no modifiers.',
      },
      {
        name: 'Weekly Rate',
        strategy: 'period',
        base_period: 'weekly',
        has_multipliers: false,
        has_factors: false,
        multipliers: [],
        factors: [],
        description: 'Simple per-week rental charge with no modifiers.',
      },
    ],

    /* ============================================================ */
    /*  ACTIVE DEFINITION (editor state)                              */
    /* ============================================================ */

    definition: {
      name: '',
      description: '',
      strategy: 'period',
      base_period: 'daily',
      enable_multiplier: false,
      enable_factor: false,
      day_type: '24_hour',
      business_start: '09:00',
      business_end: '17:00',
      rental_days_per_week: 7,
      leeway_minutes: 0,
      first_day_cutoff: '12:00',
      last_day_cutoff: '12:00',
      multipliers: [],
      factors: [],
    },

    /* ============================================================ */
    /*  CALCULATOR STATE                                              */
    /* ============================================================ */

    calc: {
      unit_price: 50,
      start_date: '2026-03-05',
      end_date: '2026-03-09',
      quantity: 5,
      calculated: false,
      lines: [],
      subtotal: 0,
      factor_text: '',
      factor_total: 0,
      total: 0,
    },

    /* ============================================================ */
    /*  PRODUCT RATES (mock data)                                     */
    /* ============================================================ */

    productRates: [
      { product: 'PA Speaker JBL EON 615', store: 'London', type: 'Rental', price: 50.00, valid_from: null, valid_to: null, priority: 10, seasonal: false },
      { product: 'PA Speaker JBL EON 615', store: 'Manchester', type: 'Rental', price: 45.00, valid_from: null, valid_to: null, priority: 10, seasonal: false },
      { product: 'PA Speaker JBL EON 615', store: 'London', type: 'Rental', price: 40.00, valid_from: '2026-12-01', valid_to: '2027-01-05', priority: 20, seasonal: true },
      { product: 'Lighting Desk Avolites', store: 'All Stores', type: 'Rental', price: 120.00, valid_from: null, valid_to: null, priority: 10, seasonal: false },
      { product: 'Cable 20m XLR', store: 'All Stores', type: 'Sale', price: 25.00, valid_from: null, valid_to: null, priority: 10, seasonal: false },
    ],

    /* ============================================================ */
    /*  HELPERS                                                       */
    /* ============================================================ */

    strategyLabel(id) {
      const map = { period: 'Period', usage: 'Usage', fixed: 'Fixed', hybrid: 'Hybrid' };
      return map[id] || id;
    },

    basePeriodLabel(id) {
      const map = { half_hourly: 'Half Hourly', hourly: 'Hourly', daily: 'Daily', weekly: 'Weekly', monthly: 'Monthly' };
      return map[id] || id || '\u2014';
    },

    /* ============================================================ */
    /*  PRESET GRID ACTIONS                                           */
    /* ============================================================ */

    openPreset(index) {
      const preset = JSON.parse(JSON.stringify(this.presets[index]));
      this.selectedPreset = index;
      this.definition = {
        name: preset.name,
        description: preset.description || '',
        strategy: preset.strategy,
        base_period: preset.base_period || 'daily',
        enable_multiplier: preset.has_multipliers,
        enable_factor: preset.has_factors,
        day_type: '24_hour',
        business_start: '09:00',
        business_end: '17:00',
        rental_days_per_week: 7,
        leeway_minutes: 0,
        first_day_cutoff: '12:00',
        last_day_cutoff: '12:00',
        multipliers: preset.multipliers.length > 0 ? preset.multipliers : [
          { period: 'Day 1', multiplier: 1.0 },
          { period: 'Day 2', multiplier: 1.0 },
          { period: 'Day 3', multiplier: 0.8 },
          { period: 'Day 4', multiplier: 0.6 },
          { period: 'Day 5+', multiplier: 0.5 },
        ],
        factors: preset.factors.length > 0 ? preset.factors : [
          { from: 1, to: '10', factor: 1.0 },
          { from: 11, to: '50', factor: 0.9 },
          { from: 51, to: '\u221e', factor: 0.8 },
        ],
      };

      // Reset calculator
      this.calc.calculated = false;
      this.calc.lines = [];
      this.calc.subtotal = 0;
      this.calc.factor_text = '';
      this.calc.factor_total = 0;
      this.calc.total = 0;

      // Pre-calculate
      this.$nextTick(() => this.calculate());

      this.view = 'editor';
    },

    createNew() {
      this.selectedPreset = null;
      this.definition = {
        name: 'New Rate Definition',
        description: '',
        strategy: 'period',
        base_period: 'daily',
        enable_multiplier: false,
        enable_factor: false,
        day_type: '24_hour',
        business_start: '09:00',
        business_end: '17:00',
        rental_days_per_week: 7,
        leeway_minutes: 0,
        first_day_cutoff: '12:00',
        last_day_cutoff: '12:00',
        multipliers: [
          { period: 'Day 1', multiplier: 1.0 },
          { period: 'Day 2', multiplier: 1.0 },
          { period: 'Day 3', multiplier: 0.8 },
          { period: 'Day 4', multiplier: 0.6 },
          { period: 'Day 5+', multiplier: 0.5 },
        ],
        factors: [
          { from: 1, to: '10', factor: 1.0 },
          { from: 11, to: '50', factor: 0.9 },
          { from: 51, to: '\u221e', factor: 0.8 },
        ],
      };
      this.calc.calculated = false;
      this.calc.lines = [];
      this.view = 'editor';
    },

    /* ============================================================ */
    /*  MULTIPLIER & FACTOR TABLE                                     */
    /* ============================================================ */

    addMultiplierRow() {
      const nextNum = this.definition.multipliers.length + 1;
      const periodUnit = this.basePeriodLabel(this.definition.base_period).replace(/ly$/i, '');
      this.definition.multipliers.push({
        period: periodUnit + ' ' + nextNum + '+',
        multiplier: 0.5,
      });
    },

    removeMultiplierRow(index) {
      this.definition.multipliers.splice(index, 1);
    },

    addFactorRow() {
      const last = this.definition.factors[this.definition.factors.length - 1];
      const nextFrom = last ? (parseInt(last.to) || parseInt(last.from) + 10) + 1 : 1;
      this.definition.factors.push({
        from: nextFrom,
        to: '\u221e',
        factor: 0.7,
      });
    },

    removeFactorRow(index) {
      this.definition.factors.splice(index, 1);
    },

    /* ============================================================ */
    /*  RATE CALCULATOR                                               */
    /* ============================================================ */

    calculate() {
      const unitPrice = parseFloat(this.calc.unit_price) || 0;
      const quantity = parseInt(this.calc.quantity) || 1;
      const startDate = new Date(this.calc.start_date);
      const endDate = new Date(this.calc.end_date);

      if (isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || endDate < startDate) {
        this.calc.calculated = false;
        return;
      }

      const lines = [];
      let subtotal = 0;

      const strategy = this.definition.strategy;

      if (strategy === 'fixed') {
        // Fixed: single charge
        const fixedTotal = unitPrice;
        lines.push({
          label: 'Fixed charge: 1 \u00d7 \u00a3' + unitPrice.toFixed(2),
          total: fixedTotal,
        });
        subtotal = fixedTotal;

      } else if (strategy === 'period' || strategy === 'usage' || strategy === 'hybrid') {
        // Calculate number of periods
        let numPeriods = 1;
        const diffMs = endDate.getTime() - startDate.getTime();

        if (this.definition.base_period === 'daily') {
          numPeriods = Math.ceil(diffMs / (1000 * 60 * 60 * 24)) + 1;
        } else if (this.definition.base_period === 'hourly') {
          numPeriods = Math.max(1, Math.ceil(diffMs / (1000 * 60 * 60)));
        } else if (this.definition.base_period === 'half_hourly') {
          numPeriods = Math.max(1, Math.ceil(diffMs / (1000 * 60 * 30)));
        } else if (this.definition.base_period === 'weekly') {
          numPeriods = Math.max(1, Math.ceil((diffMs / (1000 * 60 * 60 * 24) + 1) / 7));
        } else if (this.definition.base_period === 'monthly') {
          const months = (endDate.getFullYear() - startDate.getFullYear()) * 12 + (endDate.getMonth() - startDate.getMonth());
          numPeriods = Math.max(1, months + 1);
        }

        const periodLabel = this.basePeriodLabel(this.definition.base_period).replace(/ly$/i, '');

        if (strategy === 'hybrid') {
          // Hybrid: fixed charge for period 1, then per-period for remainder
          const fixedAmount = unitPrice;
          lines.push({
            label: periodLabel + ' 1 (fixed): 1 \u00d7 \u00a3' + fixedAmount.toFixed(2) + ' \u00d7 1.0',
            total: fixedAmount,
          });
          subtotal += fixedAmount;

          // Remaining periods at a sub-rate (half price for demo)
          const subRate = unitPrice * 0.5;
          for (let p = 2; p <= numPeriods; p++) {
            const mult = this.getMultiplier(p);
            const periodTotal = subRate * mult;
            lines.push({
              label: periodLabel + ' ' + p + ' (sub): 1 \u00d7 \u00a3' + subRate.toFixed(2) + ' \u00d7 ' + mult.toFixed(1),
              total: periodTotal,
            });
            subtotal += periodTotal;
          }

        } else {
          // Period or Usage
          for (let p = 1; p <= numPeriods; p++) {
            const mult = this.definition.enable_multiplier ? this.getMultiplier(p) : 1.0;
            const periodTotal = unitPrice * mult;
            lines.push({
              label: periodLabel + ' ' + p + ': 1 \u00d7 \u00a3' + unitPrice.toFixed(2) + ' \u00d7 ' + mult.toFixed(1),
              total: periodTotal,
            });
            subtotal += periodTotal;
          }
        }
      }

      // Apply factor
      let factorValue = 1.0;
      let factorText = '';

      if (this.definition.enable_factor && this.definition.factors.length > 0) {
        factorValue = this.getFactor(quantity);
        factorText = 'Quantity ' + quantity + ' \u00d7 Factor ' + factorValue.toFixed(1);
      }

      const factorTotal = subtotal * quantity * factorValue;
      const total = factorTotal;

      this.calc.lines = lines;
      this.calc.subtotal = subtotal;
      this.calc.factor_text = factorText;
      this.calc.factor_total = factorTotal;
      this.calc.total = total;
      this.calc.calculated = true;
    },

    getMultiplier(periodNumber) {
      const multipliers = this.definition.multipliers;
      if (!multipliers || multipliers.length === 0) return 1.0;

      // Direct match by index (1-based)
      if (periodNumber <= multipliers.length) {
        return parseFloat(multipliers[periodNumber - 1].multiplier) || 1.0;
      }

      // Use last row as fallback (the "N+" row)
      return parseFloat(multipliers[multipliers.length - 1].multiplier) || 1.0;
    },

    getFactor(quantity) {
      const factors = this.definition.factors;
      if (!factors || factors.length === 0) return 1.0;

      for (let i = 0; i < factors.length; i++) {
        const from = parseInt(factors[i].from) || 0;
        const toVal = parseInt(factors[i].to);
        const to = isNaN(toVal) ? Infinity : toVal;

        if (quantity >= from && quantity <= to) {
          return parseFloat(factors[i].factor) || 1.0;
        }
      }

      // Default to last factor if beyond all ranges
      return parseFloat(factors[factors.length - 1].factor) || 1.0;
    },
  };
}
</script>
@endverbatim
