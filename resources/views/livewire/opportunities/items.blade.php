<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AssignItemToSection;
use App\Actions\Opportunities\ChangeItemDates;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\CreateOpportunitySection;
use App\Actions\Opportunities\DeleteOpportunitySection;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Actions\Opportunities\RenameOpportunitySection;
use App\Actions\Opportunities\ReorderOpportunityItems;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\SubstituteItem;
use App\Actions\Opportunities\ToggleItemOptional;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AssignItemToSectionData;
use App\Data\Opportunities\ChangeItemDatesData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunitySectionData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Data\Opportunities\RenameOpportunitySectionData;
use App\Data\Opportunities\ReorderOpportunityItemsData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Data\Opportunities\SubstituteItemData;
use App\Data\Opportunities\ToggleItemOptionalData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunitySection;
use App\Models\Product;
use App\Services\AvailabilityService;
use App\Services\Opportunities\ProductSearchService;
use App\Support\Formatter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Volt\Component;

/**
 * Editable line-item editor for an opportunity (M8-3d-ii) — the centrepiece of the
 * opportunity module. Embedded as the Overview tab's main content (the standalone
 * "Line Items" tab/route was removed in the show-page restructure); it nests inside
 * the Show page via `<livewire:opportunities.items :opportunity="…">` and reads as a
 * section, not a second page (the shared header + tabs are owned by the Show page).
 *
 * Every line mutation flows through the SAME event-sourced action classes the API
 * uses (AddOpportunityItem / ChangeItemQuantity / OverrideItemPrice / … ), which
 * authorise `opportunities.edit` internally. After each mutation the opportunity is
 * re-read so live totals (ex-tax model) and per-line availability refresh.
 *
 * Lines are grouped for display: a line with `section_id` sits under its custom
 * {@see OpportunitySection}; otherwise it is auto-grouped by the product's
 * parent-group -> product-group tree. Accessories render as display-only sub-rows
 * (ratio x line qty, zero priced) and are NEVER persisted as opportunity_items.
 *
 * The product picker is a two-tier search: a client-side MiniSearch index built
 * from {@see ProductSearchService::catalogueIndex()} embedded once into the page,
 * plus a debounced server fallback via the {@see searchProducts()} `#[Renderless]`
 * method (the first `#[Renderless]` use in the codebase) backed by the Postgres
 * `pg_trgm` index (degrades to ilike on SQLite).
 */
