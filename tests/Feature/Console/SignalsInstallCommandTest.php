<?php

use App\Services\ConnectionTesters\PostgresConnectionTester;
use App\Services\ConnectionTesters\RedisConnectionTester;
use App\Services\ConnectionTesters\S3ConnectionTester;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

pest()->group('env-writing');

beforeEach(function () {
    // Use a temp SQLite file for the pgsql connection so tests don't conflict
    // with the test framework's own :memory: database
    $this->tempDb = tempnam(sys_get_temp_dir(), 'signals_test_');
    config(['database.connections.pgsql.driver' => 'sqlite']);
    $this->originalDbDefault = config('database.default');

    // Back up .env so tests don't corrupt it (crash-safe via .env.test-backup)
    $envPath = base_path('.env');
    $backupPath = base_path('.env.test-backup');
    $this->envExisted = file_exists($envPath);
    if ($this->envExisted) {
        $this->originalEnv = file_get_contents($envPath);
        file_put_contents($backupPath, $this->originalEnv);
    }
});

afterEach(function () {
    // Restore original database default before framework teardown
    config(['database.default' => $this->originalDbDefault]);
    app('db')->purge('pgsql');

    // Clean up temp database
    @unlink($this->tempDb);

    // Restore .env and remove crash-safe backup
    $envPath = base_path('.env');
    $backupPath = base_path('.env.test-backup');
    if ($this->envExisted) {
        file_put_contents($envPath, $this->originalEnv);
    } elseif (file_exists($envPath)) {
        unlink($envPath);
    }
    @unlink($backupPath);

    // Clear any cached config/routes/views in case tests run without the guard
    Artisan::call('config:clear');
    Artisan::call('route:clear');
});

it('registers the signals:install command', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('signals:install');
});

it('has the correct description', function () {
    $command = Artisan::all()['signals:install'];

    expect($command->getDescription())
        ->toBe('Configure Signals infrastructure: database, cache, storage, and websockets');
});

it('accepts a force option', function () {
    $command = Artisan::all()['signals:install'];
    $definition = $command->getDefinition();

    expect($definition->hasOption('force'))->toBeTrue();
});

it('registers all non-interactive options', function (string $optionName) {
    $command = Artisan::all()['signals:install'];
    $definition = $command->getDefinition();

    expect($definition->hasOption($optionName))->toBeTrue();
})->with([
    'db-host',
    'db-port',
    'db-database',
    'db-username',
    'db-password',
    'cache-driver',
    'redis-host',
    'redis-port',
    'redis-password',
    'storage-driver',
    's3-provider',
    's3-bucket',
    's3-region',
    's3-key',
    's3-secret',
    's3-endpoint',
    'reverb-host',
    'reverb-port',
    'reverb-scheme',
    'app-url',
    'skip-npm',
]);

it('runs non-interactively with all required options', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $redisTester = $this->mock(RedisConnectionTester::class);
    $redisTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'Redis 7.2', 'error' => null]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-port' => '5432',
        '--db-database' => $this->tempDb,
        '--db-username' => 'signals',
        '--db-password' => 'secret',
        '--cache-driver' => 'redis',
        '--redis-host' => '127.0.0.1',
        '--redis-port' => '6379',
        '--redis-password' => 'null',
        '--storage-driver' => 'local',
        '--reverb-host' => '0.0.0.0',
        '--reverb-port' => '8080',
        '--reverb-scheme' => 'http',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertSuccessful();
});

it('fails non-interactively without db-password', function () {
    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('skips npm when --skip-npm is provided', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Skipping npm install and build');
});

it('runs npm install and build when skip-npm is not provided', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    // config:cache resets the Process facade, so assert via output instead
    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Installing frontend dependencies')
        ->expectsOutputToContain('Dependencies installed')
        ->expectsOutputToContain('Building frontend assets')
        ->expectsOutputToContain('Frontend assets built');
});

it('continues with warning when npm install fails', function () {
    Process::fake([
        '*npm install*' => Process::result(exitCode: 1, errorOutput: 'ERR!'),
        '*npm run build*' => Process::result(exitCode: 1, errorOutput: 'ERR!'),
    ]);

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    // config:cache resets the Process facade, so assert via output instead
    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('npm install failed');
});

it('skips redis config when cache-driver is database', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $redisTester = $this->mock(RedisConnectionTester::class);
    $redisTester->shouldNotReceive('test');

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertSuccessful();
});

