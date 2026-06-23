{{--
    ============================================================================
    EDITOR LAB PROTOTYPE — "Local First" variant  (THROWAWAY)
    ============================================================================
    This is the `local-first` prototype.

        Alpine store (render source of truth)
          + IndexedDB / Dexie (instant reload-from-cache mirror)
          + a background SYNC QUEUE (debounced + requestIdleCallback flush)
          + a hand-rolled pointer-events nested DnD (Nestable-style indent).

    Everything updates the local Alpine store + IndexedDB IMMEDIATELY (feels
    instant, survives reload), and enqueues a mutation that flushes to the
    server in the background via $wire calls. A "synced / syncing…" pill makes
    the deferral visible.

    >>> ONLY this file is edited. Service / model / migration / routes are FIXED.

    SHARED BACK-END CONTRACT — app(App\Services\Prototypes\PrototypeEditorService::class)
    (see the original stub header for full notes). persistTree() node shape is
    an ORDERED (final display pre-order) array of ['id'=>int,'depth'=>int].
    ============================================================================
--}}
<?php

use App\Models\Opportunity;
use App\Services\Prototypes\PrototypeEditorService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    /** The prototype key that scopes this editor's private copy of the tree. */
    public const VARIANT = 'local-first';

    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        $this->opportunity = $opportunity;

        $this->service()->ensureSeeded($this->opportunity->id, self::VARIANT);
    }

    /**
     * The seeded line-item tree as a flat, path-ordered (pre-order) array.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function tree(): array
    {
        return $this->service()->tree($this->opportunity->id, self::VARIANT);
    }

    /**
     * Persist a re-ordered / re-nested tree from a DnD drop.
     *
     * @param  array<int, array{id: int, depth: int}>  $nodes  ordered [{id,depth}], final display pre-order
     */
    public function persistTree(array $nodes): void
    {
        $this->service()->persistTree($this->opportunity->id, self::VARIANT, $nodes);
        unset($this->tree);
    }

    public function updateField(int $id, string $field, mixed $value): void
    {
        $this->service()->updateField($id, $this->opportunity->id, self::VARIANT, $field, $value);
        unset($this->tree);
    }

    public function addGroup(?string $afterPath = null, string $name = 'New group'): int
    {
        $id = $this->service()->addGroup($this->opportunity->id, self::VARIANT, $afterPath, $name);
        unset($this->tree);

        return $id;
    }

    public function addItem(?string $parentPath = null, string $name = 'New item'): int
    {
        $id = $this->service()->addItem($this->opportunity->id, self::VARIANT, $parentPath, $name);
        unset($this->tree);

        return $id;
    }

    public function deleteNode(int $id): void
    {
        $this->service()->deleteNode($id, $this->opportunity->id, self::VARIANT);
        unset($this->tree);
    }

    public function cloneNode(int $id): int
    {
        $id = $this->service()->cloneNode($id, $this->opportunity->id, self::VARIANT);
        unset($this->tree);

        return $id;
    }

    /**
     * Force a fresh server snapshot of the tree (used by the "reload from
     * server" debug button so we can prove cache-vs-server parity).
     *
     * @return array<int, array<string, mixed>>
     */
    public function serverTree(): array
    {
        unset($this->tree);

        return $this->tree();
    }

    private function service(): PrototypeEditorService
    {
        return app(PrototypeEditorService::class);
    }
}; ?>

<div
    x-data="localFirstEditor({
        oppId: {{ $opportunity->id }},
        variant: @js(self::VARIANT),
    })"
    x-init="boot()"
    class="local-first-editor"
