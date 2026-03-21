<?php

use App\Actions\Products\CreateStockLevel;
use App\Actions\Products\UpdateStockLevel;
use App\Data\Products\CreateStockLevelData;
use App\Data\Products\UpdateStockLevelData;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?int $stockLevelId = null;
    public ?int $productId = null;
    public ?int $storeId = null;
    public string $itemName = '';
    public string $assetNumber = '';
    public string $serialNumber = '';
    public string $barcode = '';
    public string $location = '';
    public int $quantityHeld = 0;

    public function mount(?StockLevel $stockLevel = null): void
    {
        // Pre-populate product from query param
        $productIdParam = request()->query('product_id');
        if (is_string($productIdParam) && is_numeric($productIdParam)) {
            $this->productId = (int) $productIdParam;
        }

        // Default to first store if only one exists
        $stores = Store::query()->orderBy('name')->get(['id']);
        if ($stores->count() === 1) {
            $this->storeId = $stores->first()->id;
        }

        if ($stockLevel?->exists) {
            $this->stockLevelId = $stockLevel->id;
            $this->productId = $stockLevel->product_id;
            $this->storeId = $stockLevel->store_id;
            $this->itemName = $stockLevel->item_name ?? '';
            $this->assetNumber = $stockLevel->asset_number ?? '';
            $this->serialNumber = $stockLevel->serial_number ?? '';
            $this->barcode = $stockLevel->barcode ?? '';
            $this->location = $stockLevel->location ?? '';
            $this->quantityHeld = $stockLevel->quantity_held ?? 0;
        }
    }

    public function save(): void
    {
        $this->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'storeId' => ['required', 'integer', 'exists:stores,id'],
            'itemName' => ['nullable', 'string', 'max:255'],
            'assetNumber' => ['nullable', 'string', 'max:255'],
            'serialNumber' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'quantityHeld' => ['required', 'integer', 'min:0'],
        ]);

        $payload = [
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'item_name' => $this->itemName ?: null,
            'asset_number' => $this->assetNumber ?: null,
            'serial_number' => $this->serialNumber ?: null,
            'barcode' => $this->barcode ?: null,
            'location' => $this->location ?: null,
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
        $isEditing = $this->stockLevelId !== null;

        return [
            'isEditing' => $isEditing,
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
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
                <a href="{{ route('stock-levels.show', $stockLevelId) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $itemName ?: 'Stock Level' }}</a>
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
        <form wire:submit="save">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">
                {{-- LEFT COLUMN --}}
                <div class="space-y-6">
                    <x-signals.form-section title="Assignment">
                        <div class="space-y-3">
                            <flux:select wire:model="productId" label="Product" required>
                                <option value="">Select a product...</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </flux:select>

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
                            <flux:input wire:model="itemName" label="Item Name" />
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:input wire:model="assetNumber" label="Asset Number" />
                                <flux:input wire:model="serialNumber" label="Serial Number" />
                            </div>
                            <flux:input wire:model="barcode" label="Barcode" />
                        </div>
                    </x-signals.form-section>

                    <div class="flex items-center gap-4 pt-2">
                        <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Stock Level' }}</flux:button>
                        <flux:button variant="ghost" href="{{ $isEditing ? route('stock-levels.show', $stockLevelId) : route('stock-levels.index') }}" wire:navigate>Cancel</flux:button>
                    </div>
                </div>

                {{-- RIGHT COLUMN --}}
                <div class="space-y-6" style="position: sticky; top: 24px;">
                    <x-signals.form-section title="Stock">
                        <div class="space-y-3">
                            <flux:input wire:model.number="quantityHeld" label="Quantity Held" type="number" min="0" required />
                            <flux:input wire:model="location" label="Location" />
                        </div>
                    </x-signals.form-section>
                </div>
            </div>
        </form>
    </div>
</section>
