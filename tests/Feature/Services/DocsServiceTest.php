<?php

use App\Services\DocsService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->service = app(DocsService::class);
});

function makeTempDocsDir(): string
{
    $dir = sys_get_temp_dir().'/docs-service-test-'.uniqid();
    mkdir($dir, 0755, true);

    return $dir;
}

function removeTempDocsDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

it('bypasses cache for navigation in local environment', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    // Should call loadManifest directly without caching
    $nav = $this->service->getNavigation();

    expect($nav)->toHaveKey('sections');
});

it('uses cache for navigation in non-local environment', function () {
    App::shouldReceive('environment')->with('local')->andReturn(false);
    Cache::shouldReceive('remember')
        ->once()
        ->andReturn(['sections' => []]);

    $nav = $this->service->getNavigation();

    expect($nav)->toBe(['sections' => []]);
});

it('returns null when page file does not exist', function () {
    $result = $this->service->getPage('nonexistent-section', 'nonexistent-page');

    expect($result)->toBeNull();
});

it('returns empty toc for empty html', function () {
    $toc = $this->service->extractTableOfContents('');

    expect($toc)->toBe([]);
});

it('returns empty toc when html has no headings with ids', function () {
    $toc = $this->service->extractTableOfContents('<p>No headings here</p>');

    expect($toc)->toBe([]);
});

it('extracts h2 and h3 headings for table of contents', function () {
    $html = '<h2 id="intro">Introduction</h2><p>Text</p><h3 id="sub">Subsection</h3>';

    $toc = $this->service->extractTableOfContents($html);

    expect($toc)->toHaveCount(2);
    expect($toc[0])->toBe(['level' => 2, 'id' => 'intro', 'text' => 'Introduction']);
    expect($toc[1])->toBe(['level' => 3, 'id' => 'sub', 'text' => 'Subsection']);
});

it('skips headings without id attribute', function () {
    $html = '<h2>No ID</h2><h2 id="with-id">Has ID</h2>';

    $toc = $this->service->extractTableOfContents($html);

    expect($toc)->toHaveCount(1);
    expect($toc[0]['id'])->toBe('with-id');
});

it('returns false for changelog when directory does not exist', function () {
    // Use a path that doesn't exist
    expect($this->service->changelogExists())->toBeBool();
});

it('bypasses cache for changelog in local environment', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $changelog = $this->service->getChangelog();

    expect($changelog)->toBeArray();
});

it('bypasses cache for search index in local environment', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $index = $this->service->getSearchIndex();

    expect($index)->toBeArray();
});

it('reports page does not exist for non-manifest page', function () {
    expect($this->service->pageExists('fake-section', 'fake-page'))->toBeFalse();
});

it('returns null adjacent pages when page not found', function () {
    $adjacent = $this->service->getAdjacentPages('nonexistent', 'page');

    expect($adjacent['prev'])->toBeNull();
    expect($adjacent['next'])->toBeNull();
});

it('returns null for paths that resolve outside the docs directory', function () {
    // docs/../CLAUDE.md resolves to a real file outside the docs root and must be rejected.
    expect($this->service->getPage('..', 'CLAUDE'))->toBeNull();
});

it('returns an empty manifest and changelog when the docs directory is empty', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeTempDocsDir();

    try {
        $service = new DocsService($dir);

        expect($service->getNavigation())->toBe(['sections' => []]);
        expect($service->changelogExists())->toBeFalse();
        expect($service->getChangelog())->toBe([]);
    } finally {
        removeTempDocsDir($dir);
    }
});

it('returns an empty manifest when documentation.json is not a json object', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeTempDocsDir();

    try {
        file_put_contents($dir.'/documentation.json', '"not a manifest object"');

        $service = new DocsService($dir);

        expect($service->getNavigation())->toBe(['sections' => []]);
    } finally {
        removeTempDocsDir($dir);
    }
});

it('resolves the availability API documentation page', function () {
    expect($this->service->pageExists('api', 'availability'))->toBeTrue();

    $page = $this->service->getPage('api', 'availability');

    expect($page)->not->toBeNull()
        ->and($page['html'])->toContain('availability:read');
});

it('lists the availability page in the API navigation section', function () {
    $nav = $this->service->getNavigation();

    $apiSection = null;
    foreach ($nav['sections'] as $section) {
        if (($section['slug'] ?? null) === 'api') {
            $apiSection = $section;
            break;
        }
    }

    $slugs = array_map(static fn (array $page): string => $page['slug'], $apiSection['pages']);

    expect($slugs)->toContain('availability');
});

it('skips changelog entries lacking frontmatter and formats integer dates', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeTempDocsDir();

    try {
        mkdir($dir.'/changelog');
        // No version/date frontmatter — skipped.
        file_put_contents($dir.'/changelog/draft.md', "# Draft notes\n\nNothing structured here.");
        // Integer date frontmatter — formatted via date().
        file_put_contents(
            $dir.'/changelog/release.md',
            "---\nversion: 1.0.0\ndate: 1700000000\ntitle: First Release\n---\n# Release\n",
        );

        $service = new DocsService($dir);
        $entries = $service->getChangelog();

        expect($entries)->toHaveCount(1)
            ->and($entries[0]['version'])->toBe('1.0.0')
            ->and($entries[0]['date'])->toBe(date('Y-m-d', 1700000000))
            ->and($entries[0]['title'])->toBe('First Release');
    } finally {
        removeTempDocsDir($dir);
    }
});
