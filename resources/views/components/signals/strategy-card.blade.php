@props(['name' => null, 'description' => null, 'selected' => false])

<div {{ $attributes->merge(['class' => 's-strategy-card' . ($selected ? ' selected' : '')]) }}>
    @isset($icon)<div class="s-strategy-icon">{{ $icon }}</div>@endisset
    @if($name)<div class="s-strategy-name">{{ $name }}</div>@endif
    @if($description)<div class="s-strategy-desc">{{ $description }}</div>@endif
</div>
