<?php

namespace App\Services;

use App\Services\ConnectionTesters\PostgresConnectionTester;
use App\Services\ConnectionTesters\RedisConnectionTester;
use App\Services\ConnectionTesters\S3ConnectionTester;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class SystemHealthService
{
    /**
     * Run all health checks and return results.
     *
     * @return array<int, array{name: string, status: string, details: array<string, mixed>}>
     */
    public function check(): array
    {
        return [
            $this->checkDatabase(),
            $this->checkRedis(),
            $this->checkStorage(),
            $this->checkQueue(),
            $this->checkScheduler(),
            $this->checkPhp(),
        ];
    }

    /**
     * @return array{name: string, status: string, details: array<string, mixed>}
     */
    public function checkDatabase(): array
    {
        try {
            $tester = app(PostgresConnectionTester::class);
            $result = $tester->test([
                'host' => config('database.connections.pgsql.host'),
                'port' => (int) config('database.connections.pgsql.port'),
                'database' => config('database.connections.pgsql.database'),
                'username' => config('database.connections.pgsql.username'),
                'password' => config('database.connections.pgsql.password'),
            ]);

            return [
                'name' => 'PostgreSQL',
                'status' => $result['success'] ? 'ok' : 'error',
                'details' => [
                    'version' => $result['version'],
                    'database' => config('database.connections.pgsql.database'),
                    'host' => config('database.connections.pgsql.host').':'.config('database.connections.pgsql.port'),
                    'error' => $result['error'],
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'name' => 'PostgreSQL',
                'status' => 'error',
                'details' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * @return array{name: string, status: string, details: array<string, mixed>}
     */
    public function checkRedis(): array
    {
        $cacheDriver = config('cache.default');
        $queueDriver = config('queue.default');
        $sessionDriver = config('session.driver');
        $usingRedis = in_array('redis', [$cacheDriver, $queueDriver, $sessionDriver]);

        if (! $usingRedis) {
            return [
                'name' => 'Redis',
                'status' => 'skipped',
                'details' => ['reason' => 'Not in use'],
            ];
        }

        try {
            $tester = app(RedisConnectionTester::class);
            $result = $tester->test([
                'host' => config('database.redis.default.host', '127.0.0.1'),
                'port' => (int) config('database.redis.default.port', 6379),
                'password' => config('database.redis.default.password'),
            ]);

            $services = collect(['cache' => $cacheDriver, 'queue' => $queueDriver, 'sessions' => $sessionDriver])
                ->filter(fn ($driver) => $driver === 'redis')
                ->keys()
                ->implode(', ');

            return [
                'name' => 'Redis',
                'status' => $result['success'] ? 'ok' : 'error',
                'details' => [
                    'version' => $result['version'] ?? null,
                    'services' => $services,
                    'error' => $result['error'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'name' => 'Redis',
                'status' => 'error',
                'details' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * @return array{name: string, status: string, details: array<string, mixed>}
     */
    public function checkStorage(): array
    {
        $disk = config('filesystems.default');

        if ($disk !== 's3') {
            return [
                'name' => 'S3 Storage',
                'status' => 'skipped',
                'details' => ['reason' => 'Using local disk'],
            ];
        }

        if (empty(config('filesystems.disks.s3.key'))) {
            return [
                'name' => 'S3 Storage',
                'status' => 'warning',
                'details' => ['reason' => 'Configured but missing credentials'],
            ];
        }

        try {
            $tester = app(S3ConnectionTester::class);
            $result = $tester->test([
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
                'region' => config('filesystems.disks.s3.region'),
                'bucket' => config('filesystems.disks.s3.bucket'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'use_path_style' => config('filesystems.disks.s3.use_path_style_endpoint', false),
            ]);

            return [
                'name' => 'S3 Storage',
                'status' => $result['success'] ? 'ok' : 'error',
                'details' => [
                    'bucket' => config('filesystems.disks.s3.bucket'),
                    'region' => config('filesystems.disks.s3.region'),
                    'error' => $result['error'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'name' => 'S3 Storage',
                'status' => 'error',
                'details' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * @return array{name: string, status: string, details: array<string, mixed>}
     */
    public function checkQueue(): array
    {
        try {
            $pendingCount = collect(['default', 'webhooks', 'notifications', 'exports', 'imports', 'mail'])
                ->sum(fn (string $queue) => Queue::size($queue));
            $failedCount = app('queue.failer')->count();

            $status = $failedCount > 0 ? 'warning' : 'ok';

            return [
                'name' => 'Queue',
                'status' => $status,
                'details' => [
                    'driver' => config('queue.default'),
                    'pending_jobs' => $pendingCount,
                    'failed_jobs' => $failedCount,
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'name' => 'Queue',
                'status' => 'error',
                'details' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * @return array{name: string, status: string, details: array<string, mixed>}
     */
    public function checkScheduler(): array
    {
        $lastRun = Cache::get('scheduler:last-run');

        if (! $lastRun) {
            return [
                'name' => 'Scheduler',
                'status' => 'warning',
                'details' => ['reason' => 'No heartbeat detected — scheduler may not be running'],
            ];
        }

        $minutesSinceLastRun = (int) abs(now()->diffInMinutes($lastRun));
        $status = $minutesSinceLastRun <= 5 ? 'ok' : 'warning';

        return [
            'name' => 'Scheduler',
            'status' => $status,
            'details' => [
                'last_run' => $lastRun->toIso8601String(),
                'minutes_ago' => $minutesSinceLastRun,
            ],
        ];
    }

    /**
     * @return array{name: string, status: string, details: array<string, mixed>}
     */
    public function checkPhp(): array
    {
        return [
            'name' => 'PHP',
            'status' => 'ok',
            'details' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
        ];
    }
}
