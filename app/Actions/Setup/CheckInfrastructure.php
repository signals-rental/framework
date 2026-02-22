<?php

namespace App\Actions\Setup;

use App\Services\ConnectionTesters\PostgresConnectionTester;
use App\Services\ConnectionTesters\RedisConnectionTester;
use Illuminate\Support\Facades\Schema;

class CheckInfrastructure
{
    /**
     * Run infrastructure pre-flight checks using the existing connection testers.
     *
     * @return array{passed: bool, checks: array<string, array{passed: bool, message: string}>}
     */
    public function __invoke(): array
    {
        $checks = [];

        $checks['database'] = $this->checkDatabase();
        $checks['migrations'] = $this->checkMigrations($checks['database']['passed']);
        $checks['redis'] = $this->checkRedis();
        $checks['reverb'] = $this->checkReverb();

        $passed = collect($checks)->every(fn (array $check) => $check['passed']);

        return [
            'passed' => $passed,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{passed: bool, message: string}
     */
    private function checkDatabase(): array
    {
        $tester = app(PostgresConnectionTester::class);

        $config = [
            'host' => config('database.connections.pgsql.host', '127.0.0.1'),
            'port' => (int) config('database.connections.pgsql.port', 5432),
            'database' => config('database.connections.pgsql.database', 'signals'),
            'username' => config('database.connections.pgsql.username', 'signals'),
            'password' => config('database.connections.pgsql.password', ''),
        ];

        $result = $tester->test($config);

        if ($result['success']) {
            return ['passed' => true, 'message' => $result['version'] ?? 'Connected'];
        }

        return ['passed' => false, 'message' => 'Connection failed: '.$result['error']];
    }

    /**
     * @return array{passed: bool, message: string}
     */
    private function checkMigrations(bool $databaseConnected): array
    {
        if (! $databaseConnected) {
            return ['passed' => false, 'message' => 'Skipped (database not connected)'];
        }

        $requiredTables = ['users', 'settings', 'stores', 'cache', 'jobs'];
        $missing = [];

        try {
            foreach ($requiredTables as $table) {
                if (! Schema::hasTable($table)) {
                    $missing[] = $table;
                }
            }
        } catch (\Throwable $e) {
            return ['passed' => false, 'message' => 'Could not check tables: '.$e->getMessage()];
        }

        if (count($missing) > 0) {
            return [
                'passed' => false,
                'message' => 'Missing tables: '.implode(', ', $missing).'. Run php artisan migrate.',
            ];
        }

        return ['passed' => true, 'message' => count($requiredTables).' required tables found'];
    }

    /**
     * @return array{passed: bool, message: string}
     */
    private function checkRedis(): array
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            return ['passed' => true, 'message' => 'Using database driver (Redis not required)'];
        }

        $tester = app(RedisConnectionTester::class);

        $config = [
            'host' => config('database.redis.default.host', '127.0.0.1'),
            'port' => (int) config('database.redis.default.port', 6379),
            'password' => config('database.redis.default.password'),
        ];

        $result = $tester->test($config);

        if ($result['success']) {
            return ['passed' => true, 'message' => $result['version'] ?? 'Connected'];
        }

        return ['passed' => false, 'message' => 'Connection failed: '.$result['error']];
    }

    /**
     * @return array{passed: bool, message: string}
     */
    private function checkReverb(): array
    {
        $appId = config('reverb.apps.apps.0.app_id')
            ?? config('broadcasting.connections.reverb.app_id');

        if (empty($appId)) {
            return ['passed' => false, 'message' => 'Reverb not configured (missing app_id)'];
        }

        return ['passed' => true, 'message' => 'Configured (App ID: '.$appId.')'];
    }
}
