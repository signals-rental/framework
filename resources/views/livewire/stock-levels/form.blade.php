<?php

use App\Actions\Products\CreateStockLevel;
use App\Actions\Products\UpdateStockLevel;
use App\Data\Products\CreateStockLevelData;
use App\Data\Products\UpdateStockLevelData;
use App\Enums\StockMethod;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Api\RansackFilter;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?int $stockLevelId = null;
    public ?int $productId = null;
    public ?int $storeId = null;
    public string $itemName = '';
    public string $assetNumber = '';
    public string $serialNumber = '';
    public int $quantityHeld = 0;

    /** Product autocomplete */
    public string $productSearch = '';

    /** @var array<int, array{id: int, name: string, stock_method: int|null}> */
    public array $productResults = [];

    public bool $productSelected = false;
    public ?string $selectedProductName = null;
    public ?string $stockMethodLabel = null;
    public bool $isSerialisedStock = false;

    /** Entry mode for serialised stock creation: 'single' | 'bulk' */
    public string $entryMode = 'single';

    /**
     * Bulk-entry rows. status ∈ '' (empty/unchecked), 'valid', 'duplicate'.
     *
     * @var list<array{asset_number: string, serial_number: string, asset_status: string, serial_status: string}>
     */
    public array $bulkRows = [];

    /** Whether a product_id was passed via query param (for cancel redirect) */
    public ?int $sourceProductId = null;

    /** Whether any stores exist */
    public bool $hasStores = true;

    public function mount(?StockLevel $stockLevel = null): void
    {
        // Check if any stores exist
        $storeQuery = Store::query()->orderBy('name');
        $stores = $storeQuery->get(['id']);
        $this->hasStores = $stores->count() > 0;

        // Default to first store if only one exists
        if ($stores->count() === 1) {
            $this->storeId = $stores->first()->id;
        }

        // Pre-populate product from query param
        $productIdParam = request()->query('product_id');
        if (is_string($productIdParam) && is_numeric($productIdParam)) {
            $this->sourceProductId = (int) $productIdParam;
            $this->selectProduct((int) $productIdParam);
        }

        if ($stockLevel?->exists) {
            $this->stockLevelId = $stockLevel->id;
            $this->storeId = $stockLevel->store_id;
            $this->assetNumber = $stockLevel->asset_number ?? '';
            $this->serialNumber = $stockLevel->serial_number ?? '';
            $this->quantityHeld = (int) ($stockLevel->quantity_held ?? 0);

            // Load the product for editing
            if ($stockLevel->product_id) {
                $this->selectProduct($stockLevel->product_id);
            }
        }
    }

    public function updatedProductSearch(): void
    {
        $search = trim($this->productSearch);

        if (mb_strlen($search) < 1) {
            $this->productResults = [];

            return;
        }

        $this->productResults = Product::query()
            ->where('name', 'ilike', '%' . RansackFilter::escapeLike($search) . '%')
            ->where('is_active', true)
            ->limit(15)
            ->get(['id', 'name', 'stock_method'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'stock_method' => $p->stock_method?->value,
            ])
            ->toArray();
    }

    public function selectProduct(int $productId): void
    {
        $product = Product::query()->where('is_active', true)->find($productId, ['id', 'name', 'stock_method']);

        if (! $product) {
            // Also allow selecting inactive products when editing existing stock levels
            $product = Product::query()->find($productId, ['id', 'name', 'stock_method']);
        }

        if (! $product) {
            return;
        }

        $this->productId = $product->id;
        $this->selectedProductName = $product->name;
        $this->itemName = $product->name;
        $this->productSelected = true;
        $this->productSearch = '';
        $this->productResults = [];

        // Determine stock method
        $this->isSerialisedStock = $product->stock_method === StockMethod::Serialised;
        $this->stockMethodLabel = $product->stock_method?->label() ?? 'Bulk';

        if ($this->isSerialisedStock) {
            $this->quantityHeld = 1;
        }
    }

    public function clearProduct(): void
    {
        $this->productId = null;
        $this->selectedProductName = null;
        $this->itemName = '';
        $this->productSelected = false;
        $this->productSearch = '';
        $this->productResults = [];
        $this->stockMethodLabel = null;
        $this->isSerialisedStock = false;
        $this->quantityHeld = 0;
        $this->entryMode = 'single';
        $this->bulkRows = [];
    }

    /**
     * Switch between single and bulk serialised-entry modes.
     */
    public function setEntryMode(string $mode): void
    {
        if (! in_array($mode, ['single', 'bulk'], true)) {
            return;
        }

        $this->entryMode = $mode;

        if ($mode === 'bulk' && $this->bulkRows === []) {
            $this->bulkRows = [$this->emptyBulkRow()];
        }
    }

    /**
     * @return array{asset_number: string, serial_number: string, asset_status: string, serial_status: string}
     */
    private function emptyBulkRow(): array
    {
        return [
            'asset_number' => '',
            'serial_number' => '',
            'asset_status' => '',
            'serial_status' => '',
        ];
    }

    public function addBulkRow(): void
    {
        $this->bulkRows[] = $this->emptyBulkRow();
        $this->dispatch('bulk-row-added');
    }

    public function removeBulkRow(int $index): void
    {
        if (count($this->bulkRows) <= 1) {
            return;
        }

        unset($this->bulkRows[$index]);
        $this->bulkRows = array_values($this->bulkRows);
        $this->revalidateBulkRows();
    }

    /**
     * Livewire hook — re-check every row whenever any bulk field changes
     * (covers cross-row duplicate effects on blur).
     */
    public function updatedBulkRows(): void
    {
        $this->revalidateBulkRows();
    }

    /**
     * Recompute asset_status + serial_status for every row.
     *
     * Empty value → ''. Value already in the DB column OR appearing more than
     * once across the form rows → 'duplicate'. Otherwise → 'valid'.
     */
    public function revalidateBulkRows(): void
    {
        $assets = array_values(array_filter(array_map(
            static fn (array $row): string => trim($row['asset_number']),
            $this->bulkRows,
        ), static fn (string $value): bool => $value !== ''));

        $serials = array_values(array_filter(array_map(
            static fn (array $row): string => trim($row['serial_number']),
            $this->bulkRows,
        ), static fn (string $value): bool => $value !== ''));

        $existingAssets = $assets === []
            ? []
            : StockLevel::query()->whereIn('asset_number', $assets)->pluck('asset_number')->all();

        $existingSerials = $serials === []
            ? []
            : StockLevel::query()->whereIn('serial_number', $serials)->pluck('serial_number')->all();

        $assetCounts = array_count_values($assets);
        $serialCounts = array_count_values($serials);

        foreach ($this->bulkRows as $i => $row) {
            $this->bulkRows[$i]['asset_status'] = $this->statusFor(
                $row['asset_number'],
                $existingAssets,
                $assetCounts,
            );
            $this->bulkRows[$i]['serial_status'] = $this->statusFor(
                $row['serial_number'],
                $existingSerials,
                $serialCounts,
            );
        }
    }

    /**
     * @param  list<string>  $existing
     * @param  array<string, int>  $counts
     */
    private function statusFor(string $value, array $existing, array $counts): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (in_array($value, $existing, true) || ($counts[$value] ?? 0) > 1) {
            return 'duplicate';
        }

        return 'valid';
    }

    /**
     * Classify a bulk row by how many of its two fields are filled.
     *
     * @param  array{asset_number: string, serial_number: string, asset_status: string, serial_status: string}  $row
     * @return 'empty'|'partial'|'complete'
     */
    private function rowState(array $row): string
    {
        $hasAsset = trim($row['asset_number']) !== '';
        $hasSerial = trim($row['serial_number']) !== '';

        if (! $hasAsset && ! $hasSerial) {
            return 'empty';
        }

        if ($hasAsset && $hasSerial) {
            return 'complete';
        }

        return 'partial';
    }

    /**
     * Count of fully-completed rows (both fields filled). Drives the submit label.
     */
    public function completeRowCount(): int
    {
        return count(array_filter(
            $this->bulkRows,
            fn (array $row): bool => $this->rowState($row) === 'complete',
        ));
    }

    /**
     * Whether the bulk form is in a submittable state.
     *
     * Empty rows (e.g. the trailing row left after pressing Enter) are ignored.
     * The form is submittable when there is at least one complete row, no
     * partial rows remain, and every complete row is unique (valid/valid).
     */
    public function bulkCanSubmit(): bool
    {
        if ($this->entryMode !== 'bulk' || $this->bulkRows === []) {
            return false;
        }

        $hasComplete = false;

        foreach ($this->bulkRows as $row) {
            $state = $this->rowState($row);

            if ($state === 'empty') {
                continue;
            }

            if ($state === 'partial') {
                return false;
            }

            // Complete row — must be unique on both fields.
            if ($row['asset_status'] !== 'valid' || $row['serial_status'] !== 'valid') {
                return false;
            }

            $hasComplete = true;
        }

        return $hasComplete;
    }

    public function save(): void
    {
        // Bulk serialised entry (create-only).
        if ($this->isSerialisedStock && $this->entryMode === 'bulk' && ! $this->stockLevelId) {
            $this->saveBulk();

            return;
        }

        $this->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'storeId' => ['required', 'integer', 'exists:stores,id'],
            'assetNumber' => ['nullable', 'string', 'max:255', \Illuminate\Validation\Rule::unique('stock_levels', 'asset_number')->ignore($this->stockLevelId)],
            'serialNumber' => ['nullable', 'string', 'max:255', \Illuminate\Validation\Rule::unique('stock_levels', 'serial_number')->ignore($this->stockLevelId)],
            'quantityHeld' => ['required', 'integer', 'min:0'],
        ], [
            'assetNumber.unique' => 'This asset / barcode number is already in use.',
            'serialNumber.unique' => 'This serial number is already in use.',
        ]);

        // Bulk products may only ever have a single stock level.
        if (! $this->stockLevelId) {
            $product = \App\Models\Product::find($this->productId);
            $isBulk = $product && $product->stock_method !== \App\Enums\StockMethod::Serialised;

            if ($isBulk && StockLevel::where('product_id', $this->productId)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'productId' => 'Bulk products can only have a single stock level.',
                ]);
            }
        }

        $payload = [
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'item_name' => $this->itemName ?: null,
            'asset_number' => $this->assetNumber ?: null,
            'serial_number' => $this->serialNumber ?: null,
            'barcode' => $this->assetNumber ?: null,
            'quantity_held' => $this->quantityHeld,
        ];

        if ($this->stockLevelId) {
            $stockLevel = StockLevel::findOrFail($this->stockLevelId);
            $result = (new UpdateStockLevel)($stockLevel, UpdateStockLevelData::from($payload));
        } else {
            $result = (new CreateStockLevel)(CreateStockLevelData::from($payload));
        }

        $this->redirect(route('stock-levels.show', $result->id), navigate: true);
    }

    /**
     * Create one serialised StockLevel per bulk row inside a single transaction.
     */
    private function saveBulk(): void
    {
        $this->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'storeId' => ['required', 'integer', 'exists:stores,id'],
        ]);

        // Defensive: never trust the client — re-run uniqueness server-side.
        $this->revalidateBulkRows();

        // Empty rows (e.g. the trailing Enter row) are ignored; create only
        // the complete rows. bulkCanSubmit() guarantees no partial rows remain
        // and every complete row is unique.
        if (! $this->bulkCanSubmit()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bulkRows' => 'Every row must have a unique asset number and serial number before you can create them.',
            ]);
        }

        $completeRows = array_filter(
            $this->bulkRows,
            fn (array $row): bool => $this->rowState($row) === 'complete',
        );

        \Illuminate\Support\Facades\DB::transaction(function () use ($completeRows): void {
            foreach ($completeRows as $row) {
                $asset = trim($row['asset_number']);
                $serial = trim($row['serial_number']);

                (new CreateStockLevel)(CreateStockLevelData::from([
                    'product_id' => $this->productId,
                    'store_id' => $this->storeId,
                    'item_name' => $this->itemName ?: null,
                    'asset_number' => $asset,
                    'serial_number' => $serial,
                    'barcode' => $asset,
                    'quantity_held' => 1,
                ]));
            }
        });

        $this->redirect(route('products.stock', $this->productId), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->stockLevelId !== null,
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

<section class="w-full">
    @if($isEditing)
        <x-signals.page-header title="Edit Stock Level">
            <x-slot:breadcrumbs>
                <a href="{{ route('stock-levels.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Stock Levels</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <a href="{{ route('stock-levels.show', $stockLevelId) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $selectedProductName ?: 'Stock Level' }}</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Edit</span>
            </x-slot:breadcrumbs>
        </x-signals.page-header>
    @else
        <x-signals.page-header title="Create Stock Level">
            <x-slot:breadcrumbs>
                <a href="{{ route('stock-levels.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Stock Levels</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-signals.page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        @if(! $hasStores)
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-6 text-center dark:border-amber-700 dark:bg-amber-950">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-200">You need to create a store before adding stock levels.</p>
                <a href="{{ route('admin.settings.stores') }}" wire:navigate class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-[var(--link)] hover:underline">
                    Go to Store Settings &rarr;
                </a>
            </div>
        @else
            <form wire:submit="save">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">
                    {{-- LEFT COLUMN --}}
                    <div class="space-y-6">
                        <x-signals.form-section title="Assignment">
                            <div class="space-y-3">
                                {{-- Product Autocomplete --}}
                                <div>
                                    <flux:label>Product <span class="text-red-500">*</span></flux:label>

                                    @if($productSelected)
                                        <div class="mt-1 flex items-center gap-2">
                                            <div class="flex min-h-10 flex-1 items-center gap-2 rounded-lg border border-[var(--card-border)] bg-[var(--card-bg)] px-3 py-2 text-sm text-[var(--text-primary)]">
                                                <span>{{ $selectedProductName }}</span>
                                                @if($stockMethodLabel)
                                                    <x-signals.stock-method-badge :serialised="$isSerialisedStock" :label="$stockMethodLabel . ' Stock'" />
                                                @endif
                                            </div>
                                            @unless($isEditing)
                                                <button type="button" wire:click="clearProduct" class="rounded p-1 text-[var(--text-muted)] hover:bg-[var(--s-subtle)] hover:text-[var(--text-primary)]">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                </button>
                                            @endunless
                                        </div>
                                    @else
                                        <div class="relative mt-1">
                                            <flux:input
                                                wire:model.live.debounce.300ms="productSearch"
                                                placeholder="Search for a product..."
                                                autocomplete="off"
                                            />
                                            @if(count($productResults) > 0)
                                                <div class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--card-border)] bg-[var(--card-bg)] shadow-lg">
                                                    @foreach($productResults as $result)
                                                        <button
                                                            type="button"
                                                            wire:key="product-result-{{ $result['id'] }}"
                                                            wire:click="selectProduct({{ $result['id'] }})"
                                                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-[var(--s-subtle)]"
                                                        >
                                                            <span>{{ $result['name'] }}</span>
                                                            @if($result['stock_method'] !== null)
                                                                <x-signals.stock-method-badge :serialised="$result['stock_method'] === 2" />
                                                            @endif
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @elseif(mb_strlen(trim($productSearch)) >= 1)
                                                <div class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--card-border)] bg-[var(--card-bg)] p-3 text-center text-sm text-[var(--text-muted)] shadow-lg">
                                                    No products found
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @error('productId')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <flux:select wire:model="storeId" label="Store" required>
                                    <option value="">Select a store...</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </x-signals.form-section>

                        {{-- Single / Bulk switcher (serialised create only) --}}
                        @if($isSerialisedStock && ! $isEditing)
                            <div class="flex flex-wrap items-center gap-1">
                                <button type="button" wire:click="setEntryMode('single')"
                                        class="s-chip {{ $entryMode === 'single' ? 'on' : '' }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                                    Single
                                </button>
                                <button type="button" wire:click="setEntryMode('bulk')"
                                        class="s-chip {{ $entryMode === 'bulk' ? 'on' : '' }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                    Bulk
                                </button>
                            </div>
                        @endif

                        @if($isSerialisedStock && $entryMode === 'bulk' && ! $isEditing)
                            <x-signals.form-section title="Serialised Items">
                                <div class="space-y-3"
                                    x-data
                                    x-on:bulk-row-added.window="$nextTick(() => { const inputs = $root.querySelectorAll('[data-bulk-asset]'); inputs[inputs.length - 1]?.focus(); })"
                                >
                                    @php($bulkGridCols = count($bulkRows) > 1 ? 'grid-cols-[1fr_1fr_2rem]' : 'grid-cols-2')
                                    <div class="grid {{ $bulkGridCols }} gap-4 text-sm font-medium text-[var(--text-primary)] max-sm:hidden">
                                        <span>Asset Number / Barcode</span>
                                        <span>Serial Number</span>
                                        @if(count($bulkRows) > 1)<span></span>@endif
                                    </div>

                                    @foreach($bulkRows as $i => $row)
                                        <div wire:key="bulk-row-{{ $i }}" class="grid {{ $bulkGridCols }} items-center gap-4 max-sm:grid-cols-1">
                                            {{-- Asset Number --}}
                                            <flux:input
                                                wire:model.live.debounce.400ms="bulkRows.{{ $i }}.asset_number"
                                                data-bulk-asset
                                                x-on:keydown.enter.prevent="$wire.addBulkRow()"
                                                aria-label="Asset Number / Barcode"
                                            >
                                                @if($row['asset_status'] === 'valid')
                                                    <x-slot name="iconTrailing">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                    </x-slot>
                                                @elseif($row['asset_status'] === 'duplicate')
                                                    <x-slot name="iconTrailing">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    </x-slot>
                                                @endif
                                            </flux:input>

                                            {{-- Serial Number --}}
                                            <flux:input
                                                wire:model.live.debounce.400ms="bulkRows.{{ $i }}.serial_number"
                                                x-on:keydown.enter.prevent="$wire.addBulkRow()"
                                                aria-label="Serial Number"
                                            >
                                                @if($row['serial_status'] === 'valid')
                                                    <x-slot name="iconTrailing">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                    </x-slot>
                                                @elseif($row['serial_status'] === 'duplicate')
                                                    <x-slot name="iconTrailing">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    </x-slot>
                                                @endif
                                            </flux:input>

                                            {{-- Remove --}}
                                            @if(count($bulkRows) > 1)
                                                <div class="flex justify-center">
                                                    <button type="button" wire:click="removeBulkRow({{ $i }})"
                                                            class="rounded p-1 text-[var(--text-muted)] hover:bg-[var(--s-subtle)] hover:text-[var(--red)]">
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    <div class="flex items-center gap-3 pt-1">
                                        <button type="button" wire:click="addBulkRow" class="s-btn s-btn-sm">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
                                            Add row
                                        </button>
                                        <span class="text-xs text-[var(--text-muted)]">Press Enter to add a row</span>
                                    </div>

                                    @error('bulkRows')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </x-signals.form-section>
                        @else
                            <x-signals.form-section title="Identification">
                                <div class="space-y-3">
                                    <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                        <flux:input wire:model="assetNumber" label="Asset Number / Barcode" />
                                        <flux:input wire:model="serialNumber" label="Serial Number" />
                                    </div>
                                </div>
                            </x-signals.form-section>
                        @endif

                        <div class="flex items-center gap-4 pt-2">
                            @if($isSerialisedStock && $entryMode === 'bulk' && ! $isEditing)
                                @php($completeRows = $this->completeRowCount())
                                <flux:button variant="primary" type="submit" :disabled="! $this->bulkCanSubmit() || ! $hasStores">
                                    {{ $completeRows > 0 ? 'Create ' . $completeRows . ' Stock ' . \Illuminate\Support\Str::plural('Level', $completeRows) : 'Create Stock Levels' }}
                                </flux:button>
                            @else
                                <flux:button variant="primary" type="submit" :disabled="! $hasStores">{{ $isEditing ? 'Save Changes' : 'Create Stock Level' }}</flux:button>
                            @endif
                            <flux:button
                                variant="ghost"
                                :href="$isEditing
                                    ? route('stock-levels.show', $stockLevelId)
                                    : ($sourceProductId ? route('products.show', $sourceProductId) : route('stock-levels.index'))"
                                wire:navigate
                            >Cancel</flux:button>
                        </div>
                    </div>

                    {{-- RIGHT COLUMN --}}
                    <div class="space-y-6" style="position: sticky; top: 24px;">
                        @unless($isSerialisedStock && $entryMode === 'bulk' && ! $isEditing)
                        <x-signals.form-section title="Stock">
                            <div class="space-y-3">
                                @if($isSerialisedStock)
                                    <div>
                                        <flux:input wire:model.number="quantityHeld" label="Quantity Held" type="number" min="1" max="1" value="1" required disabled />
                                        <p class="mt-1 text-xs text-[var(--text-muted)]">Serialised stock is always quantity 1.</p>
                                    </div>
                                @else
                                    <flux:input wire:model.number="quantityHeld" label="Quantity Held" type="number" min="0" required />
                                @endif
                            </div>
                        </x-signals.form-section>
                        @endunless
                    </div>
                </div>
            </form>
        @endif
    </div>
</section>
