<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasSignalsBranding;
use App\Services\ConnectionTesters\PostgresConnectionTester;
use App\Services\ConnectionTesters\RedisConnectionTester;
use App\Services\ConnectionTesters\S3ConnectionTester;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'signals:status')]
class SignalsStatusCommand extends Command
{
    use HasSignalsBranding;

    protected $signature = 'signals:status';

    protected $description = 'Display Signals installation status and connection health';

    public function handle(): int
    {
        $this->displaySignalsLogo();

        $this->line('  <fg=white;options=bold>Signals</> — Rental Management Framework');
        $this->line('  Professional rental software. Free. Open Source. Forever.');
        $this->newLine();
        $this->line('  Thank you for making the right choice for your rental business.');
        $this->newLine();

        $this->showInstallationState();
        $this->showDatabaseStatus();
        $this->showRedisStatus();
        $this->showS3Status();
        $this->showReverbStatus();
        $this->newLine();

        return self::SUCCESS;
    }

    protected function showInstallationState(): void
    {
        $installed = config('signals.installed', false);
        $setupComplete = config('signals.setup_complete', false);

        $this->components->twoColumnDetail(
            '<fg=white;options=bold>Installation</>',
        );

        $this->components->twoColumnDetail(
            'Infrastructure',
            $installed
                ? '<fg=green>Configured</>'
                : '<fg=yellow>Not configured</> — run <fg=white>php artisan signals:install</>',
        );

        $this->components->twoColumnDetail(
            'Application Setup',
            $setupComplete
                ? '<fg=green>Complete</>'
                : '<fg=yellow>Pending</> — run <fg=white>php artisan signals:setup</>',
        );

        $this->components->twoColumnDetail(
            'Environment',
            config('app.env', 'production'),
        );

        $this->components->twoColumnDetail(
            'Debug Mode',
            config('app.debug', false) ? '<fg=yellow>Enabled</>' : '<fg=green>Disabled</>',
        );

        $this->components->twoColumnDetail(
            'URL',
            config('app.url', 'not set'),
        );

        $this->newLine();
    }

    protected function showDatabaseStatus(): void
    {
        $this->components->twoColumnDetail(
            '<fg=white;options=bold>PostgreSQL</>',
        );

        if (empty(config('database.connections.pgsql.password'))) {
            $this->components->twoColumnDetail(
                'Status',
                '<fg=gray>Not configured</>',
            );
            $this->newLine();

            return;
        }

        $tester = app(PostgresConnectionTester::class);
        $result = $tester->test([
            'host' => config('database.connections.pgsql.host'),
            'port' => (int) config('database.connections.pgsql.port'),
            'database' => config('database.connections.pgsql.database'),
            'username' => config('database.connections.pgsql.username'),
            'password' => config('database.connections.pgsql.password'),
        ]);

        if ($result['success']) {
            $this->components->twoColumnDetail('Status', '<fg=green>Connected</>');
            $this->components->twoColumnDetail('Version', $result['version']);
            $this->components->twoColumnDetail('Database', config('database.connections.pgsql.database'));
            $this->components->twoColumnDetail('Host', config('database.connections.pgsql.host').':'.config('database.connections.pgsql.port'));
        } else {
            $this->components->twoColumnDetail('Status', '<fg=red>Disconnected</>');
            $this->components->twoColumnDetail('Error', $result['error']);
        }

        $this->newLine();
    }

    protected function showRedisStatus(): void
    {
        $this->components->twoColumnDetail(
            '<fg=white;options=bold>Redis</>',
        );

        $cacheDriver = config('cache.default');
        $queueDriver = config('queue.default');
        $sessionDriver = config('session.driver');

        $usingRedis = in_array('redis', [$cacheDriver, $queueDriver, $sessionDriver]);

        if (! $usingRedis) {
            $this->components->twoColumnDetail('Status', '<fg=gray>Not in use</> (using database driver)');
            $this->newLine();

            return;
        }

        $tester = app(RedisConnectionTester::class);
        $result = $tester->test([
            'host' => config('database.redis.default.host', '127.0.0.1'),
            'port' => (int) config('database.redis.default.port', 6379),
            'password' => config('database.redis.default.password'),
        ]);

        if ($result['success']) {
            $this->components->twoColumnDetail('Status', '<fg=green>Connected</>');
            $this->components->twoColumnDetail('Version', $result['version']);
        } else {
            $this->components->twoColumnDetail('Status', '<fg=red>Disconnected</>');
            $this->components->twoColumnDetail('Error', $result['error']);
        }

        $services = [];
        if ($cacheDriver === 'redis') {
            $services[] = 'cache';
        }
        if ($queueDriver === 'redis') {
            $services[] = 'queue';
        }
        if ($sessionDriver === 'redis') {
            $services[] = 'sessions';
        }

        $this->components->twoColumnDetail('Services', implode(', ', $services));
        $this->newLine();
    }

    protected function showS3Status(): void
    {
        $this->components->twoColumnDetail(
            '<fg=white;options=bold>S3 Storage</>',
        );

        $disk = config('filesystems.default');

        if ($disk !== 's3') {
            $this->components->twoColumnDetail('Status', '<fg=gray>Not configured</> (using local disk)');
            $this->newLine();

            return;
        }

        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');

        if (empty(config('filesystems.disks.s3.key'))) {
            $this->components->twoColumnDetail('Status', '<fg=yellow>Configured but missing credentials</>');
            $this->newLine();

            return;
        }

        $tester = app(S3ConnectionTester::class);
        $result = $tester->test([
            'key' => config('filesystems.disks.s3.key'),
            'secret' => config('filesystems.disks.s3.secret'),
            'region' => $region,
            'bucket' => $bucket,
            'endpoint' => config('filesystems.disks.s3.endpoint'),
            'use_path_style' => config('filesystems.disks.s3.use_path_style_endpoint', false),
        ]);

        if ($result['success']) {
            $this->components->twoColumnDetail('Status', '<fg=green>Connected</>');
            $this->components->twoColumnDetail('Bucket', $bucket);
            $this->components->twoColumnDetail('Region', $region);
        } else {
            $this->components->twoColumnDetail('Status', '<fg=red>Disconnected</>');
            $this->components->twoColumnDetail('Error', $result['error']);
        }

        $this->newLine();
    }

    protected function showReverbStatus(): void
    {
        $this->components->twoColumnDetail(
            '<fg=white;options=bold>Reverb (Websockets)</>',
        );

        $broadcast = config('broadcasting.default');

        if ($broadcast !== 'reverb') {
            $this->components->twoColumnDetail('Status', '<fg=gray>Not configured</>');
            $this->newLine();

            return;
        }

        $host = config('reverb.servers.reverb.hostname', 'not set');
        $port = config('reverb.servers.reverb.port', 'not set');
        $appId = config('reverb.apps.apps.0.app_id', 'not set');

        $this->components->twoColumnDetail('Status', '<fg=green>Configured</>');
        $this->components->twoColumnDetail('Host', "{$host}:{$port}");
        $this->components->twoColumnDetail('App ID', $appId);

        $this->newLine();
    }
}
