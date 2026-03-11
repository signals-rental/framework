#!/usr/bin/env php
<?php

/**
 * Merge coverage reports from multiple PHPUnit/Pest test runs.
 *
 * Usage:
 *   php bin/coverage-merge.php <directory> [--html=<path>] [--text]
 *
 * The <directory> should contain .php files produced by --coverage-php.
 */

require __DIR__.'/../vendor/autoload.php';

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlReport;
use SebastianBergmann\CodeCoverage\Report\Text as TextReport;
use SebastianBergmann\CodeCoverage\Report\Thresholds;

$dir = $argv[1] ?? null;

if (! $dir || ! is_dir($dir)) {
    fwrite(STDERR, "Usage: php bin/coverage-merge.php <directory> [--html=<path>] [--text]\n");
    exit(1);
}

$files = glob($dir.'/*.php');

if (empty($files)) {
    fwrite(STDERR, "No .php coverage files found in {$dir}\n");
    exit(1);
}

echo 'Merging '.count($files)." coverage file(s)...\n";

$merged = null;

foreach ($files as $file) {
    echo '  Loading '.basename($file)."...\n";
    $coverage = include $file;

    if (! $coverage instanceof CodeCoverage) {
        fwrite(STDERR, '  Skipping '.basename($file)." — not a CodeCoverage object\n");

        continue;
    }

    if ($merged === null) {
        $merged = $coverage;
    } else {
        $merged->merge($coverage);
    }
}

if ($merged === null) {
    fwrite(STDERR, "No valid coverage data found\n");
    exit(1);
}

// Parse output options
$htmlPath = null;
$showText = false;

foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--html=')) {
        $htmlPath = substr($arg, 7);
    } elseif ($arg === '--text') {
        $showText = true;
    }
}

// Default to text output
if (! $htmlPath && ! $showText) {
    $showText = true;
}

if ($showText) {
    $report = new TextReport(Thresholds::default());
    echo $report->process($merged, true);
}

if ($htmlPath) {
    echo "\nGenerating HTML report to {$htmlPath}...\n";
    (new HtmlReport)->process($merged, $htmlPath);
    echo "Done.\n";
}
