@props([
    'text' => '',
    'position' => 'top',
])

<span {{ $attributes->merge(['class' => "s-tip s-tip-{$position}"]) }}>
    {{ $slot }}
    <span class="s-tip-text">{{ $text }}</span>
</span>
