@props(['title' => null])

<div {{ $attributes->merge(['class' => 's-sidebar']) }}>
    @if($title)
        <div class="s-sidebar-header">
            <span class="s-sidebar-title">{{ $title }}</span>
        </div>
    @endif
    {{ $slot }}
</div>
