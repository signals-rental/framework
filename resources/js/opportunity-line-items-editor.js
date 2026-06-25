/**
 * Production local-first opportunity line-item editor (Alpine data factory).
 * Ported from editor-local-first prototype with real $wire action mapping.
 */
import {
    pendingLocalIdsFromQueue,
    reconcileLocalTree,
} from './line-item-tree-reconcile';

const INDENT_PX = 22;
const DB_NAME = 'signals_opportunity_line_items';

let lfDexie = null;

function dexieDb() {
    if (lfDexie) {
        return lfDexie;
    }

    if (typeof Dexie === 'undefined') {
        return null;
    }

    lfDexie = new Dexie(DB_NAME);
    lfDexie.version(1).stores({
        rows: '[opp+id], opp',
        meta: 'key',
    });

    return lfDexie;
}

export default function createOpportunityLineItemsEditor(cfg) {
    return {
        oppId: cfg.oppId,
        editable: !!cfg.editable,
        catalogue: cfg.catalogue || [],
        currencySymbol: cfg.currencySymbol || '£',
        echoChannel: cfg.echoChannel || '',
        destinations: cfg.destinations || [],
        sectionOptions: cfg.sectionOptions || [],
        serverGrandTotal: cfg.serverGrandTotal || '0',
        dealPriceInput: cfg.dealTotalRaw || '',
        hasDealPrice: !!cfg.hasDealPrice,

        get seedPayload() {
            return (window.__lfSeed || {})[this.oppId] || { tree: [], revision: 0 };
        },

        get seedRows() {
            return this.seedPayload.tree || [];
        },

        rows: [],
        baseRevision: 0,
        conflicts: {},
        openMenu: null,
        confirmDeleteId: null,

        queue: [],
        syncState: 'idle',
        _flushTimer: null,
        _idleHandle: null,
        _flushing: false,
        _booted: false,
        _cacheChain: Promise.resolve(),

        dragId: null,
        _drag: null,
        _placeholderEl: null,

        picker: {
            open: false,
            target: null,
            isQuickAdd: false,
            results: [],
            highlight: 0,
            loading: false,
            localCount: 0,
            serverCount: 0,
            query: '',
            seq: 0,
            quantity: 1,
            mode: 'add',
            substituteItemId: null,
        },
        quickAddQty: 1,
        quickAddQtyHint: '',
        _serverTimer: null,
        _searchController: null,
        _broadcast: null,
        _echoChannel: null,

        _tempSeq: 0,

        async boot() {
            if (this._booted) {
                return;
            }

            if (this.rows.length) {
                this._booted = true;

                return;
            }

            this._booted = true;
            this.baseRevision = Number(this.seedPayload.revision) || 0;

            this.initProductSearch();
            this.initBroadcast();
            this.initEcho();

            const cached = await this.loadCache();

            if (cached && cached.length) {
                this.rows = this.normalize(cached);
                this.syncFlash('cached', 'loaded from cache');
            } else {
                this.rows = this.normalize(this.seedRows);
                this.applyDefaultCollapse(this.rows);
                await this.saveCache();
                this.setSync('synced');
            }
        },

        initProductSearch() {
            if (!window.signals?.productSearch?.createProductSearch) {
                return;
            }

            this._searchController = window.signals.productSearch.createProductSearch({
                catalogue: this.catalogue,
                serverSearch: (term) => this.$wire.searchProducts(term),
                limit: 12,
            });

            window.addEventListener('scroll', () => this.positionPicker(), true);
            window.addEventListener('resize', () => this.positionPicker());
        },

        initBroadcast() {
            if (typeof BroadcastChannel === 'undefined') {
                return;
            }

            this._broadcast = new BroadcastChannel(`signals-line-items-${this.oppId}`);
            this._broadcast.onmessage = (event) => {
                if (event.data === 'invalidate') {
                    this.pullFromServer();
                }
            };
        },

        initEcho() {
            if (!window.Echo || !this.echoChannel) {
                return;
            }

            this._echoChannel = window.Echo.private(this.echoChannel);
            this._echoChannel.listen('.availability.changed', () => this.pullFromServer());
        },

        notifyTabsInvalidate() {
            this._broadcast?.postMessage('invalidate');
        },

        async loadCache() {
            const db = dexieDb();

            if (!db) {
                return null;
            }

            try {
                return await db.rows.where('opp').equals(this.oppId).toArray();
            } catch (e) {
                console.warn('line-items cache read failed', e);

                return null;
            }
        },

        saveCache() {
            const db = dexieDb();

            if (!db) {
                return Promise.resolve();
            }

            const snapshot = this.rows.map((r) => ({ ...r, opp: this.oppId }));

            this._cacheChain = this._cacheChain.then(async () => {
                if (!snapshot.length) {
                    return;
                }

                try {
                    await db.transaction('rw', db.rows, async () => {
                        await db.rows.where('opp').equals(this.oppId).delete();
                        await db.rows.bulkPut(snapshot);
                    });
                } catch (e) {
                    console.warn('line-items cache write failed', e);
                }
            });

            return this._cacheChain;
        },

        normalize(rows) {
            const clone = rows.map((r) => ({ ...r, depth: Number(r.depth) }));

            return this.recomputeFlags(clone);
        },

        applyDefaultCollapse(rows) {
            for (let i = 0; i < rows.length; i++) {
                const r = rows[i];

                if (r.item_type === 'product') {
                    const next = rows[i + 1];
                    const hasAccessoryChild = !!(next && next.depth > r.depth && next.item_type === 'accessory');
                    r.is_collapsed = hasAccessoryChild;
                } else {
                    r.is_collapsed = false;
                }
            }

            return rows;
        },

        canPlace(draggedNode, parentNode, originalParentId = null) {
            const type = draggedNode.item_type;

            if (type === 'accessory') {
                if (!parentNode || parentNode.item_type !== 'product') {
                    return false;
                }

                if (originalParentId == null) {
                    return parentNode.item_type === 'product';
                }

                return parentNode.id === originalParentId;
            }

            if (parentNode === null) {
                return true;
            }

            return parentNode.item_type === 'group';
        },

        recomputeFlags(rows) {
            for (let i = 0; i < rows.length; i++) {
                const next = rows[i + 1];
                rows[i].has_children = !!(next && next.depth > rows[i].depth);
            }

            return rows;
        },

        get visibleRows() {
            const out = [];
            let hideBelowDepth = Infinity;

            for (const r of this.rows) {
                if (r.depth > hideBelowDepth) {
                    continue;
                }

                hideBelowDepth = Infinity;
                out.push(r);
                if (r.is_collapsed) {
                    hideBelowDepth = r.depth;
                }
            }

            return out;
        },

        get grandTotal() {
            return this.rows
                .filter((r) => r.item_type !== 'group')
                .reduce((s, r) => s + (Number(r.charge_total) || 0), 0);
        },

        get displayGrandTotal() {
            if (this.rows.length) {
                return this.money(this.grandTotal);
            }

            return this.formatMajor(parseMoney(this.serverGrandTotal || this.$root?.dataset?.serverGrandTotal || '0'));
        },

        get conflictCount() {
            return Object.keys(this.conflicts).length;
        },

        groupSubtotal(row) {
            const idx = this.rows.findIndex((r) => r.id === row.id);
            let sum = 0;

            for (let i = idx + 1; i < this.rows.length; i++) {
                if (this.rows[i].depth <= row.depth) {
                    break;
                }

                if (this.rows[i].item_type !== 'group') {
                    sum += Number(this.rows[i].charge_total) || 0;
                }
            }

            return sum;
        },

        groupSubtotalDisplay(row) {
            return this.money(this.groupSubtotal(row));
        },

        money(minor) {
            const amount = (Number(minor) / 100).toFixed(2);

            return this.currencySymbol + amount;
        },

        formatMajor(amount) {
            return this.currencySymbol + (Number(amount) || 0).toFixed(2);
        },

        fmtQty(q) {
            return Number(q).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        },

        statusClass(label) {
            const map = {
                Available: 's-badge-emerald',
                Reserved: 's-badge-blue',
                Shortage: 's-badge-red',
            };

            return map[label] || 's-badge-zinc';
        },

        get syncLabel() {
            return { idle: 'Local', cached: 'Cached', syncing: 'Syncing…', synced: 'Synced' }[this.syncState];
        },

        expandAll() {
            this.rows.forEach((r) => {
                r.is_collapsed = false;
            });
            this.saveCache();
        },

        collapseAll() {
            this.rows.forEach((r) => {
                if (r.has_children) {
                    r.is_collapsed = true;
                }
            });
            this.saveCache();
        },

        toggleCollapse(id) {
            const row = this.rows.find((r) => r.id === id);

            if (!row) {
                return;
            }

            row.is_collapsed = !row.is_collapsed;
            this.saveCache();
        },

        groupMoveOptions(row) {
            if (row.item_type === 'group') {
                return [];
            }

            const opts = this.rows
                .filter((r) => r.item_type === 'group' && r.id !== row.parent_group_id)
                .map((r) => ({ id: r.id, name: r.name }));

            if (row.parent_group_id) {
                opts.unshift({ id: null, name: 'auto group' });
            }

            return opts;
        },

        openEditLineModal(row) {
            this.$dispatch('open-modal', { id: 'edit-line', line: { ...row } });
        },

        async toggleOptionalRow(row) {
            await this.$wire.toggleOptional(row.id);
            row.is_optional = !row.is_optional;
            await this.pullFromServer();
        },

        async mergeDupes(row) {
            await this.$wire.mergeDuplicates(row.id);
            await this.pullFromServer();
            this.notifyTabsInvalidate();
        },

        async assignRowToGroup(row, groupId) {
            await this.$wire.assignToGroup(row.id, groupId);
            await this.pullFromServer();
            this.notifyTabsInvalidate();
        },

        async applyDealPrice() {
            const val = this.dealPriceInput.trim();

            if (!val) {
                return;
            }

            await this.$wire.setDealPrice(val);
            this.hasDealPrice = true;
        },

        async clearDealPrice() {
            await this.$wire.clearDealPrice();
            this.dealPriceInput = '';
            this.hasDealPrice = false;
        },

        beginEdit(id, field, ev) {
            if (!this.editable) {
                return;
            }

            const row = this.rows.find((r) => r.id === id);

            if (!row) {
                return;
            }

            if (row.item_type === 'group' && field !== 'name') {
                return;
            }

            const cell = ev ? ev.currentTarget : null;
            const isText = field === 'name';
            let current;

            if (field === 'unit_price') {
                current = row.unit_price_raw ?? (Number(row.unit_price || 0) / 100).toFixed(2);
            } else if (field === 'discount_percent') {
                current = row.discount_percent || '';
            } else {
                current = row[field];
            }

            const input = document.createElement('input');
            const widthClass = isText ? ' lf-edit-text' : (field === 'unit_price' ? ' lf-edit-price' : '');
            input.className = 'lf-edit-input' + widthClass;
            input.type = 'text';
            input.value = current ?? '';

            const host = cell || (ev ? ev.target : null) || this.$refs.tbody?.querySelector(`tr[data-id="${id}"] .lf-name`);

            if (!host) {
                return;
            }

            const prevHTML = host.style.display;
            host.style.display = 'none';
            host.parentNode.insertBefore(input, host.nextSibling);
            input.focus();
            input.select();

            const commit = (save) => {
                if (input._done) {
                    return;
                }

                input._done = true;
                const val = input.value.trim();
                input.remove();
                host.style.display = prevHTML;

                if (save) {
                    this.applyField(id, field, val);
                }
            };

            input.addEventListener('blur', () => commit(true));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    commit(true);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    commit(false);
                }
            });
        },

        applyField(id, field, value) {
            const row = this.rows.find((r) => r.id === id);

            if (!row) {
                return;
            }

            if (field === 'unit_price') {
                const major = parseMoney(value);
                row.unit_price = Math.round(major * 100);
                row.unit_price_raw = major.toFixed(2);
                row.unit_price_display = this.money(row.unit_price);
            } else if (field === 'quantity') {
                row.quantity = String(Math.round((parseFloat(value) || 0) * 100) / 100);
            } else if (field === 'days') {
                row.days = Math.max(0, parseInt(value || '0', 10));
            } else if (field === 'discount_percent') {
                row.discount_percent = value === '' ? null : String(Math.round((parseFloat(value) || 0) * 100) / 100);
            } else {
                row[field] = value;
            }

            if (['unit_price', 'quantity', 'days', 'discount_percent'].includes(field)) {
                const disc = row.discount_percent ? parseFloat(row.discount_percent) : 0;
                const gross = (parseFloat(row.quantity) || 0) * (row.days || 0) * (row.unit_price || 0);
                row.charge_total = Math.round(gross * (1 - disc / 100));
                row.charge_total_display = this.money(row.charge_total);
            }

            this.afterLocalMutation();
            this.enqueue({ kind: 'field', id, field, value });
        },

        addGroup(parentGroupId = null, name = 'New section') {
            const tmpId = this.tempId();
            let depth = 1;
            let insertAt = this.rows.length;

            if (parentGroupId != null) {
                const pIdx = this.rows.findIndex((r) => r.id === parentGroupId);

                if (pIdx !== -1) {
                    depth = this.rows[pIdx].depth + 1;
                    insertAt = pIdx + 1;

                    while (insertAt < this.rows.length && this.rows[insertAt].depth > this.rows[pIdx].depth) {
                        insertAt++;
                    }
                }
            }

            this.rows.splice(insertAt, 0, this.blankRow(tmpId, 'group', depth, name));
            this.afterLocalMutation();
            this.enqueue({ kind: 'addGroup', tmpId, parentGroupId, name });
        },

        deleteNode(id) {
            const idx = this.rows.findIndex((r) => r.id === id);

            if (idx === -1) {
                return;
            }

            const target = this.rows[idx];
            let end = idx + 1;

            while (end < this.rows.length && this.rows[end].depth > target.depth) {
                end++;
            }

            this.rows.splice(idx, end - idx);
            this.afterLocalMutation();
            this.enqueue({ kind: 'delete', id });
        },

        tempId() {
            return -1 * (Date.now() * 1000 + (++this._tempSeq));
        },

        blankRow(id, type, depth, name) {
            return {
                id,
                item_type: type,
                depth,
                name,
                quantity: '1',
                days: 1,
                unit_price: 0,
                unit_price_display: this.money(0),
                unit_price_raw: '0.00',
                discount_percent: null,
                charge_total: 0,
                charge_total_display: this.money(0),
                type_label: type === 'group' ? null : 'Rental',
                status_label: type === 'group' ? null : 'Reserved',
                is_collapsed: false,
                has_children: false,
                is_optional: false,
                has_duplicates: false,
            };
        },

        afterLocalMutation() {
            this.recomputeFlags(this.rows);
            this.saveCache();
        },

        onHandleDown(ev, id) {
            if (!this.editable) {
                return;
            }

            if (ev.button != null && ev.button !== 0) {
                return;
            }

            ev.preventDefault();

            const idx = this.rows.findIndex((r) => r.id === id);

            if (idx === -1) {
                return;
            }

            const node = this.rows[idx];
            let end = idx + 1;

            while (end < this.rows.length && this.rows[end].depth > node.depth) {
                end++;
            }

            const block = this.rows.slice(idx, end);
            const blockIds = new Set(block.map((r) => r.id));

            let originalParentId = null;

            for (let i = idx - 1; i >= 0; i--) {
                if (this.rows[i].depth === node.depth - 1) {
                    originalParentId = this.rows[i].id;
                    break;
                }

                if (this.rows[i].depth < node.depth - 1) {
                    break;
                }
            }

            this.dragId = id;
            this._drag = {
                block,
                blockIds,
                startX: ev.clientX,
                startDepth: node.depth,
                targetIndex: idx,
                targetDepth: node.depth,
                originalParentId,
                originalIndex: idx,
                valid: true,
                pointerId: ev.pointerId,
            };

            const ghost = this.$refs.ghost;
            ghost.textContent = (node.item_type === 'group' ? '▦ ' : '') + node.name
                + (block.length > 1 ? `  (+${block.length - 1})` : '');
            this.moveGhost(ev.clientX, ev.clientY);

            this._onMove = (e) => this.onDragMove(e);
            this._onUp = (e) => this.onDragUp(e);
            window.addEventListener('pointermove', this._onMove);
            window.addEventListener('pointerup', this._onUp, { once: true });
        },

        moveGhost(x, y) {
            const g = this.$refs.ghost;
            g.style.transform = `translate(${x + 14}px, ${y + 8}px)`;
        },

        onDragMove(ev) {
            if (!this._drag) {
                return;
            }

            this.moveGhost(ev.clientX, ev.clientY);

            const tbody = this.$refs.tbody;
            const trs = Array.from(tbody.querySelectorAll('tr.lf-row'))
                .filter((tr) => !this._drag.blockIds.has(Number(tr.dataset.id)));

            let beforeId = null;

            for (const tr of trs) {
                const rect = tr.getBoundingClientRect();

                if (ev.clientY < rect.top + rect.height / 2) {
                    beforeId = Number(tr.dataset.id);
                    break;
                }
            }

            const rest = this.rows.filter((r) => !this._drag.blockIds.has(r.id));
            let insertIndex = beforeId == null ? rest.length : rest.findIndex((r) => r.id === beforeId);

            if (insertIndex < 0) {
                insertIndex = rest.length;
            }

            const dx = ev.clientX - this._drag.startX;
            let depth = this._drag.startDepth + Math.round(dx / INDENT_PX);

            const above = rest[insertIndex - 1] || null;
            const below = rest[insertIndex] || null;
            const maxDepth = above ? above.depth + 1 : 1;
            const minDepth = below ? below.depth : 1;
            depth = Math.max(1, Math.min(depth, maxDepth));
            depth = Math.max(depth, Math.min(minDepth, maxDepth));

            const node = this._drag.block[0];
            depth = this.constrainDepth(node, rest, insertIndex, depth, minDepth, maxDepth);

            const parent = this.parentAt(rest, insertIndex, depth);
            const valid = depth !== null && this.canPlace(node, parent, this._drag.originalParentId);

            this._drag.targetIndex = insertIndex;
            this._drag.targetDepth = depth;
            this._drag.valid = valid;

            this.renderPlaceholder(beforeId, valid ? depth : Math.max(1, depth || minDepth), valid);
        },

        parentAt(rest, insertIndex, depth) {
            if (depth == null || depth <= 1) {
                return null;
            }

            for (let i = insertIndex - 1; i >= 0; i--) {
                if (rest[i].depth === depth - 1) {
                    return rest[i];
                }

                if (rest[i].depth < depth - 1) {
                    return null;
                }
            }

            return null;
        },

        constrainDepth(node, rest, insertIndex, desired, minDepth, maxDepth) {
            const lo = Math.max(1, minDepth);
            const hi = Math.max(lo, maxDepth);
            const ok = (d) => this.canPlace(node, this.parentAt(rest, insertIndex, d), this._drag.originalParentId);

            if (desired >= lo && desired <= hi && ok(desired)) {
                return desired;
            }

            for (let r = 1; r <= (hi - lo); r++) {
                const down = desired - r;
                const up = desired + r;

                if (down >= lo && ok(down)) {
                    return down;
                }

                if (up <= hi && ok(up)) {
                    return up;
                }
            }

            return null;
        },

        renderPlaceholder(beforeId, depth, valid = true) {
            this.removePlaceholder();
            const tbody = this.$refs.tbody;
            const ph = document.createElement('tr');
            ph.className = 'lf-placeholder' + (valid ? '' : ' lf-placeholder-invalid');
            const td = document.createElement('td');
            td.colSpan = 9;
            const bar = document.createElement('div');
            bar.className = 'lf-placeholder-bar';
            bar.style.marginLeft = ((Math.max(1, depth) - 1) * INDENT_PX + 24) + 'px';
            td.appendChild(bar);
            ph.appendChild(td);

            if (beforeId == null) {
                tbody.appendChild(ph);
            } else {
                const ref = tbody.querySelector(`tr[data-id="${beforeId}"]`);

                if (ref) {
                    tbody.insertBefore(ph, ref);
                } else {
                    tbody.appendChild(ph);
                }
            }

            this._placeholderEl = ph;
        },

        removePlaceholder() {
            if (this._placeholderEl) {
                this._placeholderEl.remove();
                this._placeholderEl = null;
            }
        },

        onDragUp() {
            window.removeEventListener('pointermove', this._onMove);
            this.removePlaceholder();

            const d = this._drag;
            this.dragId = null;
            this._drag = null;

            if (!d) {
                return;
            }

            if (!d.valid || d.targetDepth == null) {
                this.recomputeFlags(this.rows);

                return;
            }

            const rest = this.rows.filter((r) => !d.blockIds.has(r.id));
            const at = Math.max(0, Math.min(d.targetIndex, rest.length));
            const parent = this.parentAt(rest, at, d.targetDepth);

            if (!this.canPlace(d.block[0], parent, d.originalParentId)) {
                this.recomputeFlags(this.rows);

                return;
            }

            const delta = d.targetDepth - d.startDepth;
            const moved = d.block.map((r) => ({ ...r, depth: Math.max(1, r.depth + delta) }));
            rest.splice(at, 0, ...moved);

            for (let i = 0; i < rest.length; i++) {
                const prevDepth = i === 0 ? 0 : rest[i - 1].depth;
                rest[i].depth = Math.max(1, Math.min(rest[i].depth, prevDepth + 1));
            }

            this.rows = this.recomputeFlags(rest);
            this.saveCache();
            this.enqueue({ kind: 'persistTree' });
        },

        enqueue(mutation) {
            if (!this.editable) {
                return;
            }

            this.queue.push(mutation);
            this.scheduleFlush();
        },

        scheduleFlush() {
            this.setSync('syncing');
            clearTimeout(this._flushTimer);
            this._flushTimer = setTimeout(() => this.idleFlush(), 250);
        },

        idleFlush() {
            if ('requestIdleCallback' in window) {
                this._idleHandle = requestIdleCallback(() => this.flush(), { timeout: 1500 });
            } else {
                this.flush();
            }
        },

        async flush() {
            if (this._flushing) {
                return;
            }

            if (!this.queue.length) {
                this.setSync('synced');

                return;
            }

            this._flushing = true;
            this.setSync('syncing');

            const batch = this.queue.splice(0, this.queue.length);

            try {
                await this.applyBatchToServer(batch);
                this.setSync(this.queue.length ? 'syncing' : 'synced');
                Livewire.dispatch('opportunity-totals-updated');
                this.notifyTabsInvalidate();
            } catch (e) {
                console.warn('line-items flush failed, re-queueing', e);
                this.queue.unshift(...batch);
                this.setSync('syncing');
                setTimeout(() => this.scheduleFlush(), 1500);
            } finally {
                this._flushing = false;

                if (this.queue.length) {
                    this.scheduleFlush();
                }
            }
        },

        async applyBatchToServer(batch) {
            let structural = false;

            for (const m of batch) {
                if (m.kind === 'addGroup') {
                    const realId = await this.$wire.addGroup(m.parentGroupId ?? null, m.name ?? 'New section');
                    this.remapTempId(m.tmpId, realId);
                    structural = true;
                } else if (m.kind === 'addProduct') {
                    await this.$wire.addProduct(m.productId, m.quantity, m.destination);
                    structural = true;
                } else if (m.kind === 'delete') {
                    if (m.id > 0) {
                        await this.$wire.removeItem(m.id);
                    }

                    structural = true;
                } else if (m.kind === 'field') {
                    if (m.id > 0) {
                        const row = this.rows.find((r) => r.id === m.id);
                        let value = m.value;

                        if (row) {
                            if (m.field === 'unit_price') {
                                value = row.unit_price_raw ?? (Number(row.unit_price || 0) / 100).toFixed(2);
                            } else if (m.field === 'discount_percent') {
                                value = row.discount_percent ?? '';
                            } else {
                                value = row[m.field];
                            }
                        }

                        await this.$wire.updateField(m.id, m.field, value);
                    }
                } else if (m.kind === 'persistTree') {
                    structural = true;
                }
            }

            if (structural) {
                const realRows = this.rows.filter((r) => r.id > 0);

                if (realRows.length) {
                    const result = await this.$wire.persistTree(
                        realRows.map((r) => ({ id: r.id, depth: r.depth })),
                        this.baseRevision,
                    );

                    if (result?.stale) {
                        await this.handleStalePull();

                        return;
                    }

                    if (result?.revision != null) {
                        this.baseRevision = result.revision;
                    }
                }

                await this.pullFromServer(false);
            }

            await this.saveCache();
        },

        remapTempId(tmpId, realId) {
            const row = this.rows.find((r) => r.id === tmpId);

            if (row) {
                row.id = realId;
            }

            for (const r of this.rows) {
                if (r.parent_group_id === tmpId) {
                    r.parent_group_id = realId;
                }
            }

            for (const m of this.queue) {
                if (m.id === tmpId) {
                    m.id = realId;
                }

                if (m.parentGroupId === tmpId) {
                    m.parentGroupId = realId;
                }
            }
        },

        async handleStalePull() {
            await this.pullFromServer(true);
        },

        async pullFromServer(markConflicts = true) {
            const pending = pendingLocalIdsFromQueue(this.queue);
            const payload = await this.$wire.pullTree(this.baseRevision, this.rows, pending);

            if (!payload) {
                return;
            }

            if (payload.stale && markConflicts) {
                const reconciled = reconcileLocalTree(this.rows, payload.tree, pending);
                this.rows = this.normalize(reconciled.rows);
                this.conflicts = reconciled.conflicts;
            } else {
                this.rows = this.normalize(payload.tree);
                this.conflicts = payload.conflicts || {};
            }

            this.baseRevision = payload.revision ?? this.baseRevision;
            await this.saveCache();
        },

        onPickerInput(target, raw, isQuickAdd, opts = {}) {
            if (!this._searchController) {
                return;
            }

            const parsed = this._searchController.parseQuickAdd(raw);
            this.picker.target = target;
            this.picker.isQuickAdd = isQuickAdd;
            this.picker.quantity = parsed.quantity;
            this.picker.query = parsed.term;
            this.picker.mode = opts.mode || 'add';
            this.picker.substituteItemId = opts.itemId ?? null;

            if (isQuickAdd) {
                if (parsed.quantity > 1) {
                    this.quickAddQty = parsed.quantity;
                }

                this.quickAddQtyHint = parsed.quantity > 1 ? parsed.quantity + '×' : '';
            }

            if (parsed.term.trim().length < 2) {
                this.picker.results = [];
                this.picker.open = false;

                return;
            }

            const local = this._searchController.searchLocal(parsed.term);
            this.applyPickerResults(local);
            this.picker.loading = true;
            this.picker.open = true;
            this.positionPicker();

            const mySeq = ++this.picker.seq;
            clearTimeout(this._serverTimer);
            this._serverTimer = setTimeout(async () => {
                let server = [];

                try {
                    server = await this._searchController.searchServer(parsed.term);
                } catch {
                    server = [];
                }

                if (mySeq !== this.picker.seq) {
                    return;
                }

                this.applyPickerResults(this._searchController.merge(local, server));
                this.picker.loading = false;
            }, 250);
        },

        applyPickerResults(results) {
            this.picker.results = results;
            this.picker.highlight = 0;
            this.picker.localCount = results.filter((r) => r.source !== 'server').length;
            this.picker.serverCount = results.filter((r) => r.source === 'server').length;
        },

        onPickerKeydown(event, target, isQuickAdd) {
            if (!this.picker.open) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.picker.highlight = (this.picker.highlight + 1) % Math.max(this.picker.results.length, 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.picker.highlight = (this.picker.highlight - 1 + this.picker.results.length) % Math.max(this.picker.results.length, 1);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                const hit = this.picker.results[this.picker.highlight];

                if (hit) {
                    this.choosePickerHit(hit);
                }
            } else if (event.key === 'Escape' || event.key === 'Tab') {
                this.picker.open = false;
            }
        },

        choosePickerHit(hit) {
            if (this.picker.mode === 'substitute' && this.picker.substituteItemId) {
                this.$wire.substituteItem(this.picker.substituteItemId, hit.id);
                this.picker.open = false;
                this.pullFromServer();

                return;
            }

            const qty = this.picker.isQuickAdd
                ? (Number(this.quickAddQty) > 0 ? Number(this.quickAddQty) : 1)
                : (this.picker.quantity || 1);

            this.$wire.quickAdd(hit.id, qty).then(() => {
                this.pullFromServer();
                Livewire.dispatch('opportunity-totals-updated');
            });

            this.picker.open = false;
            this.picker.results = [];

            if (this.picker.isQuickAdd && this.picker.target) {
                this.picker.target.value = '';
                this.quickAddQty = 1;
                this.quickAddQtyHint = '';
            }
        },

        positionPicker() {
            if (!this.picker.open || !this.picker.target) {
                return;
            }

            const rect = this.picker.target.getBoundingClientRect();
            const dd = this.$refs.pickerDropdown;

            if (!dd) {
                return;
            }

            dd.style.left = (rect.left + window.scrollX) + 'px';
            dd.style.top = (rect.bottom + window.scrollY + 3) + 'px';
            dd.style.minWidth = Math.max(rect.width, 300) + 'px';
        },

        closePickerSoon() {
            setTimeout(() => {
                this.picker.open = false;
            }, 150);
        },

        setSync(state) {
            this.syncState = state;
        },

        syncFlash(state) {
            this.setSync(state);

            if (state !== 'syncing') {
                clearTimeout(this._flashT);
                this._flashT = setTimeout(() => {
                    if (!this.queue.length) {
                        this.setSync('synced');
                    }
                }, 1200);
            }
        },
    };
}

function parseMoney(str) {
    const cleaned = String(str ?? '').replace(/[^0-9.\-]/g, '');

    return parseFloat(cleaned) || 0;
}
