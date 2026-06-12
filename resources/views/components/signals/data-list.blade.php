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
                <div class="s-data-list-value">
                    @if(!empty($item['href']))
                        <a href="{{ $item['href'] }}" wire:navigate class="font-semibold hover:underline" style="color: var(--blue); text-decoration: none;">{{ $item['value'] }}</a>
                    @elseif(!empty($item['badge']))
                        <span class="s-badge {{ $item['badge'] }}">{{ $item['value'] }}</span>
                    @elseif(!empty($item['mono']))
                        <span style="font-family: var(--font-mono); font-size: 12px;">{{ $item['value'] }}</span>
                    @else
                        {{ $item['value'] }}
                    @endif
                </div>
            </div>
        @endforeach
    @else
        {{ $slot }}
    @endif
</div>
