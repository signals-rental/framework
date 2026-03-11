<?php

use App\Actions\Setup\CheckInfrastructure;
use App\Services\ConnectionTesters\PostgresConnectionTester;
use App\Services\ConnectionTesters\RedisConnectionTester;

beforeEach(function () {
    // Default: Redis not required
    config([
        'cache.default' => 'database',
        'queue.default' => 'database',
    ]);
});

it('returns consistent result shape', function () {
    $mockDb = Mockery::mock(PostgresConnectionTester::class);
    $mockDb->shouldReceive('test')->andReturn(['success' => true, 'version' => 'PostgreSQL 16.0', 'error' => null]);
    app()->instance(PostgresConnectionTester::class, $mockDb);

    config(['reverb.apps.apps' => [['app_id' => 'test-id']]]);

    $result = (new CheckInfrastructure)();

    expect($result)->toHaveKeys(['passed', 'checks']);
    expect($result['checks'])->toHaveKeys(['database', 'migrations', 'redis', 'reverb']);

    foreach ($result['checks'] as $check) {
        expect($check)->toHaveKeys(['passed', 'message']);
    }
});

it('passes when all checks pass', function () {
    $mockDb = Mockery::mock(PostgresConnectionTester::class);
    $mockDb->shouldReceive('test')->andReturn(['success' => true, 'version' => 'PostgreSQL 16.0', 'error' => null]);
    app()->instance(PostgresConnectionTester::class, $mockDb);

    config(['reverb.apps.apps' => [['app_id' => 'test-id']]]);

    $result = (new CheckInfrastructure)();

    expect($result['passed'])->toBeTrue();
    expect($result['checks']['database']['passed'])->toBeTrue();
});

it('fails when database check fails', function () {
    $mockDb = Mockery::mock(PostgresConnectionTester::class);
    $mockDb->shouldReceive('test')->andReturn(['success' => false, 'version' => null, 'error' => 'Connection refused']);
    app()->instance(PostgresConnectionTester::class, $mockDb);

    config(['reverb.apps.apps' => [['app_id' => 'test-id']]]);

    $result = (new CheckInfrastructure)();

    expect($result['passed'])->toBeFalse();
    expect($result['checks']['database']['passed'])->toBeFalse();
    expect($result['checks']['database']['message'])->toContain('Connection refused');
});

it('skips migration check when database fails', function () {
    $mockDb = Mockery::mock(PostgresConnectionTester::class);
    $mockDb->shouldReceive('test')->andReturn(['success' => false, 'version' => null, 'error' => 'Connection refused']);
    app()->instance(PostgresConnectionTester::class, $mockDb);

    $result = (new CheckInfrastructure)();

    expect($result['checks']['migrations']['passed'])->toBeFalse();
    expect($result['checks']['migrations']['message'])->toContain('Skipped');
});

it('passes redis check when not using redis', function () {
    $mockDb = Mockery::mock(PostgresConnectionTester::class);
    $mockDb->shouldReceive('test')->andReturn(['success' => true, 'version' => 'PostgreSQL 16.0', 'error' => null]);
    app()->instance(PostgresConnectionTester::class, $mockDb);

    config([
        'cache.default' => 'database',
        'queue.default' => 'database',
    ]);

    $result = (new CheckInfrastructure)();

    expect($result['checks']['redis']['passed'])->toBeTrue();
    expect($result['checks']['redis']['message'])->toContain('not required');
});

it('checks redis when cache driver is redis', function () {
    $mockDb = Mockery::mock(PostgresConnectionTester::class);
    $mockDb->shouldReceive('test')->andReturn(['success' => true, 'version' => 'PostgreSQL 16.0', 'error' => null]);
    app()->instance(PostgresConnectionTester::class, $mockDb);

    $mockRedis = Mockery::mock(RedisConnectionTester::class);
    $mockRedis->shouldReceive('test')->andReturn(['success' => true, 'version' => 'Redis 7.0', 'error' => null]);
    app()->instance(RedisConnectionTester::class, $mockRedis);

    config(['cache.default' => 'redis']);

    $result = (new CheckInfrastructure)();

    expect($result['checks']['redis']['passed'])->toBeTrue();
});

