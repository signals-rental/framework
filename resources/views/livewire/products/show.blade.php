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
        $this->product->load(['productGroup', 'taxClass', 'purchaseTaxClass', 'rentalRevenueGroup', 'saleRevenueGroup', 'subRentalCostGroup', 'purchaseCostGroup', 'countryOfOrigin']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $words = preg_split('/\s+/', trim($this->product->name));
        $initials = mb_strtoupper(
            mb_substr($words[0] ?? '', 0, 1) . mb_substr($words[1] ?? '', 0, 1)
        );

        $iconSrc = null;
        $iconFullSrc = null;
        $fileService = app(\App\Services\FileService::class);
        if ($this->product->icon_thumb_url) {
            try {
                $iconSrc = $fileService->signedUrl($this->product->icon_thumb_url);
            } catch (\Throwable) {
                // Fall back to initials
            }
        }
        if ($this->product->icon_url) {
            try {
                $iconFullSrc = $fileService->signedUrl($this->product->icon_url);
            } catch (\Throwable) {
            }
        }

        return [
            'initials' => $initials,
            'iconSrc' => $iconSrc,
            'iconFullSrc' => $iconFullSrc,
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product])

    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'overview'])

    {{-- 3-column layout --}}
    <div class="grid grid-cols-[240px_1fr_280px] gap-6 px-6 py-4 max-lg:grid-cols-[240px_1fr] max-md:grid-cols-1 max-md:px-5 max-sm:px-3">

        {{-- ============================================================ --}}
        {{-- LEFT SIDEBAR --}}
        {{-- ============================================================ --}}
        <div class="space-y-6">
            @if($product->description)
                <div class="rounded-lg border border-[var(--card-border)] bg-white px-3 py-2">
                    <p class="text-xs text-[var(--text-secondary)]">
                        {{ $product->description }}
                    </p>
                </div>
            @endif

            {{-- Quick Stats --}}
            <x-signals.panel title="Quick Stats">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Stock Levels', 'value' => (string) ($product->stock_levels_count ?? 0)],
                    ['label' => 'Accessories', 'value' => (string) ($product->accessories_count ?? 0)],
                    ['label' => 'Replacement Charge', 'value' => $product->replacement_charge ? $product->formatMoneyCost('replacement_charge') : 'Not set'],
                    ['label' => 'Stock Method', 'value' => $product->stock_method?->value ?? 'Not set'],
                ]" />
            </x-signals.panel>

            {{-- Product Group --}}
            <x-signals.panel title="Product Group">
                @if($product->productGroup)
                    <div class="flex items-center gap-2">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-4 text-[var(--text-muted)]"><path d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg>
                        <span class="text-sm font-medium" style="font-family: var(--font-display);">{{ $product->productGroup->name }}</span>
                    </div>
                @else
                    <p class="text-sm text-[var(--text-muted)]">No group assigned.</p>
                @endif
            </x-signals.panel>

            {{-- Tax & Revenue --}}
            <x-signals.panel title="Tax & Revenue">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    ['label' => 'Tax Class', 'value' => $product->taxClass?->name ?? 'Not set'],
                    ['label' => 'Purchase Tax Class', 'value' => $product->purchaseTaxClass?->name ?? 'Not set'],
                    ['label' => 'Rental Revenue Group', 'value' => $product->rentalRevenueGroup?->name ?? 'Not set'],
                    ['label' => 'Sale Revenue Group', 'value' => $product->saleRevenueGroup?->name ?? 'Not set'],
                    ['label' => 'Sub-Rental Cost Group', 'value' => $product->subRentalCostGroup?->name ?? 'Not set'],
                    ['label' => 'Purchase Cost Group', 'value' => $product->purchaseCostGroup?->name ?? 'Not set'],
                ])" />
            </x-signals.panel>
        </div>

        {{-- ============================================================ --}}
        {{-- CENTER CONTENT --}}
        {{-- ============================================================ --}}
        <div class="space-y-6">
            {{-- Product Details --}}
            <x-signals.panel title="Product Details">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    ['label' => 'Product Type', 'value' => $product->product_type->label()],
                    ['label' => 'Stock Method', 'value' => $product->stock_method?->value ?? 'Not set'],
                    ['label' => 'SKU', 'value' => $product->sku ?? 'Not set'],
                    ['label' => 'Barcode', 'value' => $product->barcode ?? 'Not set'],
                    ['label' => 'Weight', 'value' => $product->weight ? $product->weight . ' kg' : 'Not set'],
                    ['label' => 'Buffer %', 'value' => $product->buffer_percent ? $product->buffer_percent . '%' : 'Not set'],
                    ['label' => 'Post-Rent Unavailability', 'value' => $product->post_rent_unavailability ? $product->post_rent_unavailability . ' days' : 'Not set'],
                    ['label' => 'Accessory Only', 'value' => $product->accessory_only ? 'Yes' : 'No'],
                    ['label' => 'Discountable', 'value' => $product->discountable ? 'Yes' : 'No'],
                    ['label' => 'Country of Origin', 'value' => $product->countryOfOrigin?->name ?? 'Not set'],
                ])" />
            </x-signals.panel>

            {{-- Pricing --}}
            <x-signals.panel title="Pricing">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    ['label' => 'Sub-Rental Price', 'value' => $product->sub_rental_price ? $product->formatMoneyCost('sub_rental_price') : 'Not set'],
                    ['label' => 'Purchase Price', 'value' => $product->purchase_price ? $product->formatMoneyCost('purchase_price') : 'Not set'],
                    ['label' => 'Replacement Charge', 'value' => $product->replacement_charge ? $product->formatMoneyCost('replacement_charge') : 'Not set'],
                ])" />
            </x-signals.panel>

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
            {{-- Product Image --}}
            <livewire:components.icon-upload :model="$product" />

            {{-- Key Attributes --}}
            <x-signals.panel title="Key Attributes">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    $product->weight ? ['label' => 'Weight', 'value' => $product->weight . ' kg'] : null,
                    $product->barcode ? ['label' => 'Barcode', 'value' => $product->barcode] : null,
                    $product->sku ? ['label' => 'SKU', 'value' => $product->sku] : null,
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
