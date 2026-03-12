<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Shortage Resolution')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  SHORTAGE RESOLUTION TOKENS — maps to brand system in app.css    */
  /* ================================================================ */
  :root {
    --sr-bg: var(--content-bg);
    --sr-panel: var(--card-bg);
    --sr-surface: var(--base);
    --sr-border: var(--card-border);
    --sr-border-subtle: var(--grey-border);
    --sr-text: var(--text-primary);
    --sr-text-secondary: var(--text-secondary);
    --sr-text-muted: var(--text-muted);
    --sr-accent: var(--green);
    --sr-accent-dim: var(--green-muted);
    --sr-hover: rgba(0, 0, 0, 0.03);
    --sr-shadow: var(--shadow-card);
    --sr-critical: var(--red);
    --sr-critical-bg: rgba(220, 38, 38, 0.06);
    --sr-warning: var(--amber);
    --sr-warning-bg: rgba(217, 119, 6, 0.06);
    --sr-resolved: var(--green);
    --sr-resolved-bg: rgba(5, 150, 105, 0.06);
    --sr-info: var(--blue);
    --sr-info-bg: rgba(37, 99, 235, 0.06);
    --sr-violet: var(--violet);
    --sr-violet-bg: rgba(124, 58, 237, 0.06);
    --sr-cyan: var(--cyan);
    --sr-cyan-bg: rgba(8, 145, 178, 0.06);
    --sr-row-hover: var(--table-row-hover);
    --sr-table-border: var(--table-border);
    --sr-table-header-bg: var(--table-header-bg);
    --sr-resolver-quote: var(--violet);
    --sr-resolver-substitution: var(--cyan);
    --sr-resolver-transfer: var(--blue);
    --sr-resolver-dateshift: var(--amber);
    --sr-resolver-partial: var(--grey);
    --sr-resolver-subhire: var(--rose);
    --sr-resolver-waitlist: var(--grey-light);
  }

  .dark {
    --sr-bg: var(--content-bg);
    --sr-panel: var(--card-bg);
    --sr-surface: var(--navy-mid);
    --sr-border: var(--card-border);
    --sr-border-subtle: #283040;
    --sr-text: var(--text-primary);
    --sr-text-secondary: var(--text-secondary);
    --sr-text-muted: var(--text-muted);
    --sr-accent: var(--green);
    --sr-accent-dim: rgba(5, 150, 105, 0.12);
    --sr-hover: rgba(255, 255, 255, 0.04);
    --sr-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    --sr-critical-bg: rgba(220, 38, 38, 0.12);
    --sr-warning-bg: rgba(217, 119, 6, 0.12);
    --sr-resolved-bg: rgba(5, 150, 105, 0.12);
    --sr-info-bg: rgba(37, 99, 235, 0.12);
    --sr-violet-bg: rgba(124, 58, 237, 0.12);
    --sr-cyan-bg: rgba(8, 145, 178, 0.12);
    --sr-row-hover: var(--table-row-hover);
    --sr-table-border: var(--table-border);
    --sr-table-header-bg: var(--table-header-bg);
  }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */
  @keyframes sr-fade-in {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes sr-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
  }

  @keyframes sr-bar-grow {
    from { width: 0; }
  }

  @keyframes sr-slide-down {
    from { opacity: 0; max-height: 0; }
    to { opacity: 1; max-height: 2000px; }
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */
  .sr-page {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 64px);
    background: var(--sr-bg);
    position: relative;
  }

  .sr-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 32px 64px;
    width: 100%;
  }

  /* ================================================================ */
  /*  HEADER                                                           */
  /* ================================================================ */
  .sr-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
  }

  .sr-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .sr-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    color: var(--sr-text);
    letter-spacing: -0.01em;
    line-height: 1;
  }

  .sr-subtitle {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sr-accent);
    margin-top: 2px;
  }

  .sr-header-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--sr-critical-bg);
    color: var(--sr-critical);
    flex-shrink: 0;
  }

  .sr-header-icon svg {
    width: 20px;
    height: 20px;
  }

  /* ================================================================ */
  /*  TAB BAR                                                          */
  /* ================================================================ */
  .sr-tabs {
    display: flex;
    gap: 2px;
    border-bottom: 1px solid var(--sr-border);
    margin-bottom: 24px;
  }

  .sr-tab {
    padding: 10px 20px;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--sr-text-muted);
    cursor: pointer;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    transition: color 0.15s, border-color 0.15s;
    position: relative;
    bottom: -1px;
  }

  .sr-tab:hover {
    color: var(--sr-text-secondary);
  }

  .sr-tab-active {
    color: var(--sr-accent);
    border-bottom-color: var(--sr-accent);
  }

  .sr-tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 10px;
    font-weight: 700;
    border-radius: 9px;
    margin-left: 6px;
  }

  .sr-tab-badge-critical {
    background: var(--sr-critical-bg);
    color: var(--sr-critical);
  }

  .sr-tab-badge-info {
    background: var(--sr-info-bg);
    color: var(--sr-info);
  }

  /* ================================================================ */
  /*  STATS BAR                                                        */
  /* ================================================================ */
  .sr-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 24px;
    animation: sr-fade-in 0.3s ease;
  }

  .sr-stat-card {
    background: var(--sr-panel);
    border: 1px solid var(--sr-border);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .sr-stat-card:hover {
    border-color: var(--sr-border-subtle);
    box-shadow: var(--sr-shadow);
  }

  .sr-stat-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .sr-stat-icon svg {
    width: 18px;
    height: 18px;
  }

  .sr-stat-icon-critical {
    background: var(--sr-critical-bg);
    color: var(--sr-critical);
  }

  .sr-stat-icon-warning {
    background: var(--sr-warning-bg);
    color: var(--sr-warning);
  }

  .sr-stat-icon-info {
    background: var(--sr-info-bg);
    color: var(--sr-info);
  }

  .sr-stat-icon-resolved {
    background: var(--sr-resolved-bg);
    color: var(--sr-resolved);
  }

  .sr-stat-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sr-text-muted);
    margin-bottom: 2px;
  }

  .sr-stat-value {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 700;
    color: var(--sr-text);
    line-height: 1;
  }

  /* ================================================================ */
  /*  SECTION HEADINGS                                                 */
  /* ================================================================ */
  .sr-section-title {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--sr-text-secondary);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .sr-section-title svg {
    width: 14px;
    height: 14px;
    color: var(--sr-text-muted);
  }

  /* ================================================================ */
  /*  HORIZONTAL BAR CHART                                             */
  /* ================================================================ */
  .sr-bar-chart {
    background: var(--sr-panel);
    border: 1px solid var(--sr-border);
    padding: 20px;
    margin-bottom: 24px;
  }

  .sr-bar-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
  }

  .sr-bar-row:last-child {
    margin-bottom: 0;
  }

  .sr-bar-label {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--sr-text);
    min-width: 100px;
    text-align: right;
  }

  .sr-bar-track {
    flex: 1;
    height: 24px;
    background: var(--sr-hover);
    position: relative;
    overflow: hidden;
  }

  .sr-bar-fill {
    height: 100%;
    background: var(--sr-critical);
    animation: sr-bar-grow 0.6s ease-out;
    display: flex;
    align-items: center;
    padding-left: 8px;
    min-width: 30px;
  }

  .sr-bar-value {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 600;
    color: white;
  }

  /* ================================================================ */
  /*  TABLES                                                           */
  /* ================================================================ */
  .sr-table-wrap {
    background: var(--sr-panel);
    border: 1px solid var(--sr-border);
    margin-bottom: 24px;
    overflow: hidden;
  }

  .sr-table-header {
    display: grid;
    background: var(--sr-table-header-bg);
    border-bottom: 1px solid var(--sr-table-border);
    padding: 0 16px;
  }

  .sr-th {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sr-text-muted);
    padding: 10px 8px;
  }

  .sr-table-row {
    display: grid;
    padding: 0 16px;
    border-bottom: 1px solid var(--sr-table-border);
    transition: background 0.1s;
    align-items: center;
  }

  .sr-table-row:last-child {
    border-bottom: none;
  }

  .sr-table-row:hover {
    background: var(--sr-row-hover);
  }

  .sr-td {
    padding: 10px 8px;
    font-size: 13px;
    color: var(--sr-text);
  }

  .sr-td-mono {
    font-family: var(--font-mono);
    font-size: 12px;
  }

  .sr-td-bold {
    font-weight: 600;
  }

  .sr-td-muted {
    color: var(--sr-text-muted);
    font-size: 12px;
  }

  /* Dashboard tables grid columns */
  .sr-dispatch-grid {
    grid-template-columns: 50px 1fr 100px 1fr 100px 90px;
  }

  .sr-resolution-grid {
    grid-template-columns: 90px 1fr 1fr 80px 90px 90px;
  }

  /* ================================================================ */
  /*  BADGES                                                           */
  /* ================================================================ */
  .sr-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .sr-badge-critical {
    background: var(--sr-critical-bg);
    color: var(--sr-critical);
  }

  .sr-badge-warning {
    background: var(--sr-warning-bg);
    color: var(--sr-warning);
  }

  .sr-badge-normal {
    background: var(--sr-hover);
    color: var(--sr-text-secondary);
  }

  .sr-badge-resolved {
    background: var(--sr-resolved-bg);
    color: var(--sr-resolved);
  }

  .sr-badge-info {
    background: var(--sr-info-bg);
    color: var(--sr-info);
  }

  .sr-badge-pending {
    background: var(--sr-warning-bg);
    color: var(--sr-warning);
  }

  .sr-badge-fulfilled {
    background: var(--sr-resolved-bg);
    color: var(--sr-resolved);
  }

  .sr-badge-active {
    background: var(--sr-info-bg);
    color: var(--sr-info);
  }

  .sr-badge-confirmed {
    background: var(--sr-resolved-bg);
    color: var(--sr-resolved);
  }

  .sr-badge-received {
    background: var(--sr-cyan-bg);
    color: var(--sr-cyan);
  }

  .sr-badge-cancelled {
    background: var(--sr-hover);
    color: var(--sr-text-muted);
  }

  .sr-badge-serialised {
    background: var(--sr-violet-bg);
    color: var(--sr-violet);
  }

  .sr-badge-bulk {
    background: var(--sr-info-bg);
    color: var(--sr-info);
  }

  .sr-badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    animation: sr-pulse 2s ease-in-out infinite;
  }

  /* ================================================================ */
  /*  SHORTAGE LIST (Shortages Tab)                                    */
  /* ================================================================ */
  .sr-shortage-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .sr-shortage-row {
    background: var(--sr-panel);
    border: 1px solid var(--sr-border);
    padding: 14px 20px;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
    display: grid;
    grid-template-columns: 1fr 100px 180px 1fr 100px 100px 90px 80px;
    align-items: center;
    gap: 12px;
  }

  .sr-shortage-row:hover {
    border-color: var(--sr-border-subtle);
    box-shadow: var(--sr-shadow);
  }

  .sr-shortage-row-expanded {
    border-color: var(--sr-accent);
    box-shadow: 0 0 0 1px var(--sr-accent-dim);
  }

  .sr-shortage-product {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .sr-shortage-product-name {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 700;
    color: var(--sr-text);
  }

  .sr-shortage-product-sku {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--sr-text-muted);
  }

  /* Quantity bar */
  .sr-qty-bar {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .sr-qty-bar-track {
    height: 8px;
    background: rgba(220, 38, 38, 0.15);
    overflow: hidden;
    display: flex;
  }

  .sr-qty-bar-available {
    height: 100%;
    background: var(--sr-accent);
    transition: width 0.4s ease;
  }

  .sr-qty-bar-label {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--sr-text-muted);
  }

  .sr-shortage-opp {
    font-size: 12px;
    color: var(--sr-text-secondary);
  }

  .sr-shortage-opp-name {
    font-weight: 600;
    color: var(--sr-text);
  }

  .sr-shortage-dates {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--sr-text-muted);
  }

  .sr-shortage-dispatch {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
  }

  .sr-shortage-dispatch-critical {
    color: var(--sr-critical);
  }

  .sr-shortage-dispatch-warning {
    color: var(--sr-warning);
  }

  .sr-shortage-dispatch-normal {
    color: var(--sr-text-secondary);
  }

  /* ================================================================ */
  /*  SHORTAGE DETAIL PANEL                                            */
  /* ================================================================ */
  .sr-detail-panel {
    background: var(--sr-surface);
    border: 1px solid var(--sr-accent);
    border-top: none;
    padding: 24px;
    animation: sr-fade-in 0.25s ease;
    margin-bottom: 2px;
  }

  .sr-detail-info {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--sr-border);
  }

  .sr-detail-info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .sr-detail-info-label {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sr-text-muted);
  }

  .sr-detail-info-value {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 700;
    color: var(--sr-text);
  }

  .sr-detail-info-value-mono {
    font-family: var(--font-mono);
    font-size: 13px;
    font-weight: 600;
    color: var(--sr-text);
  }

  .sr-detail-info-value-critical {
    color: var(--sr-critical);
  }

  /* ================================================================ */
  /*  RESOLUTION OPTIONS                                               */
  /* ================================================================ */
  .sr-options-title {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--sr-text-secondary);
    margin-bottom: 12px;
  }

  .sr-options-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 24px;
  }

  .sr-option-card {
    background: var(--sr-panel);
    border: 1px solid var(--sr-border);
    padding: 14px 16px 14px 20px;
    display: grid;
    grid-template-columns: 1fr 80px 80px 70px 120px 90px;
    align-items: center;
    gap: 12px;
    transition: border-color 0.15s, box-shadow 0.15s;
    position: relative;
  }

  .sr-option-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
  }

  .sr-option-card:hover {
    border-color: var(--sr-border-subtle);
    box-shadow: var(--sr-shadow);
  }

  .sr-option-card-applied {
    opacity: 0.7;
    border-color: var(--sr-accent);
  }

  .sr-option-card-quote::before { background: var(--sr-resolver-quote); }
  .sr-option-card-substitution::before { background: var(--sr-resolver-substitution); }
  .sr-option-card-transfer::before { background: var(--sr-resolver-transfer); }
  .sr-option-card-dateshift::before { background: var(--sr-resolver-dateshift); }
  .sr-option-card-partial::before { background: var(--sr-resolver-partial); }
  .sr-option-card-subhire::before { background: var(--sr-resolver-subhire); }
  .sr-option-card-waitlist::before { background: var(--sr-resolver-waitlist); }

  .sr-option-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
  }

  .sr-option-name {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 700;
    color: var(--sr-text);
  }

  .sr-option-desc {
    font-size: 12px;
    color: var(--sr-text-secondary);
    line-height: 1.4;
  }

  .sr-option-qty {
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 600;
    color: var(--sr-text);
    text-align: center;
  }

  .sr-option-cost {
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 600;
    color: var(--sr-text);
    text-align: center;
  }

  .sr-option-cost-none {
    color: var(--sr-text-muted);
  }

  .sr-option-lead {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--sr-text-muted);
    text-align: center;
  }

  /* Confidence bar */
  .sr-confidence {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
  }

  .sr-confidence-bar {
    width: 100%;
    height: 4px;
    background: var(--sr-hover);
    overflow: hidden;
  }

  .sr-confidence-fill {
    height: 100%;
    background: var(--sr-accent);
    transition: width 0.4s ease;
  }

  .sr-confidence-fill-high {
    background: var(--sr-accent);
  }

  .sr-confidence-fill-medium {
    background: var(--sr-warning);
  }

  .sr-confidence-fill-low {
    background: var(--sr-critical);
  }

  .sr-confidence-label {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--sr-text-muted);
  }

  /* Apply button */
  .sr-apply-btn {
    padding: 6px 14px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border: 1px solid var(--sr-accent);
    background: transparent;
    color: var(--sr-accent);
    cursor: pointer;
    transition: all 0.15s;
  }

  .sr-apply-btn:hover {
    background: var(--sr-accent);
    color: white;
  }

  .sr-apply-btn-applied {
    background: var(--sr-accent);
    color: white;
    cursor: default;
    pointer-events: none;
  }

  /* ================================================================ */
  /*  EXISTING RESOLUTIONS                                             */
  /* ================================================================ */
  .sr-existing-title {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--sr-text-muted);
    margin-bottom: 8px;
  }

  .sr-existing-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    background: var(--sr-panel);
    border: 1px solid var(--sr-border);
    margin-bottom: 4px;
  }

  .sr-existing-check {
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--sr-accent);
  }

  .sr-existing-check svg {
    width: 14px;
    height: 14px;
  }

  .sr-existing-desc {
    flex: 1;
    font-size: 12px;
    color: var(--sr-text);
  }

  .sr-existing-qty {
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 600;
    color: var(--sr-text);
  }

  /* ================================================================ */
  /*  VIRTUAL STOCK TAB                                                */
  /* ================================================================ */
  .sr-vsi-grid {
    grid-template-columns: 120px 80px 1fr 100px 60px 110px 110px 90px 90px;
  }

  .sr-vsi-detail {
    background: var(--sr-surface);
    border: 1px solid var(--sr-border);
    border-top: none;
    padding: 20px;
    animation: sr-fade-in 0.25s ease;
  }

  .sr-vsi-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
  }

  .sr-vsi-section-title {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--sr-text-muted);
    margin-bottom: 10px;
  }

  /* Cost apportionment */
  .sr-apportion-select {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    padding: 6px 28px 6px 10px;
    background: var(--sr-panel);
    border: 1px solid var(--sr-border);
    color: var(--sr-text);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
  }

  .sr-apportion-select:focus {
    outline: none;
    border-color: var(--sr-accent);
  }

  /* Allocation table */
  .sr-alloc-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid var(--sr-border);
    font-size: 12px;
  }

  .sr-alloc-row:last-child {
    border-bottom: none;
  }

  /* Virtual asset numbers */
  .sr-vasset {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--sr-text-secondary);
    padding: 3px 0;
  }

  /* Status timeline */
  .sr-timeline {
    display: flex;
    align-items: center;
    gap: 0;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--sr-border);
  }

  .sr-timeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    position: relative;
  }

  .sr-timeline-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--sr-border-subtle);
    border: 2px solid var(--sr-panel);
    z-index: 1;
  }

  .sr-timeline-dot-complete {
    background: var(--sr-accent);
  }

  .sr-timeline-dot-current {
    background: var(--sr-info);
    animation: sr-pulse 2s ease-in-out infinite;
  }

  .sr-timeline-label {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--sr-text-muted);
    white-space: nowrap;
  }

  .sr-timeline-label-active {
    color: var(--sr-accent);
    font-weight: 700;
  }

  .sr-timeline-line {
    flex: 1;
    height: 2px;
    background: var(--sr-border-subtle);
    min-width: 30px;
    margin-bottom: 20px;
  }

  .sr-timeline-line-complete {
    background: var(--sr-accent);
  }

  /* ================================================================ */
  /*  RESOLVERS TAB                                                    */
  /* ================================================================ */
  .sr-resolver-cards {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 32px;
  }

  .sr-resolver-card {
    background: var(--sr-panel);
    border: 1px solid var(--sr-border);
    padding: 20px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  .sr-resolver-card:hover {
    border-color: var(--sr-border-subtle);
    box-shadow: var(--sr-shadow);
  }

  .sr-resolver-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 12px;
  }

  .sr-resolver-name {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 700;
    color: var(--sr-text);
  }

  .sr-resolver-key {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--sr-text-muted);
    margin-top: 2px;
  }

  .sr-resolver-priority {
    font-family: var(--font-mono);
    font-size: 20px;
    font-weight: 700;
    color: var(--sr-text-muted);
    line-height: 1;
  }

  .sr-resolver-desc {
    font-size: 12px;
    color: var(--sr-text-secondary);
    line-height: 1.5;
    margin-bottom: 16px;
  }

  .sr-resolver-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-top: 12px;
    border-top: 1px solid var(--sr-border);
  }

  .sr-resolver-meta-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .sr-resolver-meta-label {
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sr-text-muted);
  }

  .sr-resolver-meta-value {
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 600;
    color: var(--sr-text);
  }

  /* Toggle switch */
  .sr-toggle {
    position: relative;
    width: 36px;
    height: 20px;
    cursor: pointer;
  }

  .sr-toggle-track {
    width: 100%;
    height: 100%;
    background: var(--sr-border-subtle);
    border-radius: 10px;
    transition: background 0.2s;
  }

  .sr-toggle-track-on {
    background: var(--sr-accent);
  }

  .sr-toggle-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    background: white;
    border-radius: 50%;
    transition: transform 0.2s;
  }

  .sr-toggle-thumb-on {
    transform: translateX(16px);
  }

  .sr-toggle-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    color: var(--sr-text-secondary);
  }

  /* ================================================================ */
  /*  STORE CONFIG                                                     */
  /* ================================================================ */
  .sr-config-panel {
    background: var(--sr-panel);
    border: 1px solid var(--sr-border);
    padding: 24px;
  }

  .sr-config-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid var(--sr-border);
  }

  .sr-config-row:last-child {
    border-bottom: none;
  }

  .sr-config-label {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--sr-text);
  }

  .sr-config-select {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    padding: 6px 28px 6px 10px;
    background: var(--sr-surface);
    border: 1px solid var(--sr-border);
    color: var(--sr-text);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
  }

  .sr-config-select:focus {
    outline: none;
    border-color: var(--sr-accent);
  }

  /* Sortable resolver list */
  .sr-sortable-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 8px;
  }

  .sr-sortable-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--sr-surface);
    border: 1px solid var(--sr-border);
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    color: var(--sr-text);
  }

  .sr-sortable-handle {
    color: var(--sr-text-muted);
    cursor: grab;
  }

  .sr-sortable-handle svg {
    width: 14px;
    height: 14px;
  }

  .sr-sortable-order {
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--sr-text-muted);
    min-width: 18px;
  }

  /* ================================================================ */
  /*  TWO-COLUMN LAYOUT for dashboard                                  */
  /* ================================================================ */
  .sr-dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
  }

  .sr-dashboard-full {
    grid-column: 1 / -1;
  }
