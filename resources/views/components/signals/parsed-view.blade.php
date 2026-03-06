@props(['title' => null])

<div {{ $attributes->merge(['class' => 's-parsed-view']) }}>
    @if($title)
        <div class="s-parsed-view-header">
            <span class="s-parsed-view-title">{{ $title }}</span>
        </div>
    @endif
    {{ $slot }}
</div>
