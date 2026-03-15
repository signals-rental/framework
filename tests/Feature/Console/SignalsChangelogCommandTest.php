<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    // Clean up only test-created changelog files, not pre-existing ones
    $testVersions = ['0.3.0', '0.1.0-test', '1.0.0-beta.1'];
    foreach ($testVersions as $version) {
        $file = base_path("docs/changelog/{$version}.md");
        if (file_exists($file)) {
            File::delete($file);
        }
    }
});

it('rejects invalid version format', function () {
    $this->artisan('signals:changelog', ['version' => 'not-a-version'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid version format');
});

it('rejects version without patch number', function () {
    $this->artisan('signals:changelog', ['version' => '1.0'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid version format');
});

it('accepts valid semver version', function () {
    $this->artisan('signals:changelog', ['version' => '0.3.0'])
        ->assertSuccessful();

    expect(file_exists(base_path('docs/changelog/0.3.0.md')))->toBeTrue();
});

it('accepts semver with pre-release suffix', function () {
    $this->artisan('signals:changelog', ['version' => '1.0.0-beta.1'])
        ->assertSuccessful();

    expect(file_exists(base_path('docs/changelog/1.0.0-beta.1.md')))->toBeTrue();
});

it('creates changelog file with front matter', function () {
    $this->artisan('signals:changelog', ['version' => '0.3.0'])
        ->assertSuccessful();

    $content = File::get(base_path('docs/changelog/0.3.0.md'));

    expect($content)->toContain('version: 0.3.0');
    expect($content)->toContain('date:');
});

it('ensures the changelog directory exists', function () {
    $this->artisan('signals:changelog', ['version' => '0.1.0-test'])
        ->assertSuccessful();

    expect(file_exists(base_path('docs/changelog/0.1.0-test.md')))->toBeTrue();
});

it('prompts to overwrite existing changelog and cancels when declined', function () {
    $dir = base_path('docs/changelog');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/9.9.9.md', 'existing content');

    $this->artisan('signals:changelog', ['version' => '9.9.9'])
        ->expectsConfirmation('  docs/changelog/9.9.9.md already exists. Overwrite?', 'no')
        ->assertSuccessful();

    // Original content should be preserved
    expect(File::get($dir.'/9.9.9.md'))->toBe('existing content');

    File::delete($dir.'/9.9.9.md');
});

it('overwrites existing changelog when confirmed', function () {
    $dir = base_path('docs/changelog');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/9.9.9.md', 'old content');

    $this->artisan('signals:changelog', ['version' => '9.9.9'])
        ->expectsConfirmation('  docs/changelog/9.9.9.md already exists. Overwrite?', 'yes')
        ->assertSuccessful();

    // Content should be regenerated
    $content = File::get($dir.'/9.9.9.md');
    expect($content)->not->toBe('old content');
    expect($content)->toContain('version: 9.9.9');

    File::delete($dir.'/9.9.9.md');
});

it('handles empty git log output gracefully', function () {
    \Illuminate\Support\Facades\Process::fake([
        'git log*' => \Illuminate\Support\Facades\Process::result(output: ''),
    ]);

    $this->artisan('signals:changelog', ['version' => '0.3.0'])
        ->assertSuccessful();

    $content = File::get(base_path('docs/changelog/0.3.0.md'));
    // File should exist with front matter only — no categories since no commits
    expect($content)->toContain('version: 0.3.0');
});

it('handles git log process failure gracefully', function () {
    \Illuminate\Support\Facades\Process::fake([
        'git log*' => \Illuminate\Support\Facades\Process::result(exitCode: 1, output: '', errorOutput: 'not a git repository'),
        'git rev-parse*' => \Illuminate\Support\Facades\Process::result(exitCode: 1, output: ''),
    ]);

    $this->artisan('signals:changelog', ['version' => '0.3.0'])
        ->assertSuccessful();

    $content = File::get(base_path('docs/changelog/0.3.0.md'));
    expect($content)->toContain('version: 0.3.0');
});

it('categorises commits by prefix into changelog sections', function () {
    \Illuminate\Support\Facades\Process::fake([
        'git log *--pretty=format:%s' => \Illuminate\Support\Facades\Process::result(
            output: "Add user authentication\nFix login bug\nUpdate dashboard layout\nRemove legacy code\nSome random change"
        ),
        'git rev-parse*' => \Illuminate\Support\Facades\Process::result(exitCode: 1, output: ''),
        'git log --diff-filter*' => \Illuminate\Support\Facades\Process::result(exitCode: 1, output: ''),
    ]);

    $this->artisan('signals:changelog', ['version' => '0.3.0'])
        ->assertSuccessful();

    $content = File::get(base_path('docs/changelog/0.3.0.md'));
    expect($content)->toContain('### Added')
        ->and($content)->toContain('- Add user authentication')
        ->and($content)->toContain('### Fixed')
        ->and($content)->toContain('- Fix login bug')
        ->and($content)->toContain('### Changed')
        ->and($content)->toContain('- Update dashboard layout')
        ->and($content)->toContain('### Removed')
        ->and($content)->toContain('- Remove legacy code');
});
