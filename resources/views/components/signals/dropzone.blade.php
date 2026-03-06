@props(['text' => 'Drop your file here or click to browse', 'hint' => null])

<div {{ $attributes->merge(['class' => 's-dropzone']) }}>
    @isset($icon)
        <div class="s-dropzone-icon">{{ $icon }}</div>
    @endisset
    <div class="s-dropzone-text">{{ $text }}</div>
    @if($hint)<div class="s-dropzone-hint">{{ $hint }}</div>@endif
</div>
