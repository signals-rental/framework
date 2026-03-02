@foreach ($navigation['sections'] as $section)
    <div class="docs-nav-section">
        <div class="docs-nav-group-label">{{ $section['title'] }}</div>
        @foreach ($section['pages'] as $page)
            <a href="{{ route('docs.show', [$section['slug'], $page['slug']]) }}"
               class="docs-nav-item {{ (isset($currentSection, $currentPage) && $currentSection === $section['slug'] && $currentPage === $page['slug']) ? 'active' : '' }}">
                {{ $page['title'] }}
            </a>
        @endforeach
    </div>
@endforeach

<div class="docs-nav-section">
    <div class="docs-nav-group-label">Resources</div>
    <a href="{{ route('docs.changelog') }}"
       class="docs-nav-item {{ request()->routeIs('docs.changelog') ? 'active' : '' }}">
        Changelog
    </a>
</div>
