<?php

use App\Services\ConnectionTesters\PostgresConnectionTester;
use App\Services\ConnectionTesters\RedisConnectionTester;
use App\Services\ConnectionTesters\S3ConnectionTester;
use Illuminate\Support\Facades\Artisan;

it('registers the signals:status command', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('signals:status');
});

it('has the correct description', function () {
    $command = Artisan::all()['signals:status'];

    expect($command->getDescription())
        ->toBe('Display Signals installation status and connection health');
});

it('runs successfully', function () {
    $this->artisan('signals:status')
        ->assertExitCode(0);
});

it('shows installation state', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $this->artisan('signals:status')
        ->expectsOutputToContain('Configured')
        ->assertExitCode(0);
});

it('shows not-configured state', function () {
    config(['signals.installed' => false, 'signals.setup_complete' => false]);

    $this->artisan('signals:status')
        ->expectsOutputToContain('Not configured')
        ->assertExitCode(0);
});

it('shows redis not-in-use when no redis drivers', function () {
    config([
        'cache.default' => 'database',
        'queue.default' => 'database',
        'session.driver' => 'database',
    ]);

    $this->artisan('signals:status')
        ->expectsOutputToContain('Not in use')
        ->assertExitCode(0);
});

it('shows s3 not-configured when using local disk', function () {
    config(['filesystems.default' => 'local']);

    $this->artisan('signals:status')
        ->expectsOutputToContain('local disk')
        ->assertExitCode(0);
});

it('shows reverb not-configured when broadcast is not reverb', function () {
    config(['broadcasting.default' => 'null']);

    $this->artisan('signals:status')
        ->expectsOutputToContain('Not configured')
        ->assertExitCode(0);
});

it('shows database not-configured when password is empty', function () {
    config(['database.connections.pgsql.password' => '']);

    $this->artisan('signals:status')
        ->expectsOutputToContain('Not configured')
        ->assertExitCode(0);
});

it('shows database disconnected when password is set but connection fails', function () {
    config([
        'database.connections.pgsql.password' => 'bad-password',
        'database.connections.pgsql.host' => '127.0.0.1',
        'database.connections.pgsql.port' => 1,
    ]);

    $this->artisan('signals:status')
        ->assertExitCode(0);
});

it('shows redis connected when using redis driver', function () {
    config([
        'cache.default' => 'redis',
        'queue.default' => 'database',
        'session.driver' => 'database',
    ]);

    $this->artisan('signals:status')
        ->assertExitCode(0);
});

it('shows s3 missing credentials when s3 disk with empty key', function () {
    config([
        'filesystems.default' => 's3',
        'filesystems.disks.s3.key' => '',
    ]);

    $this->artisan('signals:status')
        ->expectsOutputToContain('missing credentials')
        ->assertExitCode(0);
});

it('shows reverb configured when broadcast is reverb', function () {
    config([
        'broadcasting.default' => 'reverb',
        'reverb.servers.reverb.hostname' => 'localhost',
        'reverb.servers.reverb.port' => '8080',
        'reverb.apps.apps.0.app_id' => 'test-app-id',
    ]);

    $this->artisan('signals:status')
        ->expectsOutputToContain('Configured')
        ->assertExitCode(0);
});

it('shows database connected with version and details when connection succeeds', function () {
    config([
        'database.connections.pgsql.password' => 'secret',
        'database.connections.pgsql.host' => '127.0.0.1',
        'database.connections.pgsql.port' => 5432,
        'database.connections.pgsql.database' => 'signals_test',
    ]);

    $mock = $this->mock(PostgresConnectionTester::class);
    $mock->shouldReceive('test')->once()->andReturn([
        'success' => true,
        'version' => 'PostgreSQL 16.2',
        'error' => null,
    ]);

    $this->artisan('signals:status')
        ->expectsOutputToContain('Connected')
        ->expectsOutputToContain('PostgreSQL 16.2')
        ->expectsOutputToContain('signals_test')
        ->assertExitCode(0);
});

it('shows redis disconnected with error when connection fails', function () {
    config([
        'cache.default' => 'redis',
        'queue.default' => 'database',
        'session.driver' => 'database',
    ]);

    $mock = $this->mock(RedisConnectionTester::class);
    $mock->shouldReceive('test')->once()->andReturn([
        'success' => false,
        'version' => null,
        'error' => 'Connection refused',
    ]);

    $this->artisan('signals:status')
        ->expectsOutputToContain('Disconnected')
        ->expectsOutputToContain('Connection refused')
        ->assertExitCode(0);
});

it('shows all redis services when cache, queue, and session use redis', function () {
    config([
        'cache.default' => 'redis',
        'queue.default' => 'redis',
        'session.driver' => 'redis',
    ]);

    $mock = $this->mock(RedisConnectionTester::class);
    $mock->shouldReceive('test')->once()->andReturn([
        'success' => true,
        'version' => 'Redis 7.2',
        'error' => null,
    ]);

    $this->artisan('signals:status')
        ->expectsOutputToContain('Connected')
        ->expectsOutputToContain('cache, queue, sessions')
        ->assertExitCode(0);
});

it('shows s3 connected with bucket and region when connection succeeds', function () {
    config([
        'filesystems.default' => 's3',
        'filesystems.disks.s3.key' => 'AKIATEST',
        'filesystems.disks.s3.secret' => 'secret',
        'filesystems.disks.s3.bucket' => 'my-bucket',
        'filesystems.disks.s3.region' => 'eu-west-2',
    ]);

    $mock = $this->mock(S3ConnectionTester::class);
    $mock->shouldReceive('test')->once()->andReturn([
        'success' => true,
        'error' => null,
    ]);

    $this->artisan('signals:status')
        ->expectsOutputToContain('Connected')
        ->expectsOutputToContain('my-bucket')
        ->expectsOutputToContain('eu-west-2')
        ->assertExitCode(0);
});

it('shows s3 disconnected with error when connection fails', function () {
    config([
        'filesystems.default' => 's3',
        'filesystems.disks.s3.key' => 'AKIATEST',
        'filesystems.disks.s3.secret' => 'secret',
        'filesystems.disks.s3.bucket' => 'my-bucket',
        'filesystems.disks.s3.region' => 'eu-west-2',
    ]);

    $mock = $this->mock(S3ConnectionTester::class);
    $mock->shouldReceive('test')->once()->andReturn([
        'success' => false,
        'error' => 'Access Denied',
    ]);

    $this->artisan('signals:status')
        ->expectsOutputToContain('Disconnected')
        ->expectsOutputToContain('Access Denied')
        ->assertExitCode(0);
});
