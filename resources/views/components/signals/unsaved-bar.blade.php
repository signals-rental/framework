@props(['message' => 'You have unsaved changes'])

<div {{ $attributes->merge(['class' => 's-unsaved-bar']) }}>
    <span class="s-unsaved-dot"></span>
    <span class="s-unsaved-text">{{ $message }}</span>
    {{ $slot }}
</div>
