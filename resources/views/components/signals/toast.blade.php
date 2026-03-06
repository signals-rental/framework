@props(['message' => null])

<div {{ $attributes->merge(['class' => 's-toast']) }}>
    @isset($icon)
        <span class="s-toast-icon">{{ $icon }}</span>
    @endisset
    {{ $message ?? $slot }}
</div>
