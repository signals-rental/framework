@props(['align' => 'left'])

<div {{ $attributes->merge(['class' => 's-dropdown', 'style' => $align === 'right' ? 'left: auto; right: 0;' : '']) }}>
    {{ $slot }}
</div>
