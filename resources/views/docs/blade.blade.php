<x-layouts.docs :pageTitle="$title" :pageDescription="$description ?? ''" :currentSection="$sectionTitle">
    {{-- Left sidebar navigation --}}
    <x-slot:sidebar>
        @include('docs.partials.sidebar', ['navigation' => $navigation, 'currentSection' => $currentSection, 'currentPage' => $currentPage])
    </x-slot:sidebar>

    {{-- Search --}}
    <x-slot:search>
        <div class="docs-search" x-data="docsSearch()" @click.outside="close()" @keydown.escape.window="close()">
            <div class="docs-search-input-wrap">
                <svg class="docs-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                <input type="text"
                       class="docs-search-input"
                       placeholder="Search docs..."
                       x-model="query"
                       @focus="open = query.length > 0"
                       @input="open = query.length > 0"
                       @keydown.arrow-down.prevent="moveDown()"
                       @keydown.arrow-up.prevent="moveUp()"
                       @keydown.enter.prevent="go()">
                <kbd class="docs-search-kbd">/</kbd>
            </div>
            <div class="docs-search-results" x-show="open && results.length > 0" x-cloak x-transition.opacity>
                <template x-for="(result, index) in results" :key="result.url">
                    <a :href="result.url"
                       class="docs-search-result"
                       :class="{ 'active': index === selected }"
                       @mouseenter="selected = index">
                        <span class="docs-search-result-title" x-text="result.title"></span>
                        <span class="docs-search-result-section" x-text="result.section"></span>
                        <span class="docs-search-result-snippet" x-show="result.snippet" x-text="result.snippet"></span>
                    </a>
                </template>
            </div>
            <div class="docs-search-results" x-show="open && query.length > 0 && results.length === 0" x-cloak x-transition.opacity>
                <div class="docs-search-empty">No results found</div>
            </div>
        </div>
    </x-slot:search>

    {{-- Main content: rendered from blade view --}}
    @include($bladeView)

    {{-- Previous / Next navigation --}}
    <nav class="docs-pagination">
        @if ($prev)
            <a href="{{ route('docs.show', [$prev['section'], $prev['slug']]) }}" class="docs-pagination-link docs-pagination-prev">
                <span class="docs-pagination-label">&larr; Previous</span>
                <span class="docs-pagination-title">{{ $prev['title'] }}</span>
            </a>
        @else
            <div></div>
        @endif
        @if ($next)
            <a href="{{ route('docs.show', [$next['section'], $next['slug']]) }}" class="docs-pagination-link docs-pagination-next">
                <span class="docs-pagination-label">Next &rarr;</span>
                <span class="docs-pagination-title">{{ $next['title'] }}</span>
            </a>
        @endif
    </nav>

    {{-- Search data for client-side filtering --}}
    <x-slot:searchData>
        <script id="docs-search-data" type="application/json">{!! $searchDataJson !!}</script>
    </x-slot:searchData>

    {{-- Right sidebar: empty for blade pages --}}
    <x-slot:toc></x-slot:toc>
</x-layouts.docs>