</style>

<div class="sr-page" x-data="shortageResolution()" x-cloak>
  <div class="sr-container">

    {{-- ============================================================ --}}
    {{--  HEADER                                                       --}}
    {{-- ============================================================ --}}
    <div class="sr-header">
      <div class="sr-header-left">
        <div class="sr-header-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
        </div>
        <div>
          <div class="sr-title">Shortage Resolution</div>
          <div class="sr-subtitle">Detection & Resolution Engine</div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  TAB BAR                                                      --}}
    {{-- ============================================================ --}}
    <div class="sr-tabs">
      <button class="sr-tab" :class="{ 'sr-tab-active': activeTab === 'dashboard' }"
              @click="activeTab = 'dashboard'">Dashboard</button>
      <button class="sr-tab" :class="{ 'sr-tab-active': activeTab === 'shortages' }"
              @click="activeTab = 'shortages'">
        Shortages
        <span class="sr-tab-badge sr-tab-badge-critical">12</span>
      </button>
      <button class="sr-tab" :class="{ 'sr-tab-active': activeTab === 'virtual-stock' }"
              @click="activeTab = 'virtual-stock'">
        Virtual Stock
        <span class="sr-tab-badge sr-tab-badge-info">3</span>
      </button>
      <button class="sr-tab" :class="{ 'sr-tab-active': activeTab === 'resolvers' }"
              @click="activeTab = 'resolvers'">Resolvers</button>
    </div>

    {{-- ============================================================ --}}
    {{--  DASHBOARD TAB                                                --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'dashboard'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">

      {{-- Stats bar --}}
      <div class="sr-stats">
        <div class="sr-stat-card">
          <div class="sr-stat-icon sr-stat-icon-critical">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          </div>
          <div>
            <div class="sr-stat-label">Active Shortages</div>
            <div class="sr-stat-value">12</div>
          </div>
        </div>

        <div class="sr-stat-card">
          <div class="sr-stat-icon sr-stat-icon-critical">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
          </div>
          <div>
            <div class="sr-stat-label">Critical (Confirmed)</div>
            <div class="sr-stat-value">5</div>
          </div>
        </div>

        <div class="sr-stat-card">
          <div class="sr-stat-icon sr-stat-icon-warning">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
          <div>
            <div class="sr-stat-label">Pending Resolutions</div>
            <div class="sr-stat-value">8</div>
          </div>
        </div>

        <div class="sr-stat-card">
          <div class="sr-stat-icon sr-stat-icon-info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
              <line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
          </div>
          <div>
            <div class="sr-stat-label">Virtual Stock Intakes</div>
            <div class="sr-stat-value">3</div>
          </div>
        </div>
      </div>

      {{-- Shortages by Warehouse --}}
      <div class="sr-section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="18" height="18" rx="2"/>
          <path d="M3 9h18"/>
          <path d="M9 21V9"/>
        </svg>
        Shortages by Warehouse
      </div>
      <div class="sr-bar-chart">
        <template x-for="wh in warehouseData" :key="wh.name">
          <div class="sr-bar-row">
            <div class="sr-bar-label" x-text="wh.name"></div>
            <div class="sr-bar-track">
              <div class="sr-bar-fill" :style="'width:' + (wh.count / maxWarehouseCount * 100) + '%'">
                <span class="sr-bar-value" x-text="wh.count"></span>
              </div>
            </div>
          </div>
        </template>
      </div>

      <div class="sr-dashboard-grid">
        {{-- Upcoming Dispatch Shortages --}}
        <div>
          <div class="sr-section-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Upcoming Dispatch Shortages
          </div>
          <div class="sr-table-wrap">
            <div class="sr-table-header sr-dispatch-grid">
              <div class="sr-th">Days</div>
              <div class="sr-th">Product</div>
              <div class="sr-th">Qty Short</div>
              <div class="sr-th">Opportunity</div>
              <div class="sr-th">Store</div>
              <div class="sr-th">Priority</div>
            </div>
            <template x-for="row in dispatchShortages" :key="row.product">
              <div class="sr-table-row sr-dispatch-grid">
                <div class="sr-td sr-td-bold" x-text="row.days"></div>
                <div class="sr-td sr-td-bold" x-text="row.product"></div>
                <div class="sr-td sr-td-mono" x-text="row.qtyShort + ' of ' + row.qtyTotal"></div>
                <div class="sr-td" x-text="row.opportunity"></div>
                <div class="sr-td sr-td-muted" x-text="row.store"></div>
                <div class="sr-td">
                  <span class="sr-badge"
                        :class="{
                          'sr-badge-critical': row.priority === 'Critical',
                          'sr-badge-warning': row.priority === 'Warning',
                          'sr-badge-normal': row.priority === 'Normal'
                        }"
                        x-text="row.priority"></span>
                </div>
              </div>
            </template>
          </div>
        </div>

        {{-- Recent Resolutions --}}
        <div>
          <div class="sr-section-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            Recent Resolutions
          </div>
          <div class="sr-table-wrap">
            <div class="sr-table-header sr-resolution-grid">
              <div class="sr-th">Time</div>
              <div class="sr-th">Product</div>
              <div class="sr-th">Resolution</div>
              <div class="sr-th">Qty</div>
              <div class="sr-th">Cost</div>
              <div class="sr-th">Status</div>
            </div>
            <template x-for="row in recentResolutions" :key="row.product + row.time">
              <div class="sr-table-row sr-resolution-grid">
                <div class="sr-td sr-td-muted" x-text="row.time"></div>
                <div class="sr-td sr-td-bold" x-text="row.product"></div>
                <div class="sr-td" x-text="row.resolution"></div>
                <div class="sr-td sr-td-mono" x-text="row.qty"></div>
                <div class="sr-td sr-td-mono" x-text="row.cost"></div>
                <div class="sr-td">
                  <span class="sr-badge"
                        :class="{
                          'sr-badge-fulfilled': row.status === 'Fulfilled',
                          'sr-badge-confirmed': row.status === 'Confirmed',
                          'sr-badge-pending': row.status === 'Pending',
                          'sr-badge-active': row.status === 'Active'
                        }"
                        x-text="row.status"></span>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  SHORTAGES TAB                                                --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'shortages'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">

      <div class="sr-section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Active Shortages
      </div>

      <div class="sr-shortage-list">
        <template x-for="shortage in shortages" :key="shortage.id">
          <div>
            {{-- Shortage row --}}
            <div class="sr-shortage-row"
                 :class="{ 'sr-shortage-row-expanded': expandedShortage === shortage.id }"
                 @click="toggleShortage(shortage.id)">
              <div class="sr-shortage-product">
                <span class="sr-shortage-product-name" x-text="shortage.product_name"></span>
                <span class="sr-shortage-product-sku" x-text="shortage.sku"></span>
              </div>
              <div>
                <span class="sr-badge"
                      :class="shortage.is_critical ? 'sr-badge-critical' : 'sr-badge-warning'">
                  <span class="sr-badge-dot"></span>
                  <span x-text="shortage.shortfall + ' short'"></span>
                </span>
              </div>
              <div class="sr-qty-bar">
                <div class="sr-qty-bar-track">
                  <div class="sr-qty-bar-available"
                       :style="'width:' + (shortage.available / shortage.requested * 100) + '%'"></div>
                </div>
                <div class="sr-qty-bar-label" x-text="shortage.available + ' avail / ' + shortage.requested + ' req'"></div>
              </div>
              <div class="sr-shortage-opp">
                <div class="sr-shortage-opp-name" x-text="shortage.opportunity_name"></div>
                <div style="font-size:11px; color:var(--sr-text-muted);" x-text="shortage.opportunity_number"></div>
              </div>
              <div class="sr-td-muted" x-text="shortage.store"></div>
              <div class="sr-shortage-dates" x-text="shortage.starts + ' - ' + shortage.ends"></div>
              <div class="sr-shortage-dispatch"
                   :class="{
                     'sr-shortage-dispatch-critical': shortage.days_until_dispatch <= 3,
                     'sr-shortage-dispatch-warning': shortage.days_until_dispatch > 3 && shortage.days_until_dispatch <= 7,
                     'sr-shortage-dispatch-normal': shortage.days_until_dispatch > 7
                   }"
                   x-text="shortage.days_until_dispatch + 'd to dispatch'"></div>
              <div>
                <span class="sr-badge"
                      :class="shortage.tracking_type === 'Serialised' ? 'sr-badge-serialised' : 'sr-badge-bulk'"
                      x-text="shortage.tracking_type"></span>
              </div>
            </div>

            {{-- Expanded detail panel --}}
            <div class="sr-detail-panel"
                 x-show="expandedShortage === shortage.id"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">

              {{-- Shortage info grid --}}
              <div class="sr-detail-info">
                <div class="sr-detail-info-item">
                  <span class="sr-detail-info-label">Requested Qty</span>
                  <span class="sr-detail-info-value" x-text="shortage.requested"></span>
                </div>
                <div class="sr-detail-info-item">
                  <span class="sr-detail-info-label">Available Qty</span>
                  <span class="sr-detail-info-value" x-text="shortage.available"></span>
                </div>
                <div class="sr-detail-info-item">
                  <span class="sr-detail-info-label">Shortfall</span>
                  <span class="sr-detail-info-value sr-detail-info-value-critical" x-text="shortage.shortfall"></span>
                </div>
                <div class="sr-detail-info-item">
                  <span class="sr-detail-info-label">Remaining Shortfall</span>
                  <span class="sr-detail-info-value sr-detail-info-value-critical" x-text="shortage.remaining_shortfall"></span>
                </div>
              </div>

              {{-- Existing resolutions --}}
              <div x-show="shortage.existing_resolutions && shortage.existing_resolutions.length > 0"
                   style="margin-bottom: 20px;">
                <div class="sr-existing-title">Existing Resolutions</div>
                <template x-for="res in shortage.existing_resolutions" :key="res.description">
                  <div class="sr-existing-row">
                    <div class="sr-existing-check">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"/>
                      </svg>
                    </div>
                    <div class="sr-existing-desc" x-text="res.description"></div>
                    <div class="sr-existing-qty" x-text="'Qty: ' + res.qty"></div>
                    <span class="sr-badge"
                          :class="{
                            'sr-badge-fulfilled': res.status === 'Fulfilled',
                            'sr-badge-pending': res.status === 'Pending',
                            'sr-badge-confirmed': res.status === 'Confirmed',
                            'sr-badge-cancelled': res.status === 'Cancelled'
                          }"
                          x-text="res.status"></span>
                  </div>
                </template>
              </div>

              {{-- Resolution options --}}
              <div class="sr-options-title">Resolution Options</div>
              <div class="sr-options-grid">
                <template x-for="(option, optIdx) in shortage.options" :key="option.resolver">
                  <div class="sr-option-card"
                       :class="[
                         'sr-option-card-' + option.type,
                         option.applied ? 'sr-option-card-applied' : ''
                       ]">
                    <div class="sr-option-info">
                      <div class="sr-option-name" x-text="option.resolver"></div>
                      <div class="sr-option-desc" x-text="option.description"></div>
                    </div>
                    <div class="sr-option-qty" x-text="option.qty_resolved"></div>
                    <div class="sr-option-cost"
                         :class="{ 'sr-option-cost-none': option.cost === '—' }"
                         x-text="option.cost"></div>
                    <div class="sr-option-lead" x-text="option.lead_time"></div>
                    <div class="sr-confidence">
                      <div class="sr-confidence-bar">
                        <div class="sr-confidence-fill"
                             :class="{
                               'sr-confidence-fill-high': option.confidence >= 0.7,
                               'sr-confidence-fill-medium': option.confidence >= 0.4 && option.confidence < 0.7,
                               'sr-confidence-fill-low': option.confidence < 0.4
                             }"
                             :style="'width:' + (option.confidence * 100) + '%'"></div>
                      </div>
                      <div class="sr-confidence-label" x-text="Math.round(option.confidence * 100) + '%'"></div>
                    </div>
                    <div>
                      <button class="sr-apply-btn"
                              :class="{ 'sr-apply-btn-applied': option.applied }"
                              @click.stop="applyOption(shortage.id, optIdx)"
                              x-text="option.applied ? 'Applied' : 'Apply'"></button>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  VIRTUAL STOCK TAB                                            --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'virtual-stock'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">

      <div class="sr-section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
          <line x1="12" y1="22.08" x2="12" y2="12"/>
        </svg>
        Virtual Stock Intakes
      </div>

      <div class="sr-table-wrap">
        <div class="sr-table-header sr-vsi-grid">
          <div class="sr-th">Reference</div>
          <div class="sr-th">Source</div>
          <div class="sr-th">Product</div>
          <div class="sr-th">Warehouse</div>
          <div class="sr-th">Qty</div>
          <div class="sr-th">Available From</div>
          <div class="sr-th">Available Until</div>
          <div class="sr-th">Cost</div>
          <div class="sr-th">Status</div>
        </div>
        <template x-for="vsi in virtualStockIntakes" :key="vsi.reference">
          <div>
            <div class="sr-table-row sr-vsi-grid"
                 style="cursor:pointer;"
                 :style="expandedVsi === vsi.reference ? 'border-bottom:none;background:var(--sr-row-hover);' : ''"
                 @click="toggleVsi(vsi.reference)">
              <div class="sr-td sr-td-mono sr-td-bold" x-text="vsi.reference"></div>
              <div class="sr-td">
                <span class="sr-badge sr-badge-info" x-text="vsi.source"></span>
              </div>
              <div class="sr-td sr-td-bold" x-text="vsi.product"></div>
              <div class="sr-td sr-td-muted" x-text="vsi.warehouse"></div>
              <div class="sr-td sr-td-mono" x-text="vsi.qty"></div>
              <div class="sr-td sr-td-mono" x-text="vsi.available_from"></div>
              <div class="sr-td sr-td-mono" x-text="vsi.available_until"></div>
              <div class="sr-td sr-td-mono" x-text="vsi.cost"></div>
              <div class="sr-td">
                <span class="sr-badge"
                      :class="{
                        'sr-badge-confirmed': vsi.status === 'Confirmed',
                        'sr-badge-pending': vsi.status === 'Pending',
                        'sr-badge-received': vsi.status === 'Received'
                      }"
                      x-text="vsi.status"></span>
              </div>
            </div>

            {{-- VSI expanded detail --}}
            <div class="sr-vsi-detail"
                 x-show="expandedVsi === vsi.reference"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">
              <div class="sr-vsi-detail-grid">
                {{-- Left column --}}
                <div>
                  <div class="sr-vsi-section-title">Source Details</div>
                  <div style="margin-bottom:16px;">
                    <div class="sr-alloc-row">
                      <span style="color:var(--sr-text-muted);font-size:12px;">Supplier</span>
                      <span style="font-weight:600;font-size:12px;" x-text="vsi.supplier"></span>
                    </div>
                    <div class="sr-alloc-row">
                      <span style="color:var(--sr-text-muted);font-size:12px;">PO Reference</span>
                      <span class="sr-td-mono" style="font-size:12px;" x-text="vsi.po_reference"></span>
                    </div>
                  </div>

                  <div class="sr-vsi-section-title">Cost Apportionment Method</div>
                  <select class="sr-apportion-select" x-model="vsi.apportion_method">
                    <option value="primary_job">Primary Job</option>
                    <option value="even_split">Even Split</option>
                    <option value="proportional_qty">Proportional Qty</option>
                    <option value="proportional_qty_duration">Proportional Qty x Duration</option>
                    <option value="manual">Manual</option>
                  </select>

                  <div class="sr-vsi-section-title" style="margin-top:16px;">Allocated Opportunities</div>
                  <template x-for="alloc in vsi.allocations" :key="alloc.opportunity">
                    <div class="sr-alloc-row">
                      <span style="font-size:12px;font-weight:600;" x-text="alloc.opportunity"></span>
                      <span class="sr-td-mono" style="font-size:12px;" x-text="alloc.cost"></span>
                    </div>
                  </template>
                </div>

                {{-- Right column --}}
                <div>
                  <div class="sr-vsi-section-title">Virtual Asset Numbers</div>
                  <template x-for="asset in vsi.assets" :key="asset">
                    <div class="sr-vasset" x-text="asset"></div>
                  </template>
                </div>
              </div>

              {{-- Status timeline --}}
              <div class="sr-timeline">
                <template x-for="(step, stepIdx) in vsi.timeline" :key="step.label">
                  <template x-if="true">
                    <div style="display:contents;">
                      <div class="sr-timeline-step">
                        <div class="sr-timeline-dot"
                             :class="{
                               'sr-timeline-dot-complete': step.complete,
                               'sr-timeline-dot-current': step.current
                             }"></div>
                        <div class="sr-timeline-label"
                             :class="{ 'sr-timeline-label-active': step.complete || step.current }"
                             x-text="step.label"></div>
                      </div>
                      <div class="sr-timeline-line"
                           :class="{ 'sr-timeline-line-complete': step.complete }"
                           x-show="stepIdx < vsi.timeline.length - 1"></div>
                    </div>
                  </template>
                </template>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{--  RESOLVERS TAB                                                --}}
    {{-- ============================================================ --}}
    <div x-show="activeTab === 'resolvers'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">

      <div class="sr-section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
        </svg>
        Resolver Registry
      </div>

      <div class="sr-resolver-cards">
        <template x-for="resolver in resolvers" :key="resolver.key">
          <div class="sr-resolver-card">
            <div class="sr-resolver-header">
              <div>
                <div class="sr-resolver-name" x-text="resolver.name"></div>
                <div class="sr-resolver-key" x-text="resolver.key"></div>
              </div>
              <div class="sr-resolver-priority" x-text="resolver.priority"></div>
            </div>
            <div class="sr-resolver-desc" x-text="resolver.description"></div>
            <div style="display:flex; align-items:center; gap:20px; margin-bottom:16px;">
              <div class="sr-toggle-label">
                <span>Enabled</span>
                <div class="sr-toggle" @click="resolver.enabled = !resolver.enabled">
                  <div class="sr-toggle-track" :class="{ 'sr-toggle-track-on': resolver.enabled }"></div>
                  <div class="sr-toggle-thumb" :class="{ 'sr-toggle-thumb-on': resolver.enabled }"></div>
                </div>
              </div>
              <div class="sr-toggle-label" x-show="resolver.auto_configurable">
                <span>Auto-Execute</span>
                <div class="sr-toggle" @click="resolver.auto_executable = !resolver.auto_executable">
                  <div class="sr-toggle-track" :class="{ 'sr-toggle-track-on': resolver.auto_executable }"></div>
                  <div class="sr-toggle-thumb" :class="{ 'sr-toggle-thumb-on': resolver.auto_executable }"></div>
                </div>
              </div>
            </div>
            <div class="sr-resolver-meta">
              <div class="sr-resolver-meta-item">
                <span class="sr-resolver-meta-label">Used (30d)</span>
                <span class="sr-resolver-meta-value" x-text="resolver.times_used"></span>
              </div>
              <div class="sr-resolver-meta-item">
                <span class="sr-resolver-meta-label">Success Rate</span>
                <span class="sr-resolver-meta-value" x-text="resolver.success_rate + '%'"></span>
              </div>
            </div>
          </div>
        </template>
      </div>

      {{-- Store Configuration --}}
      <div class="sr-section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        Store Configuration
      </div>

      <div class="sr-config-panel">
        <div class="sr-config-row">
          <span class="sr-config-label">Confirmation Policy</span>
          <select class="sr-config-select" x-model="storeConfig.confirmationPolicy">
            <option value="block">Block</option>
            <option value="warn">Warn</option>
            <option value="allow">Allow</option>
          </select>
        </div>
        <div class="sr-config-row">
          <span class="sr-config-label">Auto-Resolve Enabled</span>
          <div class="sr-toggle" @click="storeConfig.autoResolve = !storeConfig.autoResolve">
            <div class="sr-toggle-track" :class="{ 'sr-toggle-track-on': storeConfig.autoResolve }"></div>
            <div class="sr-toggle-thumb" :class="{ 'sr-toggle-thumb-on': storeConfig.autoResolve }"></div>
          </div>
        </div>
        <div class="sr-config-row">
          <span class="sr-config-label">Dispatch Policy</span>
          <select class="sr-config-select" x-model="storeConfig.dispatchPolicy">
            <option value="block">Block</option>
            <option value="warn_partial">Warn Partial</option>
            <option value="allow_partial">Allow Partial</option>
          </select>
        </div>
        <div class="sr-config-row" style="flex-direction:column; align-items:flex-start; gap:8px;">
          <span class="sr-config-label">Preferred Resolver Order</span>
          <div class="sr-sortable-list" style="width:100%;">
            <template x-for="(key, idx) in storeConfig.resolverOrder" :key="key">
              <div class="sr-sortable-item">
                <div class="sr-sortable-handle">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"/>
                    <line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/>
                    <line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/>
                    <line x1="3" y1="18" x2="3.01" y2="18"/>
                  </svg>
                </div>
                <span class="sr-sortable-order" x-text="(idx + 1) + '.'"></span>
                <span x-text="key"></span>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

