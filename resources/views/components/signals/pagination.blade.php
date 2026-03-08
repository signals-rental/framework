@props([
    'paginator' => null,
    'perPageOptions' => [10, 25, 50, 100],
])

@if($paginator && $paginator->hasPages())
    <div {{ $attributes->merge(['class' => 's-pagination']) }}>
        {{-- Previous --}}
        <button
            class="s-pagination-btn"
            @if($paginator->onFirstPage()) disabled @endif
            wire:click="previousPage"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        </button>

        {{-- Pages --}}
        @php
            $current = $paginator->currentPage();
            $last = $paginator->lastPage();
            $window = 2;
            $pages = [];

            for ($i = 1; $i <= $last; $i++) {
                if ($i === 1 || $i === $last || abs($i - $current) <= $window) {
                    $pages[] = $i;
                } elseif (end($pages) !== '...') {
                    $pages[] = '...';
                }
            }
        @endphp

        @foreach($pages as $page)
            @if($page === '...')
                <span class="s-pagination-ellipsis">&hellip;</span>
            @else
                <button
                    class="s-pagination-btn {{ $page === $current ? 'active' : '' }}"
                    wire:click="gotoPage({{ $page }})"
                >{{ $page }}</button>
            @endif
        @endforeach

        {{-- Next --}}
        <button
            class="s-pagination-btn"
            @if(!$paginator->hasMorePages()) disabled @endif
            wire:click="nextPage"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </button>

        {{-- Info --}}
        <span class="s-pagination-info">
            {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </span>

        {{-- Per page --}}
        @if(count($perPageOptions) > 1)
            <div class="s-pagination-per-page">
                <select wire:model.live="perPage">
                    @foreach($perPageOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
                <span>per page</span>
            </div>
        @endif
    </div>
@endif
