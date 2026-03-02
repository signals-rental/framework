<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github-dark.min.css">
    </head>
    <body class="min-h-screen bg-[var(--content-bg)] text-[13px] leading-normal text-[var(--text-primary)] antialiased">

        {{-- Docs header --}}
        <header class="docs-header">
            <a href="{{ route('home') }}" class="docs-header-brand">
                <span class="footer-mark" style="font-size: 10px; padding: 2px 8px;">
                    SIGNALS<span class="footer-mark-accent"></span>
                </span>
            </a>
            <a href="{{ route('docs.index') }}" class="docs-header-title">Documentation</a>
            {{ $search ?? '' }}
            <div class="docs-header-actions">
                <a href="/docs/api" target="_blank" rel="noopener noreferrer" class="docs-header-api-link">API Reference</a>
                <a href="https://github.com/signals-rental/framework" target="_blank" rel="noopener noreferrer" class="header-icon-btn" aria-label="GitHub" title="GitHub">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12Z"/></svg>
                </a>
                <button x-data x-on:click="$flux.dark = ! $flux.dark" class="header-icon-btn" aria-label="Toggle dark mode">
                    <svg x-show="! $flux.dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" /></svg>
                    <svg x-show="$flux.dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>
                </button>
            </div>
        </header>

        {{-- Mobile bar: nav toggle + actions --}}
        <div class="docs-mobile-bar" x-data="{ navOpen: false, searchOpen: false }">
            <div class="docs-mobile-bar-row">
                <button @click="navOpen = !navOpen; searchOpen = false" class="docs-mobile-toggle">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    Navigation
                </button>
                <div class="docs-mobile-actions">
                    <button @click="searchOpen = !searchOpen; navOpen = false" class="header-icon-btn" aria-label="Search">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    </button>
                    <a href="https://github.com/signals-rental/framework" target="_blank" rel="noopener noreferrer" class="header-icon-btn" aria-label="GitHub" title="GitHub">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12Z"/></svg>
                    </a>
                    <button x-data x-on:click="$flux.dark = ! $flux.dark" class="header-icon-btn" aria-label="Toggle dark mode">
                        <svg x-show="! $flux.dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" /></svg>
                        <svg x-show="$flux.dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>
                    </button>
                </div>
            </div>
            <div class="docs-mobile-search" x-show="searchOpen" x-cloak x-transition>
                <div x-data="docsSearch()" @keydown.escape.window="close()">
                    <div class="docs-mobile-search-input-wrap">
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
                    </div>
                    <div class="docs-search-results docs-mobile-search-results" x-show="open && results.length > 0" x-cloak x-transition.opacity>
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
                    <div class="docs-search-results docs-mobile-search-results" x-show="open && query.length > 0 && results.length === 0" x-cloak x-transition.opacity>
                        <div class="docs-search-empty">No results found</div>
                    </div>
                </div>
            </div>
            <nav class="docs-mobile-nav" x-show="navOpen" x-cloak @click.outside="navOpen = false" x-transition>
                {{ $sidebar }}
            </nav>
        </div>

        {{-- Three-column layout --}}
        <div class="docs-layout">
            {{-- Left sidebar --}}
            <aside class="docs-sidebar">
                <nav class="docs-nav">
                    {{ $sidebar }}
                </nav>
            </aside>

            {{-- Main content --}}
            <main class="docs-content">
                {{ $slot }}
            </main>

            {{-- Right sidebar: On This Page + Promo --}}
            <aside class="docs-toc">
                {{ $toc ?? '' }}

                {{-- Promo placeholder --}}
                <div class="docs-promo">
                    <div class="docs-promo-image">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 168 96" fill="none">
                            <rect width="168" height="96" rx="2" fill="var(--table-header-bg)"/>
                            <rect x="24" y="16" width="120" height="8" rx="2" fill="var(--card-border)"/>
                            <rect x="34" y="32" width="100" height="6" rx="2" fill="var(--card-border)" opacity="0.6"/>
                            <rect x="44" y="44" width="80" height="6" rx="2" fill="var(--card-border)" opacity="0.4"/>
                            <circle cx="84" cy="66" r="8" fill="var(--green)" opacity="0.3"/>
                            <path d="M80 66l3 3 5-5" stroke="var(--green)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="54" y="80" width="60" height="6" rx="2" fill="var(--card-border)" opacity="0.3"/>
                        </svg>
                    </div>
                    <div class="docs-promo-badge">Signals Cloud</div>
                    <div class="docs-promo-title">Managed hosting for Signals</div>
                    <p class="docs-promo-text">Deploy in seconds. We handle servers, backups, and updates so you can focus on your rental business.</p>
                    <a href="https://signals.rent" class="docs-promo-cta">Learn more &rarr;</a>
                </div>
            </aside>
        </div>

        {{ $searchData ?? '' }}
        @fluxScripts
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"></script>
        <script>
            hljs.highlightAll();

            document.addEventListener('keydown', (e) => {
                if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                    e.preventDefault();
                    const input = document.querySelector('.docs-search-input');
                    if (input) input.focus();
                }
            });

            document.addEventListener('alpine:init', () => {
                Alpine.data('docsSearch', () => ({
                    query: '',
                    open: false,
                    selected: 0,
                    pages: [],
                    init() {
                        this.pages = JSON.parse(document.getElementById('docs-search-data')?.textContent || '[]');
                    },
                    get results() {
                        if (this.query.length === 0) return [];
                        const q = this.query.toLowerCase();
                        const matched = [];
                        for (const p of this.pages) {
                            const titleMatch = p.title.toLowerCase().includes(q);
                            const sectionMatch = p.section.toLowerCase().includes(q);
                            const contentMatch = p.content ? p.content.toLowerCase().includes(q) : false;
                            if (titleMatch || sectionMatch || contentMatch) {
                                let snippet = '';
                                if (contentMatch && !titleMatch) {
                                    const idx = p.content.toLowerCase().indexOf(q);
                                    const start = Math.max(0, idx - 40);
                                    const end = Math.min(p.content.length, idx + q.length + 60);
                                    snippet = (start > 0 ? '...' : '') + p.content.slice(start, end).trim() + (end < p.content.length ? '...' : '');
                                }
                                matched.push({ ...p, snippet });
                                if (matched.length >= 8) break;
                            }
                        }
                        return matched;
                    },
                    close() { this.open = false; this.selected = 0; },
                    moveDown() { if (this.selected < this.results.length - 1) this.selected++; },
                    moveUp() { if (this.selected > 0) this.selected--; },
                    go() {
                        if (this.results.length > 0) {
                            window.location.href = this.results[this.selected].url;
                        }
                    }
                }));

                Alpine.data('docsToc', () => ({
                    activeId: '',
                    init() {
                        const headings = document.querySelectorAll('.docs-prose h2[id], .docs-prose h3[id]');
                        if (headings.length === 0) return;

                        const observer = new IntersectionObserver(
                            (entries) => {
                                entries.forEach(entry => {
                                    if (entry.isIntersecting) {
                                        this.activeId = entry.target.id;
                                    }
                                });
                            },
                            { rootMargin: '0px 0px -80% 0px', threshold: 0.1 }
                        );
                        headings.forEach(h => observer.observe(h));
                    }
                }));
            });
        </script>
    </body>
</html>
