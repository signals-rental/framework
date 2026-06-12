@php
    // Centralise tab-count loading so every product tab shows correct, consistent
    // counts regardless of which relations the individual page component loaded.
    $product->loadCount(['stockLevels', 'accessories', 'attachments', 'rates', 'activities']);
@endphp
<x-signals.module-tabs
    :tabs="[
        ['name' => 'overview', 'label' => 'Overview', 'route' => route('products.show', $product)],
        ['name' => 'stock', 'label' => 'Stock', 'route' => route('products.stock', $product), 'count' => $product->stock_levels_count ?? 0],
        ['name' => 'accessories', 'label' => 'Accessories', 'route' => route('products.accessories', $product), 'count' => $product->accessories_count ?? 0],
        ...(auth()->user()?->can('rates.view') ? [['name' => 'rates', 'label' => 'Rates', 'route' => route('products.rates', $product), 'count' => $product->rates_count ?? 0]] : []),
        ['name' => 'activities', 'label' => 'Activities', 'route' => route('products.activities', $product), 'count' => $product->activities_count ?? 0],
        ['name' => 'custom-fields', 'label' => 'Custom Fields', 'route' => route('products.custom-fields', $product)],
        ['name' => 'files', 'label' => 'Files', 'route' => route('products.files', $product), 'count' => $product->attachments_count ?? 0],
    ]"
    :active="$activeTab"
/>
