@props(['placeholder' => ''])

<div {{ $attributes->merge(['class' => 's-editor']) }}>
    @isset($toolbar)
        <div class="s-editor-toolbar">{{ $toolbar }}</div>
    @endisset
    <textarea class="s-editor-textarea" placeholder="{{ $placeholder }}">{{ $slot }}</textarea>
</div>
