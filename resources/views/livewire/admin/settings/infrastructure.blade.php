<?php

use App\Services\ConnectionTesters\PostgresConnectionTester;
use App\Services\ConnectionTesters\RedisConnectionTester;
use App\Services\ConnectionTesters\S3ConnectionTester;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Infrastructure')] class extends Component {
    // Database
    public string $dbHost = '';
    public int $dbPort = 5432;
    public string $dbDatabase = '';
    public string $dbUsername = '';
    public string $dbPassword = '';

    // Redis
    public string $redisHost = '';
    public int $redisPort = 6379;
    public string $redisPassword = '';

    // S3
    public string $s3Key = '';
    public string $s3Secret = '';
    public string $s3Region = '';
    public string $s3Bucket = '';
    public string $s3Endpoint = '';
    public bool $s3PathStyle = false;

    // Queue
    public string $queueConnection = 'database';

    // Test results
    /** @var array{success: bool, version?: string|null, error?: string|null}|null */
    public ?array $dbTestResult = null;
    /** @var array{success: bool, version?: string|null, error?: string|null}|null */
    public ?array $redisTestResult = null;
    /** @var array{success: bool, error?: string|null}|null */
    public ?array $s3TestResult = null;

    public function mount(): void
    {
        Gate::authorize('owner');

        $this->dbHost = (string) config('database.connections.pgsql.host', '127.0.0.1');
        $this->dbPort = (int) config('database.connections.pgsql.port', 5432);
        $this->dbDatabase = (string) config('database.connections.pgsql.database', 'signals');
        $this->dbUsername = (string) config('database.connections.pgsql.username', 'postgres');
        $this->dbPassword = (string) config('database.connections.pgsql.password', '');

        $this->redisHost = (string) config('database.redis.default.host', '127.0.0.1');
        $this->redisPort = (int) config('database.redis.default.port', 6379);
        $this->redisPassword = (string) config('database.redis.default.password', '');

        $this->s3Key = (string) config('filesystems.disks.s3.key', '');
        $this->s3Secret = (string) config('filesystems.disks.s3.secret', '');
        $this->s3Region = (string) config('filesystems.disks.s3.region', 'us-east-1');
        $this->s3Bucket = (string) config('filesystems.disks.s3.bucket', '');
        $this->s3Endpoint = (string) config('filesystems.disks.s3.endpoint', '');
        $this->s3PathStyle = (bool) config('filesystems.disks.s3.use_path_style_endpoint', false);

        $this->queueConnection = (string) config('queue.default', 'database');
    }

    public function testDatabase(): void
    {
        Gate::authorize('owner');

        $this->dbTestResult = app(PostgresConnectionTester::class)->test([
            'host' => $this->dbHost,
            'port' => $this->dbPort,
            'database' => $this->dbDatabase,
            'username' => $this->dbUsername,
            'password' => $this->dbPassword,
        ]);
    }

    public function testRedis(): void
    {
        Gate::authorize('owner');

        $this->redisTestResult = app(RedisConnectionTester::class)->test([
            'host' => $this->redisHost,
            'port' => $this->redisPort,
            'password' => $this->redisPassword ?: null,
        ]);
    }

    public function testS3(): void
    {
        Gate::authorize('owner');

        $this->s3TestResult = app(S3ConnectionTester::class)->test([
            'key' => $this->s3Key,
            'secret' => $this->s3Secret,
            'region' => $this->s3Region,
            'bucket' => $this->s3Bucket,
            'endpoint' => $this->s3Endpoint ?: null,
            'use_path_style' => $this->s3PathStyle,
        ]);
    }

    public function saveDatabase(): void
    {
        Gate::authorize('owner');

        $this->validate([
            'dbHost' => ['required', 'string', 'max:255'],
            'dbPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'dbDatabase' => ['required', 'string', 'max:255'],
            'dbUsername' => ['required', 'string', 'max:255'],
            'dbPassword' => ['nullable', 'string', 'max:255'],
        ]);

        $this->writeEnv([
            'DB_HOST' => $this->dbHost,
            'DB_PORT' => (string) $this->dbPort,
            'DB_DATABASE' => $this->dbDatabase,
            'DB_USERNAME' => $this->dbUsername,
            'DB_PASSWORD' => $this->dbPassword,
        ]);

        $this->dispatch('infrastructure-saved');
    }

    public function saveRedis(): void
    {
        Gate::authorize('owner');

        $this->validate([
            'redisHost' => ['required', 'string', 'max:255'],
            'redisPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'redisPassword' => ['nullable', 'string', 'max:255'],
        ]);

        $this->writeEnv([
            'REDIS_HOST' => $this->redisHost,
            'REDIS_PORT' => (string) $this->redisPort,
            'REDIS_PASSWORD' => $this->redisPassword ?: 'null',
        ]);

        $this->dispatch('infrastructure-saved');
    }

    public function saveS3(): void
    {
        Gate::authorize('owner');

        $this->validate([
            's3Key' => ['required', 'string', 'max:255'],
            's3Secret' => ['required', 'string', 'max:255'],
            's3Region' => ['required', 'string', 'max:50'],
            's3Bucket' => ['required', 'string', 'max:255'],
            's3Endpoint' => ['nullable', 'string', 'max:500'],
        ]);

        $this->writeEnv([
            'AWS_ACCESS_KEY_ID' => $this->s3Key,
            'AWS_SECRET_ACCESS_KEY' => $this->s3Secret,
            'AWS_DEFAULT_REGION' => $this->s3Region,
            'AWS_BUCKET' => $this->s3Bucket,
            'AWS_ENDPOINT' => $this->s3Endpoint ?: '',
            'AWS_USE_PATH_STYLE_ENDPOINT' => $this->s3PathStyle ? 'true' : 'false',
        ]);

        $this->dispatch('infrastructure-saved');
    }

    public function saveQueue(): void
    {
        Gate::authorize('owner');

        $this->validate([
            'queueConnection' => ['required', 'string', 'in:database,redis'],
        ]);

        $this->writeEnv([
            'QUEUE_CONNECTION' => $this->queueConnection,
        ]);

        $this->dispatch('infrastructure-saved');
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function writeEnv(array $variables): void
    {
        $envPath = app()->basePath('.env');
        $backup = file_get_contents($envPath);

        try {
            Env::writeVariables($variables, $envPath, overwrite: true);
            Artisan::call('config:clear');
        } catch (\Throwable $e) {
            if ($backup !== false) {
                file_put_contents($envPath, $backup);
                Artisan::call('config:clear');
            }

            report($e);

            throw $e;
        }
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="system" title="Infrastructure" description="Configure database, cache, storage, and queue connections.">

        <flux:callout variant="danger" class="mb-6">
            <flux:callout.heading>Danger Zone</flux:callout.heading>
            <flux:callout.text>
                Incorrect settings here can make the application inaccessible. Only modify these values if you are
                certain of the new configuration. Always test connections before saving. Changes are written directly
                to the environment file and take effect immediately.
            </flux:callout.text>
        </flux:callout>

        <div class="space-y-8">

            {{-- PostgreSQL --}}
            <x-signals.form-section title="PostgreSQL Database" description="Primary database connection. Changing these values incorrectly will break the application.">
                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <flux:input wire:model="dbHost" label="Host" placeholder="127.0.0.1" />
                        </div>
                        <flux:input wire:model="dbPort" label="Port" type="number" placeholder="5432" />
                    </div>
                    <flux:input wire:model="dbDatabase" label="Database Name" placeholder="signals" />
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="dbUsername" label="Username" placeholder="postgres" />
                        <flux:input wire:model="dbPassword" label="Password" type="password" placeholder="Password" />
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <flux:button size="sm" wire:click="testDatabase">
                            <span wire:loading.remove wire:target="testDatabase">Test Connection</span>
                            <span wire:loading wire:target="testDatabase">Testing...</span>
                        </flux:button>
                        <flux:button size="sm" variant="danger" wire:click="saveDatabase" wire:confirm="You are about to modify the database configuration. Incorrect values can make the application inaccessible. Changes are written to the .env file and take effect immediately. Continue?">Save Changes</flux:button>

                        @if($dbTestResult)
                            @if($dbTestResult['success'])
                                <span class="text-xs text-green-600 dark:text-green-400">
                                    Connected &mdash; {{ $dbTestResult['version'] }}
                                </span>
                            @else
                                <span class="text-xs text-red-600 dark:text-red-400">
                                    Failed: {{ $dbTestResult['error'] }}
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
            </x-signals.form-section>

            {{-- Redis --}}
            <x-signals.form-section title="Redis" description="Used for caching, sessions, queues, and real-time broadcasting.">
                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <flux:input wire:model="redisHost" label="Host" placeholder="127.0.0.1" />
                        </div>
                        <flux:input wire:model="redisPort" label="Port" type="number" placeholder="6379" />
                    </div>
                    <flux:input wire:model="redisPassword" label="Password" type="password" placeholder="Leave blank if none" />

                    <div class="flex items-center gap-3 pt-2">
                        <flux:button size="sm" wire:click="testRedis">
                            <span wire:loading.remove wire:target="testRedis">Test Connection</span>
                            <span wire:loading wire:target="testRedis">Testing...</span>
                        </flux:button>
                        <flux:button size="sm" variant="danger" wire:click="saveRedis" wire:confirm="You are about to modify the Redis configuration. Incorrect values can make the application inaccessible. Changes are written to the .env file and take effect immediately. Continue?">Save Changes</flux:button>

                        @if($redisTestResult)
                            @if($redisTestResult['success'])
                                <span class="text-xs text-green-600 dark:text-green-400">
                                    Connected &mdash; {{ $redisTestResult['version'] }}
                                </span>
                            @else
                                <span class="text-xs text-red-600 dark:text-red-400">
                                    Failed: {{ $redisTestResult['error'] }}
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
            </x-signals.form-section>

            {{-- S3 Storage --}}
            <x-signals.form-section title="S3 Storage" description="Object storage for file uploads, attachments, and documents.">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="s3Key" label="Access Key ID" />
                        <flux:input wire:model="s3Secret" label="Secret Access Key" type="password" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="s3Region" label="Region" placeholder="us-east-1" />
                        <flux:input wire:model="s3Bucket" label="Bucket" placeholder="my-bucket" />
                    </div>
                    <flux:input wire:model="s3Endpoint" label="Custom Endpoint" placeholder="Leave blank for AWS S3" />
                    <flux:checkbox wire:model="s3PathStyle" label="Use path-style endpoint (for MinIO / custom S3-compatible)" />

                    <div class="flex items-center gap-3 pt-2">
                        <flux:button size="sm" wire:click="testS3">
                            <span wire:loading.remove wire:target="testS3">Test Connection</span>
                            <span wire:loading wire:target="testS3">Testing...</span>
                        </flux:button>
                        <flux:button size="sm" variant="danger" wire:click="saveS3" wire:confirm="You are about to modify the S3 storage configuration. Incorrect values can make the application inaccessible. Changes are written to the .env file and take effect immediately. Continue?">Save Changes</flux:button>

                        @if($s3TestResult)
                            @if($s3TestResult['success'])
                                <span class="text-xs text-green-600 dark:text-green-400">Connected &mdash; upload/download verified</span>
                            @else
                                <span class="text-xs text-red-600 dark:text-red-400">
                                    Failed: {{ $s3TestResult['error'] }}
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
            </x-signals.form-section>

            {{-- Queue --}}
            <x-signals.form-section title="Queue Driver" description="Controls how background jobs are processed.">
                <div class="space-y-4">
                    <flux:select wire:model="queueConnection" label="Connection">
                        <flux:select.option value="database">Database</flux:select.option>
                        <flux:select.option value="redis">Redis (recommended)</flux:select.option>
                    </flux:select>

                    <div class="pt-2">
                        <flux:button size="sm" variant="danger" wire:click="saveQueue" wire:confirm="You are about to modify the queue configuration. Changes are written to the .env file and take effect immediately. Continue?">Save Changes</flux:button>
                    </div>
                </div>
            </x-signals.form-section>
        </div>

        <x-action-message class="mt-4" on="infrastructure-saved">
            Configuration saved. You may need to restart queue workers for changes to take effect.
        </x-action-message>

    </x-admin.layout>
</section>
