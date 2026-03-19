@php
    $format = request()->query('format', 'linkedin');
    $isSquare = $format === 'instagram';
@endphp

@extends('social.layout', ['title' => 'People & Places'])

@section('styles')
<style>
    .promo-layout { position: relative; z-index: 1; height: 100%; }

    .promo-layout.linkedin { display: grid; grid-template-columns: 380px 1fr; padding: 52px 60px; gap: 52px; }
    .promo-layout.linkedin .copy { display: flex; flex-direction: column; justify-content: center; gap: 24px; }
    .promo-layout.linkedin .viz-area { display: flex; align-items: center; justify-content: center; }
    .promo-layout.linkedin .headline { font-size: 54px; }
    .promo-layout.linkedin .subline { font-size: 14px; max-width: 380px; }
    .promo-layout.linkedin .feature { font-size: 14px; }
    .promo-layout.linkedin .cta-url { font-size: 12px; }
    .promo-layout.linkedin .node { padding: 14px 18px; }
    .promo-layout.linkedin .node-name { font-size: 15px; }
    .promo-layout.linkedin .node-type { font-size: 10px; }
    .promo-layout.linkedin .node-detail { font-size: 10px; }
    .promo-layout.linkedin .rel-label { font-size: 10px; }

    .promo-layout.instagram { display: flex; flex-direction: column; padding: 56px 56px 48px; gap: 32px; }
    .promo-layout.instagram .copy { display: flex; flex-direction: column; gap: 18px; flex-shrink: 0; }
    .promo-layout.instagram .viz-area { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; }
    .promo-layout.instagram .headline { font-size: 64px; }
    .promo-layout.instagram .subline { display: none; }
    .promo-layout.instagram .features { flex-direction: row; flex-wrap: wrap; gap: 6px 24px; }
    .promo-layout.instagram .feature { font-size: 16px; }
    .promo-layout.instagram .cta-url { font-size: 14px; }
    .promo-layout.instagram .node { padding: 18px 22px; }
    .promo-layout.instagram .node-name { font-size: 17px; }
    .promo-layout.instagram .node-type { font-size: 11px; }
    .promo-layout.instagram .node-detail { font-size: 11px; }
    .promo-layout.instagram .rel-label { font-size: 11px; }

    .headline { font-family: var(--font-display); font-weight: 700; line-height: 1.08; letter-spacing: -0.01em; text-transform: uppercase; color: var(--white); }
    .headline .hl-accent { color: var(--syn-blue); }
    .subline { font-family: var(--font-mono); line-height: 1.8; color: var(--grey-light); }
    .features { display: flex; flex-direction: column; gap: 8px; }
    .feature { display: flex; align-items: center; gap: 10px; color: var(--grey-light); font-family: var(--font-mono); }
    .feature-dot { width: 6px; height: 6px; background: var(--green); flex-shrink: 0; }
    .cta-url { font-family: var(--font-mono); text-transform: uppercase; letter-spacing: 0.06em; color: var(--grey); }

    /* ── Relationship Tree ── */
    .tree-canvas {
        position: relative;
    }

    .tree-canvas svg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        overflow: visible;
    }

    .tree-line {
        stroke: rgba(148, 163, 184, 0.18);
        stroke-width: 1.5;
        stroke-dasharray: 400;
        stroke-dashoffset: 400;
        fill: none;
    }

    .tree-line.visible {
        stroke-dashoffset: 0;
        transition: stroke-dashoffset 0.8s ease-out;
    }

    .tree-line.accent {
        stroke: rgba(5, 150, 101, 0.35);
    }

    .node {
        position: absolute;
        background: var(--navy-mid);
        border: 1px solid rgba(148, 163, 184, 0.12);
        opacity: 0;
        transform: scale(0.85);
        transition: opacity 0.4s, transform 0.4s;
        white-space: nowrap;
    }

    .node.visible {
        opacity: 1;
        transform: scale(1);
    }

    .node-type {
        font-family: var(--font-mono);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 3px;
    }

    .node-type.org { color: var(--syn-blue); }
    .node-type.contact { color: var(--syn-green); }
    .node-type.venue { color: var(--syn-amber); }
    .node-type.user { color: var(--syn-purple); }

    .node-name {
        font-family: var(--font-display);
        font-weight: 600;
        color: var(--white);
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .node-detail {
        font-family: var(--font-mono);
        color: var(--grey);
        margin-top: 3px;
    }

    .node-badge {
        position: absolute;
        top: -1px;
        right: -1px;
        width: 8px;
        height: 8px;
    }

    .node-badge.org { background: var(--syn-blue); }
    .node-badge.contact { background: var(--syn-green); }
    .node-badge.venue { background: var(--syn-amber); }
    .node-badge.user { background: var(--syn-purple); }

    .rel-label {
        position: absolute;
        font-family: var(--font-mono);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--grey);
        background: var(--navy);
        padding: 2px 8px;
        opacity: 0;
        transition: opacity 0.4s;
        white-space: nowrap;
    }

    .rel-label.visible { opacity: 1; }
