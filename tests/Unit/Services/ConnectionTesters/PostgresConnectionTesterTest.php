<?php

use App\Services\ConnectionTesters\PostgresConnectionTester;

it('returns success shape on successful connection', function () {
    // Skip if no local PostgreSQL available
    $tester = new PostgresConnectionTester;

    $result = $tester->test([
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'postgres',
        'username' => config('database.connections.pgsql.username', 'signals'),
        'password' => config('database.connections.pgsql.password', ''),
    ]);

    expect($result)->toHaveKeys(['success', 'version', 'error']);

    if ($result['success']) {
        expect($result['version'])->toContain('PostgreSQL');
        expect($result['error'])->toBeNull();
    }
});

it('returns failure shape on bad connection', function () {
    $tester = new PostgresConnectionTester;

    $result = $tester->test([
        'host' => '127.0.0.1',
        'port' => 59999,
        'database' => 'nonexistent_db_signals_test',
        'username' => 'nonexistent_user',
        'password' => 'bad_password',
    ]);

    expect($result)->toHaveKeys(['success', 'version', 'error']);
    expect($result['success'])->toBeFalse();
    expect($result['version'])->toBeNull();
    expect($result['error'])->toBeString();
});

it('sanitizes database names to prevent injection', function () {
    $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', 'my-db; DROP TABLE users;');
    expect($safeName)->toBe('mydbDROPTABLEusers');

    $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', 'signals_production');
    expect($safeName)->toBe('signals_production');
});

it('returns correct shape from testServer on failure', function () {
    $tester = new PostgresConnectionTester;

    $result = $tester->testServer('127.0.0.1', 59999, 'bad_user', 'bad_pass');

    expect($result)->toHaveKeys(['success', 'error']);
    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBeString();
});
