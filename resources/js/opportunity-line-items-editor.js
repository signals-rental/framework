/**
 * Production local-first opportunity line-item editor (Alpine data factory).
 * Ported from editor-local-first prototype with real $wire action mapping.
 */
import {
    hasStructuralPending,
    pendingLocalIdsFromQueue,
    reconcileLocalTree,
} from './line-item-tree-reconcile';
import {
    INDENT_PX,
    canPlace as lineItemCanPlace,
    parentAt as lineItemParentAt,
    resolveDropTarget,
} from './line-item-drop-target';
import { serializeRowsForCache } from './line-item-cache';
import { resolveBootSource } from './line-item-boot-reconcile';
import { normalizeRevision } from './line-item-revision';

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
        fieldsEditable: cfg.fieldsEditable !== undefined ? !!cfg.fieldsEditable : !!cfg.editable,
        catalogue: cfg.catalogue || [],
        currencySymbol: cfg.currencySymbol || '£',
        echoChannel: cfg.echoChannel || '',
        destinations: cfg.destinations || [],
        sectionOptions: cfg.sectionOptions || [],
        serverChargeTotalMinor: Number(cfg.serverChargeTotalMinor) || 0,
        serverDealTotalMinor: cfg.serverDealTotalMinor != null ? Number(cfg.serverDealTotalMinor) : null,
        dealPriceInput: cfg.dealTotalRaw || '',
        hasDealPrice: !!cfg.hasDealPrice,
        quickAddSelection: null,

        get seedPayload() {
            return (window.__lfSeed || {})[this.oppId] || { tree: [], revision: 0, cacheToken: '' };
        },

        get seedRows() {
            return this.seedPayload.tree || [];
        },

        rows: [],
        baseRevision: '0',
        conflicts: {},
        openMenu: null,
        confirmDeleteId: null,
        menuPos: { top: 0, right: 0 },
        _menuAnchor: null,

        queue: [],
        syncState: 'idle',
        _flushTimer: null,
        _idleHandle: null,
        _flushing: false,
        _deferredPull: null,
        _cacheToken: '',
        _booted: false,
        _cacheChain: Promise.resolve(),

        dragId: null,
        _drag: null,
        _placeholderEl: null,
        nestDropGroupId: null,

        chargePopover: null,
        _chargePopoverTimer: null,

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

        cancelDragState() {
            this.dragId = null;
            this._drag = null;
            this.nestDropGroupId = null;
            this.removePlaceholder();

            if (this._onMove) {
                window.removeEventListener('pointermove', this._onMove);
                this._onMove = null;
            }

            if (this.$refs?.ghost) {
                this.$refs.ghost.textContent = '';
                this.$refs.ghost.style.transform = '';
            }
        },

        async boot() {
            if (this._booted) {
                return;
            }

            this.cancelDragState();

            if (this.rows.length) {
                this._booted = true;

                return;
            }

            this._booted = true;
            this.baseRevision = normalizeRevision(this.seedPayload.revision);
            this._cacheToken = String(this.seedPayload.cacheToken || '');

            this.initProductSearch();
            this.initBroadcast();
            this.initEcho();

            const cached = await this.loadCache();
            const meta = await this.readCacheMeta();
            const bootDecision = resolveBootSource({
                cached,
                meta,
                seedPayload: this.seedPayload,
            });

            if (bootDecision.source === 'cache') {
                console.log(`boot: using cache rev=${bootDecision.cacheRevision}`);
                this.rows = this.normalize(this.enrichRowsWithServerMetadata(cached, this.seedRows));
                this.applyDefaultCollapse(this.rows);
                this.syncFlash('cached', 'loaded from cache');
            } else if (bootDecision.reason === 'cache-stale' && bootDecision.cacheRevision != null) {
                console.log(
                    `boot: cache stale (rev ${bootDecision.cacheRevision} < server ${bootDecision.serverRevision}) → using server seed`,
                );
                await this.invalidateLocalCache();
                this.rows = this.normalize(this.seedRows);
                this.applyDefaultCollapse(this.rows);
                await this.saveCache();
                this.setSync('synced');
            } else {
                console.log(
                    `boot: cache stale (${bootDecision.reason}; cache rev ${bootDecision.cacheRevision ?? 'none'} vs server ${bootDecision.serverRevision}) → using server seed`,
                );
                await this.invalidateLocalCache();
                this.rows = this.normalize(this.seedRows);
                this.applyDefaultCollapse(this.rows);
                await this.saveCache();
                this.setSync('synced');
            }
        },

        cacheMetaKey() {
            return `opp-${this.oppId}-cache`;
        },

        cacheFingerprint() {
            return {
                revision: normalizeRevision(this.seedPayload.revision),
                cacheToken: String(this._cacheToken || this.seedPayload.cacheToken || ''),
            };
        },

        async readCacheMeta() {
            const db = dexieDb();

            if (!db) {
                return null;
            }

            try {
                return await db.meta.get(this.cacheMetaKey());
            } catch (e) {
                console.warn('line-items cache meta read failed', e);

                return null;
            }
        },

        async writeCacheMeta() {
            const db = dexieDb();

            if (!db) {
                return;
            }

            const fingerprint = this.cacheFingerprint();

            try {
                await db.meta.put({
                    key: this.cacheMetaKey(),
                    revision: fingerprint.revision,
                    cacheToken: fingerprint.cacheToken,
                });
            } catch (e) {
                console.warn('line-items cache meta write failed', e);
            }
        },

        async invalidateLocalCache() {
            const db = dexieDb();

            if (!db) {
                return;
            }

            try {
                await db.transaction('rw', db.rows, db.meta, async () => {
                    await db.rows.where('opp').equals(this.oppId).delete();
                    await db.meta.delete(this.cacheMetaKey());
                });
            } catch (e) {
                console.warn('line-items cache invalidate failed', e);
            }
        },

        enrichRowsWithServerMetadata(rows, serverRows) {
            if (!serverRows?.length) {
                return rows;
            }

            const serverById = Object.fromEntries(serverRows.map((row) => [Number(row.id), row]));
            const serverFields = [
                'product_url',
                'product_id',
                'has_shortage',
                'status_label',
                'availability_status',
                'availability_url',
                'charge_breakdown',
                'charge_total',
                'charge_total_display',
                'type_label',
            ];

            return rows.map((row) => {
                const server = serverById[Number(row.id)];

                if (!server) {
                    return row;
                }

                const enriched = { ...row };

                for (const field of serverFields) {
                    if (server[field] !== undefined && server[field] !== null) {
                        enriched[field] = server[field];
                    }
                }

                return enriched;
            });
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

            window.addEventListener('scroll', () => {
                this.positionPicker();
                this.positionRowMenu();
            }, true);
            window.addEventListener('resize', () => {
                this.positionPicker();
                this.positionRowMenu();
            });
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
            if (this._drag || this.dragId) {
                return Promise.resolve();
            }

            const db = dexieDb();

            if (!db) {
                return Promise.resolve();
            }

            const snapshot = serializeRowsForCache(this.rows, this.oppId);

            this._cacheChain = this._cacheChain.then(async () => {
                if (!snapshot.length) {
                    return;
                }

                try {
                    await db.transaction('rw', db.rows, db.meta, async () => {
                        await db.rows.where('opp').equals(this.oppId).delete();
                        await db.rows.bulkPut(snapshot);
                        await db.meta.put({
                            key: this.cacheMetaKey(),
                            revision: normalizeRevision(this.baseRevision),
                            cacheToken: String(this._cacheToken || this.seedPayload.cacheToken || ''),
                        });
                    });
                } catch (e) {
                    console.warn('line-items cache write failed', {
                        name: e?.name,
                        message: e?.message || String(e),
                        rowCount: snapshot.length,
                        oppId: this.oppId,
                    });
                }
            }).catch((e) => {
                console.warn('line-items cache chain failed', {
                    name: e?.name,
                    message: e?.message || String(e),
                });
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
                .filter((r) => r.item_type !== 'group' && r.item_type !== 'text')
                .reduce((s, r) => s + (Number(r.charge_total) || 0), 0);
        },

        get displayGrandTotal() {
            const minor = this.hasDealPrice && this.serverDealTotalMinor != null
                ? this.serverDealTotalMinor
                : this.serverChargeTotalMinor;

            return this.money(minor);
        },

        applyServerTotals(payload = {}) {
            if (payload.charge_total != null) {
                this.serverChargeTotalMinor = Number(payload.charge_total) || 0;
            }

            if (payload.deal_total !== undefined) {
                this.serverDealTotalMinor = payload.deal_total != null ? Number(payload.deal_total) : null;
            }

            if (payload.has_deal_price !== undefined) {
                this.hasDealPrice = !!payload.has_deal_price;
            }
        },

        async refreshBaseRevision() {
            try {
                this.baseRevision = normalizeRevision(await this.$wire.treeRevisionToken());
            } catch (e) {
                console.warn('line-items revision refresh failed', e);
            }
        },

        async syncTotalsFromServer() {
            try {
                const snap = await this.$wire.totalsSnapshot();

                if (snap) {
                    this.applyServerTotals(snap);
                    Livewire.dispatch('opportunity-totals-updated');
                }
            } catch (e) {
                console.warn('line-items totals sync failed', e);
            }
        },

        async onMutationDone(event) {
            await this.pullFromServer(false, { mutation: true });
            await this.syncTotalsFromServer();
            this.notifyTabsInvalidate();

            const detail = event?.detail ?? {};

            if (detail.modal) {
                this.$dispatch('close-modal', detail.modal);
            }
        },

        async onLifecycleChanged(event) {
            const detail = event?.detail ?? {};

            if (detail.editable !== undefined) {
                this.editable = !!detail.editable;
            }

            if (detail.fieldsEditable !== undefined) {
                this.fieldsEditable = !!detail.fieldsEditable;
            }

            if (detail.hasDealPrice !== undefined) {
                this.hasDealPrice = !!detail.hasDealPrice;
            }

            if (detail.dealTotalRaw !== undefined) {
                this.dealPriceInput = detail.dealTotalRaw ?? '';
            }

            this.applyServerTotals({
                charge_total: detail.chargeTotalMinor,
                deal_total: detail.dealTotalMinor,
                has_deal_price: detail.hasDealPrice,
            });

            if (detail.cacheToken !== undefined) {
                this._cacheToken = String(detail.cacheToken || '');
            }

            await this.invalidateLocalCache();
            await this.$wire.refreshEditorContext();
            await this.pullFromServer(false, { lifecycle: true });
            await this.syncTotalsFromServer();
            this.notifyTabsInvalidate();
        },

        _shouldDeferPull(opts = {}) {
            if (opts.afterFlush) {
                return hasStructuralPending(this.queue);
            }

            return this._flushing || hasStructuralPending(this.queue);
        },

        _queueDeferredPull(markConflicts) {
            this._deferredPull = {
                markConflicts: markConflicts || this._deferredPull?.markConflicts,
            };
        },

        async _drainDeferredPull() {
            if (!this._deferredPull || this._flushing || hasStructuralPending(this.queue)) {
                return;
            }

            const deferred = this._deferredPull;
            this._deferredPull = null;
            await this.pullFromServer(deferred.markConflicts, { afterFlush: true });
        },

        get openMenuRow() {
            if (this.openMenu === null) {
                return null;
            }

            return this.rows.find((r) => r.id === this.openMenu) ?? null;
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
        },

        async mergeDupes(row) {
            await this.$wire.mergeDuplicates(row.id);
        },

        async assignRowToGroup(row, groupId) {
            await this.$wire.assignToGroup(row.id, groupId);
        },

        openRowMenu(event, rowId) {
            if (this.openMenu === rowId) {
                this.closeRowMenu();

                return;
            }

            this._menuAnchor = event.currentTarget;
            this.openMenu = rowId;
            this.confirmDeleteId = null;
            this.positionRowMenu();
        },

        closeRowMenu() {
            this.openMenu = null;
            this.confirmDeleteId = null;
            this._menuAnchor = null;
        },

        positionRowMenu() {
            if (this.openMenu === null || !this._menuAnchor) {
                return;
            }

            const rect = this._menuAnchor.getBoundingClientRect();

            this.menuPos = {
                top: rect.bottom + 4,
                right: window.innerWidth - rect.right,
            };
        },

        showChargePopover(event, row) {
            if (!row?.charge_breakdown) {
                return;
            }

            clearTimeout(this._chargePopoverTimer);

            const rect = event.currentTarget.getBoundingClientRect();
            const width = 220;

            this.chargePopover = {
                row,
                top: rect.bottom + 6,
                left: Math.max(8, rect.right - width),
            };
        },

        keepChargePopover() {
            clearTimeout(this._chargePopoverTimer);
        },

        hideChargePopoverSoon() {
            clearTimeout(this._chargePopoverTimer);
            this._chargePopoverTimer = setTimeout(() => {
                this.chargePopover = null;
            }, 120);
        },

        async applyDealPrice() {
            const val = this.dealPriceInput.trim();

            if (!val) {
                return;
            }

            await this.$wire.setDealPrice(val);
            this.hasDealPrice = true;
            this.fieldsEditable = false;
        },

        async clearDealPrice() {
            await this.$wire.clearDealPrice();
            this.dealPriceInput = '';
            this.hasDealPrice = false;
            this.fieldsEditable = this.editable;
        },

        editChain: ['quantity', 'days', 'unit_price', 'discount_percent'],

        tabTarget(id, field, backwards) {
            const row = this.rows.find((r) => r.id === id);

            if (!row || row.item_type === 'group' || row.item_type === 'text' || !this.fieldsEditable) {
                return null;
            }

            const chain = this.editChain;
            let i = chain.indexOf(field);

            if (i === -1) {
                return null;
            }

            i += backwards ? -1 : 1;

            if (i >= 0 && i < chain.length) {
                return { id, field: chain[i] };
            }

            if (!backwards && field === 'discount_percent') {
                const visibleIds = new Set(this.visibleRows.map((r) => r.id));
                const idx = this.rows.findIndex((r) => r.id === id);

                for (let j = idx + 1; j < this.rows.length; j++) {
                    const next = this.rows[j];

                    if (next.item_type !== 'group' && next.item_type !== 'text' && visibleIds.has(next.id)) {
                        return { id: next.id, field: 'quantity' };
                    }
                }
            }

            return null;
        },

        focusField(id, field) {
            const tr = this.$refs.tbody?.querySelector(`tr[data-id="${id}"]`);

            if (!tr) {
                return;
            }

            const host = tr.querySelector(`.lf-cell[data-field="${field}"]`);

            if (!host) {
                return;
            }

            this.$nextTick(() => this.beginEdit(id, field, { currentTarget: host, target: host }));
        },

        beginEdit(id, field, ev) {
            if (!this.editable || !this.fieldsEditable) {
                return;
            }

            const row = this.rows.find((r) => r.id === id);

            if (!row) {
                return;
            }

            if ((row.item_type === 'group' || row.item_type === 'text') && field !== 'name') {
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
            const widthClass = isText
                ? ' lf-edit-text'
                : field === 'unit_price'
                    ? ' lf-edit-price'
                    : field === 'quantity'
                        ? ' lf-edit-qty'
                        : field === 'days'
                            ? ' lf-edit-days'
                            : field === 'discount_percent'
                                ? ' lf-edit-disc'
                                : '';
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
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    commit(true);
                    const target = this.tabTarget(id, field, e.shiftKey);

                    if (target) {
                        this.focusField(target.id, target.field);
                    }
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

            if (target.item_type === 'group') {
                const groupDepth = target.depth;
                this.rows.splice(idx, 1);

                for (let i = idx; i < this.rows.length; i++) {
                    if (this.rows[i].depth <= groupDepth) {
                        break;
                    }

                    this.rows[i].depth = Math.max(1, this.rows[i].depth - 1);
                }

                this.afterLocalMutation();
                this.enqueue({ kind: 'deleteSection', id });

                return;
            }

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
            this._onCancel = (e) => this.onDragUp(e);
            window.addEventListener('pointermove', this._onMove);
            window.addEventListener('pointerup', this._onUp, { once: true });
            window.addEventListener('pointercancel', this._onCancel, { once: true });
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

            const rest = this.rows.filter((r) => !this._drag.blockIds.has(r.id));
            const node = this._drag.block[0];

            let hoverRowIndex = null;

            for (const tr of trs) {
                const rect = tr.getBoundingClientRect();

                if (ev.clientY >= rect.top && ev.clientY <= rect.bottom) {
                    const hoverId = Number(tr.dataset.id);
                    hoverRowIndex = rest.findIndex((r) => r.id === hoverId);
                    break;
                }
            }

            let beforeId = null;

            for (const tr of trs) {
                const rect = tr.getBoundingClientRect();

                if (ev.clientY < rect.top + rect.height / 2) {
                    beforeId = Number(tr.dataset.id);
                    break;
                }
            }

            let insertIndex = beforeId == null ? rest.length : rest.findIndex((r) => r.id === beforeId);

            if (insertIndex < 0) {
                insertIndex = rest.length;
            }

            const resolved = resolveDropTarget({
                rest,
                draggedNode: node,
                insertIndex,
                hoverRowIndex: hoverRowIndex >= 0 ? hoverRowIndex : null,
                clientX: ev.clientX,
                startX: this._drag.startX,
                startDepth: this._drag.startDepth,
                originalParentId: this._drag.originalParentId,
                indentPx: INDENT_PX,
            });

            this._drag.targetIndex = resolved.insertIndex;
            this._drag.targetDepth = resolved.targetDepth;
            this._drag.valid = resolved.valid;
            this.nestDropGroupId = resolved.highlightGroupId;

            this.renderPlaceholder(
                resolved.beforeId,
                resolved.valid ? resolved.targetDepth : Math.max(1, resolved.targetDepth || 1),
                resolved.valid,
            );
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
            this._onMove = null;

            if (this._onCancel) {
                window.removeEventListener('pointercancel', this._onCancel);
                this._onCancel = null;
            }

            this.removePlaceholder();

            const d = this._drag;
            this.dragId = null;
            this._drag = null;
            this.nestDropGroupId = null;

            if (this.$refs?.ghost) {
                this.$refs.ghost.textContent = '';
                this.$refs.ghost.style.transform = '';
            }

            if (!d) {
                return;
            }

            if (!d.valid || d.targetDepth == null) {
                console.warn('line-items drag rejected: invalid drop target', {
                    itemId: d.block[0]?.id,
                    itemType: d.block[0]?.item_type,
                    targetIndex: d.targetIndex,
                    targetDepth: d.targetDepth,
                    valid: d.valid,
                });
                this.recomputeFlags(this.rows);

                return;
            }

            const rest = this.rows.filter((r) => !d.blockIds.has(r.id));
            const at = Math.max(0, Math.min(d.targetIndex, rest.length));
            const parent = lineItemParentAt(rest, at, d.targetDepth);

            if (!lineItemCanPlace(d.block[0], parent, d.originalParentId)) {
                console.warn('line-items drag rejected: canPlace failed', {
                    itemId: d.block[0]?.id,
                    itemType: d.block[0]?.item_type,
                    targetIndex: at,
                    targetDepth: d.targetDepth,
                    parentId: parent?.id ?? null,
                    parentType: parent?.item_type ?? null,
                    originalParentId: d.originalParentId,
                });
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
                await this.syncTotalsFromServer();
                this.notifyTabsInvalidate();
            } catch (e) {
                const message = e?.message || String(e);
                const body = e?.response?.data?.message;
                console.error('line-items flush failed, re-queueing', message, body ?? '', e);
                this.queue.unshift(...batch);
                this.setSync('syncing');
                setTimeout(() => this.scheduleFlush(), 1500);
            } finally {
                this._flushing = false;

                if (this.queue.length) {
                    this.scheduleFlush();
                } else {
                    await this._drainDeferredPull();
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
                } else if (m.kind === 'deleteSection') {
                    if (m.id > 0) {
                        await this.$wire.deleteSection(m.id);
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
                let structuralPersistSucceeded = false;

                if (realRows.length) {
                    await this.refreshBaseRevision();

                    const nodes = realRows.map((r) => ({ id: r.id, depth: r.depth }));
                    const baseRevisionBeforePersist = this.baseRevision;

                    console.warn('line-items persistTree call', {
                        baseRevision: baseRevisionBeforePersist,
                        nodeCount: nodes.length,
                        order: nodes.map((n) => n.id).join(','),
                    });

                    const result = await this.$wire.persistTree(nodes, baseRevisionBeforePersist);

                    console.warn('line-items persistTree result', {
                        stale: !!result?.stale,
                        revision: result?.revision ?? null,
                        revisionDrift: !!result?.revision_drift,
                        baseRevision: result?.base_revision ?? baseRevisionBeforePersist,
                        serverRevisionBefore: result?.server_revision_before ?? null,
                    });

                    if (result?.revision_drift) {
                        console.warn('line-items persistTree applied despite revision drift', {
                            baseRevision: result?.base_revision ?? baseRevisionBeforePersist,
                            serverRevisionBefore: result?.server_revision_before ?? null,
                            newRevision: result?.revision ?? null,
                        });
                    }

                    if (result?.stale) {
                        console.error('line-items persistTree returned stale unexpectedly', {
                            baseRevision: baseRevisionBeforePersist,
                            serverRevision: result?.revision ?? null,
                            nodeCount: nodes.length,
                        });
                        await this.handleStalePull('persistTree-unexpected-stale');

                        return;
                    }

                    structuralPersistSucceeded = true;

                    if (result?.revision != null) {
                        this.baseRevision = normalizeRevision(result.revision);
                    }
                }

                await this.pullFromServer(false, {
                    afterFlush: true,
                    afterStructuralPersist: structuralPersistSucceeded,
                    reason: structuralPersistSucceeded ? 'post-structural-persist-sync' : 'post-structural-flush',
                });
            } else {
                await this.refreshBaseRevision();
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

        _pullReason(opts = {}) {
            if (opts.reason) {
                return opts.reason;
            }

            if (opts.afterStructuralPersist) {
                return 'after-structural-persist';
            }

            if (opts.afterFlush) {
                return 'after-flush';
            }

            if (opts.mutation) {
                return 'mutation';
            }

            if (opts.lifecycle) {
                return 'lifecycle';
            }

            return 'manual';
        },

        async handleStalePull(reason = 'unknown') {
            console.error('line-items handleStalePull — server tree will replace local rows', {
                reason,
                baseRevision: this.baseRevision,
                localOrder: this.rows.map((r) => r.id).join(','),
            });
            await this.pullFromServer(true, { afterFlush: true, reason: `handleStalePull:${reason}` });
        },

        async pullFromServer(markConflicts = true, opts = {}) {
            if (this._shouldDeferPull(opts)) {
                console.warn('line-items pullFromServer deferred', {
                    reason: this._pullReason(opts),
                    markConflicts,
                    flushing: this._flushing,
                    structuralPending: hasStructuralPending(this.queue),
                });
                this._queueDeferredPull(markConflicts);

                return;
            }

            const reason = this._pullReason(opts);
            const trackOrder = !!(opts.afterFlush || opts.afterStructuralPersist);
            const orderBefore = trackOrder ? this.rows.map((r) => r.id).join(',') : null;
            const pending = pendingLocalIdsFromQueue(this.queue);
            const localRowsForPull = opts.afterStructuralPersist ? [] : this.rows;

            console.warn('line-items pullFromServer', {
                reason,
                markConflicts,
                afterStructuralPersist: !!opts.afterStructuralPersist,
                baseRevision: this.baseRevision,
                localRowCount: localRowsForPull.length,
                pendingFieldIds: pending,
            });

            const payload = await this.$wire.pullTree(this.baseRevision, localRowsForPull, pending);

            if (!payload) {
                console.warn('line-items pullFromServer: empty payload', { reason });

                return;
            }

            this.applyServerTotals(payload);

            if (payload.cache_token !== undefined) {
                this._cacheToken = String(payload.cache_token || '');
            }

            const serverTree = payload.tree || [];

            if (payload.stale && markConflicts) {
                const reconciled = reconcileLocalTree(this.rows, serverTree, pending);
                this.rows = this.normalize(this.enrichRowsWithServerMetadata(reconciled.rows, serverTree));
                this.conflicts = reconciled.conflicts;
            } else {
                this.rows = this.normalize(this.enrichRowsWithServerMetadata(serverTree, serverTree));
                this.conflicts = payload.conflicts || {};
            }

            if (orderBefore !== null) {
                const orderAfter = this.rows.map((r) => r.id).join(',');

                if (orderBefore !== orderAfter) {
                    console.warn('line-items pull reverted or replaced local tree order', {
                        reason,
                        before: orderBefore,
                        after: orderAfter,
                        stale: !!payload.stale,
                        markConflicts,
                        afterStructuralPersist: !!opts.afterStructuralPersist,
                        serverRevision: payload.revision ?? null,
                    });
                }
            }

            this.baseRevision = normalizeRevision(payload.revision) || this.baseRevision;
            this.applyDefaultCollapse(this.rows);
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

        onQuickAddInputKeydown(event, target) {
            if (this.picker.open) {
                this.onPickerKeydown(event, target, true);

                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                this.picker.open = false;
                this.$refs.quickAddQty?.focus();
            }
        },

        onQuickAddQtyKeydown(event) {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            this.commitQuickAdd();
        },

        commitQuickAdd() {
            const hit = this.quickAddSelection
                ?? this.picker.results[this.picker.highlight]
                ?? this.picker.results[0];

            if (!hit) {
                return;
            }

            const qty = Number(this.quickAddQty) > 0 ? Number(this.quickAddQty) : 1;

            this.$wire.quickAdd(hit.id, qty).then(async () => {
                await this.refreshBaseRevision();
                await this.pullFromServer(false, { mutation: true });
                await this.syncTotalsFromServer();
            });

            this.picker.open = false;
            this.picker.results = [];
            this.quickAddSelection = null;

            if (this.picker.target) {
                this.picker.target.value = '';
            }

            this.quickAddQty = 1;
            this.quickAddQtyHint = '';
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

                if (isQuickAdd) {
                    const hit = this.picker.results[this.picker.highlight];

                    if (hit) {
                        this.quickAddSelection = hit;
                    }

                    this.picker.open = false;
                    this.$refs.quickAddQty?.focus();

                    return;
                }

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

                return;
            }

            if (this.picker.isQuickAdd) {
                this.quickAddSelection = hit;
                this.picker.open = false;
                this.$refs.quickAddQty?.focus();

                return;
            }

            const qty = this.picker.quantity || 1;

            this.$wire.quickAdd(hit.id, qty).then(async () => {
                await this.refreshBaseRevision();
                await this.pullFromServer(false, { mutation: true });
                await this.syncTotalsFromServer();
            });

            this.picker.open = false;
            this.picker.results = [];
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
