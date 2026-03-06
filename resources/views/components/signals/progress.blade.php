@props(['label' => null, 'percent' => 0])

<div {{ $attributes }}>
    @if($label || $percent)
        <div class="s-progress-label">
            @if($label)<span class="s-progress-text">{{ $label }}</span>@endif
            <span class="s-progress-pct">{{ $percent }}%</span>
        </div>
    @endif
    <div class="s-progress">
        <div class="s-progress-bar" style="width: {{ $percent }}%;"></div>
    </div>
</div>
