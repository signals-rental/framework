@props(['title' => null])

<div {{ $attributes->merge(['class' => 's-page-header']) }}>
    <div>
        @isset($breadcrumbs)
            <div class="s-breadcrumb">{{ $breadcrumbs }}</div>
        @endisset
        <div class="flex items-center gap-3 @isset($breadcrumbs) mt-1.5 @endisset">
            @isset($icon)
                <div class="s-page-icon shrink-0">{{ $icon }}</div>
            @endisset
            <div>
                @if($title)
                    <div class="s-page-title">{{ $title }}</div>
                @endif
                @isset($meta)
                    <div class="s-page-meta">{{ $meta }}</div>
                @endisset
            </div>
        </div>
    </div>
    @isset($actions)
        <div class="s-page-actions">{{ $actions }}</div>
    @endisset
</div>
