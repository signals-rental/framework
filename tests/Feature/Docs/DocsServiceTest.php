<?php

use App\Services\DocsService;

test('getNavigation returns sections with pages', function () {
    $service = app(DocsService::class);
    $nav = $service->getNavigation();

    expect($nav)->toHaveKey('sections')
        ->and($nav['sections'])->toBeArray()->toHaveCount(3)
        ->and($nav['sections'][0])->toHaveKeys(['title', 'slug', 'pages'])
        ->and($nav['sections'][0]['pages'])->toBeArray()->toHaveCount(4)
        ->and($nav['sections'][0]['pages'][0])->toHaveKeys(['title', 'slug']);
});

test('pageExists returns true for valid section and page', function () {
    $service = app(DocsService::class);

    expect($service->pageExists('getting-started', 'introduction'))->toBeTrue();
});

test('pageExists returns false for non-existent section', function () {
    $service = app(DocsService::class);

    expect($service->pageExists('nonexistent', 'page'))->toBeFalse();
});

test('pageExists returns false for non-existent page in valid section', function () {
    $service = app(DocsService::class);

    expect($service->pageExists('getting-started', 'nonexistent'))->toBeFalse();
});

test('pageExists rejects path traversal attempts', function () {
    $service = app(DocsService::class);

    expect($service->pageExists('..', '.env'))->toBeFalse()
        ->and($service->pageExists('getting-started', '../../.env'))->toBeFalse();
});

test('getPage returns title html toc and description', function () {
    $service = app(DocsService::class);
    $page = $service->getPage('getting-started', 'introduction');

    expect($page)->toBeArray()
        ->and($page)->toHaveKeys(['title', 'html', 'toc', 'description'])
        ->and($page['title'])->toBe('Introduction')
        ->and($page['description'])->toBe('Signals is an open-source rental management framework. Free, self-hostable, and plugin-extensible.')
        ->and($page['html'])->toContain('<h2')
        ->and($page['toc'])->toBeArray()->toHaveCount(8);
});

test('getPage returns null for non-existent page', function () {
    $service = app(DocsService::class);

    expect($service->getPage('nonexistent', 'page'))->toBeNull();
});

test('extractTableOfContents extracts h2 and h3 headings', function () {
    $service = app(DocsService::class);
    $html = '<h2 id="first">First</h2><p>Text</p><h3 id="nested">Nested</h3><h2 id="second">Second</h2>';
    $toc = $service->extractTableOfContents($html);

    expect($toc)->toHaveCount(3)
        ->and($toc[0])->toMatchArray(['level' => 2, 'id' => 'first', 'text' => 'First'])
        ->and($toc[1])->toMatchArray(['level' => 3, 'id' => 'nested', 'text' => 'Nested'])
        ->and($toc[2])->toMatchArray(['level' => 2, 'id' => 'second', 'text' => 'Second']);
});

test('extractTableOfContents returns empty array for empty html', function () {
    $service = app(DocsService::class);

    expect($service->extractTableOfContents(''))->toBeEmpty();
});

test('extractTableOfContents ignores headings without id', function () {
    $service = app(DocsService::class);
    $html = '<h2>No ID</h2><h2 id="with-id">With ID</h2>';
    $toc = $service->extractTableOfContents($html);

    expect($toc)->toHaveCount(1)
        ->and($toc[0]['id'])->toBe('with-id');
});

test('getAdjacentPages returns correct previous and next', function () {
    $service = app(DocsService::class);
    $adjacent = $service->getAdjacentPages('getting-started', 'installation');

    expect($adjacent['prev'])->not->toBeNull()
        ->and($adjacent['prev']['slug'])->toBe('introduction')
        ->and($adjacent['next'])->not->toBeNull()
        ->and($adjacent['next']['slug'])->toBe('configuration');
});

test('first page has null prev', function () {
    $service = app(DocsService::class);
    $adjacent = $service->getAdjacentPages('getting-started', 'introduction');

    expect($adjacent['prev'])->toBeNull()
        ->and($adjacent['next'])->not->toBeNull();
});

test('getSearchIndex returns entries with content for all pages', function () {
    $service = app(DocsService::class);
    $index = $service->getSearchIndex();

    expect($index)->toBeArray()->toHaveCount(6)
        ->and($index[0])->toHaveKeys(['title', 'section', 'url', 'content'])
        ->and($index[0]['title'])->toBe('Introduction')
        ->and($index[0]['section'])->toBe('Getting Started')
        ->and(strlen($index[0]['content']))->toBeGreaterThan(0);
});

test('last page has null next', function () {
    $service = app(DocsService::class);
    $adjacent = $service->getAdjacentPages('api', 'overview');

    expect($adjacent['next'])->toBeNull()
        ->and($adjacent['prev'])->not->toBeNull();
});
