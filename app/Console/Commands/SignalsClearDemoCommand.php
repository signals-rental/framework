<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasSignalsBranding;
use App\Models\Store;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;

#[AsCommand(name: 'signals:clear-demo')]
class SignalsClearDemoCommand extends Command
{
    use HasSignalsBranding;

    protected $signature = 'signals:clear-demo
                            {--force : Skip confirmation prompts}';

    protected $description = 'Remove demo data from the database';

    public function handle(): int
    {
        if (! settings('setup.demo_seeded_at')) {
            $this->components->warn('No demo data has been seeded.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && $this->input->isInteractive()) {
            if (! confirm('This will remove all demo data. Continue?', false)) {
                $this->components->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $this->components->info('Removing demo data...');

        $demoStoreNames = ['London Warehouse', 'Manchester Depot', 'Edinburgh Office'];
        Store::query()->whereIn('name', $demoStoreNames)->delete();

        settings()->set('setup.demo_seeded_at', '');

        $this->components->info('Demo data removed.');

        return self::SUCCESS;
    }
}
