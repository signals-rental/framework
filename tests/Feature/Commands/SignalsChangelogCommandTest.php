<?php

use Illuminate\Support\Facades\Process;

use function Pest\Laravel\artisan;

beforeEach(function () {
    $this->changelogDir = base_path('docs/changelog');
    $this->testVersion = '99.99.99';
    $this->testFile = $this->changelogDir.'/'.$this->testVersion.'.md';
});

afterEach(function () {
    if (file_exists($this->testFile)) {
        unlink($this->testFile);
    }
});

test('command creates changelog file with valid version', function () {
    Process::fake([
        '*' => Process::result(output: "Add new feature\nFix a bug\n"),
    ]);

    artisan('signals:changelog', ['version' => $this->testVersion])
        ->assertSuccessful();

    expect(file_exists($this->testFile))->toBeTrue();
});

test('command rejects invalid version format', function () {
    artisan('signals:changelog', ['version' => 'not-a-version'])
        ->assertFailed();
});

test('command populates frontmatter correctly', function () {
    Process::fake([
        '*' => Process::result(output: "Add something\n"),
    ]);

    artisan('signals:changelog', ['version' => $this->testVersion])
        ->assertSuccessful();

    $content = file_get_contents($this->testFile);

    expect($content)->toContain('version: '.$this->testVersion)
        ->and($content)->toContain('date: "'.now()->format('Y-m-d').'"');
});

test('command categorises git commits by prefix', function () {
    Process::fake([
        '*' => Process::result(output: implode("\n", [
            'Add user authentication',
            'Fix login redirect loop',
            'Update dashboard layout',
            'Remove legacy endpoint',
            'Implement search feature',
        ])),
    ]);

    artisan('signals:changelog', ['version' => $this->testVersion])
        ->assertSuccessful();

    $content = file_get_contents($this->testFile);

    expect($content)->toContain('### Added')
        ->and($content)->toContain('- Add user authentication')
        ->and($content)->toContain('- Implement search feature')
        ->and($content)->toContain('### Fixed')
        ->and($content)->toContain('- Fix login redirect loop')
        ->and($content)->toContain('### Changed')
        ->and($content)->toContain('- Update dashboard layout')
        ->and($content)->toContain('### Removed')
        ->and($content)->toContain('- Remove legacy endpoint');
});

test('uncategorised commits default to changed', function () {
    Process::fake([
        '*' => Process::result(output: "Some random commit message\n"),
    ]);

    artisan('signals:changelog', ['version' => $this->testVersion])
        ->assertSuccessful();

    $content = file_get_contents($this->testFile);

    expect($content)->toContain('### Changed')
        ->and($content)->toContain('- Some random commit message');
});
