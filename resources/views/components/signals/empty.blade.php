@props(['icon' => null, 'title' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 's-empty']) }}>
    @if($icon)<div class="s-empty-icon">{{ $icon }}</div>@endif
    @if($title)<div class="s-empty-title">{{ $title }}</div>@endif
    @if($description)<div class="s-empty-desc">{{ $description }}</div>@endif
</div>
