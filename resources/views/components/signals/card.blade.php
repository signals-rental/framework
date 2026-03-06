@props(['title' => null])

<div {{ $attributes->merge(['class' => 's-card']) }}>
    @if($title || isset($headerActions))
        <div class="s-card-header">
            @if($title)<span class="s-card-title">{{ $title }}</span>@endif
            {{ $headerActions ?? '' }}
        </div>
    @endif
    <div class="s-card-body">
        {{ $slot }}
    </div>
</div>
