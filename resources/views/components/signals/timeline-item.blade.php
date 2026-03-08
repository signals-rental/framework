@props([
    'color' => null,
    'title' => null,
    'meta' => null,
])

@php
    $dotClass = $color ? "s-timeline-dot s-timeline-dot-{$color}" : 's-timeline-dot';
@endphp

<div {{ $attributes->merge(['class' => 's-timeline-item']) }}>
    <div class="s-timeline-line"></div>
    @isset($icon)
        <div class="s-timeline-dot s-timeline-dot-icon {{ $color ? "s-timeline-dot-{$color}" : '' }}">
            {{ $icon }}
        </div>
    @else
        <div class="{{ $dotClass }}"></div>
    @endisset
    <div class="s-timeline-content">
        @if($title)
            <div class="s-timeline-title">{{ $title }}</div>
        @endif
        @if($meta)
            <div class="s-timeline-meta">{{ $meta }}</div>
        @endif
        @if($slot->isNotEmpty())
            <div class="s-timeline-body">{{ $slot }}</div>
        @endif
    </div>
</div>
