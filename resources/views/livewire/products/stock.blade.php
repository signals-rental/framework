<?php

use App\Models\Product;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name . ' — Stock');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'columns' => [
                ['key' => 'item_name', 'label' => 'Item Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text'],
                ['key' => 'asset_number', 'label' => 'Asset #', 'sortable' => true],
                ['key' => 'serial_number', 'label' => 'Serial #', 'sortable' => true],
                ['key' => 'barcode', 'label' => 'Barcode', 'sortable' => true],
                ['key' => 'location', 'label' => 'Location', 'sortable' => true],
                ['key' => 'quantity_held', 'label' => 'Held', 'sortable' => true],
                ['key' => 'quantity_allocated', 'label' => 'Allocated', 'sortable' => true],
                ['key' => 'quantity_unavailable', 'label' => 'Unavailable', 'sortable' => true],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                'forProduct' => $this->product->id,
            ],
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => 'Stock'])
    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'stock'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\StockLevel::class"
            :searchable="['item_name', 'asset_number', 'serial_number', 'barcode']"
            :with="['store']"
            :scopes="$scopes"
            default-sort="item_name"
            empty-message="No stock levels found for this product."
            actions-view="livewire.stock-levels.partials.row-actions"
            toolbar-view="livewire.products.partials.stock-toolbar"
            entity-type="stock-levels"
            :key="'product-stock-' . $product->id"
        />
    </div>
</section>