new class extends Component
{
    public Opportunity $opportunity;

    /**
     * The catalogue payload the client-side MiniSearch index is built from. Embedded
     * once on first render; never re-sent on subsequent Livewire round-trips.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $catalogue = [];

    /** The quick-add bar's destination: 'section:{id}', 'group:{key}', or '' (ungrouped). */
    public string $quickAddDestination = '';

    public bool $editable = false;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
        $this->editable = Gate::allows('opportunities.edit') && ! $opportunity->statusEnum()->isClosed();

        // Embed the catalogue index once so the client MiniSearch tier is instant.
        $this->catalogue = app(ProductSearchService::class)->catalogueIndex($opportunity->store_id);
    }

    /**
     * Server tier of the two-tier picker. Renderless so the typed-into picker never
     * triggers a component re-render — Alpine merges these hits with the local
     * MiniSearch results. Returns the raw DTO array (product + rate + accessories +
     * live availability for newer / fuzzy-matched catalogue rows).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Renderless]
    public function searchProducts(string $query): array
    {
        Gate::authorize('products.read');

        return app(ProductSearchService::class)
            ->search($query, $this->opportunity->store_id, 12)
            ->map(fn ($result) => $result->toArray())
            ->all();
    }

    /**
     * Add a product as a line item. `productId` is the catalogue Product id; the
     * picker passes the chosen quantity and (optionally) a destination so the new
     * line lands in the right section. Re-reads after committing.
     */
    public function addProduct(int $productId, float $quantity = 1, ?string $destination = null): void
    {
        $this->guardEditable();

        $product = Product::query()->find($productId);

        if ($product === null) {
            throw ValidationException::withMessages([
                'product' => 'The selected product could not be found.',
            ]);
        }

        $data = AddOpportunityItemData::from([
            'name' => $product->name,
            'item_id' => $product->id,
            'item_type' => Product::class,
            'quantity' => (string) max(0.01, $quantity),
            'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
        ]);

        $opportunity = $this->opportunity->fresh() ?? $this->opportunity;
        (new AddOpportunityItem)($opportunity, $data);

        // Assign the brand-new line to the chosen destination section (auto-group
        // destinations need no assignment — they group by product implicitly).
        $this->assignNewestToDestination($destination ?? $this->quickAddDestination);

        $this->refreshOpportunity();
        $this->dispatch('item-added');
    }

    /**
     * Quick-add bar entry point: a parsed quantity + a resolved product id from the
     * client (the "6 spiider" parsing + picker selection happen in Alpine).
     */
    public function quickAdd(int $productId, float $quantity = 1): void
    {
        $this->addProduct($productId, $quantity, $this->quickAddDestination);
    }

    public function removeItem(int $itemId): void
    {
        $this->guardEditable();

        $item = $this->findItem($itemId);
        (new RemoveOpportunityItem)($item);

        $this->refreshOpportunity();
    }

    public function updateQuantity(int $itemId, string $quantity): void
    {
        $this->guardEditable();

        $item = $this->findItem($itemId);
        (new ChangeItemQuantity)($item, ChangeItemQuantityData::from(['quantity' => $quantity]));

        $this->refreshOpportunity();
    }

    public function overridePrice(int $itemId, ?string $unitPrice): void
    {
        $this->guardEditable();

        $item = $this->findItem($itemId);
        (new OverrideItemPrice)($item, OverrideItemPriceData::from([
            'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
            'unit_price' => $unitPrice === null || $unitPrice === '' ? null : $unitPrice,
        ]));

        $this->refreshOpportunity();
    }

    public function setDiscount(int $itemId, ?string $discountPercent): void
    {
        $this->guardEditable();

        $item = $this->findItem($itemId);
        (new SetItemDiscount)($item, SetItemDiscountData::from([
            'discount_percent' => $discountPercent === null || $discountPercent === '' ? null : $discountPercent,
        ]));

        $this->refreshOpportunity();
    }

    public function changeDates(int $itemId, ?string $startsAt, ?string $endsAt): void
    {
        $this->guardEditable();

        $item = $this->findItem($itemId);
        (new ChangeItemDates)($item, ChangeItemDatesData::from([
            'starts_at' => $startsAt === '' ? null : $startsAt,
            'ends_at' => $endsAt === '' ? null : $endsAt,
        ]));

        $this->refreshOpportunity();
    }

    public function toggleOptional(int $itemId): void
    {
        $this->guardEditable();

        $item = $this->findItem($itemId);
        (new ToggleItemOptional)($item, ToggleItemOptionalData::from([
            'is_optional' => ! $item->is_optional,
        ]));

        $this->refreshOpportunity();
    }

    public function substituteItem(int $itemId, int $productId): void
    {
        $this->guardEditable();

        $item = $this->findItem($itemId);
        $product = Product::query()->find($productId);

        if ($product === null) {
            throw ValidationException::withMessages([
                'product' => 'The replacement product could not be found.',
            ]);
        }

        (new SubstituteItem)($item, SubstituteItemData::from([
            'item_id' => $product->id,
            'item_type' => Product::class,
            'name' => $product->name,
        ]));

        $this->refreshOpportunity();
    }

    public string $newSectionName = '';

    public function createSection(): void
    {
        $this->guardEditable();

        $name = trim($this->newSectionName);

        if ($name === '') {
            return;
        }

        $opportunity = $this->opportunity->fresh() ?? $this->opportunity;
        $nextOrder = (int) $opportunity->sections()->max('sort_order') + 1;

        (new CreateOpportunitySection)($opportunity, CreateOpportunitySectionData::from([
            'name' => $name,
            'sort_order' => $nextOrder,
        ]));

        $this->newSectionName = '';
        $this->refreshOpportunity();
        $this->dispatch('close-modal', name: 'create-section');
    }

    public function renameSection(int $sectionId, string $name): void
    {
        $this->guardEditable();

        $section = $this->findSection($sectionId);
        (new RenameOpportunitySection)($section, RenameOpportunitySectionData::from(['name' => $name]));

        $this->refreshOpportunity();
    }

    public function deleteSection(int $sectionId): void
    {
        $this->guardEditable();

        $section = $this->findSection($sectionId);
        (new DeleteOpportunitySection)($section);

        $this->refreshOpportunity();
    }

    /**
     * Drag-and-drop handler for `wire:sort`. `groupId` identifies the destination
     * group/section the line landed in. We first (re)assign the line's section (set
     * when dropped into a custom section, clear when dropped into an auto product
     * group) then persist the new order of that destination's lines.
     */
    public function handleSort(int $itemId, int $position, ?string $groupId = null): void
    {
        $this->guardEditable();

        $item = $this->findItem($itemId);
        $targetSectionId = $this->sectionIdFromGroupKey($groupId);

        if ($item->section_id !== $targetSectionId) {
            (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $targetSectionId]));
        }

        // Re-read so the moved line's group membership reflects the assignment,
        // then persist the order of the whole opportunity (sort_order is global).
        $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
        $orderedIds = $this->computeGlobalOrder($opportunity, $itemId, $position, $targetSectionId);

        if (count($orderedIds) > 0) {
            (new ReorderOpportunityItems)($opportunity, ReorderOpportunityItemsData::from(['item_ids' => $orderedIds]));
        }

        $this->refreshOpportunity();
    }

    /**
     * Assign / clear a line's custom section directly (the per-line menu path).
     */
    public function assignToSection(int $itemId, ?int $sectionId): void
    {
        $this->guardEditable();

        $item = $this->findItem($itemId);
        (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $sectionId]));

        $this->refreshOpportunity();
    }

    /**
     * The grouped editor model: an ordered list of groups, each carrying its lines,
     * each line carrying its accessories + availability chip + subtotal. Custom
     * sections come first (in section sort_order), then auto product-groups.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function groups(): array
    {
        $opportunity = $this->opportunity;
        $formatter = app(Formatter::class);

        $opportunity->load([
            'items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'sections',
        ]);

        $availability = $this->availabilityMap();
        $items = $opportunity->items;

        // Build a section_id -> section lookup (only sections that still exist).
        $sections = $opportunity->sections->keyBy('id');

        $groups = [];

        // 1. Custom sections, in their sort order — always shown even when empty so
        //    operators can drag lines into a freshly created section.
        foreach ($opportunity->sections as $section) {
            $groups['section:'.$section->id] = [
                'key' => 'section:'.$section->id,
                'kind' => 'section',
                'section_id' => $section->id,
                'label' => $section->name,
                'lines' => [],
                'subtotal' => 0,
            ];
        }

        // 2. Auto product-group buckets, materialised lazily as lines are placed.
        $autoBuckets = [];

        foreach ($items as $item) {
            $lineRow = $this->buildLineRow($item, $availability, $formatter);

            if ($item->section_id !== null && $sections->has($item->section_id)) {
                $groups['section:'.$item->section_id]['lines'][] = $lineRow;
                $groups['section:'.$item->section_id]['subtotal'] += $item->total;

                continue;
            }

            [$bucketKey, $bucketLabel] = $this->autoGroupFor($item);

            if (! isset($autoBuckets[$bucketKey])) {
                $autoBuckets[$bucketKey] = [
                    'key' => $bucketKey,
                    'kind' => 'auto',
                    'section_id' => null,
                    'label' => $bucketLabel,
                    'lines' => [],
                    'subtotal' => 0,
                ];
            }

            $autoBuckets[$bucketKey]['lines'][] = $lineRow;
            $autoBuckets[$bucketKey]['subtotal'] += $item->total;
        }

        // Append auto buckets after the custom sections, sorted by label.
        uasort($autoBuckets, fn ($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']));

        foreach (array_values($groups) as $group) {
            $group['subtotal_formatted'] = $formatter->money((int) $group['subtotal']);
            $ordered[] = $group;
        }

        foreach (array_values($autoBuckets) as $group) {
            $group['subtotal_formatted'] = $formatter->money((int) $group['subtotal']);
            $ordered[] = $group;
        }

        return $ordered ?? [];
    }

    /**
     * Destination options for the quick-add bar: every custom section plus the auto
     * product groups already present. Value mirrors the group key.
     *
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function destinations(): array
    {
        $options = [['value' => '', 'label' => '— Auto group —']];

        foreach ($this->groups as $group) {
            $options[] = [
                'value' => $group['key'],
                'label' => ($group['kind'] === 'section' ? 'Section · ' : 'Group · ').$group['label'],
            ];
        }

        return $options;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<int, \App\Data\Availability\OpportunityItemAvailabilityData>  $availability
     * @return array<string, mixed>
     */
    private function buildLineRow(OpportunityItem $item, array $availability, Formatter $formatter): array
    {
        $avail = $availability[$item->id] ?? null;

        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'quantity' => $this->formatQuantity($item->quantity),
            'quantity_raw' => (string) $item->quantity,
            'unit_price' => $formatter->money($item->unit_price ?? 0),
            'unit_price_raw' => $item->formatMoneyCost('unit_price'),
            'discount_percent' => $item->discount_percent !== null ? (string) $item->discount_percent : null,
            'total' => $formatter->money($item->total ?? 0),
            'is_optional' => $item->is_optional,
            'section_id' => $item->section_id,
            'starts_at' => optional($item->starts_at)?->toDateString(),
            'ends_at' => optional($item->ends_at)?->toDateString(),
            'charge_period_label' => $item->charge_period->label(),
            'availability' => $avail !== null
                ? ['status' => $this->availabilityChip($avail), 'has_shortage' => $avail->has_shortage]
                : ['status' => null, 'has_shortage' => false],
            'accessories' => $this->accessoriesFor($item),
        ];
    }

    /**
     * Accessory sub-rows for a line (display-only): ratio x line qty, zero priced.
     * Read from the linked Product's `accessories` relation; never persisted.
     *
     * @return array<int, array<string, mixed>>
     */
    private function accessoriesFor(OpportunityItem $item): array
    {
        if ($item->item_id === null || $item->item_type !== Product::class) {
            return [];
        }

        $product = $this->productCache()[$item->item_id] ?? null;

        if ($product === null) {
            return [];
        }

        $lineQty = (float) $item->quantity;

        return $product->accessories
            ->map(function ($accessory) use ($lineQty): array {
                $linked = $accessory->accessoryProduct;

                return [
                    'id' => $accessory->accessory_product_id,
                    'name' => $linked?->name ?? '—',
                    'sku' => $linked?->sku,
                    'ratio' => (string) $accessory->quantity,
                    'quantity' => $this->formatQuantity((float) $accessory->quantity * $lineQty),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Per-line availability context keyed by opportunity_item_id.
     *
     * @return array<int, \App\Data\Availability\OpportunityItemAvailabilityData>
     */
    private function availabilityMap(): array
    {
        return app(AvailabilityService::class)
            ->getOpportunityContext($this->opportunity->id)
            ->keyBy('opportunity_item_id')
            ->all();
    }

    private function availabilityChip(\App\Data\Availability\OpportunityItemAvailabilityData $avail): string
    {
        if ($avail->has_shortage) {
            return 'out';
        }

        return $avail->available_for_item > 0 ? 'available' : 'reserved';
    }

    /**
     * Resolve the auto-group bucket key + label for a product-backed line, using the
     * product's parent-group -> product-group tree. Non-product lines fall into a
     * single "Other" bucket.
     *
     * @return array{0: string, 1: string}
     */
    private function autoGroupFor(OpportunityItem $item): array
    {
        if ($item->item_id === null || $item->item_type !== Product::class) {
            return ['auto:other', __('Other')];
        }

        $product = $this->productCache()[$item->item_id] ?? null;
        $group = $product?->productGroup;

        if ($group === null) {
            return ['auto:ungrouped', __('Ungrouped')];
        }

        $parent = $group->parent;
        $label = $parent !== null ? $parent->name.' · '.$group->name : $group->name;

        return ['auto:'.$group->id, $label];
    }

    /**
     * Cache of the Product rows referenced by the opportunity's lines, with their
     * group + accessories eager-loaded (one query for the whole editor).
     *
     * @return array<int, Product>
     */
    private function productCache(): array
    {
        return once(function (): array {
            $productIds = $this->opportunity->items
                ->where('item_type', Product::class)
                ->pluck('item_id')
                ->filter()
                ->unique()
                ->all();

            if ($productIds === []) {
                return [];
            }

            return Product::query()
                ->whereIn('id', $productIds)
                ->with([
                    'productGroup.parent',
                    'accessories' => fn ($q) => $q->orderBy('sort_order')->with('accessoryProduct'),
                ])
                ->get()
                ->keyBy('id')
                ->all();
        });
    }

    private function sectionIdFromGroupKey(?string $groupKey): ?int
    {
        if ($groupKey !== null && str_starts_with($groupKey, 'section:')) {
            return (int) substr($groupKey, strlen('section:'));
        }

        return null;
    }

    /**
     * Compute the new global sort order after a drag, placing the moved line at the
     * requested position within its destination group while preserving the relative
     * order of every other line.
     *
     * @return array<int, int>
     */
    private function computeGlobalOrder(Opportunity $opportunity, int $movedId, int $position, ?int $targetSectionId): array
    {
        $items = $opportunity->items->sortBy('sort_order')->values();

        // Lines currently in the destination group (excluding the moved one), in order.
        $destination = $items
            ->filter(fn (OpportunityItem $i) => $i->id !== $movedId && $i->section_id === $targetSectionId)
            ->values();

        $position = max(0, min($position, $destination->count()));

        $reorderedDestination = $destination->all();
        array_splice($reorderedDestination, $position, 0, [$movedId]);
        $reorderedDestinationIds = array_map(
            fn ($entry) => $entry instanceof OpportunityItem ? $entry->id : $entry,
            $reorderedDestination,
        );

        // Stitch: walk the original order, emit destination ids at the slot of the
        // first destination member, drop the moved id from its old slot, keep the rest.
        $ordered = [];
        $emittedDestination = false;
        $destinationIdSet = array_flip($reorderedDestinationIds);

        foreach ($items as $item) {
            if ($item->id === $movedId) {
                continue;
            }

            if (isset($destinationIdSet[$item->id])) {
                if (! $emittedDestination) {
                    foreach ($reorderedDestinationIds as $id) {
                        $ordered[] = $id;
                    }
                    $emittedDestination = true;
                }

                continue;
            }

            $ordered[] = $item->id;
        }

        if (! $emittedDestination) {
            foreach ($reorderedDestinationIds as $id) {
                $ordered[] = $id;
            }
        }

        return $ordered;
    }

    private function assignNewestToDestination(?string $destination): void
    {
        $sectionId = $this->sectionIdFromGroupKey($destination);

        if ($sectionId === null) {
            return;
        }

        $newest = OpportunityItem::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->orderByDesc('id')
            ->first();

        if ($newest !== null) {
            (new AssignItemToSection)($newest, AssignItemToSectionData::from(['section_id' => $sectionId]));
        }
    }

    private function findItem(int $itemId): OpportunityItem
    {
        $item = OpportunityItem::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKey($itemId)
            ->first();

        if ($item === null) {
            throw ValidationException::withMessages([
                'item' => 'The line item could not be found.',
            ]);
        }

        return $item;
    }

    private function findSection(int $sectionId): OpportunitySection
    {
        $section = OpportunitySection::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKey($sectionId)
            ->first();

        if ($section === null) {
            throw ValidationException::withMessages([
                'section' => 'The section could not be found.',
            ]);
        }

        return $section;
    }

    private function guardEditable(): void
    {
        Gate::authorize('opportunities.edit');

        if ($this->opportunity->statusEnum()->isClosed()) {
            throw ValidationException::withMessages([
                'opportunity' => 'This opportunity is closed and its line items cannot be edited.',
            ]);
        }
    }

    /**
     * Re-read the opportunity so totals / availability / grouping recompute, and
     * bust the memoised computed properties.
     */
    private function refreshOpportunity(): void
    {
        $this->opportunity = $this->opportunity->fresh() ?? $this->opportunity;

        unset($this->groups, $this->destinations);
    }

    private function formatQuantity(float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }
}; ?>

@php
    $opp = $this->opportunity;
    $grandTotal = app(\App\Support\Formatter::class)->money($opp->charge_total ?? 0);
@endphp

<section
    class="w-full"
    x-data="opportunityItemsEditor(@js($catalogue), @js($editable))"
>
    {{-- Embedded line-item editor: the shared header + tabs are owned by the Show
         page that nests this component, so it renders as a section, not a page. --}}
    <div class="flex-1">
        {{-- Quick-add bar + section toolbar --}}
        @if($editable)
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <div class="flex items-center gap-2 flex-1 min-w-[280px] s-panel" style="padding: 8px 10px;">
                    <span class="text-[var(--accent)] font-bold">+</span>
                    <div class="relative flex-1" x-data="{ q: '' }">
                        <input
                            type="text"
                            class="s-input w-full"
                            placeholder="Quick add — &ldquo;6 spiider&rdquo;, or a SKU, then Enter"
                            autocomplete="off"
                            x-model="q"
                            x-ref="quickAddInput"
                            x-on:input="onPickerInput($refs.quickAddInput, q, true)"
                            x-on:keydown="onPickerKeydown($event, $refs.quickAddInput, true)"
                            x-on:focus="onPickerInput($refs.quickAddInput, q, true)"
                            x-on:blur="closePickerSoon()"
                        >
                        <span class="text-xs text-[var(--text-faint)] font-mono" x-show="quickAddQtyHint" x-text="quickAddQtyHint"></span>
                    </div>
                    <span class="text-xs text-[var(--text-faint)]">into</span>
                    <select class="s-input" style="max-width: 230px;" wire:model="quickAddDestination">
                        @foreach($this->destinations as $dest)
                            <option value="{{ $dest['value'] }}" wire:key="dest-{{ $dest['value'] }}">{{ $dest['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('open-modal', 'create-section')">
                    + Section
                </button>
            </div>
        @endif

        @if(empty($this->groups))
            <x-signals.empty
                title="No line items"
                description="{{ $editable ? 'Use the quick-add bar above or a blank cell to start building this kit list.' : 'This opportunity has no line items yet.' }}"
            />
        @else
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th style="width: 20px;"></th>
                            <th class="text-center" style="width: 70px;">Qty</th>
                            <th>Product</th>
                            <th class="text-center" style="width: 120px;">Status</th>
                            <th class="text-right" style="width: 100px;">Rate</th>
                            <th class="text-right" style="width: 120px;">Total</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    @foreach($this->groups as $group)
                        @php $collapsedKey = $group['key']; @endphp
                        {{-- Group / section header (its own tbody — valid as a direct table child) --}}
                        <tbody wire:key="grp-{{ $group['key'] }}">
                            <tr class="s-table-group-row">
                                <td colspan="5">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 font-semibold"
                                        x-on:click="toggleGroup('{{ $collapsedKey }}')"
                                    >
                                        <span class="text-xs text-[var(--text-faint)]" x-text="isCollapsed('{{ $collapsedKey }}') ? '▸' : '▾'"></span>
                                        @if($group['kind'] === 'section')
                                            <span class="s-badge s-badge-blue">Section</span>
                                        @endif
                                        <span>{{ $group['label'] }}</span>
                                        <span class="text-xs text-[var(--text-faint)]">{{ count($group['lines']) }} {{ \Illuminate\Support\Str::plural('item', count($group['lines'])) }}</span>
                                    </button>
                                </td>
                                <td class="text-right font-mono font-semibold">{{ $group['subtotal_formatted'] }}</td>
                                <td class="text-center">
                                    @if($editable && $group['kind'] === 'section')
                                        <div class="relative inline-block" x-data="rowActionsMenu()">
                                            <button type="button" class="s-btn-icon" x-ref="trigger" x-on:click="toggle()">⋯</button>
                                            {{-- Teleported to <body> so the table-wrap overflow can't clip it; positioned
                                                 fixed against the trigger's rect, right-aligned and above content. --}}
                                            <template x-teleport="body">
                                                <div class="s-dropdown" x-show="open" x-cloak
                                                    x-on:click.outside="open = false"
                                                    x-on:keydown.escape.window="open = false"
                                                    :style="menuStyle"
                                                    style="position: fixed; z-index: 1000; min-width: 180px;">
                                                    <button type="button" class="s-dropdown-item w-full text-left"
                                                        x-on:click="open = false; $dispatch('open-modal', { id: 'rename-section', sectionId: {{ $group['section_id'] }}, name: @js($group['label']) })">
                                                        Rename
                                                    </button>
                                                    <button type="button" class="s-dropdown-item w-full text-left" style="color: var(--red);"
                                                        x-on:click="open = false"
                                                        wire:click="deleteSection({{ $group['section_id'] }})"
                                                        wire:confirm="Delete this section? Its line items move back to auto-grouping.">
                                                        Delete
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        </tbody>

                        {{-- Lines (sortable within the group); collapse via x-show keeps the table valid --}}
                        <tbody
                            x-show="!isCollapsed('{{ $collapsedKey }}')"
                            @if($editable)
                                wire:sort="handleSort"
                                wire:sort:group="opportunity-lines"
                                wire:sort:group-id="{{ $group['key'] }}"
                            @endif
                        >
                            @foreach($group['lines'] as $line)
                                        <tr class="s-table-line" wire:key="line-{{ $line['id'] }}" wire:sort:item="{{ $line['id'] }}">
                                            <td class="text-center">
                                                @if($editable)
                                                    <span wire:sort:handle class="cursor-grab text-[var(--text-faint)] select-none">⠿</span>
                                                @endif
                                            </td>
                                            <td class="text-center" wire:sort:ignore>
                                                @if($editable)
                                                    <input
                                                        type="number" min="0.01" step="0.01"
                                                        class="s-input text-center font-mono"
                                                        style="width: 60px;"
                                                        value="{{ $line['quantity_raw'] }}"
                                                        wire:change="updateQuantity({{ $line['id'] }}, $event.target.value)"
                                                    >
                                                @else
                                                    <span class="font-mono text-[var(--text-muted)]">{{ $line['quantity'] }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-2" wire:sort:ignore>
                                                    <div>
                                                        <div class="font-medium flex items-center gap-2">
                                                            {{ $line['name'] }}
                                                            @if(count($line['accessories']) > 0)
                                                                <button type="button"
                                                                    class="s-chip text-xs"
                                                                    x-on:click="toggleLine({{ $line['id'] }})"
                                                                    title="{{ count($line['accessories']) }} accessories">
                                                                    +{{ count($line['accessories']) }}
                                                                </button>
                                                            @endif
                                                            @if($line['is_optional'])
                                                                <span class="s-badge s-badge-zinc">Optional</span>
                                                            @endif
                                                        </div>
                                                        @if($line['description'])
                                                            <div class="text-xs text-[var(--text-muted)]">{{ $line['description'] }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center" wire:sort:ignore>
                                                @php
                                                    $chip = $line['availability']['status'];
                                                    $chipMap = [
                                                        'available' => ['s-status-green', 'Available'],
                                                        'reserved' => ['s-status-blue', 'Reserved'],
                                                        'out' => ['s-status-red', 'Shortage'],
                                                    ];
                                                @endphp
                                                @if($chip && isset($chipMap[$chip]))
                                                    <span class="s-status {{ $chipMap[$chip][0] }}"><span class="s-status-dot"></span> {{ $chipMap[$chip][1] }}</span>
                                                @else
                                                    <span class="text-[var(--text-faint)]">—</span>
                                                @endif
                                            </td>
                                            <td class="text-right font-mono">{{ $line['unit_price'] }}</td>
                                            <td class="text-right font-mono">{{ $line['total'] }}</td>
                                            <td class="text-center" wire:sort:ignore>
                                                @if($editable)
                                                    <div class="relative inline-block" x-data="rowActionsMenu()">
                                                        <button type="button" class="s-btn-icon" x-ref="trigger" x-on:click="toggle()">⋯</button>
                                                        {{-- Teleported to <body> so the table-wrap overflow can't clip it; positioned
                                                             fixed against the trigger's rect, right-aligned and above content. --}}
                                                        <template x-teleport="body">
                                                            <div class="s-dropdown" x-show="open" x-cloak
                                                                x-on:click.outside="open = false"
                                                                x-on:keydown.escape.window="open = false"
                                                                :style="menuStyle"
                                                                style="position: fixed; z-index: 1000; min-width: 220px; max-height: 320px; overflow-y: auto;">
                                                                <button type="button" class="s-dropdown-item w-full text-left"
                                                                    x-on:click="open = false; $dispatch('open-modal', { id: 'edit-line', line: @js($line) })">
                                                                    Edit price / discount / dates
                                                                </button>
                                                                <button type="button" class="s-dropdown-item w-full text-left" x-on:click="open = false" wire:click="toggleOptional({{ $line['id'] }})">
                                                                    {{ $line['is_optional'] ? 'Mark required' : 'Mark optional' }}
                                                                </button>
                                                                <hr class="s-dropdown-sep">
                                                                @if($line['section_id'] !== null)
                                                                    <button type="button" class="s-dropdown-item w-full text-left" x-on:click="open = false" wire:click="assignToSection({{ $line['id'] }}, null)">
                                                                        Move to auto group
                                                                    </button>
                                                                @endif
                                                                @foreach($this->groups as $g)
                                                                    @if($g['kind'] === 'section' && $g['section_id'] !== $line['section_id'])
                                                                        <button type="button" class="s-dropdown-item w-full text-left"
                                                                            wire:key="assign-{{ $line['id'] }}-{{ $g['section_id'] }}"
                                                                            x-on:click="open = false"
                                                                            wire:click="assignToSection({{ $line['id'] }}, {{ $g['section_id'] }})">
                                                                            Move to &ldquo;{{ $g['label'] }}&rdquo;
                                                                        </button>
                                                                    @endif
                                                                @endforeach
                                                                <hr class="s-dropdown-sep">
                                                                <button type="button" class="s-dropdown-item w-full text-left" style="color: var(--red);"
                                                                    x-on:click="open = false"
                                                                    wire:click="removeItem({{ $line['id'] }})"
                                                                    wire:confirm="Remove this line item?">
                                                                    Remove
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>

                                        {{-- Accessory sub-rows (display only): sibling rows, hidden via x-show --}}
                                        @foreach($line['accessories'] as $accessory)
                                            <tr
                                                class="s-table-accessory"
                                                wire:key="acc-{{ $line['id'] }}-{{ $accessory['id'] }}"
                                                wire:sort:ignore
                                                x-show="isLineExpanded({{ $line['id'] }})"
                                                x-cloak
                                            >
                                                <td></td>
                                                <td class="text-center font-mono text-[var(--text-muted)]">{{ $accessory['quantity'] }}</td>
                                                <td>
                                                    <span class="text-[var(--text-muted)] text-sm pl-6">
                                                        ↳ {{ $accessory['name'] }}
                                                        @if($accessory['sku'])
                                                            <span class="font-mono text-xs text-[var(--text-faint)] ml-2">{{ $accessory['sku'] }} · {{ $accessory['ratio'] }}×</span>
                                                        @endif
                                                    </span>
                                                </td>
                                                <td class="text-center"><span class="s-chip">incl.</span></td>
                                                <td class="text-right font-mono text-[var(--text-faint)]">{{ app(\App\Support\Formatter::class)->money(0) }}</td>
                                                <td class="text-right font-mono text-[var(--text-faint)]">{{ app(\App\Support\Formatter::class)->money(0) }}</td>
                                                <td></td>
                                            </tr>
                                        @endforeach
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </x-signals.table-wrap>

            <div class="flex justify-end mt-4">
                <div class="text-[var(--text-muted)]">
                    Charge total (ex-tax) <b class="font-mono text-lg text-[var(--text)] ml-2">{{ $grandTotal }}</b>
                </div>
            </div>
        @endif
    </div>

    {{-- Shared product-picker dropdown. Teleported to <body> with a z-index above the
         edit-line modal backdrop so the substitute search overlays it; positioned in
         Alpine via positionPicker() using document (scroll-offset) coordinates. --}}
    <template x-teleport="body">
        <div
            x-ref="pickerDropdown"
            x-show="picker.open"
            x-cloak
            class="s-dropdown"
            style="position: absolute; z-index: 120; min-width: 300px; max-height: 340px; overflow-y: auto;"
            x-on:mousedown.prevent
        >
            <template x-for="(hit, i) in picker.results" :key="hit.id">
                <button
                    type="button"
                    class="s-dropdown-item flex items-center gap-2 w-full text-left"
                    :style="i === picker.highlight ? 'background: var(--s-subtle);' : ''"
                    x-on:mousedown.prevent="choosePickerHit(hit)"
                    x-on:mouseenter="picker.highlight = i"
                >
                    <span class="flex-1 truncate" x-text="hit.name"></span>
                    <span class="font-mono text-xs text-[var(--text-faint)]" x-text="hit.sku || ''"></span>
                    <template x-if="hit.isNew">
                        <span class="s-badge s-badge-green text-xs">new</span>
                    </template>
                </button>
            </template>
            <template x-if="picker.results.length === 0 && !picker.loading">
                <div class="px-3 py-3 text-center text-[var(--text-faint)] text-sm">No matches</div>
            </template>
            <div class="px-3 py-2 text-xs text-[var(--text-muted)] flex items-center gap-2" style="border-top: 1px solid var(--card-border);">
                <span x-text="'local ' + picker.localCount"></span>
                <template x-if="picker.loading"><span class="ml-auto" style="color: var(--amber);">searching catalogue…</span></template>
                <template x-if="!picker.loading && picker.serverCount > 0"><span x-text="'· server +' + picker.serverCount"></span></template>
            </div>
        </div>
    </template>

    {{-- Create section modal --}}
    <x-signals.modal name="create-section" title="New section" id="create-section-modal">
        <div>
            <label class="block text-sm font-medium mb-1">Section name</label>
            <input type="text" class="s-input w-full" wire:model="newSectionName"
                wire:keydown.enter.prevent="createSection" placeholder="e.g. Front of House">
            <x-slot:footer>
                <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('close-modal', 'create-section')">Cancel</button>
                <button type="button" class="s-btn s-btn-primary" wire:click="createSection">
                    Create
                </button>
            </x-slot:footer>
        </div>
    </x-signals.modal>

    {{-- Rename section modal --}}
    <div
        x-data="{ open: false, sectionId: null, name: '' }"
        x-on:open-modal.window="if ($event.detail?.id === 'rename-section') { open = true; sectionId = $event.detail.sectionId; name = $event.detail.name; }"
    >
        <template x-teleport="body">
            <div class="s-modal-backdrop" x-show="open" x-cloak x-transition.opacity x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
                <div class="s-modal s-modal-md" x-trap.noscroll="open">
                    <div class="s-modal-header">
                        <span class="s-modal-title">Rename section</span>
                        <button class="s-modal-close" type="button" x-on:click="open = false">×</button>
                    </div>
                    <div class="s-modal-body">
                        <label class="block text-sm font-medium mb-1">Section name</label>
                        <input type="text" class="s-input w-full" x-model="name">
                    </div>
                    <div class="s-modal-footer">
                        <button type="button" class="s-btn s-btn-ghost" x-on:click="open = false">Cancel</button>
                        <button type="button" class="s-btn s-btn-primary"
                            x-on:click="if (name.trim()) { $wire.renameSection(sectionId, name.trim()); open = false; }">Save</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Edit line modal (price override / discount / dates) --}}
    <div
        x-data="{ open: false, line: null, unitPrice: '', discount: '', startsAt: '', endsAt: '' }"
        x-on:open-modal.window="if ($event.detail?.id === 'edit-line') {
            open = true; line = $event.detail.line;
            unitPrice = line.unit_price_raw ?? '';
            discount = line.discount_percent ?? '';
            startsAt = line.starts_at ?? '';
            endsAt = line.ends_at ?? '';
        }"
        x-on:close-edit-line.window="open = false"
    >
        <template x-teleport="body">
            <div class="s-modal-backdrop" x-show="open" x-cloak x-transition.opacity x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
                <div class="s-modal s-modal-md" x-trap.noscroll="open">
                    <div class="s-modal-header">
                        <span class="s-modal-title">Edit line — <span x-text="line?.name"></span></span>
                        <button class="s-modal-close" type="button" x-on:click="open = false">×</button>
                    </div>
                    <div class="s-modal-body space-y-3">
                        {{-- Substitute product: reuses the shared two-tier picker. Picking a hit
                             swaps the line's product via the SubstituteItem event action and
                             closes the modal. --}}
                        <div>
                            <label class="block text-sm font-medium mb-1">Substitute product</label>
                            <input
                                type="text"
                                class="s-input w-full"
                                placeholder="Search the catalogue to swap this product…"
                                autocomplete="off"
                                x-ref="substituteInput"
                                x-on:input="onPickerInput($refs.substituteInput, $event.target.value, false, { mode: 'substitute', itemId: line?.id })"
                                x-on:keydown="onPickerKeydown($event, $refs.substituteInput, false)"
                                x-on:focus="onPickerInput($refs.substituteInput, $event.target.value, false, { mode: 'substitute', itemId: line?.id })"
                                x-on:blur="closePickerSoon()"
                            >
                            <p class="text-xs text-[var(--text-muted)] mt-1">Keeps the quantity, pricing and dates; only the catalogue product changes.</p>
                        </div>
                        <hr class="s-dropdown-sep">
                        <div>
                            <label class="block text-sm font-medium mb-1">Unit price override (leave blank to use the rate engine)</label>
                            <input type="text" class="s-input w-full font-mono" x-model="unitPrice" placeholder="e.g. 125.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Discount %</label>
                            <input type="number" min="0" max="100" step="0.01" class="s-input w-full font-mono" x-model="discount">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1">Starts</label>
                                <input type="date" class="s-input w-full" x-model="startsAt">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Ends</label>
                                <input type="date" class="s-input w-full" x-model="endsAt">
                            </div>
                        </div>
                    </div>
                    <div class="s-modal-footer">
                        <button type="button" class="s-btn s-btn-ghost" x-on:click="open = false">Cancel</button>
                        <button type="button" class="s-btn s-btn-primary"
                            x-on:click="
                                $wire.overridePrice(line.id, unitPrice === '' ? null : unitPrice);
                                $wire.setDiscount(line.id, discount === '' ? null : discount);
                                $wire.changeDates(line.id, startsAt === '' ? null : startsAt, endsAt === '' ? null : endsAt);
                                open = false;
                            ">Save</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</section>

@script
<script>
    Alpine.data('opportunityItemsEditor', (catalogue, editable) => ({
        editable,
        controller: null,
        collapsedGroups: {},
        expandedLines: {},
        quickAddQtyHint: '',
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

        init() {
            // Build the two-tier search controller: instant client MiniSearch +
            // debounced server fallback via the #[Renderless] Livewire method.
            this.controller = window.signals.productSearch.createProductSearch({
                catalogue,
                serverSearch: (term) => this.$wire.searchProducts(term),
                limit: 12,
            });

            // Reposition / hide the picker on scroll + resize.
            window.addEventListener('scroll', () => this.positionPicker(), true);
            window.addEventListener('resize', () => this.positionPicker());
        },

        // ---- collapse state ----
        isCollapsed(key) { return !!this.collapsedGroups[key]; },
        toggleGroup(key) { this.collapsedGroups[key] = !this.collapsedGroups[key]; },
        isLineExpanded(id) { return !!this.expandedLines[id]; },
        toggleLine(id) { this.expandedLines[id] = !this.expandedLines[id]; },

        // ---- picker ----
        onPickerInput(target, raw, isQuickAdd, opts = {}) {
            const parsed = this.controller.parseQuickAdd(raw);
            this.picker.target = target;
            this.picker.isQuickAdd = isQuickAdd;
            this.picker.quantity = parsed.quantity;
            this.picker.query = parsed.term;
            this.picker.mode = opts.mode || 'add';
            this.picker.substituteItemId = opts.itemId ?? null;

            if (isQuickAdd) {
                this.quickAddQtyHint = parsed.quantity > 1 ? parsed.quantity + '×' : '';
            }

            if (parsed.term.trim().length < 2) {
                this.picker.results = [];
                this.picker.open = false;
                return;
            }

            // Tier 1: instant local results.
            const local = this.controller.searchLocal(parsed.term);
            this.applyResults(local);
            this.picker.loading = true;
            this.picker.open = true;
            this.positionPicker();

            // Tier 2: debounced server fallback merged into the local results.
            const mySeq = ++this.picker.seq;
            clearTimeout(this._serverTimer);
            this._serverTimer = setTimeout(async () => {
                let server = [];
                try { server = await this.controller.searchServer(parsed.term); } catch (e) { server = []; }
                if (mySeq !== this.picker.seq) { return; }
                this.applyResults(this.controller.merge(local, server));
                this.picker.loading = false;
            }, 250);
        },

        applyResults(results) {
            this.picker.results = results;
            this.picker.highlight = 0;
            this.picker.localCount = results.filter((r) => r.source !== 'server').length;
            this.picker.serverCount = results.filter((r) => r.source === 'server').length;
        },

        onPickerKeydown(event, target, isQuickAdd) {
            if (!this.picker.open) { return; }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.picker.highlight = (this.picker.highlight + 1) % Math.max(this.picker.results.length, 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.picker.highlight = (this.picker.highlight - 1 + this.picker.results.length) % Math.max(this.picker.results.length, 1);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                const hit = this.picker.results[this.picker.highlight];
                if (hit) { this.choosePickerHit(hit); }
            } else if (event.key === 'Escape' || event.key === 'Tab') {
                this.picker.open = false;
            }
        },

        choosePickerHit(hit) {
            if (this.picker.mode === 'substitute' && this.picker.substituteItemId) {
                // Swap the line's catalogue product, keeping qty/price/dates intact.
                this.$wire.substituteItem(this.picker.substituteItemId, hit.id);
                this.picker.open = false;
                this.picker.results = [];
                if (this.picker.target) { this.picker.target.value = ''; }
                this.picker.mode = 'add';
                this.picker.substituteItemId = null;
                this.$dispatch('close-edit-line');
                return;
            }

            const qty = this.picker.quantity || 1;
            this.$wire.addProduct(hit.id, qty);
            this.picker.open = false;
            this.picker.results = [];
            if (this.picker.isQuickAdd && this.picker.target) {
                this.picker.target.value = '';
                this.quickAddQtyHint = '';
                this.$nextTick(() => this.picker.target.focus());
            }
        },

        positionPicker() {
            if (!this.picker.open || !this.picker.target) { return; }
            const rect = this.picker.target.getBoundingClientRect();
            const dd = this.$refs.pickerDropdown;
            dd.style.left = (rect.left + window.scrollX) + 'px';
            dd.style.top = (rect.bottom + window.scrollY + 3) + 'px';
            dd.style.minWidth = Math.max(rect.width, 300) + 'px';
        },

        closePickerSoon() {
            setTimeout(() => { this.picker.open = false; }, 150);
        },
    }));

    // Row-actions ("⋯") dropdown. Its menu is teleported to <body> so the
    // table-wrap's overflow can never clip it; on open we measure the trigger's
    // viewport rect and position the fixed menu right-aligned beneath it. Scroll
    // / resize closes it (cheaper and simpler than re-anchoring a moving table).
    Alpine.data('rowActionsMenu', () => ({
        open: false,
        menuStyle: '',

        toggle() {
            this.open ? this.close() : this.openMenu();
        },

        openMenu() {
            const rect = this.$refs.trigger.getBoundingClientRect();
            // Right-align the menu's right edge to the trigger's right edge.
            const right = Math.max(8, window.innerWidth - rect.right);
            const top = rect.bottom + 4;
            this.menuStyle = `top: ${top}px; right: ${right}px;`;
            this.open = true;
        },

        close() {
            this.open = false;
        },

        init() {
            this._onViewportChange = () => { if (this.open) this.close(); };
            window.addEventListener('scroll', this._onViewportChange, true);
            window.addEventListener('resize', this._onViewportChange);
        },

        destroy() {
            window.removeEventListener('scroll', this._onViewportChange, true);
            window.removeEventListener('resize', this._onViewportChange);
        },
    }));
</script>
@endscript
