<?php

use function Pest\Laravel\get;

test('docs index redirects to the first documentation page', function () {
    get(route('docs.index'))
        ->assertRedirect(route('docs.show', ['getting-started', 'introduction']));
});

test('valid documentation page returns 200', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertViewIs('docs.show')
        ->assertSee('What is Signals?');
});

test('documentation page contains rendered markdown html', function () {
    get(route('docs.show', ['getting-started', 'installation']))
        ->assertOk()
        ->assertSee('Requirements')
        ->assertSee('Quick Start');
});

test('non-existent documentation page returns 404', function () {
    get(route('docs.show', ['nonexistent', 'page']))
        ->assertNotFound();
});

test('non-existent page in valid section returns 404', function () {
    get(route('docs.show', ['getting-started', 'nonexistent']))
        ->assertNotFound();
});

test('path traversal attempts return 404', function (string $section, string $page) {
    get("/docs/{$section}/{$page}")
        ->assertNotFound();
})->with([
    ['getting-started', 'introduction.php'],
    ['GETTING-STARTED', 'introduction'],
    ['getting_started', 'introduction'],
]);

test('docs page includes sidebar navigation', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('Getting Started')
        ->assertSee('Introduction')
        ->assertSee('Installation')
        ->assertSee('Configuration');
});

test('docs page includes table of contents', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('On This Page');
});

test('docs page includes previous and next links', function () {
    get(route('docs.show', ['getting-started', 'installation']))
        ->assertSee('Previous')
        ->assertSee('Next');
});

test('first docs page has no previous link', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertDontSee('Previous');
});

test('last docs page has no next link', function () {
    get(route('docs.show', ['api', 'overview']))
        ->assertDontSee('Next &rarr;', false);
});

test('docs pages are accessible without authentication', function () {
    $this->assertGuest();

    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk();
});

test('docs page includes search data with content', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('docs-search-data', false)
        ->assertSee('Search docs...');
});

test('docs page uses standalone docs layout', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('Documentation')
        ->assertSee('SIGNALS');
});

test('docs image route serves existing images', function () {
    get(route('docs.image', ['path' => 'quick-start.png']))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

test('docs image route returns 404 for missing images', function () {
    get(route('docs.image', ['path' => 'nonexistent.png']))
        ->assertNotFound();
});

test('docs image route blocks path traversal', function () {
    get('/docs/images/../documentation.json')
        ->assertNotFound();
});
