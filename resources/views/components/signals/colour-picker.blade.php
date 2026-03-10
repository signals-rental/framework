@props(['color' => '#059669', 'interactive' => false, 'name' => null])

<div {{ $attributes->merge(['class' => 's-colour-picker']) }}>
    @if($interactive)
        <input
            type="color"
            @if($name) wire:model.live="{{ $name }}" @endif
            value="{{ $color }}"
            class="s-colour-input"
        />
        <flux:input
            @if($name) wire:model.live="{{ $name }}" @endif
            :value="$color"
            placeholder="#000000"
            maxlength="7"
            class="font-mono"
        />
    @else
        <div class="s-colour-swatch" style="background: {{ $color }};"></div>
        <span class="s-colour-value">{{ $color }}</span>
    @endif
</div>
