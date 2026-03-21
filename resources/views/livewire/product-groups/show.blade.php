<?php

use App\Models\ProductGroup;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ProductGroup $productGroup;

    public function mount(ProductGroup $productGroup): void
    {
        $this->productGroup = $productGroup->loadCount('products');
    }

    public function rendering(View $view): void
    {
        $view->title($this->productGroup->name);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'productColumns' => [
                ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                ['key' => 'product_type', 'label' => 'Type', 'sortable' => true, 'view' => 'livewire.products.partials.column-type'],
                ['key' => 'sku', 'label' => 'SKU', 'sortable' => true, 'view' => 'livewire.products.partials.column-sku'],
                ['key' => 'is_active', 'label' => 'Status', 'view' => 'livewire.products.partials.column-status'],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
            ],
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$productGroup->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('product-groups.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Product Groups</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $productGroup->name }}</span>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <a href="{{ route('product-groups.edit', $productGroup->id) }}" wire:navigate class="s-btn s-btn-sm s-btn-ghost">Edit</a>
        </x-slot:actions>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="grid grid-cols-[280px_1fr] gap-6 max-md:grid-cols-1">
            {{-- Sidebar --}}
            <div class="space-y-6">
                <x-signals.panel title="Details">
                    <x-signals.data-list layout="vertical" :items="array_filter([
                        ['label' => 'Name', 'value' => $productGroup->name],
                        $productGroup->description ? ['label' => 'Description', 'value' => $productGroup->description] : null,
                        ['label' => 'Products', 'value' => (string) ($productGroup->products_count ?? 0)],
                        ['label' => 'Created', 'value' => $productGroup->created_at?->format('d M Y') ?? '—'],
                        ['label' => 'Updated', 'value' => $productGroup->updated_at?->format('d M Y') ?? '—'],
                    ])" />
                </x-signals.panel>
            </div>

            {{-- Products table --}}
            <div>
                <h2 style="font-family: var(--font-display); font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); margin-bottom: 12px;">Products in this Group</h2>
                <livewire:components.data-table
                    :columns="$productColumns"
                    :model="\App\Models\Product::class"
                    :searchable="['name', 'sku']"
                    :scopes="['where' => ['product_group_id', $productGroup->id]]"
                    default-sort="name"
                    empty-message="No products in this group."
                    :key="'group-products-' . $productGroup->id"
                />
            </div>
        </div>
    </div>
</section>
