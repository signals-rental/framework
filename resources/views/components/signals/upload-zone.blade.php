@props(['label' => 'Upload file'])

<div {{ $attributes->merge(['class' => 's-upload-zone']) }}>
    @isset($icon){{ $icon }}@endisset
    <span class="s-upload-label">{{ $label }}</span>
</div>
