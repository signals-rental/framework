@php
    $format = request()->query('format', 'linkedin');
    $isSquare = $format === 'instagram';
@endphp

@extends('social.layout', ['title' => 'API'])

@section('styles')
<style>
    /* ── Layout ── */
    .promo-layout {
        position: relative;
        z-index: 1;
        height: 100%;
    }

    /* ── LinkedIn: side-by-side ── */
    .promo-layout.linkedin {
        display: grid;
        grid-template-columns: 400px 1fr;
        padding: 52px 60px;
        gap: 52px;
    }

    .promo-layout.linkedin .copy {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 24px;
    }

    .promo-layout.linkedin .terminal-area {
        display: flex;
    }

    .promo-layout.linkedin .headline {
        font-size: 54px;
    }

    .promo-layout.linkedin .subline {
        font-size: 14px;
        max-width: 380px;
    }

    .promo-layout.linkedin .feature {
        font-size: 14px;
    }

    .promo-layout.linkedin .cta-url {
        font-size: 12px;
    }

    .promo-layout.linkedin .terminal-body {
        font-size: 16px;
        padding: 22px 26px;
    }

    .promo-layout.linkedin .terminal-area,
    .promo-layout.instagram .terminal-area {
        overflow: hidden;
    }

    /* ── Instagram: stacked, bigger text ── */
    .promo-layout.instagram {
        display: flex;
        flex-direction: column;
        padding: 56px 56px 48px;
        gap: 28px;
    }

    .promo-layout.instagram .copy {
        display: flex;
        flex-direction: column;
        gap: 18px;
        flex-shrink: 0;
    }

    .promo-layout.instagram .terminal-area {
        flex: 1;
        min-height: 0;
        display: flex;
        align-items: stretch;
    }

    .promo-layout.instagram .terminal {
        flex: 1;
    }

    .promo-layout.instagram .headline {
        font-size: 64px;
    }

    .promo-layout.instagram .subline {
        display: none;
    }

    .promo-layout.instagram .features {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 6px 24px;
    }

    .promo-layout.instagram .feature {
        font-size: 16px;
    }

    .promo-layout.instagram .cta-url {
        font-size: 14px;
    }

    .promo-layout.instagram .terminal-body {
        font-size: 18px;
        padding: 24px 28px;
    }

    .promo-layout.instagram .terminal-header {
        padding: 14px 20px;
    }

    .promo-layout.instagram .terminal-title {
        font-size: 11px;
    }

    /* ── Copy ── */
    .headline {
        font-family: var(--font-display);
        font-weight: 700;
        line-height: 1.08;
        letter-spacing: -0.01em;
        text-transform: uppercase;
        color: var(--white);
    }

    .headline .hl-accent { color: var(--syn-green); }

    .subline {
        font-family: var(--font-mono);
        line-height: 1.8;
        color: var(--grey-light);
    }

    .features {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .feature {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--grey-light);
        font-family: var(--font-mono);
    }

    .feature-dot {
        width: 6px;
        height: 6px;
        background: var(--green);
        flex-shrink: 0;
    }

    .cta-url {
        font-family: var(--font-mono);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--grey);
    }

    /* ── Terminal ── */
    .terminal {
        width: 100%;
        background: var(--navy-mid);
        border: 1px solid rgba(148, 163, 184, 0.12);
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    }

    .terminal-header {
        background: rgba(15, 23, 42, 0.6);
        padding: 10px 16px;
        display: flex;
        align-items: center;
        gap: 7px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.08);
        flex-shrink: 0;
    }

    .terminal-dot { width: 10px; height: 10px; }
    .terminal-dot.red { background: #ef4444; }
    .terminal-dot.yellow { background: #eab308; }
    .terminal-dot.green { background: #22c55e; }

    .terminal-title {
        flex: 1;
        text-align: center;
        font-family: var(--font-mono);
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--grey);
        margin-right: 34px;
    }

    .terminal-body {
        font-family: var(--font-mono);
        line-height: 1.8;
        overflow-y: auto;
        scroll-behavior: smooth;
    }

    /* Hide scrollbar but keep functionality */
    .terminal-body::-webkit-scrollbar { width: 0; }
    .terminal-body { scrollbar-width: none; }

    /* ── Code Lines ── */
    .line {
        opacity: 0;
        transform: translateY(3px);
        white-space: pre;
    }

    .line.visible {
        opacity: 1;
        transform: translateY(0);
        transition: opacity 0.25s, transform 0.25s;
    }

    .prompt { color: var(--syn-green); }
    .method { color: var(--syn-amber); font-weight: 500; }
    .url { color: var(--white); }
    .flag { color: var(--syn-purple); }
    .flag-val { color: var(--syn-blue); }
    .comment { color: var(--grey); font-style: italic; }
    .key { color: var(--syn-blue); }
    .string { color: var(--syn-green); }
    .number { color: var(--syn-amber); }
    .brace { color: var(--grey); }

    .cursor {
        display: inline-block;
        width: 9px;
        height: 17px;
        background: var(--green);
        animation: blink 1s step-end infinite;
        vertical-align: text-bottom;
        margin-left: 1px;
    }

    @keyframes blink { 50% { opacity: 0; } }

    .response-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 2px 8px;
        background: rgba(5, 150, 105, 0.1);
        border: 1px solid rgba(5, 150, 105, 0.2);
        font-size: 12px;
        color: var(--syn-green);
        font-weight: 500;
    }

    .response-badge .dot {
        width: 5px; height: 5px;
        background: var(--syn-green);
    }

    .sep {
        height: 1px;
        background: rgba(148, 163, 184, 0.08);
        margin: 12px 0;
        opacity: 0;
    }

    .sep.visible { opacity: 1; transition: opacity 0.4s; }
</style>
@endsection

@section('content')
<div class="promo-layout {{ $format }}">
    <div class="copy">
        <span class="logo-mark {{ $isSquare ? 'size-xl' : 'size-lg' }} color-green" style="color: var(--white);">Signals</span>

        <h1 class="headline">
            Your rental<br>business,<br><span class="hl-accent">as an API.</span>
        </h1>

        <p class="subline">
            Full REST API with OpenAPI docs, token auth, Ransack-compatible filtering, and real-time webhooks.
        </p>

        <div class="features">
            <div class="feature"><span class="feature-dot"></span> Members, quotes, orders, invoices</div>
            <div class="feature"><span class="feature-dot"></span> Availability and stock in real-time</div>
            <div class="feature"><span class="feature-dot"></span> Webhooks with HMAC signing</div>
            <div class="feature"><span class="feature-dot"></span> OpenAPI spec auto-generated</div>
        </div>

        <span class="cta-url">docs.signals.rent</span>
    </div>

    <div class="terminal-area">
        <div class="terminal">
            <div class="terminal-header">
                <div class="terminal-dot red"></div>
                <div class="terminal-dot yellow"></div>
                <div class="terminal-dot green"></div>
                <div class="terminal-title">signals-api</div>
            </div>
            <div class="terminal-body" id="terminal"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const terminal = document.getElementById('terminal');

    // Force terminal to fill available space and scroll internally.
    // We calculate from the fixed canvas size and measure the copy block.
    (function constrainTerminal() {
        const canvas = document.querySelector('.canvas');
        const layout = document.querySelector('.promo-layout');
        const copy = layout.querySelector('.copy');
        const terminalEl = document.querySelector('.terminal');
        const header = document.querySelector('.terminal-header');

        const canvasH = canvas.getBoundingClientRect().height;
        const layoutStyle = getComputedStyle(layout);
        const padTop = parseFloat(layoutStyle.paddingTop);
        const padBottom = parseFloat(layoutStyle.paddingBottom);
        const gap = parseFloat(layoutStyle.gap) || parseFloat(layoutStyle.rowGap) || 0;
        const copyH = copy.getBoundingClientRect().height;
        const headerH = header.getBoundingClientRect().height;

        const isLinkedin = layout.classList.contains('linkedin');
        let termH;
        if (isLinkedin) {
            // Side-by-side: terminal fills full height minus padding
            termH = canvasH - padTop - padBottom;
        } else {
            // Stacked: terminal gets remaining height below copy
            termH = canvasH - padTop - padBottom - copyH - gap;
        }

        terminalEl.style.height = termH + 'px';
        terminal.style.maxHeight = (termH - headerH - 2) + 'px';
    })();

    function scrollTerminal() {
        terminal.scrollTop = terminal.scrollHeight;
    }

    const sequences = [
        // ── 1. Create a member ──
        {
            lines: [
                { text: '# Create a new member', cls: 'comment' },
                { parts: [
                    { text: '$ ', cls: 'prompt' },
                    { text: 'curl ', cls: '' },
                    { text: '-X ', cls: 'flag' },
                    { text: 'POST ', cls: 'method' },
                    { text: '/api/v1/members', cls: 'url' },
                    { text: ' \\', cls: '' },
                ], typed: true },
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '-H ', cls: 'flag' },
                    { text: '"Bearer sk_live_...9x4"', cls: 'flag-val' },
                    { text: ' \\', cls: '' },
                ]},
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '-d ', cls: 'flag' },
                    { text: '\'{"name": "Apex Events",', cls: 'string' },
                ]},
                { parts: [
                    { text: '       ', cls: '' },
                    { text: '"type": "organisation"}\'', cls: 'string' },
                ]},
            ],
            pause: 600,
        },
        {
            lines: [
                { text: '', cls: '' },
                { badge: '201 Created', time: '42ms' },
                { text: '{', cls: 'brace' },
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '"member"', cls: 'key' },
                    { text: ': {', cls: 'brace' },
                ]},
                { parts: [
                    { text: '    ', cls: '' },
                    { text: '"id"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '847', cls: 'number' },
                    { text: ',', cls: '' },
                ]},
                { parts: [
                    { text: '    ', cls: '' },
                    { text: '"name"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '"Apex Events"', cls: 'string' },
                    { text: ',', cls: '' },
                ]},
                { parts: [
                    { text: '    ', cls: '' },
                    { text: '"type"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '"organisation"', cls: 'string' },
                ]},
                { text: '  }', cls: 'brace' },
                { text: '}', cls: 'brace' },
            ],
            pause: 1800,
        },
        { separator: true, pause: 500 },

        // ── 2. Create an opportunity (quote) ──
        {
            lines: [
                { text: '# Create a quote', cls: 'comment' },
                { parts: [
                    { text: '$ ', cls: 'prompt' },
                    { text: 'curl ', cls: '' },
                    { text: '-X ', cls: 'flag' },
                    { text: 'POST ', cls: 'method' },
                    { text: '/api/v1/opportunities', cls: 'url' },
                    { text: ' \\', cls: '' },
                ], typed: true },
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '-d ', cls: 'flag' },
                    { text: '\'{"subject": "Summer Fest",', cls: 'string' },
                ]},
                { parts: [
                    { text: '       ', cls: '' },
                    { text: '"member_id": 847,', cls: 'string' },
                ]},
                { parts: [
                    { text: '       ', cls: '' },
                    { text: '"starts_at": "2026-07-12",', cls: 'string' },
                ]},
                { parts: [
                    { text: '       ', cls: '' },
                    { text: '"ends_at": "2026-07-14"}\'', cls: 'string' },
                ]},
            ],
            pause: 600,
        },
        {
            lines: [
                { text: '', cls: '' },
                { badge: '201 Created', time: '56ms' },
                { text: '{', cls: 'brace' },
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '"opportunity"', cls: 'key' },
                    { text: ': {', cls: 'brace' },
                ]},
                { parts: [
                    { text: '    ', cls: '' },
                    { text: '"id"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '2041', cls: 'number' },
                    { text: ',', cls: '' },
                ]},
                { parts: [
                    { text: '    ', cls: '' },
                    { text: '"subject"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '"Summer Fest"', cls: 'string' },
                    { text: ',', cls: '' },
                ]},
                { parts: [
                    { text: '    ', cls: '' },
                    { text: '"state"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '"quotation"', cls: 'string' },
                    { text: ',', cls: '' },
                ]},
                { parts: [
                    { text: '    ', cls: '' },
                    { text: '"total"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '"0.00"', cls: 'string' },
                ]},
                { text: '  }', cls: 'brace' },
                { text: '}', cls: 'brace' },
            ],
            pause: 1800,
        },
        { separator: true, pause: 500 },

        // ── 3. Check availability ──
        {
            lines: [
                { text: '# Check availability', cls: 'comment' },
                { parts: [
                    { text: '$ ', cls: 'prompt' },
                    { text: 'curl ', cls: '' },
                    { text: 'GET ', cls: 'method' },
                    { text: '/api/v1/availability', cls: 'url' },
                    { text: ' \\', cls: '' },
                ], typed: true },
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '?product_id', cls: 'flag' },
                    { text: '=', cls: '' },
                    { text: '12', cls: 'flag-val' },
                    { text: '&starts_at', cls: 'flag' },
                    { text: '=', cls: '' },
                    { text: '2026-07-12', cls: 'flag-val' },
                ]},
            ],
            pause: 600,
        },
        {
            lines: [
                { text: '', cls: '' },
                { badge: '200 OK', time: '18ms' },
                { text: '{', cls: 'brace' },
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '"available"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '24', cls: 'number' },
                    { text: ',', cls: '' },
                ]},
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '"total_stock"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '30', cls: 'number' },
                    { text: ',', cls: '' },
                ]},
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '"reserved"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '6', cls: 'number' },
                ]},
                { text: '}', cls: 'brace' },
            ],
            pause: 1800,
        },
        { separator: true, pause: 500 },

        // ── 4. Ransack filtering ──
        {
            lines: [
                { text: '# Filter with Ransack queries', cls: 'comment' },
                { parts: [
                    { text: '$ ', cls: 'prompt' },
                    { text: 'curl ', cls: '' },
                    { text: 'GET ', cls: 'method' },
                    { text: '/api/v1/opportunities', cls: 'url' },
                    { text: ' \\', cls: '' },
                ], typed: true },
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '?q[state_eq]', cls: 'flag' },
                    { text: '=', cls: '' },
                    { text: 'order', cls: 'flag-val' },
                    { text: ' \\', cls: '' },
                ]},
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '&q[total_gteq]', cls: 'flag' },
                    { text: '=', cls: '' },
                    { text: '500', cls: 'flag-val' },
                    { text: ' \\', cls: '' },
                ]},
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '&include', cls: 'flag' },
                    { text: '=', cls: '' },
                    { text: 'items,member', cls: 'flag-val' },
                ]},
            ],
            pause: 600,
        },
        {
            lines: [
                { text: '', cls: '' },
                { badge: '200 OK', time: '34ms' },
                { text: '{', cls: 'brace' },
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '"opportunities"', cls: 'key' },
                    { text: ': [', cls: 'brace' },
                    { text: ' ... ', cls: 'comment' },
                    { text: '],', cls: 'brace' },
                ]},
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '"meta"', cls: 'key' },
                    { text: ': { ', cls: 'brace' },
                    { text: '"total"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '47', cls: 'number' },
                    { text: ', ', cls: '' },
                    { text: '"page"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '1', cls: 'number' },
                    { text: ' }', cls: 'brace' },
                ]},
                { text: '}', cls: 'brace' },
            ],
            pause: 1800,
        },
        { separator: true, pause: 500 },

        // ── 5. Register a webhook ──
        {
            lines: [
                { text: '# Register a webhook', cls: 'comment' },
                { parts: [
                    { text: '$ ', cls: 'prompt' },
                    { text: 'curl ', cls: '' },
                    { text: '-X ', cls: 'flag' },
                    { text: 'POST ', cls: 'method' },
                    { text: '/api/v1/webhooks', cls: 'url' },
                    { text: ' \\', cls: '' },
                ], typed: true },
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '-d ', cls: 'flag' },
                    { text: '\'{"url": "https://hooks.co",', cls: 'string' },
                ]},
                { parts: [
                    { text: '       ', cls: '' },
                    { text: '"events": [', cls: 'string' },
                ]},
                { parts: [
                    { text: '         ', cls: '' },
                    { text: '"order.created",', cls: 'string' },
                ]},
                { parts: [
                    { text: '         ', cls: '' },
                    { text: '"invoice.issued"]}\'', cls: 'string' },
                ]},
            ],
            pause: 600,
        },
        {
            lines: [
                { text: '', cls: '' },
                { badge: '201 Created', time: '31ms' },
                { text: '{', cls: 'brace' },
                { parts: [
                    { text: '  ', cls: '' },
                    { text: '"webhook"', cls: 'key' },
                    { text: ': {', cls: 'brace' },
                ]},
                { parts: [
                    { text: '    ', cls: '' },
                    { text: '"id"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '19', cls: 'number' },
                    { text: ',', cls: '' },
                ]},
                { parts: [
                    { text: '    ', cls: '' },
                    { text: '"active"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: 'true', cls: 'number' },
                    { text: ',', cls: '' },
                ]},
                { parts: [
                    { text: '    ', cls: '' },
                    { text: '"events"', cls: 'key' },
                    { text: ': ', cls: '' },
                    { text: '2', cls: 'number' },
                ]},
                { text: '  }', cls: 'brace' },
                { text: '}', cls: 'brace' },
            ],
            pause: 0,
        },
    ];

    function sleep(ms) {
        return new Promise(r => setTimeout(r, ms));
    }

    function createSpan(text, cls) {
        const span = document.createElement('span');
        if (cls) span.className = cls;
        span.textContent = text;
        return span;
    }

    function createBadgeLine(status, time) {
        const div = document.createElement('div');
        div.className = 'line';

        const badge = document.createElement('span');
        badge.className = 'response-badge';
        const dot = document.createElement('span');
        dot.className = 'dot';
        badge.appendChild(dot);
        badge.appendChild(document.createTextNode(' ' + status));
        div.appendChild(badge);

        const ts = document.createElement('span');
        ts.className = 'comment';
        ts.textContent = '  ' + time;
        div.appendChild(ts);

        return div;
    }

    async function addLine(lineData) {
        if (lineData.badge) {
            const div = createBadgeLine(lineData.badge, lineData.time);
            terminal.appendChild(div);
            scrollTerminal();
            await sleep(30);
            div.classList.add('visible');
            return;
        }

        const div = document.createElement('div');
        div.className = 'line';

        if (lineData.text !== undefined) {
            div.appendChild(createSpan(lineData.text, lineData.cls));
            terminal.appendChild(div);
            scrollTerminal();
            await sleep(30);
            div.classList.add('visible');
            return;
        }

        if (lineData.parts) {
            terminal.appendChild(div);
            scrollTerminal();
            await sleep(30);
            div.classList.add('visible');

            if (lineData.typed) {
                for (const part of lineData.parts) {
                    const span = document.createElement('span');
                    if (part.cls) span.className = part.cls;
                    div.appendChild(span);

                    for (let i = 0; i < part.text.length; i++) {
                        span.textContent += part.text[i];
                        const old = div.querySelector('.cursor');
                        if (old) old.remove();
                        const c = document.createElement('span');
                        c.className = 'cursor';
                        div.appendChild(c);
                        scrollTerminal();
                        await sleep(28 + Math.random() * 32);
                    }
                }
                const c = div.querySelector('.cursor');
                if (c) c.remove();
            } else {
                for (const part of lineData.parts) {
                    div.appendChild(createSpan(part.text, part.cls));
                }
            }
        }
    }

    async function addSeparator() {
        const sep = document.createElement('div');
        sep.className = 'sep';
        terminal.appendChild(sep);
        scrollTerminal();
        await sleep(50);
        sep.classList.add('visible');
    }

    async function runSequence() {
        for (const block of sequences) {
            if (block.separator) {
                await addSeparator();
                await sleep(block.pause || 200);
                continue;
            }
            for (const line of block.lines) {
                await addLine(line);
                if (!line.typed) await sleep(80);
            }
            if (block.pause) await sleep(block.pause);
        }

        const fin = document.createElement('div');
        fin.className = 'line visible';
        fin.appendChild(createSpan('$ ', 'prompt'));
        const c = document.createElement('span');
        c.className = 'cursor';
        fin.appendChild(c);
        terminal.appendChild(fin);
        scrollTerminal();
    }

    setTimeout(runSequence, 800);
</script>
@endsection
