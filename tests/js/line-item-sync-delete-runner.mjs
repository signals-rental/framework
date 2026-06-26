import { resolveServerItemId } from '../../resources/js/line-item-mutation-flush.js';

const input = JSON.parse(await readStdin());

if (input.action === 'simulateOptimisticKeepaliveDeleteDuringFlush') {
    const iterations = Number(input.iterations || 1);
    let passes = 0;
    let lastOutcome = null;

    for (let i = 0; i < iterations; i++) {
        lastOutcome = await simulateOptimisticKeepaliveDeleteDuringFlush(Number(input.deleteId || 73));

        if (
            lastOutcome.markedRemoving
            && lastOutcome.stillPresentAfterDeleteCall
            && lastOutcome.instantRemoval
            && lastOutcome.keepaliveDeleteCalled
            && lastOutcome.keepaliveUsed
            && ! lastOutcome.removeItemCalled
            && ! lastOutcome.serverHasDeletedId
            && ! lastOutcome.rowsHaveDeletedId
        ) {
            passes++;
        }
    }

    process.stdout.write(JSON.stringify({
        passes,
        iterations,
        passRate: `${passes}/${iterations}`,
        ...(iterations === 1 ? lastOutcome : {}),
    }));

    process.exit(0);
}

if (input.action === 'simulateDeleteSectionCascadeTotals') {
    const sectionId = Number(input.sectionId || 10);
    const editor = createMockEditor([
        { id: sectionId, depth: 1, item_type: 'group', name: 'Lighting', charge_total: 0 },
        { id: 71, depth: 2, item_type: 'product', name: 'Fixture', charge_total: 5000 },
        { id: 72, depth: 2, item_type: 'text', name: 'Note', charge_total: 0 },
        { id: 73, depth: 2, item_type: 'group', name: 'Nested', charge_total: 0 },
        { id: 74, depth: 3, item_type: 'product', name: 'Nested lamp', charge_total: 1500 },
        { id: 80, depth: 1, item_type: 'product', name: 'Root spare', charge_total: 2500 },
    ], [], sectionId);

    const footerBefore = editor.grandTotal;

    editor.deleteNode(sectionId);

    await delay(350);

    process.stdout.write(JSON.stringify({
        footerBefore,
        footerAfter: editor.grandTotal,
        rowsRemaining: editor.rows.length,
        removedIds: [sectionId, 71, 72, 73, 74],
        stillPresent: [sectionId, 71, 72, 73, 74].filter((id) => editor.rows.some((row) => Number(row.id) === id)),
        lastTotalsDispatch: editor.totalsDispatches.at(-1) ?? null,
    }));

    process.exit(0);
}

if (input.action === 'simulateDeleteTotalsRecalc') {
    const deleteId = Number(input.deleteId || 72);
    const editor = createMockEditor([
        { id: 10, depth: 1, item_type: 'group', name: 'Lighting', charge_total: 0 },
        { id: deleteId, depth: 2, item_type: 'product', name: 'Fixture', charge_total: 5000 },
        { id: 80, depth: 1, item_type: 'product', name: 'Root spare', charge_total: 2500 },
    ], [], deleteId);

    const groupRow = editor.rows[0];
    const footerBefore = editor.grandTotal;
    const groupSubtotalBefore = editor.groupSubtotal(groupRow);

    editor.deleteNode(deleteId);

    await delay(350);

    process.stdout.write(JSON.stringify({
        footerBefore,
        footerAfter: editor.grandTotal,
        groupSubtotalBefore,
        groupSubtotalAfter: editor.groupSubtotal(groupRow),
        serverChargeTotalMinor: editor.serverChargeTotalMinor,
        totalsDispatchCount: editor.totalsDispatches.length,
        lastTotalsDispatch: editor.totalsDispatches.at(-1) ?? null,
    }));

    process.exit(0);
}

throw new Error(`Unknown action: ${input.action}`);

