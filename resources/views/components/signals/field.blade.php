@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'required' => false,
])

<div {{ $attributes->merge(['class' => 's-field' . ($error ? ' has-error' : '')]) }}>
    @if($label)
        <label class="s-field-label">
            {{ $label }}
            @if($required)
                <span class="s-field-label-required">*</span>
            @endif
        </label>
    @endif
    {{ $slot }}
    @if($error)
        <div class="s-field-error">{{ $error }}</div>
    @elseif($help)
        <div class="s-field-help">{{ $help }}</div>
    @endif
</div>
