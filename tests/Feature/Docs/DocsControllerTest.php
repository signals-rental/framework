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
        ->assertSee('What is Signals Rental Framework?');
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

test('docs page has proper html title', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('Signals Rental Framework - Documentation | Introduction', false);
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

test('changelog route returns 200', function () {
    get(route('docs.changelog'))
        ->assertOk()
        ->assertViewIs('docs.changelog');
});

test('changelog page displays version numbers', function () {
    get(route('docs.changelog'))
        ->assertSee('0.1.0');
});

test('changelog page uses docs layout', function () {
    get(route('docs.changelog'))
        ->assertSee('Documentation')
        ->assertSee('SIGNALS');
});

test('changelog page includes sidebar with changelog link', function () {
    get(route('docs.changelog'))
        ->assertSee('Getting Started')
        ->assertSee('Changelog');
});

test('changelog page includes search data', function () {
    get(route('docs.changelog'))
        ->assertSee('docs-search-data', false);
});

test('changelog page is accessible without authentication', function () {
    $this->assertGuest();

    get(route('docs.changelog'))
        ->assertOk();
});

test('changelog page has proper html title', function () {
    get(route('docs.changelog'))
        ->assertSee('Signals Rental Framework - Documentation | Changelog', false);
});

test('docs sidebar includes changelog link on regular pages', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('Changelog');
});

test('sitemap returns valid xml with all docs pages', function () {
    get(route('docs.sitemap'))
        ->assertOk()
        ->assertHeader('content-type', 'application/xml')
        ->assertSee('<urlset', false)
        ->assertSee(route('docs.show', ['getting-started', 'introduction']), false)
        ->assertSee(route('docs.changelog'), false);
});

test('docs page includes meta description when frontmatter has description', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('<meta name="description" content="', false);
});

test('docs page includes canonical url', function () {
    $url = route('docs.show', ['getting-started', 'introduction']);

    get($url)
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.$url.'">', false);
});

test('docs page includes open graph meta tags', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:site_name" content="Signals Rental Framework">', false)
        ->assertSee('<meta property="og:title" content="Introduction">', false);
});

test('docs page includes twitter card meta tags', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<meta name="twitter:title" content="Introduction">', false);
});

test('docs page includes breadcrumb json-ld with section title', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('"@type": "BreadcrumbList"', false)
        ->assertSee('"name": "Getting Started"', false);
});

test('docs page includes website json-ld', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('"@type": "WebSite"', false)
        ->assertSee('"name": "Signals Rental Framework Documentation"', false);
});

test('changelog page includes meta description', function () {
    get(route('docs.changelog'))
        ->assertOk()
        ->assertSee('<meta name="description" content="All notable changes to Signals Rental Framework, ordered by version.">', false);
});

test('changelog page includes open graph meta tags', function () {
    get(route('docs.changelog'))
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Changelog">', false)
        ->assertSee('<meta property="og:type" content="article">', false);
});

test('changelog page does not include breadcrumb json-ld', function () {
    get(route('docs.changelog'))
        ->assertOk()
        ->assertDontSee('"@type": "BreadcrumbList"', false);
});

test('docs robots.txt route returns text content', function () {
    get(route('docs.robots'))
        ->assertOk()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap:');
});
