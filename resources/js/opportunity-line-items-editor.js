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
import {
    mutationsToRequeue,
    orderFlushBatch,
    resolveServerItemId,
    rowsEligibleForPersistTree,
    shouldScheduleFlushRetry,
} from './line-item-mutation-flush';
import { normalizeRevision } from './line-item-revision';

const DB_NAME = 'signals_opportunity_line_items';

let lfDexie = null;

export function sameRowId(a, b) {
    if (a == null || b == null) {
        return false;
    }

    if (a === b) {
        return true;
    }

    const left = Number(a);
    const right = Number(b);

    return Number.isFinite(left) && Number.isFinite(right) && left === right;
}

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

function resolveEditorConfig(cfg) {
    const oppId = cfg?.oppId;
    const frozen =
        typeof window !== 'undefined' && oppId != null
            ? (window.__lfEditorConfig || {})[oppId] || {}
            : {};

    return { ...frozen, ...cfg };
}

export default function createOpportunityLineItemsEditor(cfg) {
    const resolved = resolveEditorConfig(cfg);

    return {
        oppId: resolved.oppId,
        csrfToken: resolved.csrfToken || '',
        editable: !!resolved.editable,
        fieldsEditable:
            resolved.fieldsEditable !== undefined ? !!resolved.fieldsEditable : !!resolved.editable,
        pricingFrozen: !!resolved.pricingFrozen,
        priceLocked: !!resolved.priceLocked,
        canManagePriceLock: !!resolved.canManagePriceLock,
        catalogue: resolved.catalogue || [],
        currencySymbol: resolved.currencySymbol || '£',
        echoChannel: resolved.echoChannel || '',
        destinations: resolved.destinations || [],
        sectionOptions: resolved.sectionOptions || [],
        serverChargeTotalMinor: Number(resolved.serverChargeTotalMinor) || 0,
        serverDealTotalMinor:
            resolved.serverDealTotalMinor != null ? Number(resolved.serverDealTotalMinor) : null,
        dealPriceInput: resolved.dealTotalRaw || '',
        hasDealPrice: !!resolved.hasDealPrice,
        quickAddSelection: null,
        quickAddQuery: '',

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
        menuRow: null,
        confirmDeleteId: null,
        menuPos: { top: 0, right: 0 },
        _menuAnchor: null,
        _menuOutsideSuppressedUntil: null,

        queue: [],
        syncState: 'idle',
        _flushTimer: null,
        _idleHandle: null,
        _flushing: false,
        _pendingFlush: false,
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
        quickAddQty: '',
        quickAddQtyHint: '',
        _serverTimer: null,
        _searchController: null,
        _broadcast: null,
        _echoChannel: null,
        _lifecycleListener: null,
        _onScroll: null,
        _onResize: null,

        _tempSeq: 0,
        _quickAddFocused: false,

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
            this.initLifecycleListener();

            if (this.rows.length) {
                this._booted = true;
                this.focusQuickAddOnce();

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
                this.replaceRows(this.enrichRowsWithServerMetadata(cached, this.seedRows));
                this.applyDefaultCollapse(this.rows);
                this.syncFlash('cached', 'loaded from cache');
            } else if (bootDecision.reason === 'cache-stale' && bootDecision.cacheRevision != null) {
                console.log(
                    `boot: cache stale (rev ${bootDecision.cacheRevision} < server ${bootDecision.serverRevision}) → using server seed`,
                );
                await this.invalidateLocalCache();
                this.replaceRows(this.seedRows);
                this.applyDefaultCollapse(this.rows);
                await this.saveCache();
                this.setSync('synced');
            } else {
                console.log(
                    `boot: cache stale (${bootDecision.reason}; cache rev ${bootDecision.cacheRevision ?? 'none'} vs server ${bootDecision.serverRevision}) → using server seed`,
                );
                await this.invalidateLocalCache();
                this.replaceRows(this.seedRows);
                this.applyDefaultCollapse(this.rows);
                await this.saveCache();
                this.setSync('synced');
            }

            this.focusQuickAddOnce();
        },

        focusQuickAddOnce() {
            if (this._quickAddFocused || !this.editable) {
                return;
            }

            this._quickAddFocused = true;

            this.$nextTick(() => {
                this.$refs.quickAddInput?.focus();
            });
        },

        idsMatch(a, b) {
            return sameRowId(a, b);
        },

        findRow(id) {
            return this.rows.find((row) => sameRowId(row.id, id)) ?? null;
        },

        findRowIndex(id) {
            return this.rows.findIndex((row) => sameRowId(row.id, id));
        },

        markRowJustAdded(id) {
            const row = this.findRow(id);

            if (!row) {
                return;
            }

            row._justAdded = true;
            setTimeout(() => {
                row._justAdded = false;
            }, 2000);
        },

        markRowsAddedSince(beforeIds) {
            for (const row of this.rows) {
                if (!beforeIds.has(Number(row.id))) {
                    this.markRowJustAdded(row.id);
                }
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

            // Store named refs so destroy() can remove these (capture-phase scroll +
            // resize) listeners — otherwise they accumulate on every wire:navigate.
            this._onScroll = () => {
                this.positionPicker();
                this.positionRowMenu();
            };
            this._onResize = () => {
                this.positionPicker();
                this.positionRowMenu();
            };

            window.addEventListener('scroll', this._onScroll, true);
            window.addEventListener('resize', this._onResize);
        },

        /**
         * Alpine calls destroy() when the component's root element is removed from
         * the DOM (e.g. a wire:navigate transition). Tear down every window/Echo/
         * BroadcastChannel/Livewire listener registered in boot() so they do not
         * leak across SPA navigations.
         */
        destroy() {
            if (this._onScroll) {
                window.removeEventListener('scroll', this._onScroll, true);
                this._onScroll = null;
            }

            if (this._onResize) {
                window.removeEventListener('resize', this._onResize);
                this._onResize = null;
            }

            if (this._broadcast) {
                this._broadcast.close();
                this._broadcast = null;
            }

            if (this._echoChannel && typeof this._echoChannel.stopListening === 'function') {
                this._echoChannel.stopListening('.availability.changed');
                this._echoChannel = null;
            }

            if (this._lifecycleListener && typeof this._lifecycleListener === 'function') {
                this._lifecycleListener();
                this._lifecycleListener = null;
            }
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

        initLifecycleListener() {
            if (this._lifecycleListener || typeof Livewire === 'undefined' || typeof Livewire.on !== 'function') {
                return;
            }

            this._lifecycleListener = Livewire.on('opportunity-lifecycle-changed', (detail) => {
                this.onLifecycleChanged({ detail });
            });
        },

        normalizeLifecycleDetail(event) {
            const raw = event?.detail ?? {};

            if (Array.isArray(raw) && raw.length === 1 && raw[0] && typeof raw[0] === 'object') {
                return raw[0];
            }

            return raw;
        },

        applyLifecycleFlags(detail) {
            if (detail.editable !== undefined) {
                this.editable = !!detail.editable;
            }

            if (detail.pricingFrozen !== undefined) {
                this.pricingFrozen = !!detail.pricingFrozen;
            }

            if (detail.priceLocked !== undefined) {
                this.priceLocked = !!detail.priceLocked;
            }

            if (detail.fieldsEditable !== undefined) {
                this.fieldsEditable = !!detail.fieldsEditable;
            } else {
                this.fieldsEditable = this.editable && !this.pricingFrozen;
            }

            if (detail.hasDealPrice !== undefined) {
                this.hasDealPrice = !!detail.hasDealPrice;

                if (detail.pricingFrozen === undefined && this.hasDealPrice) {
                    this.pricingFrozen = true;
                    this.fieldsEditable = false;
                }
            }

            if (detail.dealTotalRaw !== undefined) {
                this.dealPriceInput = detail.dealTotalRaw ?? '';
            }
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

        replaceRows(nextRows) {
            const normalized = this.normalize(nextRows);

            if (this.rows.length === 0) {
                this.rows.push(...normalized);
            } else {
                this.rows.splice(0, this.rows.length, ...normalized);
            }
        },

        normalize(rows) {
            const clone = rows.map((r) => ({
                ...r,
                id: Number(r.id),
                depth: Number(r.depth),
                parent_group_id: r.parent_group_id != null ? Number(r.parent_group_id) : r.parent_group_id,
            }));

            for (const row of clone) {
                if (row.unit_price != null) {
                    row.unit_price_display = this.money(row.unit_price);
                }

                if (row.item_type !== 'group' && row.charge_total != null) {
                    row.charge_total_display = this.money(row.charge_total);
                }
            }

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
                .filter((r) => r.item_type !== 'group')
                .reduce((s, r) => s + (Number(r.charge_total) || 0), 0);
        },

        get displayGrandTotal() {
            return this.money(this.grandTotal);
        },

        get displayDealPriceSubline() {
            if (!this.hasDealPrice || this.serverDealTotalMinor == null) {
                return null;
            }

            return `Deal price applied — ${this.money(this.serverDealTotalMinor)}`;
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

        syncOptimisticTotalsFromRows() {
            const chargeTotalMinor = this.grandTotal;
            this.serverChargeTotalMinor = chargeTotalMinor;

            if (typeof Livewire !== 'undefined') {
                Livewire.dispatch('opportunity-totals-updated', {
                    chargeTotalMinor,
                });
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
                    Livewire.dispatch('opportunity-totals-updated', {
                        chargeTotalMinor: Number(snap.charge_total) || 0,
                    });
                }
            } catch (e) {
                console.warn('line-items totals sync failed', e);
            }
        },

        async onMutationDone(event) {
            const beforeIds = new Set(this.rows.map((r) => Number(r.id)));

            await this.pullFromServer(false, { mutation: true });
            await this.syncTotalsFromServer();
            this.notifyTabsInvalidate();

            this.markRowsAddedSince(beforeIds);

            const detail = event?.detail ?? {};

            if (detail.modal) {
                this.$dispatch('close-modal', detail.modal);
            }
        },

        async onLifecycleChanged(event) {
            const detail = this.normalizeLifecycleDetail(event);

            this.applyLifecycleFlags(detail);

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

            // Pull does not re-read Alpine flags from the server; re-apply so lock/unlock is live.
            this.applyLifecycleFlags(detail);
        },

        _shouldDeferPull(opts = {}) {
            if (opts.force) {
                return false;
            }

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
            return this.menuRow ?? this.resolveMenuRow();
        },

        resolveMenuRow() {
            if (this.openMenu == null) {
                return null;
            }

            return this.rows.find((row) => sameRowId(row.id, this.openMenu)) ?? null;
        },

        refreshMenuRow() {
            if (this.openMenu == null) {
                this.menuRow = null;

                return;
            }

            this.menuRow = this.resolveMenuRow();
        },

        get conflictCount() {
            return Object.keys(this.conflicts).length;
        },

        groupSubtotal(row) {
            const idx = this.findRowIndex(row.id);
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
            const amount = (Number(minor) / 100).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            return this.currencySymbol + amount;
        },

        recomputeChargeBreakdown(row) {
            if (! row || row.item_type === 'group') {
                if (row) {
                    row.charge_breakdown = null;
                }

                return;
            }

            const unitPriceMinor = Number(row.unit_price) || 0;
            const quantity = parseFloat(row.quantity) || 0;
            const days = Math.max(1, Number(row.days) || 0);
            const rentalMinor = Math.round(quantity * unitPriceMinor * days);
            const surchargeMinor = 0;

            row.charge_breakdown = {
                days_line: `Days: ${this.money(unitPriceMinor)} × ${days}`,
                rental_charge_display: this.money(rentalMinor),
                surcharge_display: this.money(surchargeMinor),
            };
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
            const row = this.findRow(id);

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

        openRowMenu(event, rowOrId) {
            event?.stopPropagation?.();

            const row = typeof rowOrId === 'object' && rowOrId !== null
                ? rowOrId
                : this.rows.find((candidate) => sameRowId(candidate.id, rowOrId));

            if (! row) {
                return;
            }

            if (sameRowId(this.openMenu, row.id)) {
                this.closeRowMenu(true);

                return;
            }

            this._menuOutsideSuppressedUntil = Date.now() + 300;
            this._menuAnchor = event.currentTarget;
            this.openMenu = row.id;
            this.menuRow = row;
            this.confirmDeleteId = null;

            this.$nextTick(() => {
                this.positionRowMenu();
                requestAnimationFrame(() => this.positionRowMenu());
            });
        },

        onRowMenuOutsideClick(event) {
            if (this._menuOutsideSuppressedUntil && Date.now() < this._menuOutsideSuppressedUntil) {
                return;
            }

            if (this._menuAnchor && (event.target === this._menuAnchor || this._menuAnchor.contains(event.target))) {
                return;
            }

            this.closeRowMenu(true);
        },

        closeRowMenu(force = false) {
            if (! force && this._menuOutsideSuppressedUntil && Date.now() < this._menuOutsideSuppressedUntil) {
                return;
            }

            this.openMenu = null;
            this.menuRow = null;
            this.confirmDeleteId = null;
            this._menuAnchor = null;
            this._menuOutsideSuppressedUntil = null;
        },

        async handleRemoveMenuClick() {
            const row = this.openMenuRow;

            if (! row) {
                return;
            }

            const id = row.id;

            if (! sameRowId(this.confirmDeleteId, id)) {
                this.confirmDeleteId = id;
                this._menuOutsideSuppressedUntil = Date.now() + 1500;

                return;
            }

            this.closeRowMenu(true);
            this.deleteNode(id);
        },

        positionRowMenu() {
            if (this.openMenu === null || ! this._menuAnchor) {
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
            this.pricingFrozen = true;
            this.fieldsEditable = false;
            this.serverDealTotalMinor = Math.round(parseMoney(val) * 100);
            await this.syncTotalsFromServer();
        },

        async clearDealPrice() {
            await this.$wire.clearDealPrice();
            this.dealPriceInput = '';
            this.hasDealPrice = false;

            if (! this.priceLocked) {
                this.pricingFrozen = false;
                this.fieldsEditable = this.editable;
            }

            await this.syncTotalsFromServer();
        },

        editChain: ['quantity', 'days', 'unit_price', 'discount_percent'],

        tabTarget(id, field, backwards) {
            const row = this.findRow(id);

            if (!row || row.item_type === 'group' || !this.fieldsEditable) {
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
                const idx = this.findRowIndex(id);

                for (let j = idx + 1; j < this.rows.length; j++) {
                    const next = this.rows[j];

                    if (next.item_type !== 'group' && visibleIds.has(next.id)) {
                        return { id: next.id, field: 'quantity' };
                    }
                }
            }

            return null;
        },

        resolveEditHost(tr, field) {
            if (!tr) {
                return null;
            }

            if (field === 'name') {
                return tr.querySelector('.lf-name:not(.lf-product-link)') || tr.querySelector('.lf-name');
            }

            return tr.querySelector(`.lf-cell[data-field="${field}"]`);
        },

        tryFocusField(id, field) {
            const tr = this.$refs.tbody?.querySelector(`tr[data-id="${id}"]`);

            if (!tr) {
                return false;
            }

            const host = this.resolveEditHost(tr, field);

            if (!host) {
                return false;
            }

            this.beginEdit(id, field, { currentTarget: host, target: host });

            return true;
        },

        async focusFieldWhenReady(id, field, attempt = 0) {
            if (this.tryFocusField(id, field)) {
                return true;
            }

            if (attempt >= 15) {
                return false;
            }

            await new Promise((resolve) => requestAnimationFrame(resolve));

            return this.focusFieldWhenReady(id, field, attempt + 1);
        },

        focusField(id, field) {
            this.focusFieldWhenReady(id, field);
        },

        openLockPriceModal() {
            if (typeof Livewire !== 'undefined') {
                Livewire.dispatch('open-opportunity-lock-price-modal');
            }
        },

        toastError(message) {
            if (!message) {
                return;
            }

            if (typeof Livewire !== 'undefined') {
                Livewire.dispatch('toast', { type: 'error', message });
            }
        },

        flushErrorMessage(error) {
            const payload = error?.response?.data ?? error?.body ?? error ?? {};
            const errors = payload?.errors ?? {};
            const firstFieldError = Object.values(errors).flat()?.[0];

            return String(
                firstFieldError
                ?? payload?.message
                ?? error?.message
                ?? 'Could not save line-item changes.',
            );
        },

        isPricingGuardError(error) {
            const message = this.flushErrorMessage(error).toLowerCase();

            return message.includes('pricing is frozen')
                || message.includes('cannot be removed')
                || message.includes('cannot be edited while pricing is frozen');
        },

        beginEdit(id, field, ev) {
            if (!this.editable || !this.fieldsEditable) {
                return;
            }

            const row = this.findRow(id);

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
                    if (field === 'name' && (row.item_type === 'text' || row.item_type === 'group') && val === '') {
                        this.discardBlankInlineRow(id);

                        return;
                    }

                    this.applyField(id, field, field === 'name' ? val : input.value.trim());
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

        discardBlankInlineRow(id) {
            const row = this.findRow(id);

            if (!row) {
                return;
            }

            if (Number(row.id) > 0) {
                void this.deleteNode(id);

                return;
            }

            const idx = this.findRowIndex(id);

            if (idx !== -1) {
                this.rows.splice(idx, 1);
            }

            this.queue = this.queue.filter((m) => m.tmpId !== id && m.id !== id);
            this.afterLocalMutation();
        },

        applyField(id, field, value) {
            const row = this.findRow(id);

            if (! row) {
                return;
            }

            if (field === 'name' && (row.item_type === 'text' || row.item_type === 'group') && String(value).trim() === '') {
                this.discardBlankInlineRow(id);

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
                this.recomputeChargeBreakdown(row);
            }

            this.afterLocalMutation();
            this.enqueue({ kind: 'field', id, field, value });
        },

        addGroup(parentGroupId = null, name = '') {
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
            this.markRowJustAdded(tmpId);
            this.afterLocalMutation();
            this.enqueue({ kind: 'addGroup', tmpId, parentGroupId, name });
        },

        findDeleteBlock(id) {
            const idx = this.findRowIndex(id);

            if (idx === -1) {
                return null;
            }

            const target = this.rows[idx];
            let end = idx + 1;

            while (end < this.rows.length && this.rows[end].depth > target.depth) {
                end++;
            }

            return {
                idx,
                end,
                target,
            };
        },

        spliceLocalDeleteBlock(block) {
            const { idx, end } = block;
            this.rows.splice(idx, end - idx);
            this.afterLocalMutation();
            this.syncOptimisticTotalsFromRows();
        },

        deleteItemUrl(serverId) {
            // Group/section deletes always cascade their subtree server-side via
            // RemoveOpportunityItem (deepest-first), so there is no scope parameter —
            // the controller does not read one.
            return `/opportunities/${this.oppId}/items/${serverId}`;
        },

        sendKeepaliveDelete(serverId) {
            const token = this.csrfToken
                || document.querySelector('meta[name="csrf-token"]')?.content
                || '';

            fetch(this.deleteItemUrl(serverId), {
                method: 'DELETE',
                keepalive: true,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json',
                },
            }).then(async (res) => {
                if (! res.ok) {
                    const body = await res.text();
                    throw new Error(body || `HTTP ${res.status}`);
                }

                void this.syncTotalsFromServer();
            }).catch((e) => {
                console.error('keepalive delete failed', e);
                this.toastError(this.flushErrorMessage(e));
                void this.pullFromServer(false, { mutation: true });
            });
        },

        deleteNode(id) {
            if (this.pricingFrozen) {
                this.toastError('Line items cannot be removed while pricing is frozen.');

                return;
            }

            const block = this.findDeleteBlock(id);

            if (! block) {
                return;
            }

            const serverId = this.resolveServerItemId(id);

            for (let i = block.idx; i < block.end; i++) {
                this.rows[i]._removing = true;
            }

            if (serverId !== null) {
                this.sendKeepaliveDelete(serverId);
            } else {
                // A not-yet-persisted (temp-ID) row: purge any queued add mutation
                // for it so the next flush does not re-create the row we just
                // deleted (a ghost row). Mirrors discardBlankInlineRow's cleanup.
                this.queue = this.queue.filter((m) => ! sameRowId(m.tmpId, id) && ! sameRowId(m.id, id));
            }

            this.notifyTabsInvalidate();

            setTimeout(() => {
                const current = this.findDeleteBlock(id);

                if (! current) {
                    return;
                }

                this.spliceLocalDeleteBlock(current);
            }, 350);
        },

        tempId() {
            return -1 * (Date.now() * 1000 + (++this._tempSeq));
        },

        blankRow(id, type, depth, name) {
            const row = {
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

            this.recomputeChargeBreakdown(row);

            return row;
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

            const idx = this.findRowIndex(id);

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
                this._pendingFlush = true;

                return;
            }

            if (! this.queue.length) {
                this.setSync('synced');

                return;
            }

            this._flushing = true;
            this.setSync('syncing');

            const batch = orderFlushBatch(this.queue.splice(0, this.queue.length));
            const requeue = [];
            let caught = false;

            try {
                await this.applyBatchToServer(batch, requeue);
                this.setSync(this.queue.length || requeue.length ? 'syncing' : 'synced');
                await this.syncTotalsFromServer();
                this.notifyTabsInvalidate();
            } catch (e) {
                caught = true;
                const message = this.flushErrorMessage(e);
                console.error('line-items flush failed, re-queueing', message, e);
                this.toastError(message);
                this.setSync('syncing');
                setTimeout(() => this.scheduleFlush(), 1500);
            } finally {
                // Single source of truth for what gets re-queued: on error the full
                // batch (once), on success the partial requeue (once). Never both.
                const toRequeue = mutationsToRequeue({ caught, batch, requeue });

                if (toRequeue.length) {
                    this.queue.unshift(...toRequeue);
                }

                this._flushing = false;

                const needsRetry = shouldScheduleFlushRetry({
                    wasBlocked: false,
                    queueLength: this.queue.length,
                    pendingFlushFlag: this._pendingFlush,
                });

                this._pendingFlush = false;

                if (needsRetry) {
                    this.scheduleFlush();
                } else {
                    await this._drainDeferredPull();
                }
            }
        },

        resolveServerItemId(id) {
            return resolveServerItemId(id, this.rows);
        },

        requeueMutation(mutation, reason, requeue = null) {
            this.toastError(reason);

            if (Array.isArray(requeue)) {
                requeue.push(mutation);

                return;
            }

            this.queue.unshift(mutation);
        },

        async applyBatchToServer(batch, requeue = []) {
            let structural = false;

            for (const m of batch) {
                try {
                    if (m.kind === 'addGroup') {
                        const realId = await this.$wire.addGroup(m.parentGroupId ?? null, m.name ?? '');
                        this.remapTempId(m.tmpId, realId);
                        structural = true;
                    } else if (m.kind === 'addProduct') {
                        await this.$wire.addProduct(m.productId, m.quantity, m.destination);
                        structural = true;
                    } else if (m.kind === 'field') {
                        const serverId = this.resolveServerItemId(m.id);

                        if (serverId === null) {
                            this.requeueMutation(
                                m,
                                'Still saving that line — your edit will retry automatically.',
                                requeue,
                            );

                            continue;
                        }

                        const row = this.rows.find((r) => Number(r.id) === serverId);
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

                        await this.$wire.updateField(serverId, m.field, value);
                    } else if (m.kind === 'persistTree') {
                        structural = true;
                    }
                } catch (e) {
                    throw e;
                }
            }

            if (structural) {
                const realRows = rowsEligibleForPersistTree(this.rows);
                let structuralPersistSucceeded = false;

                if (realRows.length) {
                    await this.refreshBaseRevision();

                    const nodes = realRows.map((r) => ({ id: r.id, depth: r.depth }));
                    const baseRevisionBeforePersist = this.baseRevision;

                    console.warn('line-items persistTree call', {
                        baseRevision: baseRevisionBeforePersist,
                        nodeCount: nodes.length,
                        order: nodes.map((n) => n.id).join(','),
                        pruneOrphans: true,
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

                        if (! requeue.some((mutation) => mutation.kind === 'persistTree')) {
                            requeue.push({ kind: 'persistTree' });
                        }

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
            const tmp = Number(tmpId);
            const real = Number(realId);

            const row = this.rows.find((r) => Number(r.id) === tmp);

            if (row) {
                row.id = real;
            }

            for (const r of this.rows) {
                if (Number(r.parent_group_id) === tmp) {
                    r.parent_group_id = real;
                }
            }

            for (const m of this.queue) {
                if (Number(m.id) === tmp) {
                    m.id = real;
                }

                if (Number(m.tmpId) === tmp) {
                    m.tmpId = real;
                }

                if (Number(m.parentGroupId) === tmp) {
                    m.parentGroupId = real;
                }
            }

            if (sameRowId(this.openMenu, tmp)) {
                this.openMenu = real;
            }

            if (sameRowId(this.confirmDeleteId, tmp)) {
                this.confirmDeleteId = real;
            }

            this.refreshMenuRow();
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
                this.replaceRows(this.enrichRowsWithServerMetadata(reconciled.rows, serverTree));
                this.conflicts = reconciled.conflicts;
            } else {
                this.replaceRows(this.enrichRowsWithServerMetadata(serverTree, serverTree));
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
            this.refreshMenuRow();
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

        resetQuickAddInput() {
            this.quickAddQuery = '';
            this.quickAddSelection = null;
            this.quickAddQtyHint = '';

            if (this.picker.target) {
                this.picker.target.value = '';
            }

            this.refocusQuickAddInput();
        },

        refocusQuickAddInput() {
            this.$refs.quickAddQty?.blur();

            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    this.$refs.quickAddInput?.focus({ preventScroll: true });
                });
            });
        },

        clearQuickAddAfterCommit() {
            this.picker.open = false;
            this.picker.results = [];
            this.quickAddQty = '';
            this.resetQuickAddInput();
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
            event.stopPropagation();

            const committed = this.commitQuickAdd();

            if (committed) {
                return;
            }

            this.refocusQuickAddInput();
        },

        commitQuickAdd() {
            const hit = this.quickAddSelection
                ?? this.picker.results[this.picker.highlight]
                ?? this.picker.results[0];

            if (!hit) {
                return false;
            }

            const qty = Number(this.quickAddQty) > 0 ? Number(this.quickAddQty) : 1;
            const beforeIds = new Set(this.rows.map((r) => Number(r.id)));

            this.clearQuickAddAfterCommit();

            this.$wire.quickAdd(hit.id, qty).then(async () => {
                await this.refreshBaseRevision();
                await this.pullFromServer(false, { mutation: true, force: true });
                await this.syncTotalsFromServer();
                this.markRowsAddedSince(beforeIds);
                this.refocusQuickAddInput();
            });

            return true;
        },

        async createInlineTextLine() {
            if (!this.editable) {
                return;
            }

            const beforeIds = new Set(this.rows.map((r) => Number(r.id)));
            const itemId = Number(await this.$wire.addInlineTextLine()) || 0;

            await this.refreshBaseRevision();
            await this.pullFromServer(false, { mutation: true, force: true });
            await this.syncTotalsFromServer();
            this.markRowsAddedSince(beforeIds);

            if (itemId > 0) {
                await this.$nextTick();
                await this.focusFieldWhenReady(itemId, 'name');
            }
        },

        async createInlineSection() {
            if (!this.editable) {
                return;
            }

            const beforeIds = new Set(this.rows.map((r) => Number(r.id)));
            const groupId = Number(await this.$wire.addInlineSection()) || 0;

            await this.refreshBaseRevision();
            await this.pullFromServer(false, { mutation: true, force: true });
            await this.syncTotalsFromServer();
            this.markRowsAddedSince(beforeIds);

            if (groupId > 0) {
                await this.$nextTick();
                await this.focusFieldWhenReady(groupId, 'name');
            }
        },

        stageQuickAddSelection(hit) {
            if (!hit) {
                return;
            }

            this.quickAddSelection = hit;
            this.quickAddQuery = hit.name ?? '';
            this.picker.open = false;

            if (this.picker.target) {
                this.picker.target.value = hit.name ?? '';
                this.picker.target.dispatchEvent(new Event('input', { bubbles: true }));
            }

            this.$refs.quickAddQty?.focus();
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
                    this.stageQuickAddSelection(this.picker.results[this.picker.highlight]);

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
                this.stageQuickAddSelection(hit);

                return;
            }

            const qty = this.picker.quantity || 1;
            const beforeIds = new Set(this.rows.map((r) => Number(r.id)));

            this.$wire.quickAdd(hit.id, qty).then(async () => {
                await this.refreshBaseRevision();
                await this.pullFromServer(false, { mutation: true, force: true });
                await this.syncTotalsFromServer();
                this.markRowsAddedSince(beforeIds);
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
