<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DocsService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

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
     * Display a documentation page.
     */
    public function show(string $section, string $page): View
    {
        abort_unless($this->docs->pageExists($section, $page), 404);

        $content = $this->docs->getPage($section, $page);
        $navigation = $this->docs->getNavigation();
        $adjacent = $this->docs->getAdjacentPages($section, $page);

        return view('docs.show', [
            'title' => $content['title'],
            'description' => $content['description'],
            'html' => $content['html'],
            'toc' => $content['toc'],
            'navigation' => $navigation,
            'currentSection' => $section,
            'currentPage' => $page,
            'prev' => $adjacent['prev'],
            'next' => $adjacent['next'],
            'searchDataJson' => json_encode($this->docs->getSearchIndex(), JSON_THROW_ON_ERROR),
        ]);
    }
}
