const input = JSON.parse(await readStdin());

function sameRowId(a, b) {
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

function delay(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function tempRowId() {
    return -1 * (Date.now() * 1000 + 7);
}

if (input.action === 'simulateNewRowMenuOpen') {
    const tempId = Number(input.tempId || tempRowId());
    const row = {
        id: tempId,
        item_type: input.itemType || 'text',
        depth: 1,
        name: 'Fresh row',
        is_optional: false,
        has_duplicates: false,
    };

    const editor = createMenuEditor([row]);
    const event = { currentTarget: {}, stopPropagation() {} };

    editor.openRowMenu(event, row);

    process.stdout.write(JSON.stringify({
        menuOpen: editor.openMenu === tempId,
        menuRowResolved: editor.menuRow !== null,
        menuRowId: editor.menuRow?.id ?? null,
        hasRemoveAction: editor.menuRow?.item_type === 'text' || editor.menuRow?.item_type === 'product',
        sameRowIdWorks: sameRowId(editor.openMenu, tempId),
    }));

    process.exit(0);
}

if (input.action === 'simulateMenuDeleteOnTempRow') {
    const tempId = tempRowId();
    const row = {
        id: tempId,
        item_type: 'text',
        depth: 1,
        name: '',
        is_optional: false,
        has_duplicates: false,
    };

    const editor = createMenuEditor([row]);
    editor.openRowMenu({ currentTarget: {}, stopPropagation() {} }, row);
    editor.handleRemoveMenuClick();
    editor.handleRemoveMenuClick();

    const markedRemoving = editor.rows.some((candidate) => sameRowId(candidate.id, tempId) && candidate._removing === true);

    await delay(350);

    process.stdout.write(JSON.stringify({
        markedRemoving,
        rowRemoved: ! editor.rows.some((candidate) => sameRowId(candidate.id, tempId)),
        menuClosed: editor.openMenu === null,
    }));

    process.exit(0);
}

if (input.action === 'simulateInlineRowInteractivity') {
    const serverRow = {
        id: '42',
        item_type: 'text',
        depth: 1,
        name: '',
        quantity: '1',
        days: 1,
        unit_price: 0,
        unit_price_display: '£0.00',
        unit_price_raw: '0.00',
        discount_percent: null,
        charge_total: 0,
        charge_total_display: '£0.00',
        is_optional: false,
        has_duplicates: false,
        has_children: false,
        is_collapsed: false,
    };

    const editor = createInteractiveEditor([]);
    editor.applyPulledRows([serverRow]);

    const row = editor.findRow(42);
    editor.openRowMenu({ currentTarget: {}, stopPropagation() {} }, row);
    const dragIndex = editor.findRowIndex(42);

    process.stdout.write(JSON.stringify({
        rowIdNormalized: row?.id === 42,
        menuOpen: editor.openMenu === 42,
        menuRowResolved: editor.menuRow !== null,
        dragIndex,
        editRowResolved: editor.findRow(42) !== null,
    }));

    process.exit(0);
}

throw new Error(`Unknown action: ${input.action}`);

function createMenuEditor(rows) {
    const state = {
        rows: rows.map((row) => ({ ...row })),
        openMenu: null,
        menuRow: null,
        confirmDeleteId: null,
        pricingFrozen: false,
        editable: true,
        _menuAnchor: null,
        _menuOutsideSuppressedUntil: null,
    };

    return {
        ...state,
        get rows() {
            return state.rows;
        },
        get openMenu() {
            return state.openMenu;
        },
        get menuRow() {
            return state.menuRow;
        },
        get confirmDeleteId() {
            return state.confirmDeleteId;
        },
        setConfirmDeleteId(id) {
            state.confirmDeleteId = id;
        },
        idsMatch(a, b) {
            return sameRowId(a, b);
        },
        resolveMenuRow() {
            if (state.openMenu == null) {
                return null;
            }

            return state.rows.find((row) => sameRowId(row.id, state.openMenu)) ?? null;
        },
        refreshMenuRow() {
            state.menuRow = this.resolveMenuRow();
        },
        positionRowMenu() {},
        $nextTick(callback) {
            callback();
        },
        closeRowMenu() {
            state.openMenu = null;
            state.menuRow = null;
            state.confirmDeleteId = null;
        },
        openRowMenu(event, rowOrId) {
            event?.stopPropagation?.();

            const row = typeof rowOrId === 'object' && rowOrId !== null
                ? rowOrId
                : state.rows.find((candidate) => sameRowId(candidate.id, rowOrId));

            if (! row) {
                return;
            }

            state.openMenu = row.id;
            state.menuRow = row;
            state.confirmDeleteId = null;
        },
        findDeleteBlock(id) {
            const idx = state.rows.findIndex((row) => sameRowId(row.id, id));

            if (idx === -1) {
                return null;
            }

            const target = state.rows[idx];

            return {
                idx,
                end: idx + 1,
                target,
                isSection: target.item_type === 'group',
            };
        },
        spliceLocalDeleteBlock(block) {
            state.rows.splice(block.idx, block.end - block.idx);
        },
        deleteNode(id) {
            const block = this.findDeleteBlock(id);

            if (! block) {
                return;
            }

            for (let i = block.idx; i < block.end; i++) {
                state.rows[i]._removing = true;
            }

            setTimeout(() => {
                const current = this.findDeleteBlock(id);

                if (! current) {
                    return;
                }

                state.rows.splice(current.idx, current.end - current.idx);
            }, 350);
        },
        handleRemoveMenuClick() {
            const row = state.menuRow ?? this.resolveMenuRow();

            if (! row) {
                return;
            }

            if (! sameRowId(state.confirmDeleteId, row.id)) {
                state.confirmDeleteId = row.id;

                return;
            }

            this.closeRowMenu(true);
            this.deleteNode(row.id);
        },
    };
}

function createInteractiveEditor(rows) {
    const state = {
        rows: rows.map((row) => ({ ...row })),
        openMenu: null,
        menuRow: null,
    };

    return {
        get rows() {
            return state.rows;
        },
        get openMenu() {
            return state.openMenu;
        },
        get menuRow() {
            return state.menuRow;
        },
        normalize(rowsToNormalize) {
            return rowsToNormalize.map((row) => ({
                ...row,
                id: Number(row.id),
                depth: Number(row.depth),
            }));
        },
        applyPulledRows(serverRows) {
            const normalized = this.normalize(serverRows);

            if (state.rows.length === 0) {
                state.rows.push(...normalized);
            } else {
                state.rows.splice(0, state.rows.length, ...normalized);
            }
        },
        findRow(id) {
            return state.rows.find((row) => sameRowId(row.id, id)) ?? null;
        },
        findRowIndex(id) {
            return state.rows.findIndex((row) => sameRowId(row.id, id));
        },
        openRowMenu(event, rowOrId) {
            event?.stopPropagation?.();

            const row = typeof rowOrId === 'object' && rowOrId !== null
                ? rowOrId
                : this.findRow(rowOrId);

            if (! row) {
                return;
            }

            state.openMenu = row.id;
            state.menuRow = row;
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