it('fails redis check when redis connection fails', function () {
    $mockDb = Mockery::mock(PostgresConnectionTester::class);
    $mockDb->shouldReceive('test')->andReturn(['success' => true, 'version' => 'PostgreSQL 16.0', 'error' => null]);
    app()->instance(PostgresConnectionTester::class, $mockDb);

    $mockRedis = Mockery::mock(RedisConnectionTester::class);
    $mockRedis->shouldReceive('test')->andReturn(['success' => false, 'version' => null, 'error' => 'Connection refused']);
    app()->instance(RedisConnectionTester::class, $mockRedis);

    config(['cache.default' => 'redis']);

    $result = (new CheckInfrastructure)();

    expect($result['checks']['redis']['passed'])->toBeFalse();
    expect($result['checks']['redis']['message'])->toContain('Connection failed');
});

it('reports missing tables when some required tables do not exist', function () {
    $mockDb = Mockery::mock(PostgresConnectionTester::class);
    $mockDb->shouldReceive('test')->andReturn(['success' => true, 'version' => 'PostgreSQL 16.0', 'error' => null]);
    app()->instance(PostgresConnectionTester::class, $mockDb);

    // Mock Schema to report some tables as missing
    \Illuminate\Support\Facades\Schema::shouldReceive('hasTable')
        ->with('users')->andReturn(true);
    \Illuminate\Support\Facades\Schema::shouldReceive('hasTable')
        ->with('settings')->andReturn(false);
    \Illuminate\Support\Facades\Schema::shouldReceive('hasTable')
        ->with('stores')->andReturn(true);
    \Illuminate\Support\Facades\Schema::shouldReceive('hasTable')
        ->with('cache')->andReturn(false);
    \Illuminate\Support\Facades\Schema::shouldReceive('hasTable')
        ->with('jobs')->andReturn(true);

    config(['reverb.apps.apps' => [['app_id' => 'test-id']]]);

    $result = (new CheckInfrastructure)();

    expect($result['passed'])->toBeFalse();
    expect($result['checks']['migrations']['passed'])->toBeFalse();
    expect($result['checks']['migrations']['message'])->toContain('Missing tables');
    expect($result['checks']['migrations']['message'])->toContain('settings');
    expect($result['checks']['migrations']['message'])->toContain('cache');
});

it('reports error when Schema check throws an exception', function () {
    $mockDb = Mockery::mock(PostgresConnectionTester::class);
    $mockDb->shouldReceive('test')->andReturn(['success' => true, 'version' => 'PostgreSQL 16.0', 'error' => null]);
    app()->instance(PostgresConnectionTester::class, $mockDb);

    \Illuminate\Support\Facades\Schema::shouldReceive('hasTable')
        ->andThrow(new \RuntimeException('Connection lost'));

    config(['reverb.apps.apps' => [['app_id' => 'test-id']]]);

    $result = (new CheckInfrastructure)();

    expect($result['passed'])->toBeFalse();
    expect($result['checks']['migrations']['passed'])->toBeFalse();
    expect($result['checks']['migrations']['message'])->toContain('Could not check tables');
    expect($result['checks']['migrations']['message'])->toContain('Connection lost');
});

it('fails reverb check when not configured', function () {
    $mockDb = Mockery::mock(PostgresConnectionTester::class);
    $mockDb->shouldReceive('test')->andReturn(['success' => true, 'version' => 'PostgreSQL 16.0', 'error' => null]);
    app()->instance(PostgresConnectionTester::class, $mockDb);

    config([
        'reverb.apps.apps' => [],
        'broadcasting.connections.reverb.app_id' => null,
    ]);

    $result = (new CheckInfrastructure)();

    expect($result['checks']['reverb']['passed'])->toBeFalse();
    expect($result['checks']['reverb']['message'])->toContain('not configured');
});
