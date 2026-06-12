<x-signals.column-toggle />
@php
    // Bulk products are limited to a single stock level, so hide the create
    // button once one exists for them.
    $forProductId = $this->scopes['forProduct'] ?? null;
    $bulkLimitReached = false;
    if ($forProductId) {
        $toolbarProduct = \App\Models\Product::find($forProductId);
        $bulkLimitReached = $toolbarProduct
            && $toolbarProduct->stock_method !== \App\Enums\StockMethod::Serialised
            && \App\Models\StockLevel::where('product_id', $forProductId)->exists();
    }
@endphp
@unless($bulkLimitReached)
    <a href="{{ route('stock-levels.create', ['product_id' => $forProductId ?? '']) }}" wire:navigate class="s-btn s-btn-sm s-btn-accent">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
        New Stock Level
    </a>
@endunless
