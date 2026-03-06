@props(['on' => false])

<div {{ $attributes->merge(['class' => 's-toggle' . ($on ? ' on' : '')]) }}>
    <div class="s-toggle-knob"></div>
</div>