>
    {{--
        Seed island — written ONCE and frozen on window. The seed is only consumed
        by boot() on the very first hydrate; keeping it OUT of the reactive x-data
        expression means the x-data attribute string never changes between Livewire
        re-renders, so Livewire's morph won't re-initialise the Alpine component (a
        re-init reset rows to [] and flashed the table empty mid-sync). wire:ignore
        so Livewire never re-emits / re-runs it. Kept INSIDE the single root <div>
        to satisfy Livewire's single-root-element rule.
    --}}
    <script wire:ignore data-lf-seed>
        (function () {
            window.__lfSeed = window.__lfSeed || {};
            var key = {{ $opportunity->id }} + ':' + @js(self::VARIANT);
            // first write wins — later re-renders must not overwrite the frozen seed
            if (!(key in window.__lfSeed)) {
                window.__lfSeed[key] = @js($this->tree);
            }
        })();
    </script>

    <x-signals.page-header title="Editor Lab — Local First (PROTOTYPE)">
        <x-slot:breadcrumbs>
            <a href="{{ route('opportunities.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Opportunities</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('opportunities.show', $opportunity) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $opportunity->subject }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>Editor Lab — Local First</span>
        </x-slot:breadcrumbs>
        <x-slot:meta>
            <span class="s-badge s-badge-amber"><span class="s-badge-dot"></span> Prototype</span>
        </x-slot:meta>
        <x-slot:actions>
            {{-- Sync status pill — makes the deferred write visible --}}
            <span
                class="s-badge"
                :class="{
                    's-badge-emerald': syncState === 'synced',
                    's-badge-amber': syncState === 'syncing',
                    's-badge-blue': syncState === 'cached',
                    's-badge-zinc': syncState === 'idle',
                }"
                title="Local-first sync status"
            >
                <span class="s-badge-dot" :class="{ 'animate-pulse': syncState === 'syncing' }"></span>
                <span x-text="syncLabel"></span>
                <span x-show="queue.length" x-cloak class="ml-1 opacity-70" x-text="'(' + queue.length + ')'"></span>
            </span>
            <a href="{{ route('opportunities.show', $opportunity) }}" wire:navigate class="s-btn s-btn-sm">
                &larr; Back to opportunity
            </a>
        </x-slot:actions>
    </x-signals.page-header>

    {{-- ============================ toolbar ============================ --}}
    <x-signals.card class="mb-3">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="s-btn s-btn-sm s-btn-primary" @click="addGroup()">+ Group</button>
            <button type="button" class="s-btn s-btn-sm" @click="addItem(null)">+ Item</button>

            <span class="mx-2 h-5 w-px bg-[var(--border)]"></span>

            <button type="button" class="s-btn s-btn-sm" @click="flushNow()" :disabled="!queue.length">
                Flush sync now
                <span x-show="queue.length" x-cloak x-text="'(' + queue.length + ')'"></span>
            </button>
            <button type="button" class="s-btn s-btn-sm" @click="hydrateFromServer()" title="Drop the local cache and pull a fresh server snapshot">
                Reload from server
            </button>
            <button type="button" class="s-btn s-btn-sm s-btn-ghost" @click="hardReload()" title="window.location.reload() — proves the page paints instantly from IndexedDB before any server round-trip">
                Hard reload (cache demo)
            </button>

            <span class="ml-auto text-sm text-[var(--text-muted)]">
                Grand total:
                <strong class="text-[var(--text)] tabular-nums" x-text="grandTotalDisplay"></strong>
            </span>
        </div>
        <p class="mt-2 text-xs text-[var(--text-muted)]">
            Local-first: every edit hits the Alpine store + IndexedDB instantly, then a debounced background queue syncs to the
            server. Drag the <span class="font-mono">☰</span> handle — move left/right to change nesting depth (Nestable-style),
            up/down to reorder. Hard-reload to see the tree repaint from cache before the server responds.
        </p>
    </x-signals.card>

    {{-- ============================ tree grid ============================ --}}
    {{--
        wire:ignore — this table is rendered ENTIRELY by Alpine (x-for over the
        local store). Every background-sync $wire.* call triggers a Livewire
        re-render + DOM morph; without wire:ignore that morph walks into this
        Alpine-owned subtree and replaces the x-for rows with the server's empty
        tbody (only the "No rows" placeholder), making the whole table flash blank
        mid-sync. Ignoring it leaves the DOM under Alpine's sole control.
    --}}
    <x-signals.card>
        <div class="overflow-x-auto" wire:ignore>
            <table class="s-table lf-table w-full text-sm select-none">
                <thead>
                    <tr>
                        <th class="text-left" style="min-width: 320px;">Product</th>
                        <th class="text-left">Type</th>
                        <th class="text-left">Status</th>
                        <th class="text-left">Qty</th>
                        <th class="text-left">Days</th>
                        <th class="text-left">Price</th>
                        {{-- inline text-align: `.s-table th` (specificity 0,1,1) beats the
                             `.text-right` utility, so force right-alignment inline --}}
                        <th class="text-right" style="text-align: right;">Disc %</th>
                        {{-- wide enough for the largest expected value: 100,000,000.00 --}}
                        <th class="text-right" style="text-align: right; min-width: 150px; white-space: nowrap;">Charge Total</th>
                        <th class="text-right" style="width: 48px;"></th>
                    </tr>
                </thead>

                {{-- The whole body is rendered from the Alpine store (local source of truth). --}}
                <tbody x-ref="tbody">
                    <template x-for="row in visibleRows" :key="row.id">
                        <tr
                            class="lf-row"
                            :class="{
                                'lf-row-group': row.item_type === 'group',
                                'lf-row-dragging': dragId === row.id,
                            }"
                            :data-id="row.id"
                        >
                            {{-- Product / name cell with indent, handle, collapse caret --}}
                            <td>
                                <div class="flex items-center gap-1" :style="`padding-left:${(row.depth - 1) * 22}px`">
                                    {{-- drag handle (LEFT aligned) --}}
                                    <span
                                        class="lf-handle"
                                        title="Drag to move / re-nest"
                                        @pointerdown="onHandleDown($event, row.id)"
                                    >☰</span>

                                    {{-- collapse caret for rows with children --}}
                                    <button
                                        type="button"
                                        class="lf-caret"
                                        x-show="row.has_children"
                                        @click="toggleCollapse(row.id)"
                                        x-text="row.is_collapsed ? '▸' : '▾'"
                                    ></button>
                                    <span x-show="!row.has_children" class="lf-caret-spacer"></span>

                                    {{-- type marker --}}
                                    <template x-if="row.item_type === 'accessory'">
                                        <span class="text-[var(--text-muted)]">↳</span>
                                    </template>

                                    {{-- editable name --}}
                                    <span
                                        class="lf-name"
                                        :class="{ 'font-semibold': row.item_type === 'group' }"
                                        @dblclick="beginEdit(row.id, 'name', $event)"
                                        x-text="row.name"
                                    ></span>

                                    <template x-if="row.item_type === 'group'">
                                        <span class="s-badge s-badge-zinc ml-1">group</span>
                                    </template>
                                    <template x-if="row.item_type === 'service'">
                                        <span class="s-badge s-badge-blue ml-1">service</span>
                                    </template>
                                </div>
                            </td>

                            {{-- type label --}}
                            <td>
                                <span x-show="row.item_type !== 'group'" class="s-chip" x-text="row.type_label || '—'"></span>
                            </td>

                            {{-- status label --}}
                            <td>
                                <template x-if="row.item_type !== 'group' && row.status_label">
                                    <span
                                        class="s-badge"
                                        :class="statusClass(row.status_label)"
                                        x-text="row.status_label"
                                    ></span>
                                </template>
                            </td>

                            {{-- editable numeric cells (click-to-edit) --}}
                            <td class="text-left tabular-nums">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left" data-field="quantity" @click="beginEdit(row.id, 'quantity', $event)" x-text="fmtQty(row.quantity)"></span>
                            </td>
                            <td class="text-left tabular-nums">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left" data-field="days" @click="beginEdit(row.id, 'days', $event)" x-text="row.days"></span>
                            </td>
                            <td class="text-left tabular-nums">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left lf-cell-price" data-field="unit_price" @click="beginEdit(row.id, 'unit_price', $event)" x-text="row.unit_price_display"></span>
                            </td>
                            <td class="text-right tabular-nums">
                                <span x-show="row.item_type !== 'group'" class="lf-cell" data-field="discount_percent" @click="beginEdit(row.id, 'discount_percent', $event)" x-text="(row.discount_percent ? row.discount_percent : 0) + '%'"></span>
                            </td>

                            {{-- charge total: group rows show a live subtotal --}}
                            <td class="text-right tabular-nums font-medium" style="white-space: nowrap;">
                                <span x-show="row.item_type === 'group'" class="text-[var(--text-muted)]" x-text="groupSubtotalDisplay(row)"></span>
                                <span x-show="row.item_type !== 'group'" x-text="row.charge_total_display"></span>
                            </td>

                            {{-- row ▾ menu --}}
                            <td class="text-right">
                                <div class="relative inline-block" @click.outside="openMenu === row.id && (openMenu = null, confirmDeleteId = null)">
                                    <button type="button" class="lf-menu-btn" @click.stop="openMenu = (openMenu === row.id ? null : row.id); confirmDeleteId = null">▾</button>
                                    <div
                                        x-show="openMenu === row.id"
                                        x-cloak
                                        x-transition.opacity
                                        class="lf-menu"
                                    >
                                        <button type="button" @click="addItem(row.id); openMenu = null">Add item inside</button>
                                        <button type="button" @click="addGroup(); openMenu = null">Add group</button>
                                        <button type="button" @click="beginEdit(row.id, 'name'); openMenu = null">Rename</button>
                                        <button type="button" @click="cloneNode(row.id); openMenu = null">Clone subtree</button>
                                        {{-- two-step delete: first click arms (red/white), second click confirms --}}
                                        <button
                                            type="button"
                                            class="lf-menu-danger"
                                            :class="{ 'lf-menu-delete-armed': confirmDeleteId === row.id }"
                                            @click.stop="confirmDeleteId === row.id
                                                ? (deleteNode(row.id), openMenu = null, confirmDeleteId = null)
                                                : (confirmDeleteId = row.id)"
                                            x-text="confirmDeleteId === row.id ? 'Click again to confirm' : 'Delete'"
                                        ></button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="!rows.length">
                        <td colspan="9" class="text-center text-[var(--text-muted)] py-4">No rows. Add a group to start.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-signals.card>

    {{-- floating drag clone + live indent guide --}}
    <div x-ref="ghost" class="lf-ghost" x-show="dragId" x-cloak></div>
