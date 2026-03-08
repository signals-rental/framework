@props([
    'min' => 0,
    'max' => 100,
    'value' => 50,
    'step' => 1,
    'name' => '',
    'showValue' => true,
])

<div
    {{ $attributes->merge(['class' => 's-range']) }}
    x-data="{ val: {{ (int) $value }} }"
>
    @if($showValue)
        <span class="s-range-value" x-text="val"></span>
    @endif
    <input
        type="range"
        x-model="val"
        min="{{ $min }}"
        max="{{ $max }}"
        step="{{ $step }}"
        name="{{ $name }}"
    >
    <div class="s-range-labels">
        <span>{{ $min }}</span>
        <span>{{ $max }}</span>
    </div>
</div>
