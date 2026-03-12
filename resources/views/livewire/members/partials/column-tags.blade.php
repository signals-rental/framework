@php $tags = $item->tag_list ?? []; @endphp
@if(count($tags) > 0)
    <div style="display: flex; flex-wrap: wrap; gap: 4px;">
        @foreach(array_slice($tags, 0, 3) as $tag)
            <span class="s-badge s-badge-zinc">{{ $tag }}</span>
        @endforeach
        @if(count($tags) > 3)
            <span class="s-badge s-badge-zinc">+{{ count($tags) - 3 }}</span>
        @endif
    </div>
@endif
