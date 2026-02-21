<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasSignalsBranding;
use App\Services\ConnectionTesters\PostgresConnectionTester;
use App\Services\ConnectionTesters\RedisConnectionTester;
use App\Services\ConnectionTesters\S3ConnectionTester;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'signals:install')]
class SignalsInstallCommand extends Command
{
    use HasSignalsBranding;

    protected $signature = 'signals:install
                            {--force : Skip confirmation prompts}';

    protected $description = 'Configure Signals infrastructure: database, cache, storage, and websockets';

    public function handle(): int
    {
        $this->displayWelcomeBanner();

        if (! $this->configureDatabase()) {
            return self::FAILURE;
        }

        if (! $this->configureRedis()) {
            return self::FAILURE;
        }

        if (! $this->configureStorage()) {
            return self::FAILURE;
        }

        $this->configureReverb();

        $this->finalize();

        return self::SUCCESS;
    }

    protected function displayWelcomeBanner(): void
    {
        $this->displaySignalsLogo();

        $this->line('  <fg=white;options=bold>Signals</> — Rental Management Framework');
        $this->line('  Professional rental software. Free. Open Source. Forever.');
        $this->newLine();
        $this->line('  Thank you for making the right choice for your rental business.');
        $this->newLine();
        $this->line('  This wizard will configure your infrastructure.');
        $this->line('  You can re-run this at any time with: <fg=white>php artisan signals:install</>');
        $this->newLine();
    }

