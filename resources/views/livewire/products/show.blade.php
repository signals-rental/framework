<?php

use App\Enums\StockMethod;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Product $product;

    public float $availableQuantity = 0.0;

    public function mount(Product $product): void
    {
        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments']);
        $this->product->load(['productGroup', 'taxClass', 'purchaseTaxClass', 'rentalRevenueGroup', 'saleRevenueGroup', 'subRentalCostGroup', 'purchaseCostGroup', 'countryOfOrigin']);

        $this->availableQuantity = (float) StockLevel::query()
            ->where('product_id', $this->product->id)
            ->selectRaw('COALESCE(SUM(quantity_held - quantity_allocated - quantity_unavailable), 0) as available')
            ->value('available');
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name);
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product])

    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'overview'])

    @php
        $formatter = app(\App\Support\Formatter::class);
        $isSerialised = $product->stock_method === StockMethod::Serialised;
        $availableDisplay = fmod($availableQuantity, 1.0) === 0.0
            ? number_format($availableQuantity)
            : number_format($availableQuantity, 2);
    @endphp

    {{-- 3-column layout --}}
    <div class="grid grid-cols-[240px_1fr_280px] gap-6 px-6 py-4 max-lg:grid-cols-[240px_1fr] max-md:grid-cols-1 max-md:px-5 max-sm:px-3">

        {{-- ============================================================ --}}
        {{-- LEFT SIDEBAR --}}
        {{-- ============================================================ --}}
        <div class="space-y-6">
            {{-- Quick Stats --}}
            <x-signals.panel title="Quick Stats">
                <x-signals.data-list layout="vertical">
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Available Quantity</div>
                        <div class="s-data-list-value">
                            <x-signals.skeleton-value width="3rem">{{ $availableDisplay }}</x-signals.skeleton-value>
                        </div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Stock Levels</div>
                        <div class="s-data-list-value">
                            <x-signals.skeleton-value width="2rem">{{ $product->stock_levels_count ?? 0 }}</x-signals.skeleton-value>
                        </div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Accessories</div>
                        <div class="s-data-list-value">
                            <x-signals.skeleton-value width="2rem">{{ $product->accessories_count ?? 0 }}</x-signals.skeleton-value>
                        </div>
                    </div>
                </x-signals.data-list>
            </x-signals.panel>

            {{-- Product Group --}}
            <x-signals.panel title="Product Group">
                @if($product->productGroup)
                    <a href="{{ route('products.index', ['filters' => ['product_group_id' => $product->productGroup->id]]) }}" wire:navigate class="flex items-center gap-2 hover:underline" style="color: var(--blue-ink);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-4"><path d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg>
                        <span class="text-sm font-medium" style="font-family: var(--font-display);">{{ $product->productGroup->name }}</span>
                    </a>
                @else
                    <p class="text-sm text-[var(--text-muted)]">No group assigned.</p>
                @endif
            </x-signals.panel>
        </div>

        {{-- ============================================================ --}}
        {{-- CENTER CONTENT --}}
        {{-- ============================================================ --}}
        <div class="space-y-6">
            {{-- Description --}}
            @if($product->description)
                <x-signals.panel title="Description">
                    <p class="text-sm leading-relaxed text-[var(--text-secondary)]">{{ $product->description }}</p>
                </x-signals.panel>
            @endif

            {{-- Product Details --}}
            <x-signals.panel title="Product Details">
                <div class="s-data-list s-data-list-grid">
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Product Type</div>
                        <div class="s-data-list-value">{{ $product->product_type->label() }}</div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Stock Method</div>
                        <div class="s-data-list-value">
                            <x-signals.stock-method-badge :serialised="$isSerialised" />
                        </div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">SKU</div>
                        <div class="s-data-list-value" style="font-family: var(--font-mono); font-size: 12px;">{{ $product->sku ?? 'Not set' }}</div>
                    </div>
                    @unless($isSerialised)
                        <div class="s-data-list-item">
                            <div class="s-data-list-label">Barcode</div>
                            <div class="s-data-list-value">{{ $product->barcode ?? 'Not set' }}</div>
                        </div>
                    @endunless
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Weight</div>
                        <div class="s-data-list-value">{{ $product->weight ? $product->weight . ' kg' : 'Not set' }}</div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Buffer %</div>
                        <div class="s-data-list-value">{{ $product->buffer_percent ? $product->buffer_percent . '%' : 'Not set' }}</div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Post-Rent Unavailability</div>
                        <div class="s-data-list-value">{{ $product->post_rent_unavailability ? $product->post_rent_unavailability . ' days' : 'Not set' }}</div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Accessory Only</div>
                        <div class="s-data-list-value">{{ $product->accessory_only ? 'Yes' : 'No' }}</div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Discountable</div>
                        <div class="s-data-list-value">{{ $product->discountable ? 'Yes' : 'No' }}</div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Country of Origin</div>
                        <div class="s-data-list-value">{{ $product->countryOfOrigin?->name ?? 'Not set' }}</div>
                    </div>
                </div>
            </x-signals.panel>

            {{-- Tax & Revenue + Pricing side by side --}}
            <div class="grid grid-cols-2 gap-6 max-md:grid-cols-1">
                {{-- Tax & Revenue --}}
                <x-signals.panel title="Tax & Revenue">
                    <x-signals.data-list layout="horizontal" :items="[
                        ['label' => 'Tax Class', 'value' => $product->taxClass?->name ?? 'Not set'],
                        ['label' => 'Purchase Tax Class', 'value' => $product->purchaseTaxClass?->name ?? 'Not set'],
                        ['label' => 'Rental Revenue Group', 'value' => $product->rentalRevenueGroup?->name ?? 'Not set'],
                        ['label' => 'Sale Revenue Group', 'value' => $product->saleRevenueGroup?->name ?? 'Not set'],
                        ['label' => 'Sub-Rental Cost Group', 'value' => $product->subRentalCostGroup?->name ?? 'Not set'],
                        ['label' => 'Purchase Cost Group', 'value' => $product->purchaseCostGroup?->name ?? 'Not set'],
                    ]" />
                </x-signals.panel>

                {{-- Pricing --}}
                <x-signals.panel title="Pricing">
                    <x-signals.stat-grid style="grid-template-columns: repeat(3, 1fr);">
                        <x-signals.stat-card
                            color="green"
                            label="Sub-Rental Price"
                            :value="$product->sub_rental_price ? $formatter->money($product->sub_rental_price) : '—'"
                        />
                        <x-signals.stat-card
                            color="blue"
                            label="Purchase Price"
                            :value="$product->purchase_price ? $formatter->money($product->purchase_price) : '—'"
                        />
                        <x-signals.stat-card
                            color="amber"
                            label="Replacement Charge"
                            :value="$product->replacement_charge ? $formatter->money($product->replacement_charge) : '—'"
                        />
                    </x-signals.stat-grid>
                </x-signals.panel>
            </div>

            {{-- Activity Timeline (placeholder) --}}
            <x-signals.panel title="Activity Timeline">
                <div class="py-8 text-center text-sm text-[var(--text-muted)]">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto mb-2 size-8 opacity-30"><path d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    Activity timeline will appear here as product events are logged.
                </div>
            </x-signals.panel>
        </div>

        {{-- ============================================================ --}}
        {{-- RIGHT SIDEBAR --}}
        {{-- ============================================================ --}}
        <div class="space-y-6 max-lg:col-span-full max-lg:grid max-lg:grid-cols-2 max-lg:gap-6 max-md:grid-cols-1">
            {{-- Key Attributes --}}
            <x-signals.panel title="Key Attributes">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    $product->barcode ? ['label' => 'Barcode', 'value' => $product->barcode] : null,
                    ['label' => 'Created', 'value' => $product->created_at?->format('d M Y') ?? '—'],
                    ['label' => 'Updated', 'value' => $product->updated_at?->format('d M Y') ?? '—'],
                ])" />
            </x-signals.panel>

            {{-- Tags --}}
            <x-signals.panel title="Tags">
                @if(!empty($product->tag_list))
                    <div class="flex flex-wrap gap-1">
                        @foreach($product->tag_list as $tag)
                            <span class="s-badge s-badge-blue">{{ $tag }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[var(--text-muted)]">No tags assigned.</p>
                @endif
            </x-signals.panel>
        </div>
    </div>
</section>
