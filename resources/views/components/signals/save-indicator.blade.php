@props(['text' => 'Saved'])

<div {{ $attributes->merge(['class' => 's-save-indicator']) }}>
    <span class="s-save-dot"></span>
    {{ $text }}
</div>
