<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasSignalsBranding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

#[AsCommand(name: 'signals:changelog')]
class SignalsChangelogCommand extends Command
{
    use HasSignalsBranding;

    protected $signature = 'signals:changelog {version : Semantic version number (e.g. 0.2.0)}';

    protected $description = 'Scaffold a changelog entry from git commit history';

    /**
     * Commit prefix patterns mapped to changelog categories.
     *
     * @var array<string, list<string>>
     */
    private const CATEGORY_PREFIXES = [
        'Added' => ['Add ', 'Implement ', 'Create ', 'Introduce '],
        'Fixed' => ['Fix ', 'Resolve ', 'Patch ', 'Correct '],
        'Changed' => ['Update ', 'Refactor ', 'Improve ', 'Rename ', 'Move ', 'Migrate ', 'Change '],
        'Removed' => ['Remove ', 'Delete ', 'Drop '],
    ];

    public function handle(): int
    {
        $version = $this->argument('version');

        if (! preg_match('/^\d+\.\d+\.\d+(-[\w.]+)?$/', $version)) {
            $this->error("  Invalid version format: {$version}. Expected semver (e.g. 0.2.0)");

            return self::FAILURE;
        }

        $dir = base_path('docs/changelog');
        $filePath = $dir.'/'.$version.'.md';

        if (file_exists($filePath)) {
            if (! confirm("  docs/changelog/{$version}.md already exists. Overwrite?", false)) {
                info('  Cancelled.');

                return self::SUCCESS;
            }
        }

        $commits = $this->getCommitsSinceLastVersion($dir);
        $categories = $this->categoriseCommits($commits);
        $markdown = $this->generateMarkdown($version, $categories);

        File::ensureDirectoryExists($dir);
        File::put($filePath, $markdown);

        $this->newLine();
        info("  Created docs/changelog/{$version}.md");
        $this->line('  <fg=gray>Review and edit the generated content before committing.</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Get commit messages since the last changelog version.
     *
     * @return list<string>
     */
    private function getCommitsSinceLastVersion(string $dir): array
    {
        $ref = $this->findLastVersionRef($dir);

        $command = $ref
            ? "git log {$ref}..HEAD --pretty=format:%s"
            : 'git log --pretty=format:%s';

        $result = Process::run($command);

        if (! $result->successful() || trim($result->output()) === '') {
            return [];
        }

        return array_filter(explode("\n", trim($result->output())));
    }

    /**
     * Find a git reference for the last changelog version.
     */
    private function findLastVersionRef(string $dir): ?string
    {
        if (! is_dir($dir)) {
            return null;
        }

        $files = glob($dir.'/*.md') ?: [];
        $versions = [];

        foreach ($files as $file) {
            $basename = pathinfo($file, PATHINFO_FILENAME);
            if (preg_match('/^\d+\.\d+\.\d+/', $basename)) {
                $versions[] = $basename;
            }
        }

        if ($versions === []) {
            return null;
        }

        usort($versions, 'version_compare');
        $latest = end($versions);

        // Check for git tags
        foreach (["v{$latest}", $latest] as $tag) {
            $result = Process::run("git rev-parse --verify {$tag} 2>/dev/null");
            if ($result->successful() && trim($result->output()) !== '') {
                return $tag;
            }
        }

        // Fall back to finding the commit that created the changelog file
        $result = Process::run("git log --diff-filter=A --format=%H -- docs/changelog/{$latest}.md");
        if ($result->successful() && trim($result->output()) !== '') {
            return trim(explode("\n", trim($result->output()))[0]);
        }

        return null;
    }

    /**
     * Categorise commit messages by their prefix.
     *
     * @param  list<string>  $commits
     * @return array<string, list<string>>
     */
    private function categoriseCommits(array $commits): array
    {
        $categories = ['Added' => [], 'Changed' => [], 'Fixed' => [], 'Removed' => []];

        foreach ($commits as $commit) {
            $categorised = false;

            foreach (self::CATEGORY_PREFIXES as $category => $prefixes) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($commit, $prefix)) {
                        $categories[$category][] = $commit;
                        $categorised = true;
                        break 2;
                    }
                }
            }

            if (! $categorised) {
                $categories['Changed'][] = $commit;
            }
        }

        return array_filter($categories);
    }

    /**
     * Generate the changelog markdown content.
     *
     * @param  array<string, list<string>>  $categories
     */
    private function generateMarkdown(string $version, array $categories): string
    {
        $date = now()->format('Y-m-d');

        $md = "---\nversion: {$version}\ndate: \"{$date}\"\n---\n";

        foreach ($categories as $category => $commits) {
            $md .= "\n### {$category}\n\n";
            foreach ($commits as $commit) {
                $md .= "- {$commit}\n";
            }
        }

        return $md;
    }
}
