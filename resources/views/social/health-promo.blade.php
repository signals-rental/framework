@php
    $format = request()->query('format', 'linkedin');
    $isSquare = $format === 'instagram';
@endphp

@extends('social.layout', ['title' => 'System Health'])

@section('styles')
<style>
    .promo-layout { position: relative; z-index: 1; height: 100%; }

    .promo-layout.linkedin { display: grid; grid-template-columns: 380px 1fr; padding: 52px 60px; gap: 52px; }
    .promo-layout.linkedin .copy { display: flex; flex-direction: column; justify-content: center; gap: 24px; }
    .promo-layout.linkedin .dash-area { display: flex; align-items: center; justify-content: center; }
    .promo-layout.linkedin .headline { font-size: 54px; }
    .promo-layout.linkedin .subline { font-size: 14px; max-width: 380px; }
    .promo-layout.linkedin .feature { font-size: 14px; }
    .promo-layout.linkedin .cta-url { font-size: 12px; }
    .promo-layout.linkedin .health-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; max-width: 480px; }
    .promo-layout.linkedin .health-card { padding: 18px 20px; }
    .promo-layout.linkedin .card-label { font-size: 11px; }
    .promo-layout.linkedin .card-value { font-size: 20px; }
    .promo-layout.linkedin .card-detail { font-size: 10px; }
    .promo-layout.linkedin .metrics-bar { gap: 12px; max-width: 480px; }
    .promo-layout.linkedin .metric-item { font-size: 11px; padding: 10px 14px; }
    .promo-layout.linkedin .metric-val { font-size: 18px; }
    .promo-layout.linkedin .all-clear { font-size: 13px; max-width: 480px; }

    .promo-layout.instagram { display: flex; flex-direction: column; padding: 56px 56px 48px; gap: 28px; }
    .promo-layout.instagram .copy { display: flex; flex-direction: column; gap: 18px; flex-shrink: 0; }
    .promo-layout.instagram .dash-area { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; }
    .promo-layout.instagram .headline { font-size: 64px; }
    .promo-layout.instagram .subline { display: none; }
    .promo-layout.instagram .features { flex-direction: row; flex-wrap: wrap; gap: 6px 24px; }
    .promo-layout.instagram .feature { font-size: 16px; }
    .promo-layout.instagram .cta-url { font-size: 14px; }
    .promo-layout.instagram .health-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
    .promo-layout.instagram .health-card { padding: 22px 24px; }
    .promo-layout.instagram .card-label { font-size: 13px; }
    .promo-layout.instagram .card-value { font-size: 24px; }
    .promo-layout.instagram .card-detail { font-size: 11px; }
    .promo-layout.instagram .metrics-bar { gap: 14px; }
    .promo-layout.instagram .metric-item { font-size: 13px; padding: 14px 18px; }
    .promo-layout.instagram .metric-val { font-size: 20px; }
    .promo-layout.instagram .all-clear { font-size: 15px; }

    .headline { font-family: var(--font-display); font-weight: 700; line-height: 1.08; letter-spacing: -0.01em; text-transform: uppercase; color: var(--white); }
    .headline .hl-accent { color: var(--syn-green); }
    .subline { font-family: var(--font-mono); line-height: 1.8; color: var(--grey-light); }
    .features { display: flex; flex-direction: column; gap: 8px; }
    .feature { display: flex; align-items: center; gap: 10px; color: var(--grey-light); font-family: var(--font-mono); }
    .feature-dot { width: 6px; height: 6px; background: var(--green); flex-shrink: 0; }
    .cta-url { font-family: var(--font-mono); text-transform: uppercase; letter-spacing: 0.06em; color: var(--grey); }

    /* ── Health Dashboard ── */
    .dash-container { width: 100%; display: flex; flex-direction: column; gap: 12px; }

    .health-grid { display: grid; width: 100%; }

    .health-card {
        background: var(--navy-mid);
        border: 1px solid rgba(148, 163, 184, 0.12);
        display: flex;
        flex-direction: column;
        gap: 8px;
        opacity: 0;
        transform: translateY(6px);
        transition: opacity 0.35s, transform 0.35s, border-color 0.5s;
    }

    .health-card.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .health-card.checking {
        border-color: rgba(251, 191, 36, 0.3);
    }

    .health-card.healthy {
        border-color: rgba(52, 211, 153, 0.25);
    }

    .card-top { display: flex; align-items: center; justify-content: space-between; }

    .card-label {
        font-family: var(--font-mono);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--grey);
    }

    .card-status {
        width: 8px;
        height: 8px;
        background: rgba(148, 163, 184, 0.2);
        transition: background 0.4s;
    }

    .card-status.checking {
        background: var(--syn-amber);
        animation: pulse 0.8s ease-in-out infinite;
    }

    .card-status.healthy { background: var(--syn-green); }

    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

    .card-value {
        font-family: var(--font-display);
        font-weight: 700;
        color: var(--grey);
        transition: color 0.4s;
    }

    .card-value.checking { color: var(--syn-amber); }
    .card-value.healthy { color: var(--white); }

    .card-detail {
        font-family: var(--font-mono);
        color: var(--grey);
        transition: color 0.3s;
    }

    .card-detail.healthy { color: var(--syn-green); }

    /* ── Metrics Bar ── */
    .metrics-bar { display: grid; grid-template-columns: repeat(4, 1fr); width: 100%; }

    .metric-item {
        background: var(--navy-mid);
        border: 1px solid rgba(148, 163, 184, 0.12);
        font-family: var(--font-mono);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--grey);
        display: flex;
        flex-direction: column;
        gap: 4px;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .metric-item.visible { opacity: 1; }

    .metric-val {
        font-family: var(--font-display);
        font-weight: 700;
        color: var(--syn-green);
    }

    /* ── All-Clear Banner ── */
    .all-clear {
        font-family: var(--font-display);
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        text-align: center;
        padding: 10px;
        background: rgba(52, 211, 153, 0.06);
        border: 1px solid rgba(52, 211, 153, 0.2);
        color: var(--syn-green);
        opacity: 0;
        transition: opacity 0.5s;
    }

    .all-clear.visible { opacity: 1; }
