@php
    $typeBadgeClass = match($item->product_type) {
        \App\Enums\ProductType::Rental => 's-badge-blue',
        \App\Enums\ProductType::Sale => 's-badge-green',
        \App\Enums\ProductType::Service => 's-badge-amber',
        \App\Enums\ProductType::LossAndDamage => 's-badge-red',
        default => 's-badge-zinc',
    };
@endphp
<span class="s-badge {{ $typeBadgeClass }}">{{ $item->product_type->label() }}</span>
