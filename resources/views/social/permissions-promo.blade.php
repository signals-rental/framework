@php
    $format = request()->query('format', 'linkedin');
    $isSquare = $format === 'instagram';
@endphp

@extends('social.layout', ['title' => 'Permissions'])

@section('styles')
<style>
    .promo-layout { position: relative; z-index: 1; height: 100%; }

    .promo-layout.linkedin { display: grid; grid-template-columns: 320px 1fr; padding: 40px 48px; gap: 40px; }
    .promo-layout.linkedin .copy { display: flex; flex-direction: column; justify-content: center; gap: 16px; overflow: hidden; }
    .promo-layout.linkedin .matrix-area { display: flex; align-items: center; justify-content: center; }
    .promo-layout.linkedin .headline { font-size: 44px; }
    .promo-layout.linkedin .subline { font-size: 12px; max-width: 320px; }
    .promo-layout.linkedin .feature { font-size: 12px; }
    .promo-layout.linkedin .cta-url { font-size: 11px; }
    .promo-layout.linkedin .matrix { font-size: 13px; }
    .promo-layout.linkedin .matrix th { font-size: 12px; padding: 12px 16px; }
    .promo-layout.linkedin .matrix td { padding: 10px 16px; }
    .promo-layout.linkedin .group-label { font-size: 11px; padding: 8px 16px; }

    .promo-layout.instagram { display: flex; flex-direction: column; padding: 56px 56px 48px; gap: 28px; }
    .promo-layout.instagram .copy { display: flex; flex-direction: column; gap: 18px; flex-shrink: 0; }
    .promo-layout.instagram .matrix-area { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; }
    .promo-layout.instagram .headline { font-size: 64px; }
    .promo-layout.instagram .subline { display: none; }
    .promo-layout.instagram .features { flex-direction: row; flex-wrap: wrap; gap: 6px 24px; }
    .promo-layout.instagram .feature { font-size: 16px; }
    .promo-layout.instagram .cta-url { font-size: 14px; }
    .promo-layout.instagram .matrix { font-size: 14px; }
    .promo-layout.instagram .matrix th { font-size: 13px; padding: 14px 18px; }
    .promo-layout.instagram .matrix td { padding: 12px 18px; }
    .promo-layout.instagram .group-label { font-size: 12px; padding: 10px 18px; }

    .headline { font-family: var(--font-display); font-weight: 700; line-height: 1.08; letter-spacing: -0.01em; text-transform: uppercase; color: var(--white); }
    .headline .hl-accent { color: var(--syn-purple); }
    .subline { font-family: var(--font-mono); line-height: 1.8; color: var(--grey-light); }
    .features { display: flex; flex-direction: column; gap: 8px; }
    .feature { display: flex; align-items: center; gap: 10px; color: var(--grey-light); font-family: var(--font-mono); }
    .feature-dot { width: 6px; height: 6px; background: var(--green); flex-shrink: 0; }
    .cta-url { font-family: var(--font-mono); text-transform: uppercase; letter-spacing: 0.06em; color: var(--grey); }

    /* ── Permission Matrix ── */
    .matrix-wrap {
        background: var(--navy-mid);
        border: 1px solid rgba(148, 163, 184, 0.12);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        overflow: hidden;
    }

    .matrix {
        width: 100%;
        border-collapse: collapse;
        font-family: var(--font-mono);
    }

    .matrix th {
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--grey);
        font-weight: 500;
        border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        text-align: center;
        white-space: nowrap;
    }

    .matrix th.role-header {
        opacity: 0;
        transform: translateY(-4px);
        transition: opacity 0.3s, transform 0.3s;
    }

    .matrix th.role-header.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .matrix th.role-header.owner { color: var(--syn-purple); }
    .matrix th.role-header.admin { color: var(--syn-blue); }
    .matrix th.role-header.manager { color: var(--syn-amber); }
    .matrix th.role-header.operator { color: var(--syn-green); }
    .matrix th.role-header.viewer { color: var(--grey-light); }

    .matrix th:first-child { text-align: left; }

    .group-label {
        font-family: var(--font-display);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--grey);
        background: rgba(15, 23, 42, 0.5);
        border-bottom: 1px solid rgba(148, 163, 184, 0.06);
        opacity: 0;
        transition: opacity 0.3s;
    }

    .group-label.visible { opacity: 1; }

    .matrix td {
        border-bottom: 1px solid rgba(148, 163, 184, 0.06);
        text-align: center;
        color: var(--grey-light);
    }

    .matrix td:first-child {
        text-align: left;
        color: var(--grey-light);
    }

    .matrix tr { opacity: 0; transform: translateX(-6px); transition: opacity 0.25s, transform 0.25s; }
    .matrix tr.visible { opacity: 1; transform: translateX(0); }

    .cell-check {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        opacity: 0;
        transform: scale(0.5);
        transition: opacity 0.2s, transform 0.2s ease-out;
    }

    .cell-check.visible {
        opacity: 1;
        transform: scale(1);
    }

    .cell-check.granted { color: var(--syn-green); }
    .cell-check.denied { color: rgba(100, 116, 139, 0.3); }
</style>
@endsection

