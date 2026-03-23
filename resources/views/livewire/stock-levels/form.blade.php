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
    }

    public function save(): void
    {
        $this->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'storeId' => ['required', 'integer', 'exists:stores,id'],
            'assetNumber' => ['nullable', 'string', 'max:255'],
            'serialNumber' => ['nullable', 'string', 'max:255'],
            'quantityHeld' => ['required', 'integer', 'min:0'],
        ]);

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
                                            <div class="flex flex-1 items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-muted)] px-3 py-2 text-sm">
                                                <span>{{ $selectedProductName }}</span>
                                                @if($stockMethodLabel)
                                                    <span class="s-badge {{ $isSerialisedStock ? 's-badge-violet' : 's-badge-blue' }}">
                                                        {{ $stockMethodLabel }} Stock
                                                    </span>
                                                @endif
                                            </div>
                                            @unless($isEditing)
                                                <button type="button" wire:click="clearProduct" class="rounded p-1 text-[var(--text-muted)] hover:bg-[var(--bg-muted)] hover:text-[var(--text)]">
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
                                                <div class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg)] shadow-lg">
                                                    @foreach($productResults as $result)
                                                        <button
                                                            type="button"
                                                            wire:key="product-result-{{ $result['id'] }}"
                                                            wire:click="selectProduct({{ $result['id'] }})"
                                                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-[var(--bg-muted)]"
                                                        >
                                                            <span>{{ $result['name'] }}</span>
                                                            @if($result['stock_method'] !== null)
                                                                <span class="text-xs text-[var(--text-muted)]">
                                                                    {{ $result['stock_method'] === 2 ? 'Serialised' : 'Bulk' }}
                                                                </span>
                                                            @endif
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @elseif(mb_strlen(trim($productSearch)) >= 1)
                                                <div class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg)] p-3 text-center text-sm text-[var(--text-muted)] shadow-lg">
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

                        <x-signals.form-section title="Identification">
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                    <flux:input wire:model="assetNumber" label="Asset Number / Barcode" />
                                    <flux:input wire:model="serialNumber" label="Serial Number" />
                                </div>
                            </div>
                        </x-signals.form-section>

                        <div class="flex items-center gap-4 pt-2">
                            <flux:button variant="primary" type="submit" :disabled="! $hasStores">{{ $isEditing ? 'Save Changes' : 'Create Stock Level' }}</flux:button>
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
                    </div>
                </div>
            </form>
        @endif
    </div>
</section>
