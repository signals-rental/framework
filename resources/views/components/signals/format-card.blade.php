@props(['icon' => null, 'label' => null, 'selected' => false])

<div {{ $attributes->merge(['class' => 's-format-card' . ($selected ? ' selected' : '')]) }}>
    @if($icon)<div class="s-format-card-icon">{{ $icon }}</div>@endif
    @if($label)<div class="s-format-card-label">{{ $label }}</div>@endif
</div>