function delay(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function simulateOptimisticKeepaliveDeleteDuringFlush(deleteId) {
    const serverTree = [
        { id: 70, depth: 1, item_type: 'product', name: 'Keep A' },
        { id: deleteId, depth: 1, item_type: 'product', name: 'Delete me' },
        { id: 80, depth: 1, item_type: 'product', name: 'Keep B' },
    ];
    const wireCalls = [];
    const fetchCalls = [];
    const originalFetch = globalThis.fetch;

    globalThis.fetch = async (url, options = {}) => {
        fetchCalls.push({
            url: String(url),
            method: options.method || 'GET',
            keepalive: !!options.keepalive,
            headers: options.headers || {},
        });

        if (String(url).includes(`/items/${deleteId}`) && options.method === 'DELETE') {
            const index = serverTree.findIndex((row) => Number(row.id) === deleteId);

            if (index >= 0) {
                serverTree.splice(index, 1);
            }
        }

        return {
            ok: true,
            async text() {
                return '{"ok":true}';
            },
        };
    };

    try {
        const editor = createMockEditor(serverTree, wireCalls, deleteId);

        editor.enqueue({ kind: 'persistTree' });
        editor.scheduleFlush();

        const flushPromise = editor.flush();

        await delay(50);

        editor.deleteNode(deleteId);

        const markedRemoving = editor.rows.some((row) => Number(row.id) === deleteId && row._removing === true);
        const stillPresent = editor.rows.some((row) => Number(row.id) === deleteId);

        await delay(350);

        const instantRemoval = ! editor.rows.some((row) => Number(row.id) === deleteId);

        await flushPromise;

        return {
            markedRemoving,
            stillPresentAfterDeleteCall: stillPresent,
            instantRemoval,
            keepaliveDeleteCalled: fetchCalls.some((call) => call.method === 'DELETE' && call.url.includes(`/items/${deleteId}`)),
            keepaliveUsed: fetchCalls.some((call) => call.keepalive === true),
            removeItemCalled: wireCalls.some((call) => call.method === 'removeItem' && Number(call.id) === deleteId),
            serverHasDeletedId: serverTree.some((row) => Number(row.id) === deleteId),
            rowsHaveDeletedId: editor.rows.some((row) => Number(row.id) === deleteId),
            wireCalls,
            fetchCalls,
        };
    } finally {
        globalThis.fetch = originalFetch;
    }
}

function createMockEditor(serverTree, wireCalls, deleteId) {
    const state = {
        rows: serverTree.map((row) => ({ ...row })),
        queue: [],
        syncState: 'idle',
        _flushing: false,
        _pendingFlush: false,
        _flushTimer: null,
        baseRevision: '1',
        pricingFrozen: false,
        oppId: 6,
        csrfToken: 'test-token',
        serverChargeTotalMinor: 7500,
        totalsDispatches: [],
    };

    const $wire = {
        async persistTree(nodes) {
            wireCalls.push({ method: 'persistTree', nodeCount: nodes.length });
            state._flushing = true;
            await delay(300);
            state._flushing = false;

            return { stale: false, revision: '2' };
        },
        async removeItem(id) {
            wireCalls.push({ method: 'removeItem', id: Number(id) });
            const index = serverTree.findIndex((row) => Number(row.id) === Number(id));

            if (index >= 0) {
                serverTree.splice(index, 1);
            }
        },
        async deleteSection(id) {
            wireCalls.push({ method: 'deleteSection', id: Number(id) });
        },
        async pullTree() {
            return {
                tree: serverTree.map((row) => ({ ...row })),
                revision: '3',
                conflicts: {},
            };
        },
        async refreshBaseRevision() {},
        async syncTotalsFromServer() {},
    };

    return {
        ...state,
        $wire,
        editable: true,
        setSync(next) {
            state.syncState = next;
        },
        toastError() {},
        flushErrorMessage(error) {
            return String(error?.message || error);
        },
        resolveServerItemId(id) {
            return resolveServerItemId(id, state.rows);
        },
        findRowIndex(id) {
            return state.rows.findIndex((row) => Number(row.id) === Number(id));
        },
        get grandTotal() {
            return state.rows
                .filter((row) => row.item_type !== 'group')
                .reduce((sum, row) => sum + (Number(row.charge_total) || 0), 0);
        },
        groupSubtotal(row) {
            const idx = this.findRowIndex(row.id);
            let sum = 0;

            for (let i = idx + 1; i < state.rows.length; i++) {
                if (state.rows[i].depth <= row.depth) {
                    break;
                }

                if (state.rows[i].item_type !== 'group') {
                    sum += Number(state.rows[i].charge_total) || 0;
                }
            }

            return sum;
        },
        syncOptimisticTotalsFromRows() {
            state.serverChargeTotalMinor = this.grandTotal;
            state.totalsDispatches.push({ chargeTotalMinor: this.grandTotal });
        },
        get serverChargeTotalMinor() {
            return state.serverChargeTotalMinor;
        },
        get totalsDispatches() {
            return state.totalsDispatches;
        },
        findDeleteBlock(id) {
            const idx = state.rows.findIndex((row) => row.id === id);

            if (idx === -1) {
                return null;
            }

            const target = state.rows[idx];
            let end = idx + 1;

            while (end < state.rows.length && state.rows[end].depth > target.depth) {
                end++;
            }

            return {
                idx,
                end,
                target,
                isSection: target.item_type === 'group',
            };
        },
        spliceLocalDeleteBlock(block) {
            const { idx, end } = block;
            state.rows.splice(idx, end - idx);
            this.syncOptimisticTotalsFromRows();
        },
        deleteItemUrl(serverId, isSection) {
            const base = `/opportunities/${state.oppId}/items/${serverId}`;

            return isSection ? `${base}?scope=section` : base;
        },
        sendKeepaliveDelete(serverId, isSection) {
            const token = state.csrfToken || '';

            fetch(this.deleteItemUrl(serverId, isSection), {
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
            }).catch(() => {});
        },
        deleteNode(id) {
            if (state.pricingFrozen) {
                return;
            }

            const block = this.findDeleteBlock(id);

            if (! block) {
                return;
            }

            const { isSection } = block;
            const serverId = this.resolveServerItemId(id);

            for (let i = block.idx; i < block.end; i++) {
                state.rows[i]._removing = true;
            }

            if (serverId !== null) {
                this.sendKeepaliveDelete(serverId, isSection);
            }

            setTimeout(() => {
                const current = this.findDeleteBlock(id);

                if (! current) {
                    return;
                }

                this.spliceLocalDeleteBlock(current);
            }, 350);
        },
        async pullFromServer() {
            const payload = await $wire.pullTree();
            state.rows = payload.tree.map((row) => ({ ...row }));
            state.baseRevision = String(payload.revision || state.baseRevision);
        },
        enqueue(mutation) {
            state.queue.push(mutation);
            this.scheduleFlush();
        },
        scheduleFlush() {
            this.setSync('syncing');
            clearTimeout(state._flushTimer);
            state._flushTimer = setTimeout(() => this.flush(), 0);
        },
        async flush() {
            if (state._flushing) {
                state._pendingFlush = true;

                return;
            }

            if (! state.queue.length) {
                this.setSync('synced');

                return;
            }

            state._flushing = true;
            this.setSync('syncing');

            const batch = state.queue.splice(0, state.queue.length);

            try {
                for (const mutation of batch) {
                    if (mutation.kind === 'persistTree') {
                        const nodes = state.rows.map((row) => ({ id: row.id, depth: row.depth }));
                        await $wire.persistTree(nodes);
                    }
                }

                this.setSync(state.queue.length ? 'syncing' : 'synced');
            } finally {
                state._flushing = false;

                if (state._pendingFlush || state.queue.length) {
                    state._pendingFlush = false;
                    await this.flush();
                }
            }
        },
        get rows() {
            return state.rows;
        },
        get syncState() {
            return state.syncState;
        },
        get queue() {
            return state.queue;
        },
        get _flushing() {
            return state._flushing;
        },
    };
}

async function readStdin() {
    const chunks = [];

    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }

    return Buffer.concat(chunks).toString('utf8');
}
