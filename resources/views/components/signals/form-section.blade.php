@props(['title' => null])

<div {{ $attributes->merge(['class' => 's-form-section']) }}>
    @if($title || isset($headerActions))
        <div class="s-form-section-header">
            <div class="s-form-section-title">
                @isset($icon){{ $icon }}@endisset
                {{ $title }}
            </div>
            {{ $headerActions ?? '' }}
        </div>
    @endif
    <div class="s-form-section-body">
        {{ $slot }}
    </div>
</div>
