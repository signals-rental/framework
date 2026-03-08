@props([
    'items' => [],
    'layout' => 'horizontal',
])

@php
    $layoutClass = match($layout) {
        'vertical' => 's-data-list-vertical',
        'grid' => 's-data-list-grid',
        default => 's-data-list-horizontal',
    };
@endphp

<div {{ $attributes->merge(['class' => "s-data-list {$layoutClass}"]) }}>
    @if(count($items) > 0)
        @foreach($items as $item)
            <div class="s-data-list-item">
                <div class="s-data-list-label">{{ $item['label'] }}</div>
                <div class="s-data-list-value">{{ $item['value'] }}</div>
            </div>
        @endforeach
    @else
        {{ $slot }}
    @endif
</div>
