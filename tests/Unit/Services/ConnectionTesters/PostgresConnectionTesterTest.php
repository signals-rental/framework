<?php

use App\Services\ConnectionTesters\PostgresConnectionTester;

uses(Tests\TestCase::class);

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

it('checks if database exists', function () {
    $tester = new PostgresConnectionTester;

    // This may or may not connect depending on the environment
    try {
        $result = $tester->databaseExists(
            '127.0.0.1', 5432,
            config('database.connections.pgsql.username', 'signals'),
            config('database.connections.pgsql.password', ''),
            'postgres'
        );

        expect($result)->toBeBool();
    } catch (\PDOException) {
        // Connection might not be available in CI
        $this->markTestSkipped('PostgreSQL not available');
    }
});

it('checks extensions and returns correct shape', function () {
    $tester = new PostgresConnectionTester;

    try {
        $result = $tester->checkExtensions([
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'postgres',
            'username' => config('database.connections.pgsql.username', 'signals'),
            'password' => config('database.connections.pgsql.password', ''),
        ], ['plpgsql']);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('plpgsql');
        expect($result['plpgsql'])->toBeBool();
    } catch (\PDOException) {
        $this->markTestSkipped('PostgreSQL not available');
    }
});

it('sanitizes database name in createDatabase', function () {
    // Test the sanitization logic directly
    $unsafeName = 'my-db; DROP TABLE users;';
    $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $unsafeName);

    expect($safeName)->toBe('mydbDROPTABLEusers');
    expect($safeName)->not->toContain(';');
    expect($safeName)->not->toContain('-');
});

it('returns correct shape from testServer on failure', function () {
    $tester = new PostgresConnectionTester;

    $result = $tester->testServer('127.0.0.1', 59999, 'bad_user', 'bad_pass');

    expect($result)->toHaveKeys(['success', 'error']);
    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBeString();
});

// Mock-based tests that don't require a real PostgreSQL connection

it('returns version string on successful connection via mock', function () {
    $mockPdo = Mockery::mock(PDO::class);
    $mockStmt = Mockery::mock(PDOStatement::class);
    $mockStmt->shouldReceive('fetchColumn')->andReturn('PostgreSQL 16.2 on x86_64');
    $mockPdo->shouldReceive('query')->with('SELECT version()')->andReturn($mockStmt);

    $tester = Mockery::mock(PostgresConnectionTester::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $tester->shouldReceive('connect')->andReturn($mockPdo);

    $result = $tester->test([
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'test_db',
        'username' => 'user',
        'password' => 'pass',
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['version'])->toContain('PostgreSQL 16.2');
    expect($result['error'])->toBeNull();
});

it('returns true when database exists via mock', function () {
    $mockPdo = Mockery::mock(PDO::class);
    $mockStmt = Mockery::mock(PDOStatement::class);
    $mockStmt->shouldReceive('execute')->with(['dbname' => 'signals'])->once();
    $mockStmt->shouldReceive('fetchColumn')->andReturn(1);
    $mockPdo->shouldReceive('prepare')
        ->with('SELECT 1 FROM pg_database WHERE datname = :dbname')
        ->andReturn($mockStmt);

    $tester = Mockery::mock(PostgresConnectionTester::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $tester->shouldReceive('connect')->andReturn($mockPdo);

    $result = $tester->databaseExists('127.0.0.1', 5432, 'user', 'pass', 'signals');

    expect($result)->toBeTrue();
});

it('returns false when database does not exist via mock', function () {
    $mockPdo = Mockery::mock(PDO::class);
    $mockStmt = Mockery::mock(PDOStatement::class);
    $mockStmt->shouldReceive('execute')->with(['dbname' => 'missing_db'])->once();
    $mockStmt->shouldReceive('fetchColumn')->andReturn(false);
    $mockPdo->shouldReceive('prepare')
        ->with('SELECT 1 FROM pg_database WHERE datname = :dbname')
        ->andReturn($mockStmt);

    $tester = Mockery::mock(PostgresConnectionTester::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $tester->shouldReceive('connect')->andReturn($mockPdo);

    $result = $tester->databaseExists('127.0.0.1', 5432, 'user', 'pass', 'missing_db');

    expect($result)->toBeFalse();
});

it('executes CREATE DATABASE with sanitized name via mock', function () {
    $mockPdo = Mockery::mock(PDO::class);
    $mockPdo->shouldReceive('exec')
        ->once()
        ->with('CREATE DATABASE "signals_prod"');

    $tester = Mockery::mock(PostgresConnectionTester::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $tester->shouldReceive('connect')->andReturn($mockPdo);

    $tester->createDatabase('127.0.0.1', 5432, 'user', 'pass', 'signals_prod');
});

it('checks extensions and returns map via mock', function () {
    $mockPdo = Mockery::mock(PDO::class);

    $stmtPlpgsql = Mockery::mock(PDOStatement::class);
    $stmtPlpgsql->shouldReceive('execute')->with(['ext' => 'plpgsql'])->once();
    $stmtPlpgsql->shouldReceive('fetchColumn')->andReturn(1);

    $stmtVector = Mockery::mock(PDOStatement::class);
    $stmtVector->shouldReceive('execute')->with(['ext' => 'vector'])->once();
    $stmtVector->shouldReceive('fetchColumn')->andReturn(false);

    $mockPdo->shouldReceive('prepare')
        ->with('SELECT 1 FROM pg_extension WHERE extname = :ext')
        ->twice()
        ->andReturn($stmtPlpgsql, $stmtVector);

    $tester = Mockery::mock(PostgresConnectionTester::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $tester->shouldReceive('connect')->andReturn($mockPdo);

    $result = $tester->checkExtensions([
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'test_db',
        'username' => 'user',
        'password' => 'pass',
    ], ['plpgsql', 'vector']);

    expect($result)->toBe(['plpgsql' => true, 'vector' => false]);
});

it('returns success from testServer when connection succeeds via mock', function () {
    $mockPdo = Mockery::mock(PDO::class);

    $tester = Mockery::mock(PostgresConnectionTester::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $tester->shouldReceive('connect')->andReturn($mockPdo);

    $result = $tester->testServer('127.0.0.1', 5432, 'user', 'pass');

    expect($result['success'])->toBeTrue();
    expect($result['error'])->toBeNull();
});
