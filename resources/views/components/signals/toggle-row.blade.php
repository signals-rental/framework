@props(['label' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 's-toggle-row']) }}>
    <div>
        @if($label)<div class="s-toggle-label">{{ $label }}</div>@endif
        @if($description)<div class="s-toggle-desc">{{ $description }}</div>@endif
    </div>
    {{ $slot }}
</div>
