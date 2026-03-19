@php
    $format = request()->query('format', 'linkedin');
    $isSquare = $format === 'instagram';
@endphp

@extends('social.layout', ['title' => 'Admin Panel'])

@section('styles')
<style>
    /* ── Layout ── */
    .promo-layout {
        position: relative;
        z-index: 1;
        height: 100%;
    }

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
        align-items: center;
    }

    .promo-layout.linkedin .headline { font-size: 54px; }
    .promo-layout.linkedin .subline { font-size: 14px; max-width: 380px; }
    .promo-layout.linkedin .feature { font-size: 14px; }
    .promo-layout.linkedin .cta-url { font-size: 12px; }
    .promo-layout.linkedin .terminal-body { font-size: 16px; padding: 22px 26px; }
    .promo-layout.linkedin .terminal { max-height: 480px; display: flex; flex-direction: column; }

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

    .promo-layout.instagram .headline { font-size: 64px; }
    .promo-layout.instagram .subline { display: none; }
    .promo-layout.instagram .features { flex-direction: row; flex-wrap: wrap; gap: 6px 24px; }
    .promo-layout.instagram .feature { font-size: 16px; }
    .promo-layout.instagram .cta-url { font-size: 14px; }
    .promo-layout.instagram .terminal { display: flex; flex-direction: column; }
    .promo-layout.instagram .terminal-body { flex: 1; min-height: 0; font-size: 18px; padding: 24px 28px; }
    .promo-layout.instagram .terminal-header { padding: 14px 20px; }
    .promo-layout.instagram .terminal-title { font-size: 11px; }

    /* ── Copy ── */
    .headline {
        font-family: var(--font-display);
        font-weight: 700;
        line-height: 1.08;
        letter-spacing: -0.01em;
        text-transform: uppercase;
        color: var(--white);
    }

    .headline .hl-accent { color: var(--syn-amber); }

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

    .terminal-body::-webkit-scrollbar { width: 0; }
    .terminal-body { scrollbar-width: none; }

    .line { opacity: 0; transform: translateY(3px); white-space: pre; }
    .line.visible { opacity: 1; transform: translateY(0); transition: opacity 0.25s, transform 0.25s; }

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
    .success { color: var(--syn-green); font-weight: 500; }
    .info { color: var(--syn-blue); }
    .label { color: var(--grey-light); }

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
            One command<br>to configure<br><span class="hl-accent">everything.</span>
        </h1>

        <p class="subline">
            Batteries-included admin panel. Company settings, modules, branding, email, security — all from one place.
        </p>

        <div class="features">
            <div class="feature"><span class="feature-dot"></span> Setup wizard with connectivity tests</div>
            <div class="feature"><span class="feature-dot"></span> Modular feature toggling</div>
            <div class="feature"><span class="feature-dot"></span> Custom fields, lists, and taxonomies</div>
            <div class="feature"><span class="feature-dot"></span> Full audit trail with action log</div>
        </div>

        <span class="cta-url">docs.signals.rent</span>
    </div>

    <div class="terminal-area">
        <div class="terminal">
            <div class="terminal-header">
                <div class="terminal-dot red"></div>
                <div class="terminal-dot yellow"></div>
                <div class="terminal-dot green"></div>
                <div class="terminal-title">signals-admin</div>
            </div>
            <div class="terminal-body" id="terminal"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const terminal = document.getElementById('terminal');
    function scrollTerminal() { terminal.scrollTop = terminal.scrollHeight; }

    const sequences = [
        {
            lines: [
                { text: '# Install Signals', cls: 'comment' },
                { parts: [
                    { text: '$ ', cls: 'prompt' },
                    { text: 'php artisan ', cls: '' },
                    { text: 'signals:install', cls: 'method' },
                ], typed: true },
            ],
            pause: 600,
        },
        {
            lines: [
                { text: '', cls: '' },
                { parts: [{ text: '  Testing database connection...', cls: 'label' }] },
                { parts: [{ text: '  ✓ PostgreSQL 16.4 connected', cls: 'success' }] },
                { parts: [{ text: '  Testing Redis connection...', cls: 'label' }] },
                { parts: [{ text: '  ✓ Redis 7.2 connected', cls: 'success' }] },
                { parts: [{ text: '  Testing S3 storage...', cls: 'label' }] },
                { parts: [{ text: '  ✓ S3 bucket accessible', cls: 'success' }] },
                { text: '', cls: '' },
                { parts: [{ text: '  Running migrations... 65 tables created', cls: 'success' }] },
                { parts: [{ text: '  Seeding permissions... 48 permissions', cls: 'success' }] },
                { parts: [{ text: '  Seeding roles... 5 default roles', cls: 'success' }] },
            ],
            pause: 1800,
        },
        { separator: true, pause: 500 },
        {
            lines: [
                { text: '# Configure company settings', cls: 'comment' },
                { parts: [
                    { text: '$ ', cls: 'prompt' },
                    { text: 'php artisan ', cls: '' },
                    { text: 'signals:setup', cls: 'method' },
                ], typed: true },
            ],
            pause: 600,
        },
        {
            lines: [
                { text: '', cls: '' },
                { parts: [
                    { text: '  Company name: ', cls: 'label' },
                    { text: 'Apex Hire Ltd', cls: 'string' },
                ]},
                { parts: [
                    { text: '  Country:      ', cls: 'label' },
                    { text: 'United Kingdom', cls: 'string' },
                ]},
                { parts: [
                    { text: '  Currency:     ', cls: 'label' },
                    { text: 'GBP (£)', cls: 'string' },
                ]},
                { parts: [
                    { text: '  Timezone:     ', cls: 'label' },
                    { text: 'Europe/London', cls: 'string' },
                ]},
                { text: '', cls: '' },
                { parts: [{ text: '  ✓ Settings saved', cls: 'success' }] },
            ],
            pause: 1800,
        },
        { separator: true, pause: 500 },
        {
            lines: [
                { text: '# Enable modules', cls: 'comment' },
                { parts: [
                    { text: '$ ', cls: 'prompt' },
                    { text: 'php artisan ', cls: '' },
                    { text: 'signals:modules ', cls: 'method' },
                    { text: '--enable', cls: 'flag' },
                ], typed: true },
            ],
            pause: 600,
        },
        {
            lines: [
                { text: '', cls: '' },
                { parts: [
                    { text: '  ✓ ', cls: 'success' },
                    { text: 'Members & CRM', cls: 'label' },
                ]},
                { parts: [
                    { text: '  ✓ ', cls: 'success' },
                    { text: 'Opportunities (Quotes & Orders)', cls: 'label' },
                ]},
                { parts: [
                    { text: '  ✓ ', cls: 'success' },
                    { text: 'Product Catalogue', cls: 'label' },
                ]},
                { parts: [
                    { text: '  ✓ ', cls: 'success' },
                    { text: 'Invoicing & Payments', cls: 'label' },
                ]},
                { parts: [
                    { text: '  ✓ ', cls: 'success' },
                    { text: 'Stock & Availability', cls: 'label' },
                ]},
                { parts: [
                    { text: '  ✓ ', cls: 'success' },
                    { text: 'Crew & Services', cls: 'label' },
                ]},
                { text: '', cls: '' },
                { parts: [{ text: '  6 modules enabled. Ready to go.', cls: 'info' }] },
            ],
            pause: 1800,
        },
        { separator: true, pause: 500 },
        {
            lines: [
                { text: '# Create admin user', cls: 'comment' },
                { parts: [
                    { text: '$ ', cls: 'prompt' },
                    { text: 'php artisan ', cls: '' },
                    { text: 'signals:make-admin', cls: 'method' },
                ], typed: true },
            ],
            pause: 600,
        },
        {
            lines: [
                { text: '', cls: '' },
                { parts: [
                    { text: '  Name:  ', cls: 'label' },
                    { text: 'Sarah Chen', cls: 'string' },
                ]},
                { parts: [
                    { text: '  Email: ', cls: 'label' },
                    { text: 'sarah@apexhire.co.uk', cls: 'string' },
                ]},
                { parts: [
                    { text: '  Role:  ', cls: 'label' },
                    { text: 'Owner', cls: 'string' },
                ]},
                { text: '', cls: '' },
                { parts: [{ text: '  ✓ Admin user created. Invitation sent.', cls: 'success' }] },
            ],
            pause: 0,
        },
    ];

    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    function createSpan(text, cls) {
        const span = document.createElement('span');
        if (cls) span.className = cls;
        span.textContent = text;
        return span;
    }

    async function addLine(lineData) {
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
            if (block.separator) { await addSeparator(); await sleep(block.pause || 200); continue; }
            for (const line of block.lines) { await addLine(line); if (!line.typed) await sleep(80); }
            if (block.pause) await sleep(block.pause);
        }
        const fin = document.createElement('div');
        fin.className = 'line visible';
        fin.appendChild(createSpan('$ ', 'prompt'));
        fin.appendChild(Object.assign(document.createElement('span'), { className: 'cursor' }));
        terminal.appendChild(fin);
        scrollTerminal();
    }

    setTimeout(runSequence, 800);
</script>
@endsection
