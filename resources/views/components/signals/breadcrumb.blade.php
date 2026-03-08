@props([
    'items' => [],
    'separator' => '/',
])

<div {{ $attributes->merge(['class' => 's-breadcrumb']) }}>
    @foreach($items as $i => $item)
        @if($i > 0)
            <span>{{ $separator }}</span>
        @endif
        @if(isset($item['href']) && $i < count($items) - 1)
            <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
        @else
            <span>{{ $item['label'] }}</span>
        @endif
    @endforeach
</div>