</style>
@endsection

@section('content')
<div class="promo-layout {{ $format }}">
    <div class="copy">
        <span class="logo-mark {{ $isSquare ? 'size-xl' : 'size-lg' }} color-green" style="color: var(--white);">Signals</span>

        <h1 class="headline">
            People &<br>places,<br><span class="hl-accent">unified.</span>
        </h1>

        <p class="subline">
            One universal entity for contacts, organisations, venues, and users. Custom fields, relationships, and full contact details.
        </p>

        <div class="features">
            <div class="feature"><span class="feature-dot"></span> Contacts, orgs, venues — one model</div>
            <div class="feature"><span class="feature-dot"></span> Emails, phones, addresses, links</div>
            <div class="feature"><span class="feature-dot"></span> Custom fields with EAV storage</div>
            <div class="feature"><span class="feature-dot"></span> Relationships between members</div>
        </div>

        <span class="cta-url">docs.signals.rent</span>
    </div>

    <div class="viz-area">
        <div class="tree-canvas" id="tree"></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const tree = document.getElementById('tree');
    const isSquare = @json($isSquare);

    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    // Fixed pixel positions — different layout per format
    // LinkedIn: landscape, wider canvas ~660px wide x 520px tall
    // Instagram: square, canvas ~960px wide x 600px tall
    const layouts = {
        linkedin: {
            width: 640,
            height: 500,
            nodes: [
                { id: 'org',      type: 'org',     name: 'Festival Sound Ltd', detail: 'VAT: GB123456789', x: 220, y: 0 },
                { id: 'contact1', type: 'contact', name: 'James Hartley',      detail: 'Production Mgr',   x: 20,  y: 160 },
                { id: 'contact2', type: 'contact', name: 'Lisa Chen',          detail: 'Finance Director',  x: 400, y: 160 },
                { id: 'venue',    type: 'venue',   name: 'Alexandra Palace',   detail: 'London N22',        x: 20,  y: 380 },
                { id: 'user',     type: 'user',    name: 'Sarah Admin',        detail: 'Owner \u00b7 2FA',  x: 400, y: 380 },
            ],
        },
        instagram: {
            width: 880,
            height: 620,
            nodes: [
                { id: 'org',      type: 'org',     name: 'Festival Sound Ltd', detail: 'VAT: GB123456789', x: 310, y: 0 },
                { id: 'contact1', type: 'contact', name: 'James Hartley',      detail: 'Production Mgr',   x: 40,  y: 190 },
                { id: 'contact2', type: 'contact', name: 'Lisa Chen',          detail: 'Finance Director',  x: 540, y: 190 },
                { id: 'venue',    type: 'venue',   name: 'Alexandra Palace',   detail: 'London N22',        x: 40,  y: 430 },
                { id: 'user',     type: 'user',    name: 'Sarah Admin',        detail: 'Owner \u00b7 2FA',  x: 540, y: 430 },
            ],
        },
    };

    const layout = isSquare ? layouts.instagram : layouts.linkedin;
    const nodes = layout.nodes;

    const connections = [
        { from: 'org', to: 'contact1', label: 'employee', accent: true },
        { from: 'org', to: 'contact2', label: 'employee', accent: true },
        { from: 'contact1', to: 'venue', label: 'manages' },
        { from: 'contact2', to: 'user', label: 'admin' },
    ];

    // Size the canvas
    tree.style.width = layout.width + 'px';
    tree.style.height = layout.height + 'px';

    // Create SVG for lines
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    tree.appendChild(svg);

    // Create nodes
    const nodeEls = {};
    for (const n of nodes) {
        const el = document.createElement('div');
        el.className = 'node';
        el.style.left = n.x + 'px';
        el.style.top = n.y + 'px';

        const badge = document.createElement('div');
        badge.className = 'node-badge ' + n.type;
        el.appendChild(badge);

        const typeEl = document.createElement('div');
        typeEl.className = 'node-type ' + n.type;
        typeEl.textContent = n.type;
        el.appendChild(typeEl);

        const nameEl = document.createElement('div');
        nameEl.className = 'node-name';
        nameEl.textContent = n.name;
        el.appendChild(nameEl);

        const detailEl = document.createElement('div');
        detailEl.className = 'node-detail';
        detailEl.textContent = n.detail;
        el.appendChild(detailEl);

        tree.appendChild(el);
        nodeEls[n.id] = { el, data: n };
    }

    function getNodeCenter(id) {
        const el = nodeEls[id].el;
        const treeRect = tree.getBoundingClientRect();
        const nodeRect = el.getBoundingClientRect();
        return {
            x: nodeRect.left - treeRect.left + nodeRect.width / 2,
            y: nodeRect.top - treeRect.top + nodeRect.height / 2,
        };
    }

    function drawConnection(conn) {
        const from = getNodeCenter(conn.from);
        const to = getNodeCenter(conn.to);
        const midY = (from.y + to.y) / 2;

        const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        line.setAttribute('d', 'M' + from.x + ',' + from.y + ' C' + from.x + ',' + midY + ' ' + to.x + ',' + midY + ' ' + to.x + ',' + to.y);
        line.classList.add('tree-line');
        if (conn.accent) { line.classList.add('accent'); }
        svg.appendChild(line);

        const label = document.createElement('div');
        label.className = 'rel-label';
        label.textContent = conn.label;
        label.style.left = ((from.x + to.x) / 2 - 24) + 'px';
        label.style.top = (midY - 8) + 'px';
        tree.appendChild(label);

        return { line, label };
    }

    async function animate() {
        // Show org first
        await sleep(600);
        nodeEls['org'].el.classList.add('visible');
        await sleep(800);

        // Show contacts
        nodeEls['contact1'].el.classList.add('visible');
        await sleep(300);
        nodeEls['contact2'].el.classList.add('visible');
        await sleep(600);

        // Draw lines from org to contacts
        for (const conn of connections.slice(0, 2)) {
            await sleep(200);
            const { line, label } = drawConnection(conn);
            await sleep(50);
            line.classList.add('visible');
            await sleep(200);
            label.classList.add('visible');
        }

        await sleep(800);

        // Show venue + line
        nodeEls['venue'].el.classList.add('visible');
        await sleep(400);
        const c3 = drawConnection(connections[2]);
        await sleep(50);
        c3.line.classList.add('visible');
        await sleep(200);
        c3.label.classList.add('visible');

        await sleep(800);

        // Show user + line
        nodeEls['user'].el.classList.add('visible');
        await sleep(400);
        const c4 = drawConnection(connections[3]);
        await sleep(50);
        c4.line.classList.add('visible');
        await sleep(200);
        c4.label.classList.add('visible');
    }

    setTimeout(animate, 400);
</script>
@endsection
