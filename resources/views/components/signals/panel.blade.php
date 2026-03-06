@props(['title' => null])

<div {{ $attributes->merge(['class' => 's-panel']) }}>
    @if($title || isset($headerActions))
        <div class="s-panel-header">
            @if($title)<span class="s-panel-title">{{ $title }}</span>@endif
            {{ $headerActions ?? '' }}
        </div>
    @endif
    <div class="s-panel-body">
        {{ $slot }}
    </div>
</div>
