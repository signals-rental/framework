<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasSignalsBranding;
use App\Services\ConnectionTesters\PostgresConnectionTester;
use App\Services\ConnectionTesters\RedisConnectionTester;
use App\Services\ConnectionTesters\S3ConnectionTester;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

#[AsCommand(name: 'signals:install')]
class SignalsInstallCommand extends Command
{
    use HasSignalsBranding;

    protected $signature = 'signals:install
                            {--force : Skip confirmation prompts}
                            {--db-host= : PostgreSQL host}
                            {--db-port= : PostgreSQL port}
                            {--db-database= : Database name}
                            {--db-username= : Database username}
                            {--db-password= : Database password}
                            {--cache-driver= : Cache/queue driver (redis or database)}
                            {--redis-host= : Redis host}
                            {--redis-port= : Redis port}
                            {--redis-password= : Redis password}
                            {--storage-driver= : File storage driver (s3 or local)}
                            {--s3-provider= : S3 provider (aws, minio, digitalocean, r2, other)}
                            {--s3-bucket= : S3 bucket name}
                            {--s3-region= : S3 region}
                            {--s3-key= : S3 access key ID}
                            {--s3-secret= : S3 secret access key}
                            {--s3-endpoint= : S3 endpoint URL}
                            {--reverb-host= : Reverb websocket host}
                            {--reverb-port= : Reverb websocket port}
                            {--reverb-scheme= : Reverb scheme (http or https)}
                            {--app-url= : Application URL}
                            {--skip-npm : Skip npm install and build}';

    protected $description = 'Configure Signals infrastructure: database, cache, storage, and websockets';

    private bool $interactive = true;

