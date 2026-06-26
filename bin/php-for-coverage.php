#!/usr/bin/env php
<?php

/**
 * Run PHP with a code-coverage driver configured for PHPUnit/Pest.
 *
 * Prefers PCOV (lighter, faster) when the extension is available for the
 * current PHP binary, otherwise falls back to Xdebug coverage mode.
 *
 * Usage:
 *   php bin/php-for-coverage.php artisan test --coverage-php build/coverage/pgsql.php
 */

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/php-for-coverage.php <command...>\n");
    exit(1);
}

$ini = ['-d', 'memory_limit=-1'];
$env = array_merge($_ENV, [
    'COVERAGE_COLLECTING' => '1',
]);

$pcovExtension = resolvePcovExtensionPath();

if ($pcovExtension !== null) {
    $ini[] = '-d';
    $ini[] = 'extension='.$pcovExtension;
    $ini[] = '-d';
    $ini[] = 'pcov.enabled=1';
    $ini[] = '-d';
    $ini[] = 'pcov.directory='.dirname(__DIR__);
    $ini[] = '-d';
    $ini[] = 'xdebug.mode=off';
    $env['XDEBUG_MODE'] = 'off';
} elseif (extension_loaded('pcov')) {
    $ini[] = '-d';
    $ini[] = 'pcov.enabled=1';
    $ini[] = '-d';
    $ini[] = 'pcov.directory='.dirname(__DIR__);
    $ini[] = '-d';
    $ini[] = 'xdebug.mode=off';
    $env['XDEBUG_MODE'] = 'off';
} elseif (extension_loaded('xdebug')) {
    $ini[] = '-d';
    $ini[] = 'xdebug.mode=coverage';
    $env['XDEBUG_MODE'] = 'coverage';
}

$command = array_merge([PHP_BINARY], $ini, array_slice($argv, 1));

$process = proc_open(
    $command,
    [
        0 => STDIN,
        1 => STDOUT,
        2 => STDERR,
    ],
    $pipes,
    getcwd() ?: dirname(__DIR__),
    $env,
);

if (! is_resource($process)) {
    fwrite(STDERR, "Failed to start coverage process.\n");
    exit(1);
}

exit(proc_close($process));

function resolvePcovExtensionPath(): ?string
{
    if (extension_loaded('pcov')) {
        return null;
    }

    $extensionDir = ini_get('extension_dir');

    if (is_string($extensionDir) && $extensionDir !== '') {
        $candidate = rtrim($extensionDir, '/').'/pcov.so';

        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $build = shell_exec(escapeshellarg(PHP_BINARY).' -i 2>/dev/null | grep "PHP Extension Build"');

    if (! is_string($build) || ! preg_match('/API(\d+)/', $build, $matches)) {
        return null;
    }

    $candidates = [
        '/opt/homebrew/lib/php/pecl/'.$matches[1].'/pcov.so',
        '/usr/local/lib/php/pecl/'.$matches[1].'/pcov.so',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}
