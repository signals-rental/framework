const input = JSON.parse(await readStdin());

if (input.action === 'simulateQuickAddEmptyQtyDefault') {
    const editor = createQuickAddEditor({ quickAddQty: '' });

    const qtyUsed = editor.commitQuickAddForTest();

    process.stdout.write(JSON.stringify({
        qtyUsed,
        qtyAfterReset: editor.quickAddQty,
    }));

    process.exit(0);
}

if (input.action === 'simulateQuickAddKeyboardRefocus') {
    const editor = createQuickAddEditor();

    editor.onQuickAddInputKeydown({ key: 'Enter', preventDefault() {} }, editor.$refs.quickAddInput);
    editor.onQuickAddQtyKeydown({ key: 'Enter', preventDefault() {}, stopPropagation() {} });

    process.stdout.write(JSON.stringify({
        quickAddQueryCleared: editor.quickAddQuery === '',
        qtyReset: editor.quickAddQty === '',
        refocusCalled: editor._refocusCalls > 0,
        wireQuickAddCalled: editor._wireQuickAddCalls === 1,
    }));

    process.exit(0);
}

throw new Error(`Unknown action: ${input.action}`);

function createQuickAddEditor(overrides = {}) {
    const state = {
        quickAddQuery: 'Spiider',
        quickAddQty: '',
        quickAddSelection: { id: 42, name: 'Spiider' },
        picker: {
            open: false,
            results: [{ id: 42, name: 'Spiider' }],
            highlight: 0,
            target: null,
        },
        rows: [],
        _refocusCalls: 0,
        _wireQuickAddCalls: 0,
        ...overrides,
    };

    const refs = {
        quickAddInput: {
            value: state.quickAddQuery,
            focus() {
                state._refocusCalls++;
            },
        },
        quickAddQty: {
            focus() {},
        },
    };

    return {
        get quickAddQuery() {
            return state.quickAddQuery;
        },
        get quickAddQty() {
            return state.quickAddQty;
        },
        get quickAddSelection() {
            return state.quickAddSelection;
        },
        get picker() {
            return state.picker;
        },
        get rows() {
            return state.rows;
        },
        get _refocusCalls() {
            return state._refocusCalls;
        },
        get _wireQuickAddCalls() {
            return state._wireQuickAddCalls;
        },
        $refs: refs,
        $wire: {
            quickAdd(productId, qty) {
                state._lastQuickAddQty = qty;
                state._wireQuickAddCalls++;

                return Promise.resolve();
            },
        },
        $nextTick(callback) {
            callback();
        },
        async refreshBaseRevision() {},
        async pullFromServer() {},
        async syncTotalsFromServer() {},
        markRowsAddedSince() {},
        resetQuickAddInput() {
            state.quickAddQuery = '';
            state.quickAddSelection = null;
            state.quickAddQtyHint = '';

            if (state.picker.target) {
                state.picker.target.value = '';
            }

            this.refocusQuickAddInput();
        },
        refocusQuickAddInput() {
            this.$nextTick(() => {
                this.$refs.quickAddInput?.focus();
            });
        },
        clearQuickAddAfterCommit() {
            state.picker.open = false;
            state.picker.results = [];
            state.quickAddQty = '';
            this.resetQuickAddInput();
        },
        onQuickAddInputKeydown(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
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

            if (! committed) {
                this.refocusQuickAddInput();
            }
        },
        commitQuickAdd() {
            const hit = state.quickAddSelection
                ?? state.picker.results[state.picker.highlight]
                ?? state.picker.results[0];

            if (! hit) {
                return false;
            }

            const qty = Number(state.quickAddQty) > 0 ? Number(state.quickAddQty) : 1;

            this.clearQuickAddAfterCommit();

            this.$wire.quickAdd(hit.id, qty).then(async () => {
                await this.refreshBaseRevision();
                await this.pullFromServer(false, { mutation: true, force: true });
                await this.syncTotalsFromServer();
                this.markRowsAddedSince(new Set());
                this.refocusQuickAddInput();
            });

            return true;
        },
        commitQuickAddForTest() {
            const hit = state.quickAddSelection;

            if (! hit) {
                return null;
            }

            const qty = Number(state.quickAddQty) > 0 ? Number(state.quickAddQty) : 1;

            this.clearQuickAddAfterCommit();

            return qty;
        },
        get _lastQuickAddQty() {
            return state._lastQuickAddQty;
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
