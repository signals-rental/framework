<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasSignalsBranding;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'signals:reset')]
class SignalsResetCommand extends Command
{
    use HasSignalsBranding;

    protected $signature = 'signals:reset
                            {--force : Required flag to confirm reset}';

    protected $description = 'Reset the Signals setup (removes settings, stores, and users)';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->components->error('The --force flag is required to reset. This action cannot be undone.');

            return self::FAILURE;
        }

        $this->components->warn('Resetting Signals setup...');

        Setting::query()->truncate();
        Store::query()->truncate();
        User::query()->truncate();

        $this->components->info('Database tables truncated (settings, stores, users)');

        Env::writeVariables(
            ['SIGNALS_SETUP_COMPLETE' => 'false'],
            app()->basePath('.env'),
            overwrite: true,
        );

        config(['signals.setup_complete' => false]);

        Artisan::call('config:clear');

        $this->components->info('Setup marked as incomplete');
        $this->newLine();
        $this->line('  Run "php artisan signals:setup" or visit /setup to reconfigure.');
        $this->newLine();

        return self::SUCCESS;
    }
}