@verbatim
<script>
function shortageResolution() {

  /* ================================================================ */
  /*  MOCK DATA                                                        */
  /* ================================================================ */
  const warehouseData = [
    { name: 'London', count: 7 },
    { name: 'Manchester', count: 3 },
    { name: 'Edinburgh', count: 2 },
  ];

  const dispatchShortages = [
    { days: 2, product: 'MegaPointe', qtyShort: 4, qtyTotal: 6, opportunity: 'Summer Festival', store: 'London', priority: 'Critical' },
    { days: 3, product: 'JBL EON 615', qtyShort: 2, qtyTotal: 8, opportunity: 'Corporate Awards', store: 'London', priority: 'Critical' },
    { days: 5, product: 'LED Panel 2.6mm', qtyShort: 10, qtyTotal: 20, opportunity: 'Trade Show', store: 'Manchester', priority: 'Warning' },
    { days: 7, product: 'Haze Machine', qtyShort: 1, qtyTotal: 3, opportunity: 'Theatre Production', store: 'Edinburgh', priority: 'Normal' },
    { days: 10, product: 'Cable 20m XLR', qtyShort: 15, qtyTotal: 50, opportunity: 'Festival Setup', store: 'London', priority: 'Normal' },
  ];

  const recentResolutions = [
    { time: '1h ago', product: 'MegaPointe', resolution: 'Transfer from Manchester', qty: 2, cost: '\u00a350', status: 'Fulfilled' },
    { time: '3h ago', product: 'JBL EON 615', resolution: 'Substitute: JBL PRX 815', qty: 2, cost: '\u2014', status: 'Confirmed' },
    { time: 'Yesterday', product: 'LED Panel', resolution: 'Sub-hire: Stage Equipment', qty: 10, cost: '\u00a32,000', status: 'Pending' },
    { time: '2 days ago', product: 'Haze Machine', resolution: 'Waitlist', qty: 1, cost: '\u2014', status: 'Active' },
  ];

  const shortages = [
    {
      id: 1,
      product_name: 'MegaPointe',
      sku: 'LGT-MP-001',
      requested: 6,
      available: 2,
      shortfall: 4,
      remaining_shortfall: 2,
      is_critical: true,
      opportunity_name: 'Summer Festival',
      opportunity_number: 'OPP-0000042',
      store: 'London',
      starts: '10 Mar',
      ends: '15 Mar',
      days_until_dispatch: 4,
      tracking_type: 'Serialised',
      existing_resolutions: [
        { description: 'Transfer 2 from Manchester', qty: 2, status: 'Fulfilled' },
      ],
      options: [
        { resolver: 'Quote Reallocation', type: 'quote', description: 'Release 2 units from unconfirmed Quote #0039', qty_resolved: 2, cost: '\u2014', lead_time: 'Immediate', confidence: 0.8, applied: false },
        { resolver: 'Substitution', type: 'substitution', description: 'Use SuperPointe (upgrade), 4 available', qty_resolved: 4, cost: '\u2014', lead_time: 'Immediate', confidence: 0.9, applied: false },
        { resolver: 'Warehouse Transfer', type: 'transfer', description: 'Transfer 4 from Manchester, available in 2 hours', qty_resolved: 4, cost: '\u00a350', lead_time: '2 hours', confidence: 0.85, applied: false },
        { resolver: 'Date Shift', type: 'dateshift', description: 'Move to 17-22 Mar, full availability', qty_resolved: 4, cost: '\u2014', lead_time: 'Immediate', confidence: 0.6, applied: false },
        { resolver: 'Partial Fulfilment', type: 'partial', description: 'Fulfil 2 of 6, shortfall remains', qty_resolved: 2, cost: '\u2014', lead_time: 'Immediate', confidence: 1.0, applied: false },
        { resolver: 'Sub-Hire', type: 'subhire', description: 'Sub-hire from Stage Equipment Ltd, \u00a3200/day \u00d7 5 days', qty_resolved: 4, cost: '\u00a31,000', lead_time: '24 hours', confidence: 0.75, applied: false },
        { resolver: 'Waitlist', type: 'waitlist', description: 'Monitor for availability changes', qty_resolved: 0, cost: '\u2014', lead_time: 'Ongoing', confidence: 0.3, applied: false },
      ],
    },
    {
      id: 2,
      product_name: 'JBL EON 615',
      sku: 'AUD-JBL-615',
      requested: 8,
      available: 6,
      shortfall: 2,
      remaining_shortfall: 2,
      is_critical: true,
      opportunity_name: 'Corporate Awards',
      opportunity_number: 'OPP-0000045',
      store: 'London',
      starts: '11 Mar',
      ends: '12 Mar',
      days_until_dispatch: 3,
      tracking_type: 'Serialised',
      existing_resolutions: [],
      options: [
        { resolver: 'Substitution', type: 'substitution', description: 'Use JBL PRX 815 (upgrade), 4 available', qty_resolved: 2, cost: '\u2014', lead_time: 'Immediate', confidence: 0.9, applied: false },
        { resolver: 'Warehouse Transfer', type: 'transfer', description: 'Transfer 2 from Manchester, available in 3 hours', qty_resolved: 2, cost: '\u00a340', lead_time: '3 hours', confidence: 0.8, applied: false },
        { resolver: 'Sub-Hire', type: 'subhire', description: 'Sub-hire from PA Solutions, \u00a375/day \u00d7 1 day', qty_resolved: 2, cost: '\u00a375', lead_time: '12 hours', confidence: 0.7, applied: false },
      ],
    },
    {
      id: 3,
      product_name: 'LED Panel 2.6mm',
      sku: 'VID-LED-26',
      requested: 20,
      available: 10,
      shortfall: 10,
      remaining_shortfall: 10,
      is_critical: false,
      opportunity_name: 'Trade Show',
      opportunity_number: 'OPP-0000048',
      store: 'Manchester',
      starts: '15 Mar',
      ends: '20 Mar',
      days_until_dispatch: 7,
      tracking_type: 'Bulk',
      existing_resolutions: [],
      options: [
        { resolver: 'Sub-Hire', type: 'subhire', description: 'Sub-hire from LED Direct, \u00a3150/panel/day \u00d7 5 days', qty_resolved: 10, cost: '\u00a37,500', lead_time: '48 hours', confidence: 0.85, applied: false },
        { resolver: 'Partial Fulfilment', type: 'partial', description: 'Fulfil 10 of 20, shortfall remains', qty_resolved: 10, cost: '\u2014', lead_time: 'Immediate', confidence: 1.0, applied: false },
        { resolver: 'Date Shift', type: 'dateshift', description: 'Move to 22-27 Mar, full availability', qty_resolved: 10, cost: '\u2014', lead_time: 'Immediate', confidence: 0.5, applied: false },
      ],
    },
    {
      id: 4,
      product_name: 'Haze Machine',
      sku: 'SFX-HZ-001',
      requested: 3,
      available: 2,
      shortfall: 1,
      remaining_shortfall: 1,
      is_critical: false,
      opportunity_name: 'Theatre Production',
      opportunity_number: 'OPP-0000051',
      store: 'Edinburgh',
      starts: '17 Mar',
      ends: '22 Mar',
      days_until_dispatch: 9,
      tracking_type: 'Serialised',
      existing_resolutions: [],
      options: [
        { resolver: 'Warehouse Transfer', type: 'transfer', description: 'Transfer 1 from London, next-day delivery', qty_resolved: 1, cost: '\u00a330', lead_time: '1 day', confidence: 0.9, applied: false },
        { resolver: 'Substitution', type: 'substitution', description: 'Use MDG theONE (premium), 1 available', qty_resolved: 1, cost: '\u2014', lead_time: 'Immediate', confidence: 0.7, applied: false },
        { resolver: 'Waitlist', type: 'waitlist', description: 'Monitor for availability changes', qty_resolved: 0, cost: '\u2014', lead_time: 'Ongoing', confidence: 0.4, applied: false },
      ],
    },
    {
      id: 5,
      product_name: 'Cable 20m XLR',
      sku: 'CBL-XLR-20',
      requested: 50,
      available: 35,
      shortfall: 15,
      remaining_shortfall: 15,
      is_critical: false,
      opportunity_name: 'Festival Setup',
      opportunity_number: 'OPP-0000053',
      store: 'London',
      starts: '20 Mar',
      ends: '25 Mar',
      days_until_dispatch: 12,
      tracking_type: 'Bulk',
      existing_resolutions: [],
      options: [
        { resolver: 'Quote Reallocation', type: 'quote', description: 'Release 10 from unconfirmed Quote #0055', qty_resolved: 10, cost: '\u2014', lead_time: 'Immediate', confidence: 0.7, applied: false },
        { resolver: 'Warehouse Transfer', type: 'transfer', description: 'Transfer 15 from Manchester, same-day', qty_resolved: 15, cost: '\u00a325', lead_time: '4 hours', confidence: 0.95, applied: false },
        { resolver: 'Partial Fulfilment', type: 'partial', description: 'Fulfil 35 of 50, shortfall remains', qty_resolved: 35, cost: '\u2014', lead_time: 'Immediate', confidence: 1.0, applied: false },
      ],
    },
  ];

  const virtualStockIntakes = [
    {
      reference: 'VSI-2026-0042',
      source: 'Sub-hire',
      product: 'MegaPointe',
      warehouse: 'London',
      qty: 4,
      available_from: '9 Mar 2026',
      available_until: '16 Mar 2026',
      cost: '\u00a31,000',
      status: 'Confirmed',
      supplier: 'Stage Equipment Ltd',
      po_reference: 'PO-2026-0187',
      apportion_method: 'primary_job',
      allocations: [
        { opportunity: 'Summer Festival (OPP-0000042)', cost: '\u00a31,000' },
      ],
      assets: ['VS-VSI-2026-0042-001', 'VS-VSI-2026-0042-002', 'VS-VSI-2026-0042-003', 'VS-VSI-2026-0042-004'],
      timeline: [
        { label: 'Created', complete: true, current: false },
        { label: 'Confirmed', complete: true, current: false },
        { label: 'Received', complete: false, current: true },
        { label: 'On Hire', complete: false, current: false },
        { label: 'Returned', complete: false, current: false },
      ],
    },
    {
      reference: 'VSI-2026-0043',
      source: 'Sub-hire',
      product: 'LED Panel 2.6mm',
      warehouse: 'Manchester',
      qty: 10,
      available_from: '12 Mar 2026',
      available_until: '20 Mar 2026',
      cost: '\u00a32,000',
      status: 'Pending',
      supplier: 'LED Direct Ltd',
      po_reference: 'PO-2026-0192',
      apportion_method: 'even_split',
      allocations: [
        { opportunity: 'Trade Show (OPP-0000048)', cost: '\u00a32,000' },
      ],
      assets: Array.from({ length: 10 }, (_, i) => 'VS-VSI-2026-0043-' + String(i + 1).padStart(3, '0')),
      timeline: [
        { label: 'Created', complete: true, current: false },
        { label: 'Confirmed', complete: false, current: true },
        { label: 'Received', complete: false, current: false },
        { label: 'On Hire', complete: false, current: false },
        { label: 'Returned', complete: false, current: false },
      ],
    },
    {
      reference: 'VSI-2026-0044',
      source: 'Purchase',
      product: 'Haze Machine',
      warehouse: 'Edinburgh',
      qty: 2,
      available_from: '15 Mar 2026',
      available_until: '\u2014 (permanent)',
      cost: '\u00a3800',
      status: 'Received',
      supplier: 'SFX Supplies',
      po_reference: 'PO-2026-0195',
      apportion_method: 'proportional_qty',
      allocations: [
        { opportunity: 'Theatre Production (OPP-0000051)', cost: '\u00a3400' },
        { opportunity: 'General Stock', cost: '\u00a3400' },
      ],
      assets: ['VS-VSI-2026-0044-001', 'VS-VSI-2026-0044-002'],
      timeline: [
        { label: 'Created', complete: true, current: false },
        { label: 'Confirmed', complete: true, current: false },
        { label: 'Received', complete: true, current: true },
        { label: 'On Hire', complete: false, current: false },
        { label: 'Returned', complete: false, current: false },
      ],
    },
  ];

  const resolvers = [
    { name: 'Quote Reallocation', key: 'quote_reallocation', priority: 10, auto_executable: false, auto_configurable: false, enabled: true, description: 'Release stock held by unconfirmed quotes with lower priority or older creation dates.', times_used: 14, success_rate: 78 },
    { name: 'Substitution', key: 'substitution', priority: 20, auto_executable: false, auto_configurable: true, enabled: true, description: 'Use alternative product with sufficient availability. Supports upgrade and equivalent substitutions.', times_used: 23, success_rate: 91 },
    { name: 'Warehouse Transfer', key: 'warehouse_transfer', priority: 30, auto_executable: false, auto_configurable: true, enabled: true, description: 'Move stock from another warehouse. Considers transfer time, cost, and vehicle availability.', times_used: 31, success_rate: 95 },
    { name: 'Date Shift', key: 'date_shift', priority: 40, auto_executable: false, auto_configurable: false, enabled: true, description: 'Offer alternative date range with full availability. Requires customer confirmation.', times_used: 8, success_rate: 62 },
    { name: 'Partial Fulfilment', key: 'partial_fulfilment', priority: 50, auto_executable: false, auto_configurable: false, enabled: true, description: 'Fulfil with available quantity. Customer informed of reduced allocation.', times_used: 5, success_rate: 100 },
    { name: 'Waitlist', key: 'waitlist', priority: 90, auto_executable: false, auto_configurable: false, enabled: true, description: 'Monitor for availability changes from cancellations, returns, or new stock. Notifies when availability changes.', times_used: 12, success_rate: 42 },
  ];

  /* ================================================================ */
  /*  COMPONENT STATE                                                  */
  /* ================================================================ */
  return {
    activeTab: 'dashboard',
    expandedShortage: null,
    expandedVsi: null,

    /* Data */
    warehouseData,
    dispatchShortages,
    recentResolutions,
    shortages,
    virtualStockIntakes,
    resolvers,

    /* Store config */
    storeConfig: {
      confirmationPolicy: 'warn',
      autoResolve: true,
      dispatchPolicy: 'warn_partial',
      resolverOrder: [
        'quote_reallocation',
        'substitution',
        'warehouse_transfer',
        'date_shift',
        'partial_fulfilment',
        'waitlist',
      ],
    },

    /* ============================================================== */
    /*  COMPUTED                                                       */
    /* ============================================================== */
    get maxWarehouseCount() {
      return Math.max(...this.warehouseData.map(w => w.count));
    },

    /* ============================================================== */
    /*  METHODS                                                        */
    /* ============================================================== */
    init() {
      // Default: expand the first shortage
      this.expandedShortage = null;
    },

    toggleShortage(id) {
      this.expandedShortage = this.expandedShortage === id ? null : id;
    },

    toggleVsi(reference) {
      this.expandedVsi = this.expandedVsi === reference ? null : reference;
    },

    applyOption(shortageId, optionIndex) {
      const shortage = this.shortages.find(s => s.id === shortageId);
      if (shortage && shortage.options[optionIndex]) {
        shortage.options[optionIndex].applied = !shortage.options[optionIndex].applied;
      }
    },
  };
}
</script>
@endverbatim
