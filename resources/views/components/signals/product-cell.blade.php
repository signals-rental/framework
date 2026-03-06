@props(['name' => null, 'sku' => null])

<div {{ $attributes->merge(['class' => 's-product-cell']) }}>
    @isset($thumb)<div class="s-product-thumb">{{ $thumb }}</div>@endisset
    <div>
        @if($name)<div class="s-product-name">{{ $name }}</div>@endif
        @if($sku)<div class="s-product-sku">{{ $sku }}</div>@endif
    </div>
</div>