    public function handle(): int
    {
        $this->interactive = $this->input->isInteractive();

        if ($this->interactive) {
            $this->displayWelcomeBanner();
        }

        try {
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
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

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
        if ($this->interactive) {
            $this->components->twoColumnDetail('<fg=white;options=bold>Database Configuration</>', '<fg=gray>PostgreSQL</>');
            $this->newLine();
        }

        $host = $this->optionOrPrompt(
            'db-host',
            'PostgreSQL Host',
            config('database.connections.pgsql.host', '127.0.0.1'),
        );

        $port = (int) $this->optionOrPrompt(
            'db-port',
            'Port',
            (string) config('database.connections.pgsql.port', '5432'),
        );

        $database = $this->optionOrPrompt(
            'db-database',
            'Database Name',
            config('database.connections.pgsql.database', 'signals'),
        );

        $username = $this->optionOrPrompt(
            'db-username',
            'Username',
            config('database.connections.pgsql.username', 'signals'),
        );

        $pass = $this->optionOrPrompt(
            'db-password',
            'Password',
            '',
            required: true,
            secret: true,
        );

        $tester = app(PostgresConnectionTester::class);

        // Test server connectivity
        $serverResult = spin(
            callback: fn () => $tester->testServer($host, $port, $username, $pass),
            message: 'Testing connection...',
        );

        if (! $serverResult['success']) {
            $this->components->error('Could not connect to PostgreSQL: '.$serverResult['error']);

            if ($this->interactive && confirm('Would you like to re-enter the database credentials?', true)) {
                return $this->configureDatabase();
            }

            return false;
        }

        $this->components->info('Connected to PostgreSQL server');

        // Check if database exists, offer to create
        try {
            $dbExists = $tester->databaseExists($host, $port, $username, $pass, $database);
        } catch (\PDOException $e) {
            $this->components->error('Failed to check database existence: '.$e->getMessage());

            return false;
        }

        if (! $dbExists) {
            $shouldCreate = ! $this->interactive
                || $this->option('force')
                || confirm("Database '{$database}' does not exist. Create it?", true);

            if ($shouldCreate) {
                try {
                    spin(
                        callback: fn () => $tester->createDatabase($host, $port, $username, $pass, $database),
                        message: "Creating database '{$database}'...",
                    );
                    $this->components->info("Database '{$database}' created");
                } catch (\PDOException $e) {
                    $this->components->error('Failed to create database: '.$e->getMessage());

                    return false;
                }
            } else {
                $this->components->warn('Database must exist before proceeding. Please create it manually and re-run.');

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
            $this->components->error('Could not connect to database: '.$dbResult['error']);

            return false;
        }

        $this->components->info("Connected to {$dbResult['version']}");

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
        $exitCode = $this->call('migrate', ['--force' => true]);

        if ($exitCode !== self::SUCCESS) {
            $this->components->error('Migrations failed. Check the output above for details.');

            return false;
        }

        $this->components->info('Migrations completed');
        $this->newLine();

        return true;
    }

    protected function configureRedis(): bool
    {
        if ($this->interactive) {
            $this->components->twoColumnDetail('<fg=white;options=bold>Cache & Queue Configuration</>', '');
            $this->newLine();
        }

        $driver = $this->optionOrSelect(
            'cache-driver',
            'Cache and queue driver',
            [
                'redis' => 'Redis (recommended)',
                'database' => 'Database (fallback)',
            ],
            'redis',
        );

        if ($driver === 'database') {
            $this->writeEnvVariables([
                'CACHE_STORE' => 'database',
                'QUEUE_CONNECTION' => 'database',
                'SESSION_DRIVER' => 'database',
            ]);

            $this->components->info('Using database driver for cache, queue, and sessions');
            $this->newLine();

            return true;
        }

        $host = $this->optionOrPrompt(
            'redis-host',
            'Redis Host',
            config('database.redis.default.host', '127.0.0.1'),
        );

        $port = (int) $this->optionOrPrompt(
            'redis-port',
            'Redis Port',
            (string) config('database.redis.default.port', '6379'),
        );

        $pass = $this->optionOrPrompt(
            'redis-password',
            'Redis Password',
            'null',
            required: false,
        );

        $tester = app(RedisConnectionTester::class);
        $result = spin(
            callback: fn () => $tester->test([
                'host' => $host,
                'port' => $port,
                'password' => $pass === 'null' ? null : $pass,
            ]),
            message: 'Testing Redis connection...',
        );

        if (! $result['success']) {
            $this->components->warn('Could not connect to Redis: '.$result['error']);

            if (! $this->interactive) {
                return false;
            }

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

            $this->components->info('Using database driver for cache, queue, and sessions');
            $this->newLine();

            return true;
        }

        $this->components->info("Connected to {$result['version']}");

        $this->writeEnvVariables([
            'CACHE_STORE' => 'redis',
            'QUEUE_CONNECTION' => 'redis',
            'SESSION_DRIVER' => 'redis',
            'REDIS_HOST' => $host,
            'REDIS_PORT' => (string) $port,
            'REDIS_PASSWORD' => $pass,
        ]);

        $this->components->info('Redis configured for cache, queue, and sessions');
        $this->newLine();

        return true;
    }

    protected function configureStorage(): bool
    {
        if ($this->interactive) {
            $this->components->twoColumnDetail('<fg=white;options=bold>File Storage Configuration</>', '');
            $this->newLine();
        }

        $driver = $this->optionOrSelect(
            'storage-driver',
            'File storage driver',
            [
                's3' => 'S3-compatible (recommended for production)',
                'local' => 'Local disk',
            ],
            's3',
        );

        if ($driver === 'local') {
            $this->writeEnvVariables([
                'FILESYSTEM_DISK' => 'local',
            ]);

            $this->components->info('Using local disk for file storage');
            $this->newLine();

            return true;
        }

        return $this->configureS3();
    }

    protected function configureS3(): bool
    {
        $providers = [
            'aws' => 'AWS S3',
            'minio' => 'Minio',
            'digitalocean' => 'DigitalOcean Spaces',
            'r2' => 'Cloudflare R2',
            'other' => 'Other S3-compatible',
        ];

        $provider = $this->optionOrSelect('s3-provider', 'S3-compatible storage provider', $providers, 'aws');

        $usePathStyle = in_array($provider, ['minio', 'r2']);

        $endpointDefaults = [
            'aws' => '',
            'minio' => 'http://localhost:9000',
            'digitalocean' => 'https://{region}.digitaloceanspaces.com',
            'r2' => 'https://{account_id}.r2.cloudflarestorage.com',
            'other' => '',
        ];

        $bucket = $this->optionOrPrompt(
            's3-bucket',
            'Bucket Name',
            config('filesystems.disks.s3.bucket', 'signals-files'),
        );

        $region = $this->selectRegion($provider);

        $accessKey = $this->optionOrPrompt('s3-key', 'Access Key ID', '', required: true);

        $secretKey = $this->optionOrPrompt('s3-secret', 'Secret Access Key', '', required: true, secret: true);

        $endpoint = $this->option('s3-endpoint') ?? '';
        if ($provider !== 'aws' && $endpoint === '') {
            $defaultEndpoint = $endpointDefaults[$provider];

            if ($provider === 'digitalocean') {
                $defaultEndpoint = str_replace('{region}', $region, $defaultEndpoint);
            }

            if ($this->interactive) {
                $endpoint = text(
                    label: 'Endpoint URL',
                    default: $defaultEndpoint,
                    required: true,
                    hint: $provider === 'r2' ? 'Replace {account_id} with your Cloudflare account ID' : '',
                );
            } else {
                if (str_contains($defaultEndpoint, '{')) {
                    throw new RuntimeException(
                        "The --s3-endpoint option is required for {$provider} in non-interactive mode."
                    );
                }
                $endpoint = $defaultEndpoint;
            }
        }

        $tester = app(S3ConnectionTester::class);
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
            $this->components->warn('S3 connection failed: '.$result['error']);

            if (! $this->interactive) {
                return false;
            }

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

                $this->components->info('Using local disk for file storage');
                $this->newLine();

                return true;
            }

            return false;
        }

        $this->components->info('S3 bucket accessible — upload, read, and delete verified');

        $this->writeEnvVariables([
            'FILESYSTEM_DISK' => 's3',
            'AWS_ACCESS_KEY_ID' => $accessKey,
            'AWS_SECRET_ACCESS_KEY' => $secretKey,
            'AWS_DEFAULT_REGION' => $region,
            'AWS_BUCKET' => $bucket,
            'AWS_ENDPOINT' => $endpoint,
            'AWS_USE_PATH_STYLE_ENDPOINT' => $usePathStyle ? 'true' : 'false',
        ]);

        $this->components->info('S3 storage configured');
        $this->newLine();

        return true;
    }