@section('content')
<div class="promo-layout {{ $format }}">
    <div class="copy">
        <span class="logo-mark {{ $isSquare ? 'size-xl' : 'size-md' }} color-green" style="color: var(--white);">Signals</span>

        <h1 class="headline">
            Roles that<br>fit your<br><span class="hl-accent">business.</span>
        </h1>

        <p class="subline">
            Four-layer authorisation: UI areas, resource actions, cost visibility, and store scoping. Built on Spatie Permission.
        </p>

        <div class="features">
            <div class="feature"><span class="feature-dot"></span> 5 built-in roles, unlimited custom</div>
            <div class="feature"><span class="feature-dot"></span> 48 granular permissions</div>
            <div class="feature"><span class="feature-dot"></span> Cost visibility controls</div>
            <div class="feature"><span class="feature-dot"></span> Multi-store scoping</div>
        </div>

        <span class="cta-url">docs.signals.rent</span>
    </div>

    <div class="matrix-area">
        <div class="matrix-wrap">
            <table class="matrix" id="matrix"></table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const matrix = document.getElementById('matrix');
    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    const roles = [
        { key: 'owner', label: 'Owner' },
        { key: 'admin', label: 'Admin' },
        { key: 'manager', label: 'Manager' },
        { key: 'operator', label: 'Operator' },
        { key: 'viewer', label: 'Viewer' },
    ];

    const groups = [
        {
            label: 'Members',
            permissions: [
                { name: 'View contacts',    access: [1, 1, 1, 1, 1] },
                { name: 'Edit contacts',    access: [1, 1, 1, 1, 0] },
                { name: 'Delete contacts',  access: [1, 1, 0, 0, 0] },
            ]
        },
        {
            label: 'Opportunities',
            permissions: [
                { name: 'View quotes',      access: [1, 1, 1, 1, 1] },
                { name: 'Create quotes',    access: [1, 1, 1, 1, 0] },
                { name: 'Approve quotes',   access: [1, 1, 1, 0, 0] },
                { name: 'Issue invoices',   access: [1, 1, 0, 0, 0] },
            ]
        },
        {
            label: 'Stock',
            permissions: [
                { name: 'View inventory',   access: [1, 1, 1, 1, 1] },
                { name: 'Dispatch items',   access: [1, 1, 1, 1, 0] },
                { name: 'Adjust stock',     access: [1, 1, 1, 0, 0] },
            ]
        },
        {
            label: 'Admin',
            permissions: [
                { name: 'View costs',       access: [1, 1, 0, 0, 0] },
                { name: 'Manage users',     access: [1, 1, 0, 0, 0] },
                { name: 'System settings',  access: [1, 0, 0, 0, 0] },
            ]
        },
    ];

    // Build header
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    headerRow.className = 'visible';
    const emptyTh = document.createElement('th');
    headerRow.appendChild(emptyTh);
    const headerCells = [];
    for (const role of roles) {
        const th = document.createElement('th');
        th.className = 'role-header ' + role.key;
        th.textContent = role.label;
        headerRow.appendChild(th);
        headerCells.push(th);
    }
    thead.appendChild(headerRow);
    matrix.appendChild(thead);

    // Build body
    const tbody = document.createElement('tbody');
    const allRows = [];
    const allChecks = [];

    for (const group of groups) {
        const groupRow = document.createElement('tr');
        const groupTd = document.createElement('td');
        groupTd.className = 'group-label';
        groupTd.colSpan = 6;
        groupTd.textContent = group.label;
        groupRow.appendChild(groupTd);
        tbody.appendChild(groupRow);
        allRows.push({ row: groupRow, isGroup: true, td: groupTd });

        for (const perm of group.permissions) {
            const tr = document.createElement('tr');
            const nameTd = document.createElement('td');
            nameTd.textContent = perm.name;
            tr.appendChild(nameTd);

            const rowChecks = [];
            for (let i = 0; i < perm.access.length; i++) {
                const td = document.createElement('td');
                const check = document.createElement('span');
                check.className = 'cell-check ' + (perm.access[i] ? 'granted' : 'denied');
                check.textContent = perm.access[i] ? '\u2713' : '\u2014';
                td.appendChild(check);
                tr.appendChild(td);
                rowChecks.push(check);
            }

            tbody.appendChild(tr);
            allRows.push({ row: tr, isGroup: false });
            allChecks.push(rowChecks);
        }
    }
    matrix.appendChild(tbody);

    async function animate() {
        // Reveal role headers one by one
        await sleep(600);
        for (const th of headerCells) {
            th.classList.add('visible');
            await sleep(200);
        }
        await sleep(400);

        // Reveal rows sequentially
        let checkIdx = 0;
        for (const item of allRows) {
            if (item.isGroup) {
                item.row.classList.add('visible');
                item.td.classList.add('visible');
                await sleep(300);
            } else {
                item.row.classList.add('visible');
                await sleep(150);

                // Cascade checkmarks across columns
                const checks = allChecks[checkIdx];
                for (const check of checks) {
                    check.classList.add('visible');
                    await sleep(60);
                }
                checkIdx++;
                await sleep(100);
            }
        }
    }

    setTimeout(animate, 400);
</script>
@endsection