</style>
@endsection

@section('content')
<div class="promo-layout {{ $format }}">
    <div class="copy">
        <span class="logo-mark {{ $isSquare ? 'size-xl' : 'size-lg' }} color-green" style="color: var(--white);">Signals</span>

        <h1 class="headline">
            Know your<br>system is<br><span class="hl-accent">healthy.</span>
        </h1>

        <p class="subline">
            Built-in health checks for database, Redis, S3, queues, and scheduler. Nightwatch APM for production observability.
        </p>

        <div class="features">
            <div class="feature"><span class="feature-dot"></span> Database, Redis, S3 connectivity</div>
            <div class="feature"><span class="feature-dot"></span> Queue and scheduler monitoring</div>
            <div class="feature"><span class="feature-dot"></span> Nightwatch APM integration</div>
            <div class="feature"><span class="feature-dot"></span> Admin dashboard with live status</div>
        </div>

        <span class="cta-url">docs.signals.rent</span>
    </div>

    <div class="dash-area">
        <div class="dash-container" id="dashboard"></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const dashboard = document.getElementById('dashboard');
    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    const services = [
        { name: 'PostgreSQL', checkText: 'Connecting...', value: '16.4', detail: '3ms response' },
        { name: 'Redis', checkText: 'Connecting...', value: '7.2', detail: '1ms response' },
        { name: 'S3 Storage', checkText: 'Testing...', value: 'Connected', detail: '89ms response' },
        { name: 'Queue', checkText: 'Checking...', value: '0 pending', detail: '3 workers active' },
        { name: 'Scheduler', checkText: 'Checking...', value: 'Running', detail: 'Last run 42s ago' },
        { name: 'Nightwatch', checkText: 'Connecting...', value: 'Connected', detail: 'APM active' },
    ];

    const metrics = [
        { label: 'Uptime', value: '14d 6h' },
        { label: 'Avg Response', value: '42ms' },
        { label: 'Cache Hit', value: '97.3%' },
        { label: 'Jobs Today', value: '12,847' },
    ];

    // Build health grid
    const grid = document.createElement('div');
    grid.className = 'health-grid';
    dashboard.appendChild(grid);

    const cards = [];
    for (const svc of services) {
        const card = document.createElement('div');
        card.className = 'health-card';

        const top = document.createElement('div');
        top.className = 'card-top';
        const label = document.createElement('span');
        label.className = 'card-label';
        label.textContent = svc.name;
        const status = document.createElement('div');
        status.className = 'card-status';
        top.appendChild(label);
        top.appendChild(status);
        card.appendChild(top);

        const val = document.createElement('div');
        val.className = 'card-value';
        val.textContent = '\u2014';
        card.appendChild(val);

        const detail = document.createElement('div');
        detail.className = 'card-detail';
        detail.textContent = '\u2014';
        card.appendChild(detail);

        grid.appendChild(card);
        cards.push({ card, status, val, detail, data: svc });
    }

    // Build metrics bar
    const bar = document.createElement('div');
    bar.className = 'metrics-bar';
    dashboard.appendChild(bar);

    const metricEls = [];
    for (const m of metrics) {
        const item = document.createElement('div');
        item.className = 'metric-item';
        const mLabel = document.createElement('span');
        mLabel.textContent = m.label;
        const mVal = document.createElement('span');
        mVal.className = 'metric-val';
        mVal.textContent = m.value;
        item.appendChild(mLabel);
        item.appendChild(mVal);
        bar.appendChild(item);
        metricEls.push(item);
    }

    // All-clear banner
    const banner = document.createElement('div');
    banner.className = 'all-clear';
    banner.textContent = '\u2713  All systems operational  \u2014  6/6 checks passed';
    dashboard.appendChild(banner);

    async function animate() {
        // Show cards one by one
        await sleep(600);

        for (const c of cards) {
            c.card.classList.add('visible');
            await sleep(200);
        }

        await sleep(400);

        // Check each service
        for (const c of cards) {
            // Checking state
            c.card.classList.add('checking');
            c.status.classList.add('checking');
            c.val.textContent = c.data.checkText;
            c.val.classList.add('checking');
            await sleep(500 + Math.random() * 400);

            // Healthy state
            c.card.classList.remove('checking');
            c.card.classList.add('healthy');
            c.status.classList.remove('checking');
            c.status.classList.add('healthy');
            c.val.textContent = c.data.value;
            c.val.classList.remove('checking');
            c.val.classList.add('healthy');
            c.detail.textContent = c.data.detail;
            c.detail.classList.add('healthy');
            await sleep(300);
        }

        await sleep(600);

        // Show metrics
        for (const m of metricEls) {
            m.classList.add('visible');
            await sleep(200);
        }

        await sleep(500);

        // Show all-clear
        banner.classList.add('visible');
    }

    setTimeout(animate, 400);
</script>
@endsection
