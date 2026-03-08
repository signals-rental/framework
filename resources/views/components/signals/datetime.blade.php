@props([
    'value' => null,
    'format' => 'd M Y',
    'timeFormat' => 'H:i',
    'showTime' => true,
    'showIcon' => false,
    'relative' => false,
    'size' => null,
])

@php
    $sizeClass = $size ? "s-datetime-{$size}" : '';
    $dt = $value instanceof \Carbon\Carbon ? $value : ($value ? \Carbon\Carbon::parse($value) : null);
@endphp

@if($dt)
    <span {{ $attributes->merge(['class' => "s-datetime {$sizeClass}"]) }}>
        @if($showIcon)
            <span class="s-datetime-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
        @endif
        <span class="s-datetime-date">{{ $dt->format($format) }}</span>
        @if($showTime)
            <span class="s-datetime-time">{{ $dt->format($timeFormat) }}</span>
        @endif
        @if($relative)
            <span class="s-datetime-relative">{{ $dt->diffForHumans() }}</span>
        @endif
    </span>
@endif
