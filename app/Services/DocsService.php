<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

class DocsService
{
    private ?MarkdownConverter $converter = null;

    /**
     * Load and decode the documentation navigation manifest.
     *
     * @return array{sections: array<int, array{title: string, slug: string, pages: array<int, array{title: string, slug: string}>}>}
     */
    public function getNavigation(): array
    {
        if (App::environment('local')) {
            return $this->loadManifest();
        }

        return Cache::remember('docs:navigation', 3600, fn (): array => $this->loadManifest());
    }

    /**
     * Parse a markdown file and return rendered content with metadata.
     *
     * @return array{title: string, description: ?string, html: string, toc: array<int, array{level: int, id: string, text: string}>}|null
     */
    public function getPage(string $section, string $page): ?array
    {
        $filePath = $this->resolveFilePath($section, $page);

        if ($filePath === null) {
            return null;
        }

        $markdown = file_get_contents($filePath);

        if ($markdown === false) {
            return null;
        }

        $result = $this->getConverter()->convert($markdown);

        $frontMatter = [];
        if ($result instanceof RenderedContentWithFrontMatter) {
            $frontMatter = $result->getFrontMatter();
        }

        $manifestTitle = $this->getManifestTitle($section, $page);
        $html = (string) $result;

        return [
            'title' => $frontMatter['title'] ?? $manifestTitle ?? $page,
            'description' => $frontMatter['description'] ?? null,
            'html' => $html,
            'toc' => $this->extractTableOfContents($html),
        ];
    }

