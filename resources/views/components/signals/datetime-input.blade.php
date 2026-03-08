@props([
    'value' => null,
    'showTime' => true,
    'placeholder' => 'Select date & time',
])

@php
    $dateValue = null;
    if ($value) {
        $dt = $value instanceof \Carbon\Carbon ? $value : \Carbon\Carbon::parse($value);
        $dateValue = $dt->format('Y-m-d H:i');
    }
@endphp

<x-signals.datepicker
    {{ $attributes }}
    :value="$dateValue ? \Carbon\Carbon::parse($dateValue)->format('Y-m-d') : null"
    :show-time="$showTime"
    :placeholder="$placeholder"
/>
