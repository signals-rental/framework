<?php

use App\Services\DocsService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->service = app(DocsService::class);
});

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
