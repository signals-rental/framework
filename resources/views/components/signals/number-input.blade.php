@props([
    'value' => 0,
    'min' => null,
    'max' => null,
    'step' => 1,
    'name' => '',
])

<div
    {{ $attributes->merge(['class' => 's-number-input']) }}
    x-data="{
        val: {{ (int) $value }},
        min: {{ $min !== null ? (int) $min : 'null' }},
        max: {{ $max !== null ? (int) $max : 'null' }},
        step: {{ (int) $step }},
        dec() { const n = this.val - this.step; if (this.min !== null && n < this.min) return; this.val = n; },
        inc() { const n = this.val + this.step; if (this.max !== null && n > this.max) return; this.val = n; },
        clamp(v) { let n = parseInt(v) || 0; if (this.min !== null && n < this.min) n = this.min; if (this.max !== null && n > this.max) n = this.max; this.val = n; }
    }"
>
    <button class="s-number-input-btn s-number-input-btn-dec" type="button" x-on:click="dec()" x-bind:disabled="min !== null && val <= min">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
    </button>
    <input
        type="number"
        class="s-number-input-field"
        x-model.number="val"
        x-on:change="clamp($event.target.value)"
        name="{{ $name }}"
        @if($min !== null) min="{{ $min }}" @endif
        @if($max !== null) max="{{ $max }}" @endif
        step="{{ $step }}"
    >
    <button class="s-number-input-btn s-number-input-btn-inc" type="button" x-on:click="inc()" x-bind:disabled="max !== null && val >= max">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    </button>
</div>