    protected function configureDatabase(): bool
    {
        $this->components->twoColumnDetail('<fg=white;options=bold>Database Configuration</>', '<fg=gray>PostgreSQL</>');
        $this->newLine();

        $host = text(
            label: 'PostgreSQL Host',
            default: config('database.connections.pgsql.host', '127.0.0.1'),
            required: true,
        );

        $port = (int) text(
            label: 'Port',
            default: (string) config('database.connections.pgsql.port', '5432'),
            required: true,
        );

        $database = text(
            label: 'Database Name',
            default: config('database.connections.pgsql.database', 'signals'),
            required: true,
        );

        $username = text(
            label: 'Username',
            default: config('database.connections.pgsql.username', 'signals'),
            required: true,
        );

        $pass = password(
            label: 'Password',
            required: true,
        );

        $tester = new PostgresConnectionTester;

        // Test server connectivity
        $serverResult = spin(
            callback: fn () => $tester->testServer($host, $port, $username, $pass),
            message: 'Testing connection...',
        );

        if (! $serverResult['success']) {
            error('Could not connect to PostgreSQL: '.$serverResult['error']);

            if (confirm('Would you like to re-enter the database credentials?', true)) {
                return $this->configureDatabase();
            }

            return false;
        }

        info('Connected to PostgreSQL server');

        // Check if database exists, offer to create
        $dbExists = $tester->databaseExists($host, $port, $username, $pass, $database);

        if (! $dbExists) {
            if (confirm("Database '{$database}' does not exist. Create it?", true)) {
                try {
                    spin(
                        callback: fn () => $tester->createDatabase($host, $port, $username, $pass, $database),
                        message: "Creating database '{$database}'...",
                    );
                    info("Database '{$database}' created");
                } catch (\Exception $e) {
                    error('Failed to create database: '.$e->getMessage());

                    return false;
                }
            } else {
                warning('Database must exist before proceeding. Please create it manually and re-run.');

                return false;
            }
        }

        // Test full connection to the target database
        $dbResult = spin(
            callback: fn () => $tester->test([
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $pass,
            ]),
            message: 'Verifying database connection...',
        );

        if (! $dbResult['success']) {
            error('Could not connect to database: '.$dbResult['error']);

            return false;
        }

        info("Connected to {$dbResult['version']}");

        // Write to .env
        $this->writeEnvVariables([
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $host,
            'DB_PORT' => (string) $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $pass,
        ]);

        // Reconfigure runtime so migrations can run
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => $host,
            'database.connections.pgsql.port' => (string) $port,
            'database.connections.pgsql.database' => $database,
            'database.connections.pgsql.username' => $username,
            'database.connections.pgsql.password' => $pass,
        ]);
        app('db')->purge('pgsql');

        // Run migrations
        $this->line('');
        $this->components->info('Running migrations...');
        $this->call('migrate', ['--force' => true]);

        info('Migrations completed');
        $this->newLine();

        return true;
    }

    protected function configureRedis(): bool
    {
        $this->components->twoColumnDetail('<fg=white;options=bold>Cache & Queue Configuration</>', '');
        $this->newLine();

        $driver = select(
            label: 'Cache and queue driver',
            options: [
                'redis' => 'Redis (recommended)',
                'database' => 'Database (fallback)',
            ],
            default: 'redis',
        );

        if ($driver === 'database') {
            $this->writeEnvVariables([
                'CACHE_STORE' => 'database',
                'QUEUE_CONNECTION' => 'database',
                'SESSION_DRIVER' => 'database',
            ]);

            info('Using database driver for cache, queue, and sessions');
            $this->newLine();

            return true;
        }

        $host = text(
            label: 'Redis Host',
            default: config('database.redis.default.host', '127.0.0.1'),
            required: true,
        );

        $port = (int) text(
            label: 'Redis Port',
            default: (string) config('database.redis.default.port', '6379'),
            required: true,
        );

        $pass = text(
            label: 'Redis Password',
            default: 'null',
            hint: 'Leave as "null" for no password',
        );

        $tester = new RedisConnectionTester;
        $result = spin(
            callback: fn () => $tester->test([
                'host' => $host,
                'port' => $port,
                'password' => $pass === 'null' ? null : $pass,
            ]),
            message: 'Testing Redis connection...',
        );

        if (! $result['success']) {
            warning('Could not connect to Redis: '.$result['error']);

            $fallback = select(
                label: 'How would you like to proceed?',
                options: [
                    'retry' => 'Re-enter Redis credentials',
                    'database' => 'Fall back to database driver',
                ],
            );

            if ($fallback === 'retry') {
                return $this->configureRedis();
            }

            $this->writeEnvVariables([
                'CACHE_STORE' => 'database',
                'QUEUE_CONNECTION' => 'database',
                'SESSION_DRIVER' => 'database',
            ]);

            info('Using database driver for cache, queue, and sessions');
            $this->newLine();

            return true;
        }

        info("Connected to {$result['version']}");

        $this->writeEnvVariables([
            'CACHE_STORE' => 'redis',
            'QUEUE_CONNECTION' => 'redis',
            'SESSION_DRIVER' => 'redis',
            'REDIS_HOST' => $host,
            'REDIS_PORT' => (string) $port,
            'REDIS_PASSWORD' => $pass,
        ]);

        info('Redis configured for cache, queue, and sessions');
        $this->newLine();

        return true;
    }

    protected function configureStorage(): bool
    {
        $this->components->twoColumnDetail('<fg=white;options=bold>File Storage Configuration</>', '');
        $this->newLine();

        $driver = select(
            label: 'File storage driver',
            options: [
                's3' => 'S3-compatible (recommended for production)',
                'local' => 'Local disk',
            ],
            default: 's3',
        );

        if ($driver === 'local') {
            $this->writeEnvVariables([
                'FILESYSTEM_DISK' => 'local',
            ]);

            info('Using local disk for file storage');
            $this->newLine();

            return true;
        }

        return $this->configureS3();
    }

    protected function configureS3(): bool
    {
        $provider = select(
            label: 'S3-compatible storage provider',
            options: [
                'aws' => 'AWS S3',
                'minio' => 'Minio',
                'digitalocean' => 'DigitalOcean Spaces',
                'r2' => 'Cloudflare R2',
                'other' => 'Other S3-compatible',
            ],
        );

        $usePathStyle = in_array($provider, ['minio', 'r2']);

        $endpointDefaults = [
            'aws' => '',
            'minio' => 'http://localhost:9000',
            'digitalocean' => 'https://{region}.digitaloceanspaces.com',
            'r2' => 'https://{account_id}.r2.cloudflarestorage.com',
            'other' => '',
        ];

        $bucket = text(
            label: 'Bucket Name',
            default: config('filesystems.disks.s3.bucket', 'signals-files'),
            required: true,
        );

        $region = $this->selectRegion($provider);

        $accessKey = text(
            label: 'Access Key ID',
            required: true,
        );

        $secretKey = password(
            label: 'Secret Access Key',
            required: true,
        );

        $endpoint = '';
        if ($provider !== 'aws') {
            $defaultEndpoint = $endpointDefaults[$provider];

            if ($provider === 'digitalocean') {
                $defaultEndpoint = str_replace('{region}', $region, $defaultEndpoint);
            }

            $endpoint = text(
                label: 'Endpoint URL',
                default: $defaultEndpoint,
                required: $provider !== 'aws',
                hint: $provider === 'r2' ? 'Replace {account_id} with your Cloudflare account ID' : '',
            );
        }

        $tester = new S3ConnectionTester;
        $result = spin(
            callback: fn () => $tester->test([
                'key' => $accessKey,
                'secret' => $secretKey,
                'region' => $region,
                'bucket' => $bucket,
                'endpoint' => $endpoint ?: null,
                'use_path_style' => $usePathStyle,
            ]),
            message: 'Testing S3 connection (upload, read, delete)...',
        );

        if (! $result['success']) {
            warning('S3 connection failed: '.$result['error']);

            $action = select(
                label: 'How would you like to proceed?',
                options: [
                    'retry' => 'Re-enter S3 credentials',
                    'local' => 'Fall back to local disk',
                    'abort' => 'Abort installation',
                ],
            );

            if ($action === 'retry') {
                return $this->configureS3();
            }

            if ($action === 'local') {
                $this->writeEnvVariables([
                    'FILESYSTEM_DISK' => 'local',
                ]);

                info('Using local disk for file storage');
                $this->newLine();

                return true;
            }

            return false;
        }

        info('S3 bucket accessible — upload, read, and delete verified');

        $this->writeEnvVariables([
            'FILESYSTEM_DISK' => 's3',
            'AWS_ACCESS_KEY_ID' => $accessKey,
            'AWS_SECRET_ACCESS_KEY' => $secretKey,
            'AWS_DEFAULT_REGION' => $region,
            'AWS_BUCKET' => $bucket,
            'AWS_ENDPOINT' => $endpoint,
            'AWS_USE_PATH_STYLE_ENDPOINT' => $usePathStyle ? 'true' : 'false',
        ]);

        info('S3 storage configured');
        $this->newLine();

        return true;
    }

    protected function selectRegion(string $provider): string
    {
        $awsRegions = [
            'us-east-1' => 'US East (N. Virginia)',
            'us-east-2' => 'US East (Ohio)',
            'us-west-1' => 'US West (N. California)',
            'us-west-2' => 'US West (Oregon)',
            'eu-west-1' => 'EU (Ireland)',
            'eu-west-2' => 'EU (London)',
            'eu-west-3' => 'EU (Paris)',
            'eu-central-1' => 'EU (Frankfurt)',
            'eu-north-1' => 'EU (Stockholm)',
            'ap-southeast-1' => 'Asia Pacific (Singapore)',
            'ap-southeast-2' => 'Asia Pacific (Sydney)',
            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
            'ap-northeast-2' => 'Asia Pacific (Seoul)',
            'ap-south-1' => 'Asia Pacific (Mumbai)',
            'ca-central-1' => 'Canada (Central)',
            'sa-east-1' => 'South America (São Paulo)',
        ];

        $doRegions = [
            'nyc3' => 'New York 3',
            'sfo3' => 'San Francisco 3',
            'ams3' => 'Amsterdam 3',
            'sgp1' => 'Singapore 1',
            'fra1' => 'Frankfurt 1',
            'syd1' => 'Sydney 1',
        ];

        return match ($provider) {
            'aws' => select(
                label: 'AWS Region',
                options: $awsRegions,
                default: config('filesystems.disks.s3.region', 'us-east-1'),
            ),
            'digitalocean' => select(
                label: 'DigitalOcean Region',
                options: $doRegions,
                default: 'nyc3',
            ),
            'r2' => 'auto',
            default => text(
                label: 'Region',
                default: config('filesystems.disks.s3.region', 'us-east-1'),
                required: true,
            ),
        };
    }

    protected function configureReverb(): void
    {
        $this->components->twoColumnDetail('<fg=white;options=bold>Websocket Configuration</>', '<fg=gray>Laravel Reverb</>');
        $this->newLine();

        $appId = (string) random_int(100000, 999999);
        $appKey = bin2hex(random_bytes(16));
        $appSecret = bin2hex(random_bytes(32));

        $host = text(
            label: 'Reverb Host',
            default: config('reverb.servers.reverb.host', '0.0.0.0'),
        );

        $port = text(
            label: 'Reverb Port',
            default: (string) config('reverb.servers.reverb.port', 8080),
        );

        $scheme = select(
            label: 'Scheme',
            options: [
                'http' => 'HTTP',
                'https' => 'HTTPS',
            ],
            default: 'http',
        );

        $this->writeEnvVariables([
            'BROADCAST_CONNECTION' => 'reverb',
            'REVERB_APP_ID' => $appId,
            'REVERB_APP_KEY' => $appKey,
            'REVERB_APP_SECRET' => $appSecret,
            'REVERB_HOST' => $host,
            'REVERB_PORT' => $port,
            'REVERB_SCHEME' => $scheme,
        ]);

        info("Reverb configured (App ID: {$appId})");
        info('Start with: php artisan reverb:start');
        $this->newLine();
    }

    protected function finalize(): void
    {
        $this->components->twoColumnDetail('<fg=white;options=bold>Finalising</>', '');
        $this->newLine();

        // Generate APP_KEY if not set
        if (empty(config('app.key'))) {
            $this->callSilently('key:generate', ['--force' => true]);
            info('Application key generated');
        }

        // App URL
        $url = text(
            label: 'Application URL',
            default: config('app.url', 'http://localhost'),
            required: true,
        );

        $this->writeEnvVariables([
            'APP_URL' => $url,
            'SIGNALS_INSTALLED' => 'true',
            'SIGNALS_SETUP_COMPLETE' => 'false',
        ]);

        // Cache config, routes, views
        $this->callSilently('config:cache');
        $this->callSilently('route:cache');
        $this->callSilently('view:cache');

        info('Configuration cached');
        $this->newLine();

        note(<<<NOTE
            Infrastructure setup complete!

            Next: Open your browser and visit:
            {$url}/setup

            Or continue setup in the terminal:
            php artisan signals:setup
        NOTE);

        $this->newLine();
    }

    protected function writeEnvVariables(array $variables): void
    {
        Env::writeVariables($variables, $this->laravel->basePath('.env'), overwrite: true);
    }
}
