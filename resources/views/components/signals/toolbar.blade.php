<div {{ $attributes->merge(['class' => 's-toolbar']) }}>
    {{ $slot }}
    @isset($right)
        <div class="s-toolbar-right">{{ $right }}</div>
    @endisset
</div>
