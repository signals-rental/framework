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
        $this->product->load(['accessories.accessoryProduct']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name . ' — Accessories');
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => 'Accessories'])
    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'accessories'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        @if($product->accessories->isNotEmpty())
            <div class="s-table-wrap">
                <table class="s-table s-table-compact w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Accessory</th>
                            <th class="text-left">Type</th>
                            <th class="text-right" style="width: 100px;">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($product->accessories as $accessory)
                            <tr wire:key="accessory-{{ $accessory->id }}">
                                <td>
                                    @if($accessory->accessoryProduct)
                                        <a href="{{ route('products.show', $accessory->accessoryProduct) }}" wire:navigate class="s-cell-link">
                                            {{ $accessory->accessoryProduct->name }}
                                        </a>
                                    @else
                                        <span class="text-[var(--text-muted)]">Unknown product</span>
                                    @endif
                                </td>
                                <td>
                                    @if($accessory->accessoryProduct)
                                        @php
                                            $accTypeBadge = match($accessory->accessoryProduct->product_type) {
                                                \App\Enums\ProductType::Rental => 's-badge-blue',
                                                \App\Enums\ProductType::Sale => 's-badge-green',
                                                \App\Enums\ProductType::Service => 's-badge-amber',
                                                \App\Enums\ProductType::LossAndDamage => 's-badge-red',
                                                default => 's-badge-zinc',
                                            };
                                        @endphp
                                        <span class="s-badge {{ $accTypeBadge }}">{{ $accessory->accessoryProduct->product_type->label() }}</span>
                                    @else
                                        <span class="text-[var(--text-muted)]">—</span>
                                    @endif
                                </td>
                                <td class="text-right s-cell-mono">{{ $accessory->quantity ?? 1 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-signals.empty title="No Accessories" description="No accessories have been linked to this product.">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-10 opacity-30"><path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.735-3.684-1.757 1.757a4.5 4.5 0 0 1-6.364 0 4.5 4.5 0 0 1 0-6.364l4.5-4.5a4.5 4.5 0 0 1 6.364 6.364l-1.757 1.757"/></svg>
                </x-slot:icon>
            </x-signals.empty>
        @endif
    </div>
</section>