it('skips s3 config when storage-driver is local', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $s3Tester = $this->mock(S3ConnectionTester::class);
    $s3Tester->shouldNotReceive('test');

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertSuccessful();
});

it('rejects invalid cache-driver values', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'memcached',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('rejects invalid storage-driver values', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $redisTester = $this->mock(RedisConnectionTester::class);
    $redisTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'Redis 7.2', 'error' => null]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'redis',
        '--redis-host' => '127.0.0.1',
        '--storage-driver' => 'ftp',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('rejects invalid reverb-scheme values', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--reverb-scheme' => 'ws',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('fails non-interactively when database connection fails', function () {
    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => false, 'error' => 'Connection refused']);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('fails non-interactively when full database connection test fails', function () {
    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => false, 'version' => null, 'error' => 'FATAL: permission denied']);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('fails when databaseExists throws a PDOException', function () {
    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andThrow(new \PDOException('permission denied'));

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('fails non-interactively when redis connection fails', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $redisTester = $this->mock(RedisConnectionTester::class);
    $redisTester->shouldReceive('test')->once()->andReturn(['success' => false, 'version' => null, 'error' => 'Connection refused']);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'redis',
        '--redis-host' => '127.0.0.1',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('fails non-interactively when s3 connection fails', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $s3Tester = $this->mock(S3ConnectionTester::class);
    $s3Tester->shouldReceive('test')->once()->andReturn(['success' => false, 'error' => 'Access Denied']);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 's3',
        '--s3-key' => 'AKIATEST',
        '--s3-secret' => 'secretkey',
        '--s3-bucket' => 'test-bucket',
        '--s3-region' => 'us-east-1',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('auto-creates database in non-interactive mode when it does not exist', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(false);
    $pgTester->shouldReceive('createDatabase')->once();
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertSuccessful();
});

it('fails non-interactively when database creation throws an exception', function () {
    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(false);
    $pgTester->shouldReceive('createDatabase')->once()->andThrow(new \PDOException('permission denied to create database'));

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('requires s3-endpoint for r2 provider in non-interactive mode', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 's3',
        '--s3-provider' => 'r2',
        '--s3-key' => 'test-key',
        '--s3-secret' => 'test-secret',
        '--s3-bucket' => 'test-bucket',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('warns when npm run build fails but install succeeds', function () {
    Process::fake([
        '*npm install*' => Process::result(output: 'done'),
        '*npm run build*' => Process::result(exitCode: 1, errorOutput: 'Build error'),
    ]);

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Dependencies installed')
        ->expectsOutputToContain('npm run build failed');
});

it('fails when migrations return a non-zero exit code', function () {
    // Use a database path inside a non-existent directory so SQLite migration fails
    $badDbPath = sys_get_temp_dir().'/signals_nonexistent_'.bin2hex(random_bytes(4)).'/test.db';

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $badDbPath,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertFailed();
});

it('completes successfully with s3 storage when connection test passes', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $s3Tester = $this->mock(S3ConnectionTester::class);
    $s3Tester->shouldReceive('test')->once()->andReturn(['success' => true, 'error' => null]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 's3',
        '--s3-provider' => 'aws',
        '--s3-key' => 'AKIATEST',
        '--s3-secret' => 'secretkey',
        '--s3-bucket' => 'test-bucket',
        '--s3-region' => 'us-east-1',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('S3 bucket accessible')
        ->expectsOutputToContain('S3 storage configured');
});

it('fails when .env file is not writable', function () {
    $envPath = base_path('.env');
    $envExistedBefore = file_exists($envPath);

    // Ensure .env exists then make it unwritable
    if (! $envExistedBefore) {
        file_put_contents($envPath, '');
    }
    chmod($envPath, 0444);

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    try {
        $this->artisan('signals:install', [
            '--no-interaction' => true,
            '--force' => true,
            '--db-host' => '127.0.0.1',
            '--db-database' => $this->tempDb,
            '--db-password' => 'secret',
            '--cache-driver' => 'database',
            '--storage-driver' => 'local',
            '--app-url' => 'http://localhost',
            '--skip-npm' => true,
        ])->assertFailed();
    } finally {
        // Restore permissions so afterEach cleanup can write
        chmod($envPath, 0644);
        if (! $envExistedBefore) {
            unlink($envPath);
        }
    }
});

it('substitutes region in digitalocean endpoint without error', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $s3Tester = $this->mock(S3ConnectionTester::class);
    $s3Tester->shouldReceive('test')->once()->andReturn(['success' => true, 'error' => null]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 's3',
        '--s3-provider' => 'digitalocean',
        '--s3-key' => 'DO-KEY',
        '--s3-secret' => 'DO-SECRET',
        '--s3-bucket' => 'my-space',
        '--s3-region' => 'nyc3',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('S3 storage configured');
});

it('prompts for database creation interactively and proceeds when accepted', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(false);
    $pgTester->shouldReceive('createDatabase')->once();
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    // Provide all values via options, but omit --force so the confirm prompt fires
    $this->artisan('signals:install', [
        '--db-host' => '127.0.0.1',
        '--db-port' => '5432',
        '--db-database' => $this->tempDb,
        '--db-username' => 'signals',
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--reverb-host' => '0.0.0.0',
        '--reverb-port' => '8080',
        '--reverb-scheme' => 'http',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])
        ->expectsConfirmation("Database '{$this->tempDb}' does not exist. Create it?", 'yes')
        ->assertSuccessful();
});

it('fails interactively when user declines database creation', function () {
    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(false);

    // Provide all values via options, but omit --force so the confirm prompt fires
    $this->artisan('signals:install', [
        '--db-host' => '127.0.0.1',
        '--db-port' => '5432',
        '--db-database' => $this->tempDb,
        '--db-username' => 'signals',
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])
        ->expectsConfirmation("Database '{$this->tempDb}' does not exist. Create it?", 'no')
        ->assertFailed();
});

it('retries database connection interactively when user confirms retry', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->twice()->andReturn(
        ['success' => false, 'error' => 'Connection refused'],
        ['success' => true, 'error' => null],
    );
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    // Provide all values via options; the confirm for retry is the interactive part
    $this->artisan('signals:install', [
        '--db-host' => '127.0.0.1',
        '--db-port' => '5432',
        '--db-database' => $this->tempDb,
        '--db-username' => 'signals',
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--reverb-host' => '0.0.0.0',
        '--reverb-port' => '8080',
        '--reverb-scheme' => 'http',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])
        ->expectsConfirmation('Would you like to re-enter the database credentials?', 'yes')
        ->assertSuccessful();
});

it('fails when an empty database password is provided', function () {
    Process::fake();

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-port' => '5432',
        '--db-database' => $this->tempDb,
        '--db-username' => 'signals',
        '--db-password' => '',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])
        ->assertFailed()
        ->expectsOutputToContain('--db-password option must not be empty');
});

it('sets SIGNALS_INSTALLED but does not modify SIGNALS_SETUP_COMPLETE in env', function () {
    // Ensure env starts with SIGNALS_SETUP_COMPLETE=false
    $envPath = base_path('.env');
    $envContent = file_get_contents($envPath);
    $envContent = preg_replace('/SIGNALS_SETUP_COMPLETE=\w+/', 'SIGNALS_SETUP_COMPLETE=false', $envContent);
    file_put_contents($envPath, $envContent);

    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-database' => $this->tempDb,
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 'local',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])->assertSuccessful();

    $envContent = file_get_contents($envPath);
    expect($envContent)->toContain('SIGNALS_INSTALLED=true');
    expect($envContent)->toContain('SIGNALS_SETUP_COMPLETE=false');
});

it('fails with an invalid --s3-provider value', function () {
    Process::fake();

    $pgTester = $this->mock(PostgresConnectionTester::class);
    $pgTester->shouldReceive('testServer')->once()->andReturn(['success' => true, 'error' => null]);
    $pgTester->shouldReceive('databaseExists')->once()->andReturn(true);
    $pgTester->shouldReceive('test')->once()->andReturn(['success' => true, 'version' => 'PostgreSQL 16.2', 'error' => null]);
    $pgTester->shouldReceive('checkExtensions')->once()->andReturn(['pgcrypto' => true]);

    config(['database.connections.pgsql.database' => $this->tempDb]);

    $this->artisan('signals:install', [
        '--no-interaction' => true,
        '--force' => true,
        '--db-host' => '127.0.0.1',
        '--db-port' => '5432',
        '--db-database' => $this->tempDb,
        '--db-username' => 'signals',
        '--db-password' => 'secret',
        '--cache-driver' => 'database',
        '--storage-driver' => 's3',
        '--s3-provider' => 'invalid-provider',
        '--app-url' => 'http://localhost',
        '--skip-npm' => true,
    ])
        ->assertFailed();
});
