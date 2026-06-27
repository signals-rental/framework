<?php

use App\Services\DocsService;
use Illuminate\Support\Facades\App;

/**
 * A throwaway docs root the test owns and tears down.
 */
function makeDocsCoverageDir(): string
{
    $dir = sys_get_temp_dir().'/docs-coverage-'.uniqid();
    mkdir($dir, 0755, true);

    return $dir;
}

function removeDocsCoverageDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        // Restore permissions so unreadable fixtures can be removed.
        @chmod($item->getPathname(), 0755);
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }

    rmdir($dir);
}

it('returns null from getPage when the resolved markdown file cannot be read', function () {
    $dir = makeDocsCoverageDir();

    try {
        // A real, manifest-free section/page whose .md exists but is unreadable
        // (chmod 000) so file_get_contents() returns false → the null guard.
        mkdir($dir.'/guide');
        $file = $dir.'/guide/locked.md';
        file_put_contents($file, "# Locked\n");
        chmod($file, 0000);

        $service = new DocsService($dir);

        expect(@$service->getPage('guide', 'locked'))->toBeNull();
    } finally {
        removeDocsCoverageDir($dir);
    }
})->skip(posix_getuid() === 0, 'chmod 000 does not block reads when running as root.');

it('returns an empty manifest when documentation.json exists but cannot be read', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeDocsCoverageDir();

    try {
        $manifest = $dir.'/documentation.json';
        file_put_contents($manifest, '{"sections":[]}');
        chmod($manifest, 0000);

        $service = new DocsService($dir);

        // file_get_contents() returns false on the unreadable manifest → the
        // content-false guard returns the empty-sections fallback.
        expect(@$service->getNavigation())->toBe(['sections' => []]);
    } finally {
        removeDocsCoverageDir($dir);
    }
})->skip(posix_getuid() === 0, 'chmod 000 does not block reads when running as root.');

it('skips a changelog entry whose markdown file cannot be read', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeDocsCoverageDir();

    try {
        mkdir($dir.'/changelog');
        // A readable, well-formed entry that must be kept.
        file_put_contents(
            $dir.'/changelog/release.md',
            "---\nversion: 2.0.0\ndate: 1700000000\ntitle: Good Release\n---\n# Release\n",
        );
        // An unreadable entry that must be skipped (file_get_contents === false).
        $locked = $dir.'/changelog/locked.md';
        file_put_contents($locked, "---\nversion: 9.9.9\ndate: 1700000000\n---\n# Locked\n");
        chmod($locked, 0000);

        $service = new DocsService($dir);
        $entries = @$service->getChangelog();

        // Only the readable entry survives; the unreadable one was skipped.
        expect($entries)->toHaveCount(1)
            ->and($entries[0]['version'])->toBe('2.0.0');
    } finally {
        removeDocsCoverageDir($dir);
    }
})->skip(posix_getuid() === 0, 'chmod 000 does not block reads when running as root.');
