<?php

namespace App\Services\ConnectionTesters;

use Redis;
use RedisException;

class RedisConnectionTester
{
    /**
     * Test a Redis connection.
     *
     * @param  array{host: string, port: int, password: string|null}  $config
     * @return array{success: bool, version: string|null, error: string|null}
     */
    public function test(array $config): array
    {
        try {
            $redis = new Redis;
            $redis->connect($config['host'], $config['port'], 5.0);

            if ($config['password'] && $config['password'] !== 'null') {
                $redis->auth($config['password']);
            }

            $redis->ping();
            $info = $redis->info('server');
            $version = $info['redis_version'] ?? 'unknown';
            $redis->close();

            return [
                'success' => true,
                'version' => 'Redis '.$version,
                'error' => null,
            ];
        } catch (RedisException $e) {
            return [
                'success' => false,
                'version' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
