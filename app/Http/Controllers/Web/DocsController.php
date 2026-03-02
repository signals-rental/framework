<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DocsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocsController extends Controller
{
    public function __construct(
        private DocsService $docs,
    ) {}

    /**
     * Redirect to the first documentation page.
     */
    public function index(): RedirectResponse
    {
        $navigation = $this->docs->getNavigation();

        if (empty($navigation['sections']) || empty($navigation['sections'][0]['pages'])) {
            abort(404);
        }

        $firstSection = $navigation['sections'][0]['slug'];
        $firstPage = $navigation['sections'][0]['pages'][0]['slug'];

        return redirect()->route('docs.show', [$firstSection, $firstPage]);
    }

    /**
     * Serve robots.txt for the docs subdomain.
     */
    public function robots(): Response
    {
        $content = file_get_contents(public_path('robots.txt'));

        return response($content ?: '', 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Generate an XML sitemap for all documentation pages.
     */
    public function sitemap(): Response
    {
        $navigation = $this->docs->getNavigation();
        $urls = [];

        foreach ($navigation['sections'] as $section) {
            foreach ($section['pages'] as $page) {
                $urls[] = route('docs.show', [$section['slug'], $page['slug']]);
            }
        }

        if ($this->docs->changelogExists()) {
            $urls[] = route('docs.changelog');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1).'</loc></url>'."\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Serve an image from the docs/images directory.
     */
    public function image(string $path): BinaryFileResponse
    {
        $docsBase = realpath(base_path('docs/images'));

        abort_if($docsBase === false, 404);

        $filePath = realpath(base_path('docs/images/'.$path));

        abort_if($filePath === false || ! str_starts_with($filePath, $docsBase.DIRECTORY_SEPARATOR), 404);

        return response()->file($filePath);
    }

    /**
     * Display the changelog page with all version entries.
     */
    public function changelog(): View
    {
        abort_unless($this->docs->changelogExists(), 404);

        $entries = $this->docs->getChangelog();
        $navigation = $this->docs->getNavigation();

        return view('docs.changelog', [
            'entries' => $entries,
            'navigation' => $navigation,
            'searchDataJson' => json_encode($this->docs->getSearchIndex(), JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Display a documentation page.
     */
    public function show(string $section, string $page): View
    {
        abort_unless($this->docs->pageExists($section, $page), 404);

        $content = $this->docs->getPage($section, $page);
        $navigation = $this->docs->getNavigation();
        $adjacent = $this->docs->getAdjacentPages($section, $page);

        $sectionTitle = collect($navigation['sections'])
            ->firstWhere('slug', $section)['title'] ?? $section;

        return view('docs.show', [
            'title' => $content['title'],
            'description' => $content['description'],
            'html' => $content['html'],
            'toc' => $content['toc'],
            'navigation' => $navigation,
            'currentSection' => $section,
            'currentPage' => $page,
            'sectionTitle' => $sectionTitle,
            'prev' => $adjacent['prev'],
            'next' => $adjacent['next'],
            'searchDataJson' => json_encode($this->docs->getSearchIndex(), JSON_THROW_ON_ERROR),
        ]);
    }
}