    /**
     * Extract h2/h3 headings from rendered HTML for the "On This Page" sidebar.
     *
     * @return array<int, array{level: int, id: string, text: string}>
     */
    public function extractTableOfContents(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $doc = new DOMDocument;
        @$doc->loadHTML(
            '<meta charset="utf-8">'.$html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        $xpath = new DOMXPath($doc);
        $headings = $xpath->query('//h2|//h3');

        if ($headings === false) {
            return [];
        }

        $toc = [];
        foreach ($headings as $heading) {
            if (! $heading instanceof \DOMElement) {
                continue;
            }

            $id = $heading->getAttribute('id');
            if ($id === '') {
                continue;
            }

            $toc[] = [
                'level' => (int) substr($heading->nodeName, 1),
                'id' => $id,
                'text' => $heading->textContent,
            ];
        }

        return $toc;
    }

    /**
     * Build a search index with plain text content for all documentation pages.
     *
     * @return array<int, array{title: string, section: string, url: string, content: string}>
     */
    public function getSearchIndex(): array
    {
        $cacheKey = 'docs:search-index';

        if (App::environment('local')) {
            return $this->buildSearchIndex();
        }

        return Cache::remember($cacheKey, 3600, fn (): array => $this->buildSearchIndex());
    }

    /**
     * Check whether a given section/page combination exists.
     */
    public function pageExists(string $section, string $page): bool
    {
        if (! $this->isInManifest($section, $page)) {
            return false;
        }

        return $this->resolveFilePath($section, $page) !== null;
    }

    /**
     * Resolve the previous and next pages for sequential navigation.
     *
     * @return array{prev: ?array{title: string, section: string, slug: string}, next: ?array{title: string, section: string, slug: string}}
     */
    public function getAdjacentPages(string $section, string $page): array
    {
        $flatPages = $this->flattenNavigation();
        $currentIndex = null;

        foreach ($flatPages as $index => $entry) {
            if ($entry['section'] === $section && $entry['slug'] === $page) {
                $currentIndex = $index;
                break;
            }
        }

        return [
            'prev' => $currentIndex !== null && $currentIndex > 0
                ? $flatPages[$currentIndex - 1]
                : null,
            'next' => $currentIndex !== null && $currentIndex < count($flatPages) - 1
                ? $flatPages[$currentIndex + 1]
                : null,
        ];
    }

    /**
     * Build the CommonMark converter with all required extensions.
     */
    private function getConverter(): MarkdownConverter
    {
        if ($this->converter !== null) {
            return $this->converter;
        }

        $config = [
            'heading_permalink' => [
                'html_class' => 'docs-heading-anchor',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'after',
                'symbol' => '#',
                'min_heading_level' => 2,
                'max_heading_level' => 3,
                'title' => 'Permalink',
                'aria_hidden' => true,
                'apply_id_to_heading' => true,
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);
        $environment->addExtension(new FrontMatterExtension);

        $this->converter = new MarkdownConverter($environment);

        return $this->converter;
    }

    /**
     * Resolve the filesystem path for a docs page, with path traversal protection.
     */
    private function resolveFilePath(string $section, string $page): ?string
    {
        $docsBase = base_path('docs');
        $expectedPath = $docsBase.DIRECTORY_SEPARATOR.$section.DIRECTORY_SEPARATOR.$page.'.md';

        $realPath = realpath($expectedPath);

        if ($realPath === false) {
            return null;
        }

        $realDocsBase = realpath($docsBase);

        if ($realDocsBase === false || ! str_starts_with($realPath, $realDocsBase.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }

    /**
     * Load and decode the documentation.json manifest file.
     *
     * @return array{sections: array<int, array{title: string, slug: string, pages: array<int, array{title: string, slug: string}>}>}
     */
    private function loadManifest(): array
    {
        $path = base_path('docs/documentation.json');

        if (! file_exists($path)) {
            return ['sections' => []];
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return ['sections' => []];
        }

        /** @var array{sections: array<int, array{title: string, slug: string, pages: array<int, array{title: string, slug: string}>}>}|null $decoded */
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return ['sections' => []];
        }

        return $decoded;
    }

    /**
     * Check whether a section/page combination exists in the manifest.
     */
    private function isInManifest(string $section, string $page): bool
    {
        $navigation = $this->getNavigation();

        foreach ($navigation['sections'] as $s) {
            if ($s['slug'] !== $section) {
                continue;
            }

            foreach ($s['pages'] as $p) {
                if ($p['slug'] === $page) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the display title for a page from the manifest.
     */
    private function getManifestTitle(string $section, string $page): ?string
    {
        $navigation = $this->getNavigation();

        foreach ($navigation['sections'] as $s) {
            if ($s['slug'] !== $section) {
                continue;
            }

            foreach ($s['pages'] as $p) {
                if ($p['slug'] === $page) {
                    return $p['title'];
                }
            }
        }

        return null;
    }

    /**
     * Build the search index by rendering all pages and stripping HTML.
     *
     * @return array<int, array{title: string, section: string, url: string, content: string}>
     */
    private function buildSearchIndex(): array
    {
        $navigation = $this->getNavigation();
        $index = [];

        foreach ($navigation['sections'] as $section) {
            foreach ($section['pages'] as $page) {
                $content = $this->getPage($section['slug'], $page['slug']);
                if ($content === null) {
                    continue;
                }

                $plainText = preg_replace('/\s+/', ' ', strip_tags($content['html'])) ?? '';

                $index[] = [
                    'title' => $content['title'],
                    'section' => $section['title'],
                    'url' => route('docs.show', [$section['slug'], $page['slug']]),
                    'content' => trim($plainText),
                ];
            }
        }

        return $index;
    }

    /**
     * Flatten the navigation into a sequential list of all pages.
     *
     * @return array<int, array{title: string, section: string, slug: string}>
     */
    private function flattenNavigation(): array
    {
        $navigation = $this->getNavigation();
        $flat = [];

        foreach ($navigation['sections'] as $section) {
            foreach ($section['pages'] as $page) {
                $flat[] = [
                    'title' => $page['title'],
                    'section' => $section['slug'],
                    'slug' => $page['slug'],
                ];
            }
        }

        return $flat;
    }
}
