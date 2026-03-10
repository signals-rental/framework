<?php

use App\Services\SystemHealthService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->service = new SystemHealthService;
});

it('returns all health check results', function () {
    $results = $this->service->check();

    expect($results)->toHaveCount(6);
    expect(collect($results)->pluck('name')->all())->toBe([
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

it('checks php info', function () {
    $result = $this->service->checkPhp();

    expect($result['name'])->toBe('PHP');
    expect($result['status'])->toBe('ok');
    expect($result['details']['version'])->toBe(PHP_VERSION);
    expect($result['details'])->toHaveKeys(['memory_limit', 'max_execution_time', 'upload_max_filesize', 'post_max_size']);
});