</div>

@push('scripts')
    {{-- Dexie 4.x — IndexedDB wrapper, loaded via CDN (no npm / Vite changes) --}}
    <script
        src="https://unpkg.com/dexie@4.0.11/dist/dexie.min.js"
        integrity="sha384-cPcqy69aXDd/DVAhVjR7F1pRGPSPFhhPA108OQ8DcgTdZixJZc3W4gzurSW1WfgH"
        crossorigin="anonymous"
    ></script>

    <style>
        [x-cloak] { display: none !important; }

        .lf-table td { vertical-align: middle; }
        .lf-row { transition: background-color .12s ease; }
        .lf-row:hover { background: var(--surface-2, rgba(127,127,127,.06)); }
        .lf-row-group { background: var(--surface-2, rgba(127,127,127,.05)); }
        .lf-row-group td { border-top: 1px solid var(--border); }
        .lf-row-dragging { opacity: .35; }

        .lf-handle {
            cursor: grab;
            color: var(--text-muted);
            padding: 0 4px;
            font-size: 13px;
            line-height: 1;
            touch-action: none;
            user-select: none;
        }
        .lf-handle:active { cursor: grabbing; }

        .lf-caret {
            width: 16px; height: 16px;
            font-size: 11px; line-height: 1;
            color: var(--text-muted);
            background: none; border: 0; cursor: pointer;
        }
        .lf-caret-spacer { display: inline-block; width: 16px; }

        .lf-name { cursor: text; }

        .lf-cell {
            cursor: pointer;
            padding: 2px 5px;
            border-radius: 4px;
            border: 1px solid transparent;
            display: inline-block;
            box-sizing: border-box;
            width: 78px;            /* fixed — matches .lf-edit-input so the column doesn't jump on edit */
            text-align: right;
        }
        .lf-cell:hover { border-color: var(--border); background: var(--surface-2, rgba(127,127,127,.08)); }
        .lf-cell-left { text-align: left; }
        /* price cell — wide enough for values up to 99,000,000.00 */
        .lf-cell-price { width: 135px; }

        .lf-edit-input {
            width: 78px;            /* matches .lf-cell width — no column reflow when swapping to the input */
            padding: 2px 5px;
            box-sizing: border-box;
            border: 1px solid var(--link, #2563eb);
            border-radius: 4px;
            background: var(--surface, #fff);
            color: var(--text);
            text-align: right;
            font: inherit;
        }
        .lf-edit-input.lf-edit-text { width: 220px; text-align: left; }
        /* price input — matches .lf-cell-price width so the column doesn't reflow on edit */
        .lf-edit-input.lf-edit-price { width: 135px; text-align: left; }

        .lf-menu-btn {
            border: 0; background: none; cursor: pointer;
            color: var(--text-muted); padding: 2px 6px; border-radius: 4px;
        }
        .lf-menu-btn:hover { background: var(--surface-2, rgba(127,127,127,.1)); }

        .lf-menu {
            position: absolute; right: 0; top: 100%; z-index: 40;
            min-width: 160px; margin-top: 4px;
            background: var(--surface, #fff);
            border: 1px solid var(--border);
            border-radius: 6px;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            padding: 4px;
            text-align: left;
        }
        .lf-menu button {
            display: block; width: 100%; text-align: left;
            padding: 6px 10px; border: 0; background: none; cursor: pointer;
            font-size: 13px; color: var(--text); border-radius: 4px;
        }
        .lf-menu button:hover { background: var(--surface-2, rgba(127,127,127,.1)); }
        .lf-menu-danger { color: var(--danger, #dc2626) !important; }
        /* armed (first click) — fill red, text white, until the confirming 2nd click */
        .lf-menu-delete-armed {
            background: var(--danger, #dc2626) !important;
            color: #fff !important;
            font-weight: 600;
        }
        .lf-menu-delete-armed:hover { background: var(--danger, #dc2626) !important; }

        /* drop placeholder gap injected between rows during a drag */
        .lf-placeholder td {
            height: 0; padding: 0; border: 0;
        }
        .lf-placeholder .lf-placeholder-bar {
            height: 3px; background: var(--brand-primary, #1e3a5f); border-radius: 2px;
            margin: 1px 0; transition: margin-left .06s ease;
        }
        /* illegal drop target — red, dashed "no-drop" bar */
        .lf-placeholder-invalid .lf-placeholder-bar {
            background: repeating-linear-gradient(
                90deg,
                var(--danger, #dc2626) 0, var(--danger, #dc2626) 6px,
                transparent 6px, transparent 12px
            );
            opacity: .8;
        }

        .lf-ghost {
            position: fixed; top: 0; left: 0; z-index: 9999;
            pointer-events: none;
            padding: 4px 12px;
            background: var(--surface, #fff);
            border: 1px solid var(--brand-primary, #1e3a5f);
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
            font-size: 13px;
            color: var(--text);
            max-width: 360px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            opacity: .95;
        }
    </style>

    <script>
        // ====================================================================
        //  Dexie store — one IndexedDB DB, one table keyed by opp+variant+id.
        // ====================================================================
        const lfDexie = new Dexie('signals_local_first_editor');
        lfDexie.version(1).stores({
            // compound key [opp+variant+id]; index on [opp+variant] for range reads
            rows: '[opp+variant+id], [opp+variant]',
            meta: 'key',
        });

        const INDENT_PX = 22; // matches the template padding-left per depth

        function localFirstEditor(cfg) {
            return {
                // ---- config ----
                oppId: cfg.oppId,
                variant: cfg.variant,
                // seed comes from the frozen window island, not the (volatile) x-data
                // expression — see the seed <script> above.
                get seedRows() {
                    const island = (window.__lfSeed || {});
                    return island[this.oppId + ':' + this.variant] || [];
                },

                // ---- local source of truth ----
                rows: [],          // flat, pre-order, the Alpine render source
                openMenu: null,
                confirmDeleteId: null, // row whose Delete button is "armed" (needs a 2nd click)

                // ---- sync queue ----
                queue: [],
                syncState: 'idle', // idle | cached | syncing | synced
                _flushTimer: null,
                _idleHandle: null,

                // ---- drag state ----
                dragId: null,
                _drag: null,       // { rows[], handleStartX, startDepth, pointerId }
                _placeholderEl: null,

                // ================================================================
                //  boot — hydrate from IndexedDB instantly, else from server seed
                // ================================================================
                _booted: false,
                async boot() {
                    // Idempotent: a Livewire re-render can re-run x-init="boot()".
                    // If we've already hydrated, do NOT re-read/replace the live store
                    // (that re-init + async cache read is what left the table briefly
                    // empty mid-sync). Once booted, the Alpine store is authoritative.
                    if (this._booted) return;
                    if (this.rows.length) { this._booted = true; return; }
                    this._booted = true;

                    this.bindLivewireRefresh();

                    const cached = await this.loadCache();
                    if (cached && cached.length) {
                        this.rows = this.normalize(cached);
                        this.syncFlash('cached', 'loaded from cache');
                    } else {
                        this.rows = this.normalize(this.seedRows);
                        // first-ever hydrate from the server seed → seed default
                        // collapse: groups open, products-with-accessories closed.
                        this.applyDefaultCollapse(this.rows);
                        await this.saveCache();
                        this.setSync('synced');
                    }
                },

                // Livewire re-renders can fire; we deliberately DON'T let server
                // pushes clobber the local store mid-session (local-first).
                bindLivewireRefresh() { /* intentionally inert — local wins */ },

                // ----------------------------------------------------------------
                //  Dexie helpers
                // ----------------------------------------------------------------
                async loadCache() {
                    try {
                        return await lfDexie.rows
                            .where('[opp+variant]').equals([this.oppId, this.variant])
                            .toArray();
                    } catch (e) { console.warn('lf cache read failed', e); return null; }
                },
                // Serialised cache writer. saveCache() is fired (un-awaited) from many
                // places — local edits, drag drops, the post-flush remap — so two of
                // them used to overlap: one transaction's range-delete could land
                // AFTER another's bulkPut and leave the IndexedDB table EMPTY (the
                // "table empties while syncing" bug). We now chain every write so they
                // run strictly one-at-a-time, and snapshot the rows at enqueue time so
                // a later mutation can't be half-written.
                _cacheChain: Promise.resolve(),
                saveCache() {
                    // snapshot NOW — decoupled from any later in-place row mutation
                    const snapshot = this.rows.map(r => ({ ...r, opp: this.oppId, variant: this.variant }));
                    this._cacheChain = this._cacheChain.then(async () => {
                        // never persist an empty set over good cached data (guards against
                        // a transient empty `this.rows` clobbering the durable mirror)
                        if (!snapshot.length) return;
                        try {
                            await lfDexie.transaction('rw', lfDexie.rows, async () => {
                                await lfDexie.rows.where('[opp+variant]').equals([this.oppId, this.variant]).delete();
                                await lfDexie.rows.bulkPut(snapshot);
                            });
                        } catch (e) { console.warn('lf cache write failed', e); }
                    });
                    return this._cacheChain;
                },

                // every row gets a stable numeric depth & we re-derive children flags
                normalize(rows) {
                    const clone = rows.map(r => ({ ...r, depth: Number(r.depth) }));
                    return this.recomputeFlags(clone);
                },

                // ----------------------------------------------------------------
                //  DEFAULT COLLAPSE STATE (applied on the FIRST hydrate from the
                //  server seed only — the IndexedDB cache path is the user's own
                //  persisted collapse state and is left exactly as-is).
                //   - groups default EXPANDED (open)
                //   - products that have an accessory child default COLLAPSED
                //  Run AFTER recomputeFlags so `depth`/order are settled.
                // ----------------------------------------------------------------
                applyDefaultCollapse(rows) {
                    for (let i = 0; i < rows.length; i++) {
                        const r = rows[i];
                        if (r.item_type === 'product') {
                            const next = rows[i + 1];
                            // collapse a product only when it actually has accessory children
                            const hasAccessoryChild = !!(next && next.depth > r.depth && next.item_type === 'accessory');
                            r.is_collapsed = hasAccessoryChild;
                        } else {
                            // groups (and everything else) start open
                            r.is_collapsed = false;
                        }
                    }
                    return rows;
                },

                // ----------------------------------------------------------------
                //  NESTING RULES — what parent each node type is allowed to live under
                //  (parentNode === null  ==>  root / top level)
                //
                //   group     : root | inside a group
                //   product   : root | inside a group
                //   service   : root | inside a group  (same as product)
                //   accessory : LOCKED to its principal product — may only be
                //               reordered among siblings under the SAME product.
                //
                //  `originalParentId` is the accessory's current parent product id at
                //  drag-start; an accessory drop is only valid if the prospective
                //  parent is that exact same product.
                // ----------------------------------------------------------------
                canPlace(draggedNode, parentNode, originalParentId = null) {
                    const type = draggedNode.item_type;

                    if (type === 'accessory') {
                        // must stay under its principal product (same parent id)
                        if (!parentNode || parentNode.item_type !== 'product') return false;
                        if (originalParentId == null) return parentNode.item_type === 'product';
                        return parentNode.id === originalParentId;
                    }

                    // group / product / service: root, or directly inside a group
                    if (parentNode === null) return true;            // top level always OK
                    return parentNode.item_type === 'group';
                },

                // re-derive has_children + parent visibility from current order/depth
                recomputeFlags(rows) {
                    for (let i = 0; i < rows.length; i++) {
                        const next = rows[i + 1];
                        rows[i].has_children = !!(next && next.depth > rows[i].depth);
                    }
                    return rows;
                },

                // ================================================================
                //  derived render data
                // ================================================================
                get visibleRows() {
                    // hide descendants of any collapsed ancestor
                    const out = [];
                    let hideBelowDepth = Infinity;
                    for (const r of this.rows) {
                        if (r.depth > hideBelowDepth) continue;
                        hideBelowDepth = Infinity;
                        out.push(r);
                        if (r.is_collapsed) hideBelowDepth = r.depth;
                    }
                    return out;
                },

                get grandTotal() {
                    return this.rows
                        .filter(r => r.item_type !== 'group')
                        .reduce((s, r) => s + (Number(r.charge_total) || 0), 0);
                },
                get grandTotalDisplay() { return this.money(this.grandTotal); },

                // subtotal of everything under a group (until the next same/shallower row)
                groupSubtotal(row) {
                    const idx = this.rows.findIndex(r => r.id === row.id);
                    let sum = 0;
                    for (let i = idx + 1; i < this.rows.length; i++) {
                        if (this.rows[i].depth <= row.depth) break;
                        if (this.rows[i].item_type !== 'group') sum += Number(this.rows[i].charge_total) || 0;
                    }
                    return sum;
                },
                groupSubtotalDisplay(row) { return this.money(this.groupSubtotal(row)); },

                money(minor) {
                    return (minor / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                fmtQty(q) { return Number(q).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 }); },

                statusClass(label) {
                    const map = {
                        'Reserved': 's-badge-blue',
                        'Booked Out': 's-badge-amber',
                        'Prepared': 's-badge-violet',
                        'Returned': 's-badge-emerald',
                    };
                    return map[label] || 's-badge-zinc';
                },

                get syncLabel() {
                    return { idle: 'Local', cached: 'Cached', syncing: 'Syncing…', synced: 'Synced' }[this.syncState];
                },

                // ================================================================
                //  inline click-to-edit  (instant local recompute + queue)
                // ================================================================
                beginEdit(id, field, ev) {
                    const row = this.rows.find(r => r.id === id);
                    if (!row) return;
                    if (row.item_type === 'group' && field !== 'name') return;

                    const cell = ev ? ev.currentTarget : null;
                    const isText = field === 'name';
                    const current = field === 'unit_price' ? row.unit_price_display
                        : field === 'discount_percent' ? (row.discount_percent || '')
                        : row[field];

                    const input = document.createElement('input');
                    // price gets a wider input that matches its display cell width so
                    // the column doesn't reflow when a large value is typed/committed.
                    const widthClass = isText ? ' lf-edit-text' : (field === 'unit_price' ? ' lf-edit-price' : '');
                    input.className = 'lf-edit-input' + widthClass;
                    input.type = isText ? 'text' : 'text';
                    input.value = current ?? '';

                    const host = cell || (ev ? ev.target : null) ||
                        this.$refs.tbody.querySelector(`tr[data-id="${id}"] .lf-name`);
                    if (!host) return;

                    const prevHTML = host.style.display;
                    host.style.display = 'none';
                    host.parentNode.insertBefore(input, host.nextSibling);
                    input.focus();
                    input.select();

                    const commit = (save) => {
                        if (input._done) return;
                        input._done = true;
                        const val = input.value.trim();
                        input.remove();
                        host.style.display = prevHTML;
                        if (save) this.applyField(id, field, val);
                    };
                    input.addEventListener('blur', () => commit(true));
                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') { e.preventDefault(); commit(true); }
                        else if (e.key === 'Escape') { e.preventDefault(); commit(false); }
                        else if (e.key === 'Tab') {
                            // keyboard tab between editable cells in the same row:
                            //   name(line item) → quantity → days → price → disc
                            // shift+tab walks the chain backwards.
                            e.preventDefault();
                            commit(true);
                            const target = this.tabTarget(id, field, e.shiftKey);
                            if (target) this.focusField(id, target);
                        }
                    });
                },

                // The ordered chain of editable fields for a row. `name` is the entry
                // point for non-product line items (services/accessories) — tabbing
                // out of the name jumps straight into the qty field.
                editChain: ['name', 'quantity', 'days', 'unit_price', 'discount_percent'],

                // resolve the next/previous editable field for Tab navigation
                tabTarget(id, field, backwards) {
                    const row = this.rows.find(r => r.id === id);
                    if (!row || row.item_type === 'group') return null;

                    // name → quantity only applies to text/line items (services,
                    // accessories) — a PRODUCT's name does not tab into qty.
                    if (field === 'name' && !backwards && row.item_type === 'product') return null;
                    if (field === 'quantity' && backwards && row.item_type === 'product') return null;

                    const chain = this.editChain;
                    let i = chain.indexOf(field);
                    if (i === -1) return null;
                    i += backwards ? -1 : 1;
                    if (i < 0 || i >= chain.length) return null; // stop at the ends
                    return chain[i];
                },

                // open the inline editor for a given field of a row, locating the host
                // cell by its data-field marker (name uses the .lf-name span).
                focusField(id, field) {
                    const tr = this.$refs.tbody.querySelector(`tr[data-id="${id}"]`);
                    if (!tr) return;
                    const host = field === 'name'
                        ? tr.querySelector('.lf-name')
                        : tr.querySelector(`.lf-cell[data-field="${field}"]`);
                    if (!host) return;
                    // defer one tick so the just-committed input is fully removed first
                    this.$nextTick(() => this.beginEdit(id, field, { currentTarget: host, target: host }));
                },

                // apply a field change to the local store immediately, recompute,
                // persist to cache, enqueue the server write.
                applyField(id, field, value) {
                    const row = this.rows.find(r => r.id === id);
                    if (!row) return;

                    if (field === 'unit_price') {
                        row.unit_price = Math.round((parseFloat(value) || 0) * 100);
                        row.unit_price_display = this.money(row.unit_price);
                    } else if (field === 'quantity') {
                        row.quantity = String(Math.round((parseFloat(value) || 0) * 100) / 100);
                    } else if (field === 'days') {
                        row.days = Math.max(0, parseInt(value || '0', 10));
                    } else if (field === 'discount_percent') {
                        row.discount_percent = (value === '' ) ? null : String(Math.round((parseFloat(value) || 0) * 100) / 100);
                    } else {
                        row[field] = value;
                    }

                    // recompute charge total locally (mirrors the service formula)
                    if (['unit_price', 'quantity', 'days', 'discount_percent'].includes(field)) {
                        const disc = row.discount_percent ? parseFloat(row.discount_percent) : 0;
                        const gross = (parseFloat(row.quantity) || 0) * (row.days || 0) * (row.unit_price || 0);
                        row.charge_total = Math.round(gross * (1 - disc / 100));
                        row.charge_total_display = this.money(row.charge_total);
                    }

                    this.afterLocalMutation();
                    this.enqueue({ kind: 'field', id, field, value });
                },

                // ================================================================
                //  add / remove / clone  (optimistic local, deferred server)
                // ================================================================
                addGroup() {
                    const tmpId = this.tempId();
                    this.rows.push(this.blankRow(tmpId, 'group', 1, 'New group'));
                    this.afterLocalMutation();
                    this.enqueue({ kind: 'addGroup', tmpId });
                },

                addItem(parentId) {
                    const tmpId = this.tempId();
                    let depth = 1, insertAt = this.rows.length, parentPathRow = null;

                    if (parentId != null) {
                        const pIdx = this.rows.findIndex(r => r.id === parentId);
                        if (pIdx !== -1) {
                            const parent = this.rows[pIdx];
                            parentPathRow = parent;
                            depth = parent.depth + 1;
                            // insert after the parent's whole subtree
                            insertAt = pIdx + 1;
                            while (insertAt < this.rows.length && this.rows[insertAt].depth > parent.depth) insertAt++;
                        }
                    }

                    const row = this.blankRow(tmpId, 'product', depth, 'New item');
                    row.type_label = 'Rental';
                    row.status_label = 'Reserved';
                    this.rows.splice(insertAt, 0, row);
                    this.afterLocalMutation();
                    this.enqueue({ kind: 'addItem', tmpId, parentId });
                },

                cloneNode(id) {
                    const idx = this.rows.findIndex(r => r.id === id);
                    if (idx === -1) return;
                    const src = this.rows[idx];
                    // collect subtree
                    const subtree = [src];
                    for (let i = idx + 1; i < this.rows.length; i++) {
                        if (this.rows[i].depth <= src.depth) break;
                        subtree.push(this.rows[i]);
                    }
                    // append clones at the end as a fresh top-level-ish branch.
                    // Each clone gets its OWN fresh temp id (never `tempId() - n`,
                    // which could overlap another mutation's id range and produce a
                    // duplicate x-for key → blank table).
                    const clones = subtree.map((r) => {
                        const c = { ...r, id: this.tempId() };
                        if (r.id === src.id) c.name = src.name + ' (copy)';
                        return c;
                    });
                    // find end of source subtree for a tidy local placement (after it)
                    let insertAt = idx + subtree.length;
                    this.rows.splice(insertAt, 0, ...clones);
                    this.afterLocalMutation();
                    this.enqueue({ kind: 'clone', id });
                },

                deleteNode(id) {
                    const idx = this.rows.findIndex(r => r.id === id);
                    if (idx === -1) return;
                    const target = this.rows[idx];
                    let end = idx + 1;
                    while (end < this.rows.length && this.rows[end].depth > target.depth) end++;
                    this.rows.splice(idx, end - idx); // cascade
                    this.afterLocalMutation();
                    this.enqueue({ kind: 'delete', id });
                },

                toggleCollapse(id) {
                    const row = this.rows.find(r => r.id === id);
                    if (!row) return;
                    row.is_collapsed = !row.is_collapsed;
                    this.saveCache(); // collapse is local-only UI state
                },

                blankRow(id, type, depth, name) {
                    return {
                        id, item_type: type, depth,
                        name, quantity: '1', days: 1,
                        unit_price: 0, unit_price_display: '0.00',
                        discount_percent: null, charge_total: 0, charge_total_display: '0.00',
                        type_label: type === 'group' ? null : 'Rental',
                        status_label: type === 'group' ? null : 'Reserved',
                        is_collapsed: false, has_children: false,
                    };
                },
                // Strictly-decreasing unique temp id. The old Date.now()+random scheme
                // could collide (two adds in the same millisecond, or clone's id-n math
                // overlapping a later add) → two rows shared an id → Alpine's keyed
                // x-for (`:key="row.id"`) hit a DUPLICATE KEY and tore down the WHOLE
                // <tbody>, which is exactly the "table empties while syncing" symptom
                // (it surfaced when a flush remapped temp ids). A monotonic counter
                // guarantees uniqueness.
                _tempSeq: 0,
                tempId() { return -1 * (Date.now() * 1000 + (++this._tempSeq)); },

                // after any local structural/data mutation
                afterLocalMutation() {
                    this.recomputeFlags(this.rows);
                    this.saveCache();
                },

                // ================================================================
                //  custom POINTER-EVENTS nested DnD (Nestable-style indent)
                // ================================================================
                onHandleDown(ev, id) {
                    if (ev.button != null && ev.button !== 0) return;
                    ev.preventDefault();

                    const idx = this.rows.findIndex(r => r.id === id);
                    if (idx === -1) return;
                    const node = this.rows[idx];

                    // gather the moving block = node + its descendants
                    let end = idx + 1;
                    while (end < this.rows.length && this.rows[end].depth > node.depth) end++;
                    const block = this.rows.slice(idx, end);
                    const blockIds = new Set(block.map(r => r.id));

                    // for accessory-lock: remember the product this accessory hangs off
                    let originalParentId = null;
                    for (let i = idx - 1; i >= 0; i--) {
                        if (this.rows[i].depth === node.depth - 1) { originalParentId = this.rows[i].id; break; }
                        if (this.rows[i].depth < node.depth - 1) break;
                    }

                    this.dragId = id;
                    this._drag = {
                        block, blockIds,
                        startX: ev.clientX,
                        startDepth: node.depth,
                        targetIndex: idx,   // index (in the "rest" array) to drop before
                        targetDepth: node.depth,
                        originalParentId,
                        originalIndex: idx, // where it came from (for snap-back)
                        valid: true,        // does the current target satisfy canPlace()?
                        pointerId: ev.pointerId,
                    };

                    // floating ghost
                    const ghost = this.$refs.ghost;
                    ghost.textContent = (node.item_type === 'group' ? '▦ ' : '') + node.name +
                        (block.length > 1 ? `  (+${block.length - 1})` : '');
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
                    if (!this._drag) return;
                    this.moveGhost(ev.clientX, ev.clientY);

                    const tbody = this.$refs.tbody;
                    const trs = Array.from(tbody.querySelectorAll('tr.lf-row'))
                        .filter(tr => !this._drag.blockIds.has(Number(tr.dataset.id)));

                    // find the row we're hovering over (by Y)
                    let beforeId = null;       // drop BEFORE this rendered row id (null = end)
                    for (const tr of trs) {
                        const rect = tr.getBoundingClientRect();
                        if (ev.clientY < rect.top + rect.height / 2) { beforeId = Number(tr.dataset.id); break; }
                    }

                    // compute the surviving array (rows minus the moving block)
                    const rest = this.rows.filter(r => !this._drag.blockIds.has(r.id));
                    let insertIndex = beforeId == null ? rest.length : rest.findIndex(r => r.id === beforeId);
                    if (insertIndex < 0) insertIndex = rest.length;

                    // desired depth from horizontal cursor travel (Nestable feel)
                    const dx = ev.clientX - this._drag.startX;
                    let depth = this._drag.startDepth + Math.round(dx / INDENT_PX);

                    // clamp depth against neighbours: the row above bounds max depth
                    const above = rest[insertIndex - 1] || null;
                    const below = rest[insertIndex] || null;
                    const maxDepth = above ? above.depth + 1 : 1;       // at most one deeper than the row above
                    const minDepth = below ? below.depth : 1;          // not shallower than the row that follows
                    depth = Math.max(1, Math.min(depth, maxDepth));
                    depth = Math.max(depth, Math.min(minDepth, maxDepth));

                    // ---- NESTING-RULE constraint -------------------------------
                    // Snap the depth to the nearest one that yields a LEGAL parent
                    // for the dragged node. An accessory is pinned to its product's
                    // child level; a group/product/service can only sit at root or
                    // directly under a group.
                    const node = this._drag.block[0];
                    depth = this.constrainDepth(node, rest, insertIndex, depth, minDepth, maxDepth);

                    const parent = this.parentAt(rest, insertIndex, depth);
                    const valid = depth !== null && this.canPlace(node, parent, this._drag.originalParentId);

                    this._drag.targetIndex = insertIndex;
                    this._drag.targetDepth = depth;
                    this._drag.valid = valid;

                    if (valid) {
                        this.renderPlaceholder(beforeId, depth, true);
                    } else {
                        // illegal spot: show a no-drop bar (never a valid placeholder)
                        this.renderPlaceholder(beforeId, Math.max(1, depth || minDepth), false);
                    }
                },

                // Given the surviving `rest` array, the index we'd insert BEFORE, and
                // a candidate depth, return the prospective parent node (the nearest
                // preceding row at depth-1) or null for a top-level (depth 1) drop.
                parentAt(rest, insertIndex, depth) {
                    if (depth == null || depth <= 1) return null;
                    for (let i = insertIndex - 1; i >= 0; i--) {
                        if (rest[i].depth === depth - 1) return rest[i];
                        if (rest[i].depth < depth - 1) return null; // gap — no valid parent
                    }
                    return null;
                },

                // Walk the legal depth band [minDepth..maxDepth] and pick the depth
                // CLOSEST to the user's desired one that produces a legal parent for
                // this node type. Returns null if nothing in range is legal (rare).
                constrainDepth(node, rest, insertIndex, desired, minDepth, maxDepth) {
                    const lo = Math.max(1, minDepth);
                    const hi = Math.max(lo, maxDepth);
                    const ok = (d) => this.canPlace(node, this.parentAt(rest, insertIndex, d), this._drag.originalParentId);

                    if (desired >= lo && desired <= hi && ok(desired)) return desired;

                    // search outward from the desired depth for the nearest legal one
                    for (let r = 1; r <= (hi - lo); r++) {
                        const down = desired - r, up = desired + r;
                        if (down >= lo && ok(down)) return down;
                        if (up <= hi && ok(up)) return up;
                    }
                    return null; // no legal depth at this position
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
                        if (ref) tbody.insertBefore(ph, ref); else tbody.appendChild(ph);
                    }
                    this._placeholderEl = ph;
                },
                removePlaceholder() {
                    if (this._placeholderEl) { this._placeholderEl.remove(); this._placeholderEl = null; }
                },

                onDragUp(ev) {
                    window.removeEventListener('pointermove', this._onMove);
                    this.removePlaceholder();

                    const d = this._drag;
                    this.dragId = null;
                    this._drag = null;
                    if (!d) return;

                    // INVALID DROP → snap back: leave the store, cache and server
                    // untouched. recomputeFlags re-paints the rows in their original
                    // place (Alpine re-renders from the unchanged array).
                    if (!d.valid || d.targetDepth == null) {
                        this.recomputeFlags(this.rows);
                        return;
                    }

                    // re-validate the resolved drop against the live tree (belt &
                    // braces — the rules must hold for the FINAL drop, not just the
                    // last pointermove placeholder).
                    const rest = this.rows.filter(r => !d.blockIds.has(r.id));
                    const at = Math.max(0, Math.min(d.targetIndex, rest.length));
                    const parent = this.parentAt(rest, at, d.targetDepth);
                    if (!this.canPlace(d.block[0], parent, d.originalParentId)) {
                        this.recomputeFlags(this.rows); // snap back
                        return;
                    }

                    // rebuild the array: remove block, splice it back at targetIndex,
                    // shifting the whole block's depth so the root sits at targetDepth.
                    // The block carries its entire subtree; only the root's depth moves
                    // (delta), descendants keep their relative offsets, so the internal
                    // parent/child relationships stay intact and still satisfy the rules.
                    const delta = d.targetDepth - d.startDepth;
                    const moved = d.block.map(r => ({ ...r, depth: Math.max(1, r.depth + delta) }));

                    rest.splice(at, 0, ...moved);

                    // depth-clamp pass: no row may be >1 deeper than the row above
                    // (mirrors the server's persistTree clamp so local == server).
                    for (let i = 0; i < rest.length; i++) {
                        const prevDepth = i === 0 ? 0 : rest[i - 1].depth;
                        rest[i].depth = Math.max(1, Math.min(rest[i].depth, prevDepth + 1));
                    }

                    this.rows = this.recomputeFlags(rest);
                    this.saveCache();
                    this.enqueue({ kind: 'persistTree' });
                },

                // ================================================================
                //  BACKGROUND SYNC QUEUE
                //  - debounced (250ms) + flushed on requestIdleCallback
                //  - field/persist mutations are COLLAPSED to the latest state
                // ================================================================
                enqueue(mutation) {
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

                async flushNow() {
                    clearTimeout(this._flushTimer);
                    if (this._idleHandle && 'cancelIdleCallback' in window) cancelIdleCallback(this._idleHandle);
                    await this.flush();
                },

                _flushing: false,
                async flush() {
                    if (this._flushing) return;
                    if (!this.queue.length) { this.setSync('synced'); return; }
                    this._flushing = true;
                    this.setSync('syncing');

                    // Drain a snapshot. We collapse runs of the same kind so a flurry
                    // of keystrokes / drags only sends what's needed.
                    const batch = this.queue.splice(0, this.queue.length);
                    try {
                        await this.applyBatchToServer(batch);
                        this.setSync(this.queue.length ? 'syncing' : 'synced');
                    } catch (e) {
                        console.warn('lf flush failed, re-queueing', e);
                        this.queue.unshift(...batch); // keep for retry
                        this.setSync('syncing');
                        setTimeout(() => this.scheduleFlush(), 1500); // backoff retry
                    } finally {
                        this._flushing = false;
                        if (this.queue.length) this.scheduleFlush();
                    }
                },

                // Translate queued local mutations into the server contract.
                // Structural ops (add/clone/delete) need real ids, so we run them
                // first, map temp ids -> real ids, then persist the final order.
                async applyBatchToServer(batch) {
                    let structural = false;

                    for (const m of batch) {
                        if (m.kind === 'addGroup') {
                            const realId = await this.$wire.addGroup();
                            this.remapTempId(m.tmpId, realId);
                            structural = true;
                        } else if (m.kind === 'addItem') {
                            // parentId may itself be a temp id already remapped on rows
                            const realId = await this.$wire.addItem(null, 'New item');
                            this.remapTempId(m.tmpId, realId);
                            structural = true;
                        } else if (m.kind === 'clone') {
                            await this.$wire.cloneNode(m.id);
                            structural = true; // we'll re-sync from a persistTree below
                        } else if (m.kind === 'delete') {
                            if (m.id > 0) await this.$wire.deleteNode(m.id);
                            structural = true;
                        } else if (m.kind === 'field') {
                            if (m.id > 0) {
                                // send the LATEST local value for this field, not the keystroke
                                const row = this.rows.find(r => r.id === m.id);
                                let value = m.value;
                                if (row) {
                                    if (m.field === 'unit_price') value = row.unit_price_display;
                                    else if (m.field === 'discount_percent') value = row.discount_percent ?? '';
                                    else value = row[m.field];
                                }
                                await this.$wire.updateField(m.id, m.field, value);
                            }
                        } else if (m.kind === 'persistTree') {
                            structural = true;
                        }
                    }

                    // Always finish a batch that touched structure with one ordered
                    // persistTree so server paths match the local tree exactly.
                    if (structural) {
                        const realRows = this.rows.filter(r => r.id > 0);
                        if (realRows.length) {
                            await this.$wire.persistTree(realRows.map(r => ({ id: r.id, depth: r.depth })));
                        }
                    }

                    await this.saveCache();
                },

                // a temp (negative) id became a real (positive) server id
                remapTempId(tmpId, realId) {
                    const row = this.rows.find(r => r.id === tmpId);
                    if (row) row.id = realId;
                },

                // ================================================================
                //  reload / cache demos
                // ================================================================
                async hydrateFromServer() {
                    // drop the cache, pull a fresh authoritative snapshot
                    await lfDexie.rows.where('[opp+variant]').equals([this.oppId, this.variant]).delete();
                    const fresh = await this.$wire.serverTree();
                    this.rows = this.normalize(fresh);
                    // fresh authoritative snapshot → re-seed default collapse
                    this.applyDefaultCollapse(this.rows);
                    await this.saveCache();
                    this.queue = [];
                    this.syncFlash('synced', 'reloaded from server');
                },

                hardReload() {
                    // proves the page repaints from IndexedDB before any server call
                    window.location.reload();
                },

                // ----------------------------------------------------------------
                //  sync indicator helpers
                // ----------------------------------------------------------------
                setSync(state) { this.syncState = state; },
                syncFlash(state, _msg) {
                    this.setSync(state);
                    if (state !== 'syncing') {
                        clearTimeout(this._flashT);
                        this._flashT = setTimeout(() => { if (!this.queue.length) this.setSync('synced'); }, 1200);
                    }
                },
            };
        }
    </script>
@endpush
