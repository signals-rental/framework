<?php

use App\Services\SystemHealthService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->service = new SystemHealthService;
});

it('returns all health check results', function () {
    $results = $this->service->check();

    expect($results)->toHaveCount(6);
    /** @var array<int, array<string, mixed>> $resultList */
    $resultList = $results;
    expect(collect($resultList)->pluck('name')->all())->toBe([
        'PostgreSQL', 'Redis', 'S3 Storage', 'Queue', 'Scheduler', 'PHP',
    ]);
});

it('checks database status', function () {
    $result = $this->service->checkDatabase();

    expect($result['name'])->toBe('PostgreSQL');
    expect($result['status'])->toBeIn(['ok', 'error']);
});

it('returns redis skipped when not in use', function () {
    config([
        'cache.default' => 'database',
        'queue.default' => 'database',
        'session.driver' => 'database',
    ]);

    $result = $this->service->checkRedis();

    expect($result['name'])->toBe('Redis');
    expect($result['status'])->toBe('skipped');
});

it('returns s3 skipped when using local disk', function () {
    config(['filesystems.default' => 'local']);

    $result = $this->service->checkStorage();

    expect($result['name'])->toBe('S3 Storage');
    expect($result['status'])->toBe('skipped');
});

it('checks queue status', function () {
    $result = $this->service->checkQueue();

    expect($result['name'])->toBe('Queue');
    expect($result['status'])->toBeIn(['ok', 'warning', 'error']);
    expect($result['details'])->toHaveKeys(['driver', 'pending_jobs', 'failed_jobs']);
});

it('returns scheduler warning when no heartbeat', function () {
    Cache::forget('scheduler:last-run');

    $result = $this->service->checkScheduler();

    expect($result['name'])->toBe('Scheduler');
    expect($result['status'])->toBe('warning');
});

it('returns scheduler ok when heartbeat is recent', function () {
    Cache::put('scheduler:last-run', now(), 300);

    $result = $this->service->checkScheduler();

    expect($result['name'])->toBe('Scheduler');
    expect($result['status'])->toBe('ok');
    expect($result['details']['minutes_ago'])->toBe(0);
});

it('returns scheduler warning when heartbeat is stale', function () {
    Cache::put('scheduler:last-run', now()->subMinutes(10), 300);

    $result = $this->service->checkScheduler();

    expect($result['name'])->toBe('Scheduler');
    expect($result['status'])->toBe('warning');
});

it('returns redis ok when cache driver is redis and connection succeeds', function () {
    config(['cache.default' => 'redis']);

    $mock = Mockery::mock(\App\Services\ConnectionTesters\RedisConnectionTester::class);
    $mock->shouldReceive('test')->once()->andReturn([
        'success' => true,
        'version' => 'Redis 7.0',
        'error' => null,
    ]);
    app()->instance(\App\Services\ConnectionTesters\RedisConnectionTester::class, $mock);

    $result = $this->service->checkRedis();

    expect($result['name'])->toBe('Redis');
    expect($result['status'])->toBe('ok');
    expect($result['details']['version'])->toBe('Redis 7.0');
});

it('returns redis error when connection fails', function () {
    config(['cache.default' => 'redis']);

    $mock = Mockery::mock(\App\Services\ConnectionTesters\RedisConnectionTester::class);
    $mock->shouldReceive('test')->once()->andReturn([
        'success' => false,
        'version' => null,
        'error' => 'Connection refused',
    ]);
    app()->instance(\App\Services\ConnectionTesters\RedisConnectionTester::class, $mock);

    $result = $this->service->checkRedis();

    expect($result['status'])->toBe('error');
    expect($result['details']['error'])->toBe('Connection refused');
});

it('returns redis error when tester throws exception', function () {
    config(['queue.default' => 'redis']);

    $mock = Mockery::mock(\App\Services\ConnectionTesters\RedisConnectionTester::class);
    $mock->shouldReceive('test')->once()->andThrow(new \RuntimeException('Redis unavailable'));
    app()->instance(\App\Services\ConnectionTesters\RedisConnectionTester::class, $mock);

    $result = $this->service->checkRedis();

    expect($result['status'])->toBe('error');
    expect($result['details']['error'])->toBe('Redis unavailable');
});

it('returns s3 warning when configured but missing credentials', function () {
    config([
        'filesystems.default' => 's3',
        'filesystems.disks.s3.key' => '',
    ]);

    $result = $this->service->checkStorage();

    expect($result['name'])->toBe('S3 Storage');
    expect($result['status'])->toBe('warning');
    expect($result['details']['reason'])->toContain('missing credentials');
});

it('returns s3 ok when connection succeeds', function () {
    config([
        'filesystems.default' => 's3',
        'filesystems.disks.s3.key' => 'test-key',
        'filesystems.disks.s3.secret' => 'test-secret',
        'filesystems.disks.s3.region' => 'us-east-1',
        'filesystems.disks.s3.bucket' => 'test-bucket',
    ]);

    $mock = Mockery::mock(\App\Services\ConnectionTesters\S3ConnectionTester::class);
    $mock->shouldReceive('test')->once()->andReturn(['success' => true, 'error' => null]);
    app()->instance(\App\Services\ConnectionTesters\S3ConnectionTester::class, $mock);

    $result = $this->service->checkStorage();

    expect($result['status'])->toBe('ok');
});

it('returns s3 error when tester throws', function () {
    config([
        'filesystems.default' => 's3',
        'filesystems.disks.s3.key' => 'test-key',
        'filesystems.disks.s3.secret' => 'test-secret',
        'filesystems.disks.s3.region' => 'us-east-1',
        'filesystems.disks.s3.bucket' => 'test-bucket',
    ]);

    $mock = Mockery::mock(\App\Services\ConnectionTesters\S3ConnectionTester::class);
    $mock->shouldReceive('test')->once()->andThrow(new \RuntimeException('S3 unavailable'));
    app()->instance(\App\Services\ConnectionTesters\S3ConnectionTester::class, $mock);

    $result = $this->service->checkStorage();

    expect($result['status'])->toBe('error');
    expect($result['details']['error'])->toBe('S3 unavailable');
});

it('returns database error when postgres tester throws', function () {
    $mock = Mockery::mock(\App\Services\ConnectionTesters\PostgresConnectionTester::class);
    $mock->shouldReceive('test')->once()->andThrow(new \RuntimeException('Connection refused'));
    app()->instance(\App\Services\ConnectionTesters\PostgresConnectionTester::class, $mock);

    $result = $this->service->checkDatabase();

    expect($result['name'])->toBe('PostgreSQL');
    expect($result['status'])->toBe('error');
    expect($result['details']['error'])->toBe('Connection refused');
});

it('returns queue error when queue check throws', function () {
    // Mock Queue facade to throw
    Illuminate\Support\Facades\Queue::shouldReceive('size')
        ->andThrow(new \RuntimeException('Queue unavailable'));

    $result = $this->service->checkQueue();

    expect($result['name'])->toBe('Queue');
    expect($result['status'])->toBe('error');
    expect($result['details']['error'])->toBe('Queue unavailable');
});

it('checks php info', function () {
    $result = $this->service->checkPhp();

    expect($result['name'])->toBe('PHP');
    expect($result['status'])->toBe('ok');
    expect($result['details']['version'])->toBe(PHP_VERSION);
    expect($result['details'])->toHaveKeys(['memory_limit', 'max_execution_time', 'upload_max_filesize', 'post_max_size']);
});