    protected function selectRegion(string $provider): string
    {
        $value = $this->option('s3-region');
        if ($value !== null) {
            return $value;
        }

        if ($provider === 'r2') {
            return 'auto';
        }

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

        if (! $this->interactive) {
            return match ($provider) {
                'digitalocean' => 'nyc3',
                default => config('filesystems.disks.s3.region', 'us-east-1'),
            };
        }

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
            default => text(
                label: 'Region',
                default: config('filesystems.disks.s3.region', 'us-east-1'),
                required: true,
            ),
        };
    }

    protected function configureReverb(): void
    {
        if ($this->interactive) {
            $this->components->twoColumnDetail('<fg=white;options=bold>Websocket Configuration</>', '<fg=gray>Laravel Reverb</>');
            $this->newLine();
        }

        $appId = (string) random_int(100000, 999999);
        $appKey = bin2hex(random_bytes(16));
        $appSecret = bin2hex(random_bytes(32));

        $host = $this->optionOrPrompt(
            'reverb-host',
            'Reverb Host',
            config('reverb.servers.reverb.host', '0.0.0.0'),
            required: false,
        );

        $port = $this->optionOrPrompt(
            'reverb-port',
            'Reverb Port',
            (string) config('reverb.servers.reverb.port', 8080),
            required: false,
        );

        $scheme = $this->optionOrSelect(
            'reverb-scheme',
            'Scheme',
            [
                'http' => 'HTTP',
                'https' => 'HTTPS',
            ],
            'http',
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

        $this->components->info("Reverb configured (App ID: {$appId})");
        $this->components->info('Start with: php artisan reverb:start');
        $this->newLine();
    }

    protected function finalize(): void
    {
        if ($this->interactive) {
            $this->components->twoColumnDetail('<fg=white;options=bold>Finalising</>', '');
            $this->newLine();
        }

        // Generate APP_KEY if not set
        if (empty(config('app.key'))) {
            $exitCode = $this->callSilently('key:generate', ['--force' => true]);
            if ($exitCode !== self::SUCCESS) {
                $this->components->warn('Failed to generate application key — you can run "php artisan key:generate" manually');
            } else {
                $this->components->info('Application key generated');
            }
        }

        // App URL
        $url = $this->optionOrPrompt(
            'app-url',
            'Application URL',
            config('app.url', 'http://localhost'),
        );

        $this->writeEnvVariables([
            'APP_URL' => $url,
            'SIGNALS_INSTALLED' => 'true',
            'SIGNALS_SETUP_COMPLETE' => 'false',
        ]);

        // Install frontend dependencies and build assets
        if ($this->option('skip-npm')) {
            $this->components->info('Skipping npm install and build (--skip-npm)');
        } else {
            try {
                $this->components->info('Installing frontend dependencies...');
                $npmInstall = Process::timeout(120)->run('npm install');
                if ($npmInstall->successful()) {
                    $this->components->info('Dependencies installed');

                    $this->components->info('Building frontend assets...');
                    $npmBuild = Process::timeout(120)->run('npm run build');
                    if ($npmBuild->successful()) {
                        $this->components->info('Frontend assets built');
                    } else {
                        $this->components->warn('npm run build failed — you can run it manually later');
                        $this->outputProcessError($npmBuild);
                    }
                } else {
                    $this->components->warn('npm install failed — you can run it manually later');
                    $this->outputProcessError($npmInstall);
                }
            } catch (\Illuminate\Process\Exceptions\ProcessTimedOutException $e) {
                $this->components->warn('npm timed out — you can run "npm install && npm run build" manually later');
            }
        }

        // Cache config, routes, views
        $cacheFailed = false;
        foreach (['config:cache', 'route:cache', 'view:cache'] as $cacheCommand) {
            if ($this->callSilently($cacheCommand) !== self::SUCCESS) {
                $this->components->warn("{$cacheCommand} failed — you can run it manually later");
                $cacheFailed = true;
            }
        }

        if (! $cacheFailed) {
            $this->components->info('Configuration cached');
        }
        $this->newLine();

        $this->components->info('Infrastructure setup complete!');
        $this->newLine();
        $this->line("  Next: Open your browser and visit: {$url}/setup");
        $this->line('  Or continue setup in the terminal: php artisan signals:setup');
        $this->newLine();
    }

    /**
     * Get a value from a command option, or fall back to an interactive prompt.
     *
     * In non-interactive mode, returns the default if available, or throws
     * a RuntimeException if the value is required and has no default.
     */
    private function optionOrPrompt(
        string $optionName,
        string $label,
        string $default = '',
        bool $required = true,
        bool $secret = false,
    ): string {
        $value = $this->option($optionName);

        if ($value !== null) {
            if ($required && $value === '') {
                throw new RuntimeException("The --{$optionName} option must not be empty.");
            }

            return $value;
        }

        if (! $this->interactive) {
            if ($required && $default === '') {
                throw new RuntimeException("The --{$optionName} option is required in non-interactive mode.");
            }

            return $default;
        }

        if ($secret) {
            return password(label: $label, required: $required);
        }

        return text(label: $label, default: $default, required: $required);
    }

    /**
     * Get a value from a command option, or fall back to an interactive select prompt.
     *
     * Validates that option values are within the allowed set. In non-interactive
     * mode without an option, returns the default.
     *
     * @param  array<string, string>  $options
     */
    private function optionOrSelect(string $optionName, string $label, array $options, string $default): string
    {
        $value = $this->option($optionName);

        if ($value !== null) {
            if (! array_key_exists($value, $options)) {
                throw new RuntimeException(
                    "Invalid value '{$value}' for --{$optionName}. Allowed: ".implode(', ', array_keys($options))
                );
            }

            return $value;
        }

        if (! $this->interactive) {
            return $default;
        }

        return select(label: $label, options: $options, default: $default);
    }

    /**
     * Write variables to the .env file, wrapping filesystem errors in RuntimeException.
     *
     * @param  array<string, string>  $variables
     */
    protected function writeEnvVariables(array $variables): void
    {
        try {
            Env::writeVariables($variables, $this->laravel->basePath('.env'), overwrite: true);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to write to .env file: {$e->getMessage()}. Check file permissions on: ".$this->laravel->basePath('.env'),
                previous: $e,
            );
        }
    }

    /**
     * Output the error from a failed process, if available.
     */
    private function outputProcessError(\Illuminate\Contracts\Process\ProcessResult $process): void
    {
        $errorOutput = trim($process->errorOutput() ?: $process->output());
        if ($errorOutput !== '') {
            $this->line("  <fg=gray>{$errorOutput}</>");
        }
    }
}
