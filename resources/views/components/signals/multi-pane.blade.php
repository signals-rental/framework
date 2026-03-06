<div {{ $attributes->merge(['class' => 's-multi-pane']) }}>
    @isset($sidebar)
        <div class="s-multi-pane-sidebar">{{ $sidebar }}</div>
    @endisset
    <div class="s-multi-pane-editor">
        {{ $slot }}
    </div>
</div>
