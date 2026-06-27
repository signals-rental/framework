<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Exercises SignalsChangelogCommand::findLastVersionRef edge cases by temporarily
 * relocating the real docs/changelog directory. The real files are always restored
 * in afterEach, even if a test fails.
 */
beforeEach(function () {
    $this->dir = base_path('docs/changelog');
    $this->stash = base_path('docs/_changelog_stash_'.uniqid());

    if (is_dir($this->dir)) {
        rename($this->dir, $this->stash);
    }
});

afterEach(function () {
    // Remove anything the test created, then restore the original directory.
    if (is_dir($this->dir)) {
        File::deleteDirectory($this->dir);
    }
    if (is_dir($this->stash)) {
        rename($this->stash, $this->dir);
    }
});

it('returns no version ref when the changelog directory does not exist', function () {
    // The directory is absent (stashed away), so findLastVersionRef short-circuits
    // on the is_dir() guard (line 99) and the command logs all history.
    expect(is_dir($this->dir))->toBeFalse();

    Process::fake([
        'git log*' => Process::result(output: "Add first feature\n"),
    ]);

    $this->artisan('signals:changelog', ['version' => '77.0.0'])
        ->assertSuccessful();

    expect(File::get(base_path('docs/changelog/77.0.0.md')))->toContain('version: 77.0.0');

    File::delete(base_path('docs/changelog/77.0.0.md'));
});

it('returns no version ref when the directory holds no version-named files', function () {
    // The directory exists but contains only non-version markdown, so the version
    // scan collects nothing and returns null (line 113).
    File::ensureDirectoryExists($this->dir);
    File::put($this->dir.'/README.md', '# not a version file');

    Process::fake([
        'git log*' => Process::result(output: "Fix a defect\n"),
    ]);

    $this->artisan('signals:changelog', ['version' => '77.0.1'])
        ->assertSuccessful();

    expect(File::get($this->dir.'/77.0.1.md'))->toContain('version: 77.0.1');
});
