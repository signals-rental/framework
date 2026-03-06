@props(['label' => null, 'percent' => 0])

<div {{ $attributes->merge(['class' => 's-qty-bar']) }}>
    <div class="s-qty-bar-track"><div class="s-qty-bar-fill" style="width: {{ $percent }}%;"></div></div>
    @if($label)<span class="s-qty-bar-label">{{ $label }}</span>@endif
</div>
