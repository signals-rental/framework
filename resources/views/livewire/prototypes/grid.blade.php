<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Opportunity Grid')] class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<style>
  /* ================================================================ */
  /*  GRID TOKENS                                                      */
  /* ================================================================ */

  :root {
    --gr-hover: rgba(0, 0, 0, 0.03);
    --gr-subtle: var(--base);
    --gr-border-sub: #ecedf1;
    --gr-faint: #b0b4c3;
    --gr-accent-light: rgba(5, 150, 105, 0.04);
    --gr-green-bg: #ecfdf3; --gr-green-bdr: #bbf7d0;
    --gr-amber-bg: #fffbeb; --gr-amber-bdr: #fde68a;
    --gr-red-bg: #fef2f2; --gr-red-bdr: #fecaca;
    --gr-blue-bg: #eff6ff; --gr-blue-bdr: #bfdbfe;
    --gr-shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.1);
    --gr-shadow-xl: 0 20px 60px rgba(0, 0, 0, 0.13);
  }

  .dark {
    --gr-hover: rgba(255, 255, 255, 0.05);
    --gr-subtle: rgba(255, 255, 255, 0.04);
    --gr-border-sub: #283040;
    --gr-faint: #475569;
    --gr-accent-light: rgba(5, 150, 105, 0.06);
    --gr-green-bg: rgba(22, 163, 74, 0.15); --gr-green-bdr: rgba(22, 163, 74, 0.3);
    --gr-amber-bg: rgba(217, 119, 6, 0.15); --gr-amber-bdr: rgba(217, 119, 6, 0.3);
    --gr-red-bg: rgba(220, 38, 38, 0.15); --gr-red-bdr: rgba(220, 38, 38, 0.3);
    --gr-blue-bg: rgba(37, 99, 235, 0.15); --gr-blue-bdr: rgba(37, 99, 235, 0.3);
    --gr-shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.4);
    --gr-shadow-xl: 0 20px 60px rgba(0, 0, 0, 0.5);
  }

  /* ================================================================ */
  /*  PAGE LAYOUT                                                      */
  /* ================================================================ */

  .gr-page {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: hidden;
    font-family: var(--font-mono);
    font-size: 12px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
  }

  /* ================================================================ */
  /*  PAGE HEADER                                                      */
  /* ================================================================ */

  .gr-ph {
    padding: 18px 24px 0;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-shrink: 0;
  }

  .gr-bc {
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .gr-bc a { color: var(--text-muted); text-decoration: none; }
  .gr-bc a:hover { color: var(--text-secondary); }

  .gr-ph-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .gr-ph-meta {
    font-size: 11px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 3px;
  }

  .gr-ph-meta span { display: flex; align-items: center; gap: 4px; }

  .gr-ph-act { display: flex; gap: 8px; align-items: center; }

  /* ================================================================ */
  /*  BADGES                                                           */
  /* ================================================================ */

  .gr-badge {
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

  .gr-badge-g { background: var(--gr-green-bg); color: #16a34a; border: 1px solid var(--gr-green-bdr); }
  .gr-bdot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

  /* ================================================================ */
  /*  BUTTONS                                                          */
  /* ================================================================ */

  .gr-btn {
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
    border: 1px solid var(--card-border);
    background: var(--card-bg);
    color: var(--text-secondary);
    transition: all 0.15s;
    white-space: nowrap;
    box-shadow: var(--shadow-card);
  }

  .gr-btn:hover { background: var(--gr-hover); color: var(--text-primary); border-color: var(--navy-light); }
  .gr-btn svg { width: 14px; height: 14px; }

  .gr-btn-p {
    background: var(--green);
    color: #fff;
    border-color: var(--green);
    box-shadow: 0 1px 3px rgba(5, 150, 105, 0.3);
  }

  .gr-btn-p:hover { background: #06b07a; border-color: #06b07a; color: #fff; }

  /* ================================================================ */
  /*  VIEW TABS                                                        */
  /* ================================================================ */

  .gr-vtabs {
    display: flex;
    padding: 14px 24px 0;
    border-bottom: 1px solid var(--gr-border-sub);
    flex-shrink: 0;
  }

  .gr-vt {
    padding: 8px 14px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: all 0.15s;
  }

  .gr-vt:hover { color: var(--text-secondary); }
  .gr-vt.on { color: var(--green); border-bottom-color: var(--green); }

  .gr-vtc {
    font-family: var(--font-mono);
    font-size: 9px;
    margin-left: 4px;
    color: var(--gr-faint);
  }

  .gr-vt.on .gr-vtc { color: var(--green); opacity: 0.6; }

  /* ================================================================ */
  /*  TOOLBAR                                                          */
  /* ================================================================ */

  .gr-tb {
    display: flex;
    align-items: center;
    padding: 10px 24px;
    gap: 8px;
    flex-shrink: 0;
  }

  .gr-fc {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border: 1px solid var(--card-border);
    color: var(--text-secondary);
    cursor: pointer;
    background: var(--card-bg);
    transition: all 0.15s;
  }

  .gr-fc:hover { border-color: var(--navy-light); color: var(--text-primary); }
  .gr-fc.on { border-color: var(--green); color: var(--green); background: var(--green-muted); }
  .gr-fc svg { width: 12px; height: 12px; }

  .gr-tbsep { width: 1px; height: 20px; background: var(--card-border); margin: 0 4px; }

  .gr-tbr { margin-left: auto; display: flex; align-items: center; gap: 8px; }

  .gr-ct {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    font-family: var(--font-display);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px 8px;
  }

  .gr-ct:hover { color: var(--text-secondary); background: var(--gr-hover); }
  .gr-ct svg { width: 12px; height: 12px; }

  /* ================================================================ */
  /*  GRID WRAPPER & TABLE                                             */
  /* ================================================================ */

  .gr-gw {
    flex: 1;
    overflow: auto;
    padding: 0 24px 120px;
  }

  .gr-gt {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    overflow: hidden;
    box-shadow: var(--shadow-card);
  }

  .gr-gt thead { position: sticky; top: 0; z-index: 10; }

  .gr-gt th {
    background: var(--gr-subtle);
    padding: 9px 12px;
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    text-align: left;
    border-bottom: 1px solid var(--card-border);
    white-space: nowrap;
    cursor: pointer;
    user-select: none;
  }

  .gr-gt th:hover { color: var(--text-secondary); }
  .gr-thc { display: flex; align-items: center; gap: 4px; }
  .gr-cc { width: 36px; text-align: center; }

  .gr-gt tbody tr { transition: background 0.08s; }
  .gr-gt tbody tr:hover { background: var(--gr-accent-light); }
  .gr-gt tbody tr.gr-sel { background: var(--green-muted); }

  .gr-gt td {
    padding: 0;
    border-bottom: 1px solid var(--gr-border-sub);
    height: 42px;
    vertical-align: middle;
    position: relative;
  }

  /* ================================================================ */
  /*  CELLS                                                            */
  /* ================================================================ */

  .gr-c {
    padding: 6px 12px;
    height: 100%;
    display: flex;
    align-items: center;
    cursor: pointer;
    min-height: 41px;
    transition: all 0.1s;
    border: 1.5px solid transparent;
  }

  .gr-c:hover { background: var(--gr-accent-light); }

  .gr-c.ed {
    background: rgba(5, 150, 105, 0.04);
    border-color: var(--green);
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    z-index: 5;
  }

  .gr-cv {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .gr-ci {
    width: 100%;
    background: transparent;
    border: none;
    color: var(--text-primary);
    font-family: var(--font-mono);
    font-size: 12px;
    outline: none;
    padding: 0;
  }

  .gr-c-cur { font-family: var(--font-mono); font-size: 12px; }
  .gr-c-num { font-family: var(--font-mono); font-size: 12px; text-align: right; justify-content: flex-end; }
  .gr-c-date { font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary); }

  .gr-c-prod { display: flex; align-items: center; gap: 8px; }

  .gr-pt {
    width: 30px;
    height: 30px;
    background: var(--gr-subtle);
    border: 1px solid var(--gr-border-sub);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
  }

  .gr-pn { font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .gr-ps { font-family: var(--font-mono); font-size: 9px; color: var(--text-muted); }

  /* ================================================================ */
  /*  CHECKBOX                                                         */
  /* ================================================================ */

  .gr-cb {
    width: 16px;
    height: 16px;
    border: 1.5px solid var(--navy-light);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    background: var(--card-bg);
  }

  .gr-cb:hover { border-color: var(--green); }
  .gr-cb.ck { background: var(--green); border-color: var(--green); }
  .gr-cb svg { display: none; }
  .gr-cb.ck svg { display: block; }

  /* ================================================================ */
  /*  STATUS BADGES                                                    */
  /* ================================================================ */

  .gr-cs {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border: 1px solid transparent;
  }

  .gr-sd { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
  .gr-s-con { background: var(--gr-green-bg); color: #16a34a; border-color: var(--gr-green-bdr); }
  .gr-s-pro { background: var(--gr-amber-bg); color: var(--amber); border-color: var(--gr-amber-bdr); }
  .gr-s-sub { background: var(--gr-blue-bg); color: var(--blue); border-color: var(--gr-blue-bdr); }

  /* Status dropdown */
  .gr-cdd {
    position: absolute;
    top: 100%;
    left: 0;
    min-width: 180px;
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    box-shadow: var(--gr-shadow-lg);
    z-index: 50;
    padding: 4px;
  }

  .gr-cdi {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    font-size: 12px;
    cursor: pointer;
    transition: background 0.1s;
  }

  .gr-cdi:hover { background: var(--gr-hover); }

  /* ================================================================ */
  /*  AVAILABILITY BARS                                                */
  /* ================================================================ */

  .gr-ab { display: flex; align-items: center; gap: 6px; }

  .gr-at {
    width: 48px;
    height: 5px;
    background: var(--gr-subtle);
    border-radius: 3px;
    overflow: hidden;
  }

  .gr-af { height: 100%; border-radius: 3px; }
  .gr-af-g { background: #16a34a; }
  .gr-af-w { background: var(--amber); }
  .gr-af-c { background: var(--red); }

  .gr-ax { font-family: var(--font-mono); font-size: 9px; color: var(--text-muted); white-space: nowrap; }

  .gr-conf {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-family: var(--font-display);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--red);
    background: var(--gr-red-bg);
    border: 1px solid var(--gr-red-bdr);
    padding: 2px 7px;
    margin-left: 6px;
  }

  /* ================================================================ */
  /*  DRAG HANDLE                                                      */
  /* ================================================================ */

  .gr-dh {
    width: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gr-faint);
    opacity: 0;
    cursor: grab;
    font-size: 11px;
    transition: opacity 0.1s;
  }

  .gr-gt tbody tr:hover .gr-dh { opacity: 0.4; }

  /* ================================================================ */
  /*  QUICK ADD ROW                                                    */
  /* ================================================================ */

  .gr-qar td { border-bottom: none; }

  .gr-qac {
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    cursor: text;
    height: 42px;
    transition: all 0.15s;
    position: relative;
  }

  .gr-qac:hover { color: var(--green); }
  .gr-qac.foc { background: var(--gr-accent-light); }
  .gr-qac svg { width: 14px; height: 14px; flex-shrink: 0; }

  .gr-qai {
    background: none;
    border: none;
    color: var(--text-primary);
    font-family: var(--font-mono);
    font-size: 12px;
    outline: none;
    width: 100%;
  }

  .gr-qai::placeholder { color: var(--gr-faint); }

  /* ================================================================ */
  /*  PRODUCT DROPDOWN                                                 */
  /* ================================================================ */

  .gr-pdd {
    position: absolute;
    top: calc(100% + 4px);
    left: 8px;
    width: 580px;
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    box-shadow: var(--gr-shadow-xl);
    z-index: 100;
    overflow: hidden;
  }

  .gr-pdd-h {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px 0;
  }

  .gr-pdd-t {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  .gr-pdd-m { font-size: 10px; color: var(--gr-faint); }

  .gr-pdd-tabs {
    display: flex;
    padding: 0 14px;
    border-bottom: 1px solid var(--gr-border-sub);
    margin-top: 6px;
  }

  .gr-pdt {
    padding: 7px 10px;
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: all 0.15s;
  }

  .gr-pdt:hover { color: var(--text-secondary); }
  .gr-pdt.on { color: var(--green); border-bottom-color: var(--green); }

  .gr-pdd-l { max-height: 340px; overflow-y: auto; padding: 4px; }
  .gr-pdd-l::-webkit-scrollbar { width: 4px; }
  .gr-pdd-l::-webkit-scrollbar-thumb { background: var(--card-border); }

  .gr-pdg {
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--gr-faint);
    padding: 10px 12px 4px;
  }

  .gr-pdi {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 12px;
    cursor: pointer;
    transition: all 0.08s;
  }

  .gr-pdi:hover { background: var(--gr-hover); }
  .gr-pdi.hl { background: var(--green-muted); }

  .gr-pdi-th {
    width: 38px;
    height: 38px;
    background: var(--gr-subtle);
    border: 1px solid var(--gr-border-sub);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    flex-shrink: 0;
  }

  .gr-pdi-i { flex: 1; min-width: 0; }
  .gr-pdi-n { font-weight: 500; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .gr-pdi-n mark, .gr-pdi-sk mark { background: rgba(5, 150, 105, 0.12); color: var(--green); padding: 0 1px; }
  .dark .gr-pdi-n mark, .dark .gr-pdi-sk mark { background: rgba(5, 150, 105, 0.25); }

  .gr-pdi-m { display: flex; align-items: center; gap: 10px; font-size: 10px; color: var(--text-muted); margin-top: 1px; }

  .gr-pdi-sk {
    font-family: var(--font-mono);
    font-size: 9px;
    background: var(--gr-subtle);
    padding: 1px 6px;
    color: var(--text-muted);
  }

  .gr-pdi-cat { display: flex; align-items: center; gap: 3px; }
  .gr-cdot { width: 6px; height: 6px; border-radius: 50%; }
  .gr-cdot-audio { background: #8b5cf6; }
  .gr-cdot-lighting { background: #f59e0b; }
  .gr-cdot-video { background: #3b82f6; }
  .gr-cdot-rigging { background: #6b7280; }
  .gr-cdot-power { background: #ef4444; }
  .gr-cdot-staging { background: #14b8a6; }
  .gr-cdot-comms { background: #f97316; }

  .gr-pdi-r { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0; }
  .gr-pdi-rt { font-family: var(--font-mono); font-size: 11px; font-weight: 600; }

  .gr-pdi-st { font-size: 9px; font-weight: 500; display: flex; align-items: center; gap: 3px; }
  .gr-pdi-st.gd { color: #16a34a; }
  .gr-pdi-st.wn { color: var(--amber); }
  .gr-pdi-st.lo { color: var(--red); }
  .gr-sdot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

  .gr-pdd-f {
    padding: 8px 14px;
    border-top: 1px solid var(--gr-border-sub);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--gr-subtle);
  }

  .gr-pdd-fh { display: flex; gap: 12px; font-size: 9px; color: var(--gr-faint); }
  .gr-pdd-fh span { display: flex; align-items: center; gap: 3px; }

  .gr-pdd-fc {
    font-family: var(--font-display);
    font-size: 10px;
    font-weight: 600;
    color: var(--green);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .gr-pdd-fc:hover { text-decoration: underline; }

  .gr-pdd-empty { padding: 32px 20px; text-align: center; color: var(--text-muted); }
  .gr-pdd-empty-icon { font-size: 28px; margin-bottom: 8px; opacity: 0.5; }
  .gr-pdd-empty-title { font-weight: 600; font-size: 12px; color: var(--text-secondary); margin-bottom: 2px; }
  .gr-pdd-empty-sub { font-size: 11px; }

  /* ================================================================ */
  /*  SUMMARY ROW                                                      */
  /* ================================================================ */

  .gr-sr td { background: var(--gr-subtle); border-top: 2px solid var(--card-border); border-bottom: none; }
  .gr-sr .gr-c { cursor: default; }
  .gr-sr .gr-c:hover { background: transparent; }

  .gr-sl {
    font-family: var(--font-mono);
    color: var(--text-muted);
    text-transform: uppercase;
    font-size: 9px;
    letter-spacing: 0.06em;
    font-weight: 500;
  }

  .gr-sv { font-family: var(--font-mono); font-size: 12px; font-weight: 600; }
  .gr-sv-t { color: var(--green); font-size: 13px; font-weight: 700; }

  /* ================================================================ */
  /*  BULK ACTION BAR                                                  */
  /* ================================================================ */

  .gr-bb {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%) translateY(80px);
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: var(--gr-shadow-xl);
    z-index: 100;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    pointer-events: none;
  }

  .gr-bb.vis { transform: translateX(-50%) translateY(0); opacity: 1; pointer-events: all; }

  .gr-bb-c {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 600;
    color: var(--green);
    padding: 4px 12px;
    background: var(--green-muted);
    white-space: nowrap;
  }

  .gr-bb-s { width: 1px; height: 24px; background: var(--card-border); }

  .gr-ba {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary);
    cursor: pointer;
    background: transparent;
    border: none;
    transition: all 0.15s;
    white-space: nowrap;
  }

  .gr-ba:hover { background: var(--gr-hover); color: var(--text-primary); }
  .gr-ba.gr-ba-dng:hover { background: var(--gr-red-bg); color: var(--red); }
  .gr-ba svg { width: 13px; height: 13px; }

  /* ================================================================ */
  /*  SAVE INDICATOR                                                   */
  /* ================================================================ */

  .gr-sav {
    position: fixed;
    top: 62px;
    right: 24px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 500;
    color: #16a34a;
    opacity: 0;
    transition: opacity 0.3s;
    z-index: 200;
    background: var(--gr-green-bg);
    border: 1px solid var(--gr-green-bdr);
    padding: 4px 12px;
  }

  .gr-sav.vis { opacity: 1; }

  .gr-sav-d {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #16a34a;
    animation: gr-pulse 1s infinite;
  }

  @keyframes gr-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

  /* ================================================================ */
  /*  KEYBOARD HINTS                                                   */
  /* ================================================================ */

  .gr-kh {
    position: fixed;
    bottom: 20px;
    right: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 9px;
    color: var(--gr-faint);
    z-index: 50;
  }

  .gr-kh span { display: flex; align-items: center; gap: 4px; }

  .gr-kbd {
    font-size: 9px;
    color: var(--text-muted);
    background: var(--gr-subtle);
    border: 1px solid var(--card-border);
    padding: 0 4px;
    font-family: var(--font-mono);
    line-height: 1.6;
  }

  /* ================================================================ */
  /*  ANIMATIONS                                                       */
  /* ================================================================ */

  @keyframes gr-fadeInUp {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .gr-gt tbody tr { animation: gr-fadeInUp 0.2s ease both; }
  .gr-gt tbody tr:nth-child(1) { animation-delay: 0.02s; }
  .gr-gt tbody tr:nth-child(2) { animation-delay: 0.04s; }
  .gr-gt tbody tr:nth-child(3) { animation-delay: 0.06s; }
  .gr-gt tbody tr:nth-child(4) { animation-delay: 0.08s; }
  .gr-gt tbody tr:nth-child(5) { animation-delay: 0.1s; }
  .gr-gt tbody tr:nth-child(6) { animation-delay: 0.12s; }
  .gr-gt tbody tr:nth-child(7) { animation-delay: 0.14s; }
  .gr-gt tbody tr:nth-child(8) { animation-delay: 0.16s; }

  .gr-c.gr-flash { animation: gr-saveFlash 0.6s ease; }
  @keyframes gr-saveFlash { 0% { background: rgba(5, 150, 105, 0.08); } 100% { background: transparent; } }

  @keyframes gr-dropIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .gr-pdd[style*="display: block"], .gr-pdd:not([style*="display: none"]):not([style*="display"]) {
    animation: gr-dropIn 0.15s ease;
  }
</style>

<div class="gr-page"
     x-data="opportunityGrid()"
     @keydown.window="handleKeydown($event)">

  {{-- ============================================================ --}}
  {{--  SUBNAV                                                       --}}
  {{-- ============================================================ --}}
  <nav class="app-subnav">
    <div class="flex h-full items-center gap-0">
      <a href="#" class="subnav-link">Overview</a>
      <a href="#" class="subnav-link active">Items</a>
      <a href="#" class="subnav-link">Crew</a>
      <a href="#" class="subnav-link">Transport</a>
      <a href="#" class="subnav-link">Notes</a>
      <a href="#" class="subnav-link">Documents</a>
      <a href="#" class="subnav-link">Activity</a>
    </div>
  </nav>

  {{-- ============================================================ --}}
  {{--  PAGE HEADER                                                  --}}
  {{-- ============================================================ --}}
  <div class="gr-ph">
    <div>
      <div class="gr-bc">
        <a href="#">Opportunities</a>
        <span style="color:var(--gr-faint);font-size:10px">&rsaquo;</span>
        <span style="color:var(--text-primary);font-weight:500">OPP-2026-0847</span>
      </div>
      <div class="gr-ph-title">
        Glastonbury 2026 &mdash; Main Stage
        <span class="gr-badge gr-badge-g"><span class="gr-bdot"></span> Confirmed</span>
      </div>
      <div class="gr-ph-meta">
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          PRG Ltd
        </span>
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          24 Jun &mdash; 29 Jun 2026
        </span>
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          Pilton, Somerset
        </span>
      </div>
    </div>
    <div class="gr-ph-act">
      <button class="gr-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export
      </button>
      <button class="gr-btn gr-btn-p">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>
        Send Quote
      </button>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{--  VIEW TABS                                                    --}}
  {{-- ============================================================ --}}
  <div class="gr-vtabs">
    <template x-for="tab in viewTabs" :key="tab.id">
      <div class="gr-vt"
           :class="{ 'on': activeViewTab === tab.id }"
           @click="activeViewTab = tab.id">
        <span x-text="tab.label"></span>
        <span class="gr-vtc" x-show="tab.count !== null" x-text="tab.count"></span>
      </div>
    </template>
  </div>

  {{-- ============================================================ --}}
  {{--  TOOLBAR                                                      --}}
  {{-- ============================================================ --}}
  <div class="gr-tb">
    <template x-for="(chip, ci) in filterChips" :key="ci">
      <div class="gr-fc"
           :class="{ 'on': (ci === 0 && activeFilter === 'all') || activeFilter === chip }"
           @click="activeFilter = ci === 0 ? 'all' : chip">
        <svg x-show="ci === 0" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        <span x-text="chip"></span>
      </div>
    </template>
    <div class="gr-tbsep"></div>
    <div class="gr-fc">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
      Filters
    </div>
    <div class="gr-tbr">
      <div class="gr-ct">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
        Columns
      </div>
      <div class="gr-ct">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Group
      </div>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{--  GRID TABLE                                                   --}}
  {{-- ============================================================ --}}
  <div class="gr-gw">
    <table class="gr-gt">
      <thead>
        <tr>
          <th class="gr-cc">
            <div class="gr-cb" :class="{ 'ck': allSelected }" @click="toggleAll()">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
          </th>
          <th style="width:30px"></th>
          <th style="min-width:260px"><div class="gr-thc">Product</div></th>
          <th style="width:70px"><div class="gr-thc">Qty</div></th>
          <th style="width:110px"><div class="gr-thc">Rate</div></th>
          <th style="width:80px"><div class="gr-thc">Disc %</div></th>
          <th style="width:110px"><div class="gr-thc">Total</div></th>
          <th style="width:100px"><div class="gr-thc">Out Date</div></th>
          <th style="width:100px"><div class="gr-thc">Return</div></th>
          <th style="width:110px"><div class="gr-thc">Status</div></th>
          <th style="width:130px"><div class="gr-thc">Availability</div></th>
        </tr>
      </thead>

      <tbody>
        {{-- Data rows --}}
        <template x-for="(row, idx) in rows" :key="idx">
          <tr :class="{ 'gr-sel': isSelected(idx) }">
            {{-- Checkbox --}}
            <td class="gr-cc">
              <div class="gr-cb" :class="{ 'ck': isSelected(idx) }" @click="toggleRow(idx)">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
            </td>

            {{-- Drag handle --}}
            <td><div class="gr-dh">&DoubleDot;</div></td>

            {{-- Product --}}
            <td>
              <div class="gr-c gr-c-prod">
                <div class="gr-pt" x-text="row.icon"></div>
                <div>
                  <div class="gr-pn" x-text="row.name"></div>
                  <div class="gr-ps" x-text="row.sku"></div>
                </div>
              </div>
            </td>

            {{-- Qty (editable) --}}
            <td>
              <div class="gr-c gr-c-num"
                   :class="{ 'ed': editingCell.row === idx && editingCell.field === 'qty' }"
                   @click="startEdit(idx, 'qty')">
                <input x-show="editingCell.row === idx && editingCell.field === 'qty'"
                       class="gr-ci" x-model="editValue"
                       @blur="finishEdit()" @keydown.enter.prevent="finishEdit()"
                       @keydown.tab.prevent="finishEdit()" @keydown.escape.prevent="cancelEdit()"
                       style="text-align:right;width:100%">
                <span x-show="!(editingCell.row === idx && editingCell.field === 'qty')"
                      class="gr-cv" x-text="row.qty"></span>
              </div>
            </td>

            {{-- Rate (editable) --}}
            <td>
              <div class="gr-c gr-c-cur"
                   :class="{ 'ed': editingCell.row === idx && editingCell.field === 'rate' }"
                   @click="startEdit(idx, 'rate')">
                <input x-show="editingCell.row === idx && editingCell.field === 'rate'"
                       class="gr-ci" x-model="editValue"
                       @blur="finishEdit()" @keydown.enter.prevent="finishEdit()"
                       @keydown.tab.prevent="finishEdit()" @keydown.escape.prevent="cancelEdit()"
                       style="width:100%">
                <span x-show="!(editingCell.row === idx && editingCell.field === 'rate')"
                      class="gr-cv" x-text="fmtCurrency(row.rate)"></span>
              </div>
            </td>

            {{-- Discount (editable) --}}
            <td>
              <div class="gr-c gr-c-num"
                   :class="{ 'ed': editingCell.row === idx && editingCell.field === 'disc' }"
                   @click="startEdit(idx, 'disc')">
                <input x-show="editingCell.row === idx && editingCell.field === 'disc'"
                       class="gr-ci" x-model="editValue"
                       @blur="finishEdit()" @keydown.enter.prevent="finishEdit()"
                       @keydown.tab.prevent="finishEdit()" @keydown.escape.prevent="cancelEdit()"
                       style="text-align:right;width:100%">
                <span x-show="!(editingCell.row === idx && editingCell.field === 'disc')"
                      class="gr-cv" x-text="row.disc"></span>
              </div>
            </td>

            {{-- Total (computed) --}}
            <td>
              <div class="gr-c gr-c-cur" style="cursor:default">
                <span class="gr-cv" x-text="fmtCurrency(row.totalRaw)"></span>
              </div>
            </td>

            {{-- Out Date --}}
            <td>
              <div class="gr-c gr-c-date" style="cursor:default">
                <span class="gr-cv" x-text="row.out"></span>
              </div>
            </td>

            {{-- Return --}}
            <td>
              <div class="gr-c gr-c-date" style="cursor:default">
                <span class="gr-cv" x-text="row.ret"></span>
              </div>
            </td>

            {{-- Status --}}
            <td>
              <div style="position:relative" @click.outside="statusDropdownRow = null">
                <div class="gr-c" @click="toggleStatusDropdown(idx)">
                  <span class="gr-cs" :class="statusMap[row.status].cls">
                    <span class="gr-sd"></span>
                    <span x-text="statusMap[row.status].label"></span>
                  </span>
                </div>
                <div class="gr-cdd" x-show="statusDropdownRow === idx"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0">
                  <template x-for="skey in statusOptions" :key="skey">
                    <div class="gr-cdi" @click.stop="setStatus(idx, skey)">
                      <span class="gr-cs" :class="statusMap[skey].cls">
                        <span class="gr-sd"></span>
                        <span x-text="statusMap[skey].label"></span>
                      </span>
                    </div>
                  </template>
                </div>
              </div>
            </td>

            {{-- Availability --}}
            <td>
              <div class="gr-c" style="cursor:default">
                <div class="gr-ab">
                  <div class="gr-at">
                    <div class="gr-af"
                         :class="row.isSub ? '' : availClass(row.avP)"
                         :style="`width:${row.avP}%${row.isSub ? ';background:var(--blue)' : ''}`"></div>
                  </div>
                  <span class="gr-ax" :style="row.isSub ? 'color:var(--blue)' : ''"
                        x-text="row.avT"></span>
                </div>
                <template x-if="row.conflict">
                  <span class="gr-conf">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span x-text="row.conflict"></span>
                  </span>
                </template>
              </div>
            </td>
          </tr>
        </template>

        {{-- Quick add row --}}
        <tr class="gr-qar">
          <td class="gr-cc"></td>
          <td></td>
          <td colspan="9">
            <div class="gr-qac" :class="{ 'foc': productOpen }">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              <input class="gr-qai" x-model="productQuery"
                     @input="openProductSearch()"
                     @focus="openProductSearch()"
                     @keydown="handleProductKeydown($event)"
                     placeholder="Type to add product — search by name or SKU...">
              <div class="gr-pdd" x-show="productOpen"
                   @click.outside="closeProductSearch()"
                   x-transition:enter="transition ease-out duration-100"
                   x-transition:enter-start="opacity-0 -translate-y-1"
                   x-transition:enter-end="opacity-100 translate-y-0"
                   x-transition:leave="transition ease-in duration-75"
                   x-transition:leave-start="opacity-100"
                   x-transition:leave-end="opacity-0">
                <div class="gr-pdd-h">
                  <span class="gr-pdd-t">Products</span>
                  <span class="gr-pdd-m" x-text="displayProducts.length + ' result' + (displayProducts.length !== 1 ? 's' : '')"></span>
                </div>
                <div class="gr-pdd-tabs">
                  <template x-for="pcat in productCategories" :key="pcat">
                    <div class="gr-pdt" :class="{ 'on': productTab === pcat }"
                         @click="productTab = pcat; productHlIdx = 0">
                      <span x-text="pcat"></span>
                      <span style="font-family:var(--font-mono);font-size:9px;opacity:.5;margin-left:2px"
                            x-text="pcat === 'All' ? filteredProducts.length : filteredProducts.filter(p => p.cat === pcat).length"></span>
                    </div>
                  </template>
                </div>
                <div class="gr-pdd-l">
                  <template x-if="displayProducts.length === 0">
                    <div class="gr-pdd-empty">
                      <div class="gr-pdd-empty-icon">&#x1F50D;</div>
                      <div class="gr-pdd-empty-title">No products found</div>
                      <div class="gr-pdd-empty-sub">Try a different search term or create a new product</div>
                    </div>
                  </template>
                  <template x-for="(product, pi) in displayProducts" :key="product.sku">
                    <div>
                      <div x-show="pi === 0 || displayProducts[pi - 1]?.cat !== product.cat"
                           class="gr-pdg" x-text="product.cat"></div>
                      <div class="gr-pdi"
                           :class="{ 'hl': pi === productHlIdx }"
                           @click="selectProduct(product)"
                           @mouseenter="productHlIdx = pi">
                        <div class="gr-pdi-th" x-text="product.icon"></div>
                        <div class="gr-pdi-i">
                          <div class="gr-pdi-n" x-html="highlightMatch(product.name, productQuery)"></div>
                          <div class="gr-pdi-m">
                            <span class="gr-pdi-sk" x-html="highlightMatch(product.sku, productQuery)"></span>
                            <span class="gr-pdi-cat">
                              <span class="gr-cdot" :class="`gr-cdot-${product.cat.toLowerCase()}`"></span>
                              <span x-text="product.cat"></span>
                            </span>
                          </div>
                        </div>
                        <div class="gr-pdi-r">
                          <span class="gr-pdi-rt" x-text="product.rate"></span>
                          <span class="gr-pdi-st" :class="stockClass(product.stock)">
                            <span class="gr-sdot"></span>
                            <span x-text="product.avail + ' avail'"></span>
                          </span>
                        </div>
                      </div>
                    </div>
                  </template>
                </div>
                <div class="gr-pdd-f">
                  <div class="gr-pdd-fh">
                    <span><span class="gr-kbd">&uarr;&darr;</span> navigate</span>
                    <span><span class="gr-kbd">Enter</span> select</span>
                    <span><span class="gr-kbd">Esc</span> close</span>
                  </div>
                  <div class="gr-pdd-fc">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Create new product
                  </div>
                </div>
              </div>
            </div>
          </td>
        </tr>

        {{-- Summary row --}}
        <tr class="gr-sr">
          <td class="gr-cc"></td>
          <td></td>
          <td><div class="gr-c"><span class="gr-sl" x-text="rows.length + ' line items'"></span></div></td>
          <td><div class="gr-c gr-c-num"><span class="gr-sv" x-text="summaryQty"></span></div></td>
          <td><div class="gr-c"></div></td>
          <td><div class="gr-c"></div></td>
          <td><div class="gr-c gr-c-cur"><span class="gr-sv gr-sv-t" x-text="fmtCurrency(summaryTotal)"></span></div></td>
          <td colspan="4"><div class="gr-c"></div></td>
        </tr>
      </tbody>
    </table>
  </div>

  {{-- ============================================================ --}}
  {{--  BULK ACTION BAR                                              --}}
  {{-- ============================================================ --}}
  <div class="gr-bb" :class="{ 'vis': selectedCount > 0 }">
    <span class="gr-bb-c" x-text="selectedCount + ' selected'"></span>
    <div class="gr-bb-s"></div>
    <button class="gr-ba">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
      Change Dates
    </button>
    <button class="gr-ba">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      Adjust Rates
    </button>
    <button class="gr-ba">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Set Status
    </button>
    <div class="gr-bb-s"></div>
    <button class="gr-ba gr-ba-dng">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
      Remove
    </button>
  </div>

  {{-- ============================================================ --}}
  {{--  SAVE INDICATOR                                               --}}
  {{-- ============================================================ --}}
  <div class="gr-sav" :class="{ 'vis': saveVisible }">
    <div class="gr-sav-d"></div>
    <span x-text="saveText"></span>
  </div>

  {{-- ============================================================ --}}
  {{--  KEYBOARD HINTS                                               --}}
  {{-- ============================================================ --}}
  <div class="gr-kh">
    <span><span class="gr-kbd">Tab</span> next cell</span>
    <span><span class="gr-kbd">Enter</span> save &amp; down</span>
    <span><span class="gr-kbd">Esc</span> cancel</span>
    <span><span class="gr-kbd">/</span> search</span>
  </div>
</div>

@verbatim
<script>
function opportunityGrid() {
  return {
    // ─── STATE ───
    activeViewTab: 'equipment',
    activeFilter: 'all',
    selectedRows: {},
    editingCell: { row: null, field: null },
    editValue: '',
    statusDropdownRow: null,
    productQuery: '',
    productOpen: false,
    productTab: 'All',
    productHlIdx: 0,
    saveVisible: false,
    saveText: 'Saved',
    saveTimeout: null,

    // ─── VIEW TABS ───
    viewTabs: [
      { id: 'equipment', label: 'Equipment', count: 8 },
      { id: 'crew', label: 'Crew', count: 3 },
      { id: 'transport', label: 'Transport', count: 2 },
      { id: 'notes', label: 'Notes', count: null },
      { id: 'documents', label: 'Documents', count: null },
      { id: 'activity', label: 'Activity', count: null },
    ],

    // ─── FILTER CHIPS ───
    filterChips: ['All Items', 'Audio', 'Lighting', 'Video', 'Rigging'],

    // ─── STATUS ───
    statusMap: {
      con: { cls: 'gr-s-con', label: 'Confirmed' },
      pro: { cls: 'gr-s-pro', label: 'Provisional' },
      sub: { cls: 'gr-s-sub', label: 'Sub-hire' },
    },
    statusOptions: ['con', 'pro', 'sub'],

    // ─── GRID ROWS ───
    rows: [
      { icon: '🔊', name: `d&b audiotechnik Y8`, sku: 'AUD-Y8-001', qty: 24, rate: 185, disc: 10, totalRaw: 3996, out: '22 Jun', ret: '30 Jun', status: 'con', avP: 80, avT: '24/30' },
      { icon: '🔊', name: `d&b Y-SUB`, sku: 'AUD-YSUB-001', qty: 16, rate: 210, disc: 10, totalRaw: 3024, out: '22 Jun', ret: '30 Jun', status: 'con', avP: 100, avT: '16/16' },
      { icon: '💡', name: `Martin MAC Viper Profile`, sku: 'LX-MVP-001', qty: 48, rate: 95, disc: 15, totalRaw: 3876, out: '23 Jun', ret: '30 Jun', status: 'con', avP: 55, avT: '48/86' },
      { icon: '💡', name: `Robe BMFL Spot`, sku: 'LX-BMFL-001', qty: 32, rate: 120, disc: 10, totalRaw: 3456, out: '23 Jun', ret: '30 Jun', status: 'pro', avP: 30, avT: '12/32', conflict: '-20' },
      { icon: '📺', name: `ROE Visual CB5 LED Panel`, sku: 'VID-CB5-001', qty: 120, rate: 45, disc: 5, totalRaw: 5130, out: '22 Jun', ret: '30 Jun', status: 'con', avP: 70, avT: '120/170' },
      { icon: '📺', name: `Brompton Tessera SX40`, sku: 'VID-SX40-001', qty: 4, rate: 280, disc: 0, totalRaw: 1120, out: '22 Jun', ret: '30 Jun', status: 'con', avP: 90, avT: '4/4' },
      { icon: '🔗', name: `Kinesys Elevation 1+`, sku: 'RIG-KIN-001', qty: 8, rate: 340, disc: 0, totalRaw: 2720, out: '21 Jun', ret: '30 Jun', status: 'sub', avP: 0, avT: 'Sub-hire', isSub: true },
      { icon: '🔗', name: `CM Lodestar 1T`, sku: 'RIG-CM1T-001', qty: 40, rate: 35, disc: 0, totalRaw: 1400, out: '21 Jun', ret: '30 Jun', status: 'con', avP: 75, avT: '40/54' },
    ],

    // ─── PRODUCT CATALOGUE ───
    catalogue: [
      { name: `d&b audiotechnik Y8`, sku: 'AUD-Y8-001', icon: '🔊', cat: 'Audio', rate: '£185.00', avail: 30, stock: 'good' },
      { name: `d&b Y-SUB`, sku: 'AUD-YSUB-001', icon: '🔊', cat: 'Audio', rate: '£210.00', avail: 16, stock: 'good' },
      { name: `d&b audiotechnik SL-Sub`, sku: 'AUD-SLS-001', icon: '🔊', cat: 'Audio', rate: '£195.00', avail: 18, stock: 'good' },
      { name: `d&b audiotechnik V8`, sku: 'AUD-V8-001', icon: '🔊', cat: 'Audio', rate: '£175.00', avail: 24, stock: 'good' },
      { name: `d&b audiotechnik J-Series`, sku: 'AUD-JS-001', icon: '🔊', cat: 'Audio', rate: '£165.00', avail: 12, stock: 'warning' },
      { name: `L-Acoustics K2`, sku: 'AUD-K2-001', icon: '🔊', cat: 'Audio', rate: '£195.00', avail: 32, stock: 'good' },
      { name: `L-Acoustics KARA II`, sku: 'AUD-KA2-001', icon: '🔊', cat: 'Audio', rate: '£145.00', avail: 48, stock: 'good' },
      { name: `L-Acoustics KS28 Sub`, sku: 'AUD-KS28-001', icon: '🔊', cat: 'Audio', rate: '£120.00', avail: 24, stock: 'good' },
      { name: `Shure Axient Digital SLXD`, sku: 'AUD-SLXD-001', icon: '🔊', cat: 'Audio', rate: '£65.00', avail: 42, stock: 'good' },
      { name: `Sennheiser EW-DX`, sku: 'AUD-EWDX-001', icon: '🔊', cat: 'Audio', rate: '£55.00', avail: 36, stock: 'good' },
      { name: `DiGiCo SD7 Quantum`, sku: 'AUD-SD7Q-001', icon: '🔊', cat: 'Audio', rate: '£850.00', avail: 2, stock: 'low' },
      { name: `DiGiCo SD12`, sku: 'AUD-SD12-001', icon: '🔊', cat: 'Audio', rate: '£450.00', avail: 4, stock: 'warning' },
      { name: `Yamaha CL5 Console`, sku: 'AUD-CL5-001', icon: '🔊', cat: 'Audio', rate: '£320.00', avail: 3, stock: 'low' },
      { name: `Allen & Heath dLive S7000`, sku: 'AUD-DL7K-001', icon: '🔊', cat: 'Audio', rate: '£380.00', avail: 2, stock: 'low' },
      { name: `Martin MAC Viper Profile`, sku: 'LX-MVP-001', icon: '💡', cat: 'Lighting', rate: '£95.00', avail: 86, stock: 'good' },
      { name: `Robe BMFL Spot`, sku: 'LX-BMFL-001', icon: '💡', cat: 'Lighting', rate: '£120.00', avail: 32, stock: 'good' },
      { name: `Robe MegaPointe`, sku: 'LX-MEGA-001', icon: '💡', cat: 'Lighting', rate: '£85.00', avail: 64, stock: 'good' },
      { name: `Robe T1 Profile`, sku: 'LX-T1P-001', icon: '💡', cat: 'Lighting', rate: '£110.00', avail: 28, stock: 'good' },
      { name: `Claypaky Sharpy Plus`, sku: 'LX-SHRP-001', icon: '💡', cat: 'Lighting', rate: '£65.00', avail: 48, stock: 'good' },
      { name: `Claypaky Scenius Unico`, sku: 'LX-SCUN-001', icon: '💡', cat: 'Lighting', rate: '£95.00', avail: 24, stock: 'good' },
      { name: `ETC Source Four Series 3 LED`, sku: 'LX-S4S3-001', icon: '💡', cat: 'Lighting', rate: '£42.00', avail: 96, stock: 'good' },
      { name: `ETC ColorSource Spot`, sku: 'LX-ETCCS-001', icon: '💡', cat: 'Lighting', rate: '£35.00', avail: 60, stock: 'good' },
      { name: `GLP JDC2 Strobe/Light`, sku: 'LX-JDC2-001', icon: '💡', cat: 'Lighting', rate: '£85.00', avail: 8, stock: 'warning' },
      { name: `GLP impression X5`, sku: 'LX-GX5-001', icon: '💡', cat: 'Lighting', rate: '£75.00', avail: 40, stock: 'good' },
      { name: `grandMA3 Full Size`, sku: 'LX-GMA3-001', icon: '💡', cat: 'Lighting', rate: '£650.00', avail: 3, stock: 'low' },
      { name: `grandMA3 Light`, sku: 'LX-GMA3L-001', icon: '💡', cat: 'Lighting', rate: '£380.00', avail: 5, stock: 'warning' },
      { name: `Avolites Arena Console`, sku: 'LX-AVA-001', icon: '💡', cat: 'Lighting', rate: '£280.00', avail: 4, stock: 'warning' },
      { name: `ROE Visual CB5 LED Panel`, sku: 'VID-CB5-001', icon: '📺', cat: 'Video', rate: '£45.00', avail: 170, stock: 'good' },
      { name: `ROE Visual BP2V2 LED Panel`, sku: 'VID-BP2-001', icon: '📺', cat: 'Video', rate: '£55.00', avail: 200, stock: 'good' },
      { name: `Brompton Tessera SX40`, sku: 'VID-SX40-001', icon: '📺', cat: 'Video', rate: '£280.00', avail: 4, stock: 'warning' },
      { name: `Brompton Tessera S8`, sku: 'VID-TS8-001', icon: '📺', cat: 'Video', rate: '£180.00', avail: 8, stock: 'warning' },
      { name: `Barco UDX-4K32 Projector`, sku: 'VID-UDX-001', icon: '📺', cat: 'Video', rate: '£1,200.00', avail: 3, stock: 'low' },
      { name: `Barco E2 Presentation Switcher`, sku: 'VID-E2-001', icon: '📺', cat: 'Video', rate: '£450.00', avail: 2, stock: 'low' },
      { name: `Disguise GX2c Media Server`, sku: 'VID-GX2-001', icon: '📺', cat: 'Video', rate: '£650.00', avail: 4, stock: 'warning' },
      { name: `Blackmagic ATEM 4 M/E`, sku: 'VID-ATEM-001', icon: '📺', cat: 'Video', rate: '£320.00', avail: 3, stock: 'low' },
      { name: `Kinesys Elevation 1+`, sku: 'RIG-KIN-001', icon: '🔗', cat: 'Rigging', rate: '£340.00', avail: 8, stock: 'warning' },
      { name: `CM Lodestar 1T`, sku: 'RIG-CM1T-001', icon: '🔗', cat: 'Rigging', rate: '£35.00', avail: 54, stock: 'good' },
      { name: `CM Lodestar 500kg`, sku: 'RIG-CM5-001', icon: '🔗', cat: 'Rigging', rate: '£28.00', avail: 80, stock: 'good' },
      { name: `Prolyte H30V Truss 3m`, sku: 'RIG-H30-001', icon: '🔗', cat: 'Rigging', rate: '£18.00', avail: 120, stock: 'good' },
      { name: `Prolyte S36R Circle Truss`, sku: 'RIG-S36-001', icon: '🔗', cat: 'Rigging', rate: '£65.00', avail: 8, stock: 'warning' },
      { name: `Tyler GT Truss 12x10`, sku: 'RIG-TGT-001', icon: '🔗', cat: 'Rigging', rate: '£85.00', avail: 12, stock: 'good' },
      { name: `Motion Labs PD SL200`, sku: 'PWR-SL200-001', icon: '⚡', cat: 'Power', rate: '£28.00', avail: 3, stock: 'low' },
      { name: `SL Power Socapex Fan-out`, sku: 'PWR-SLFO-001', icon: '⚡', cat: 'Power', rate: '£8.00', avail: 200, stock: 'good' },
      { name: `Outboard 48-way Dimmer Rack`, sku: 'PWR-DIM48-001', icon: '⚡', cat: 'Power', rate: '£120.00', avail: 6, stock: 'warning' },
      { name: `Ceeform 63A Distro`, sku: 'PWR-C63-001', icon: '⚡', cat: 'Power', rate: '£45.00', avail: 24, stock: 'good' },
      { name: `Ceeform 125A Distro`, sku: 'PWR-C125-001', icon: '⚡', cat: 'Power', rate: '£65.00', avail: 12, stock: 'good' },
      { name: `Mojo Barriers Aluminium`, sku: 'STG-MOJO-001', icon: '🏗️', cat: 'Staging', rate: '£12.00', avail: 300, stock: 'good' },
      { name: `Steeldeck 8x4 Platform`, sku: 'STG-SD84-001', icon: '🏗️', cat: 'Staging', rate: '£22.00', avail: 80, stock: 'good' },
      { name: `Riedel Bolero Wireless Intercom`, sku: 'COM-BOL-001', icon: '🎧', cat: 'Comms', rate: '£75.00', avail: 24, stock: 'good' },
      { name: `Riedel Artist 128 Frame`, sku: 'COM-ART-001', icon: '🎧', cat: 'Comms', rate: '£320.00', avail: 2, stock: 'low' },
      { name: `Clear-Com FreeSpeak II`, sku: 'COM-FS2-001', icon: '🎧', cat: 'Comms', rate: '£65.00', avail: 18, stock: 'good' },
    ],

    // ─── COMPUTED ───
    get selectedCount() {
      return Object.values(this.selectedRows).filter(Boolean).length;
    },

    get allSelected() {
      return this.rows.length > 0 && this.selectedCount === this.rows.length;
    },

    get filteredProducts() {
      const q = this.productQuery.toLowerCase().trim();
      if (!q) return [];
      return this.catalogue.filter(p =>
        p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q)
      );
    },

    get productCategories() {
      return ['All', ...new Set(this.filteredProducts.map(p => p.cat))];
    },

    get displayProducts() {
      if (this.productTab === 'All') return this.filteredProducts;
      return this.filteredProducts.filter(p => p.cat === this.productTab);
    },

    get summaryQty() {
      return this.rows.reduce((sum, r) => sum + r.qty, 0);
    },

    get summaryTotal() {
      return this.rows.reduce((sum, r) => sum + r.totalRaw, 0);
    },

    // ─── SELECTION ───
    toggleRow(idx) {
      this.selectedRows[idx] = !this.selectedRows[idx];
    },

    toggleAll() {
      const selectAll = !this.allSelected;
      this.rows.forEach((_, i) => { this.selectedRows[i] = selectAll; });
    },

    isSelected(idx) {
      return !!this.selectedRows[idx];
    },

    // ─── INLINE EDITING ───
    startEdit(idx, field) {
      if (this.editingCell.row !== null) this.finishEdit();
      this.editingCell = { row: idx, field };
      const val = this.rows[idx][field];
      this.editValue = field === 'rate' ? this.fmtCurrency(val) : String(val);
      this.$nextTick(() => {
        const input = document.querySelector('.gr-c.ed .gr-ci');
        if (input) { input.focus(); input.select(); }
      });
    },

    finishEdit() {
      const { row, field } = this.editingCell;
      if (row === null) return;
      let val = this.editValue;
      if (field === 'rate') {
        val = parseFloat(val.replace(/[^0-9.]/g, '')) || 0;
      } else {
        val = parseFloat(val) || 0;
      }
      this.rows[row][field] = val;
      this.recalcRow(row);
      this.editingCell = { row: null, field: null };
      this.editValue = '';
      this.showSave('Saved');
    },

    cancelEdit() {
      this.editingCell = { row: null, field: null };
      this.editValue = '';
    },

    recalcRow(idx) {
      const r = this.rows[idx];
      r.totalRaw = r.qty * r.rate * (1 - r.disc / 100);
    },

    // ─── STATUS ───
    toggleStatusDropdown(idx) {
      this.statusDropdownRow = this.statusDropdownRow === idx ? null : idx;
    },

    setStatus(idx, status) {
      this.rows[idx].status = status;
      this.statusDropdownRow = null;
      this.showSave(`Status: ${this.statusMap[status].label}`);
    },

    // ─── PRODUCT SEARCH ───
    openProductSearch() {
      if (this.productQuery.trim().length > 0) {
        this.productOpen = true;
        this.productHlIdx = 0;
        this.productTab = 'All';
      } else {
        this.productOpen = false;
      }
    },

    closeProductSearch() {
      this.productOpen = false;
      this.productTab = 'All';
    },

    selectProduct(product) {
      this.productQuery = '';
      this.productOpen = false;
      this.productTab = 'All';
      this.showSave(`Added ${product.name}`);
    },

    handleProductKeydown(e) {
      if (!this.productOpen) return;
      const items = this.displayProducts;
      if (!items.length) return;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        this.productHlIdx = Math.min(this.productHlIdx + 1, items.length - 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        this.productHlIdx = Math.max(this.productHlIdx - 1, 0);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (items[this.productHlIdx]) this.selectProduct(items[this.productHlIdx]);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        this.closeProductSearch();
      }
    },

    // ─── UTILITIES ───
    highlightMatch(text, query) {
      if (!query) return text;
      const safe = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      return text.replace(new RegExp(`(${safe})`, 'gi'), '<mark>$1</mark>');
    },

    fmtCurrency(val) {
      return `£${val.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
    },

    availClass(pct) {
      return pct >= 70 ? 'gr-af-g' : pct >= 40 ? 'gr-af-w' : 'gr-af-c';
    },

    stockClass(stock) {
      return stock === 'good' ? 'gd' : stock === 'warning' ? 'wn' : 'lo';
    },

    showSave(text) {
      this.saveText = text;
      this.saveVisible = true;
      if (this.saveTimeout) clearTimeout(this.saveTimeout);
      this.saveTimeout = setTimeout(() => { this.saveVisible = false; }, 2000);
    },

    handleKeydown(e) {
      if (e.key === '/' && !e.ctrlKey && !e.metaKey && document.activeElement.tagName !== 'INPUT') {
        e.preventDefault();
        const input = document.querySelector('.gr-qai');
        if (input) input.focus();
      }
    },
  };
}
</script>
@endverbatim
