@props(['count' => 0])

<div {{ $attributes->merge(['class' => 's-bulk-bar']) }}>
    <span class="s-bulk-count">{{ $count }} selected</span>
    <div class="s-bulk-actions">
        {{ $slot }}
    </div>
</div>
