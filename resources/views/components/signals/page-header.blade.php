@props(['title' => null])

<div {{ $attributes->merge(['class' => 's-page-header']) }}>
    <div>
        @isset($breadcrumbs)
            <div class="s-breadcrumb">{{ $breadcrumbs }}</div>
        @endisset
        @if($title)
            <div class="s-page-title">{{ $title }}</div>
        @endif
        @isset($meta)
            <div class="s-page-meta">{{ $meta }}</div>
        @endisset
    </div>
    @isset($actions)
        <div class="s-page-actions">{{ $actions }}</div>
    @endisset
</div>
