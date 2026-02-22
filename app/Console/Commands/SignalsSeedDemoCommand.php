<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasSignalsBranding;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'signals:seed-demo')]
class SignalsSeedDemoCommand extends Command
{
    use HasSignalsBranding;

    protected $signature = 'signals:seed-demo
                            {--force : Skip confirmation prompts}';

    protected $description = 'Seed the database with demo data for testing and evaluation';

    public function handle(): int
    {
        if (! config('signals.setup_complete')) {
            $this->components->error('Setup must be completed before seeding demo data. Run "php artisan signals:setup" first.');

            return self::FAILURE;
        }

        if (settings('setup.demo_seeded_at')) {
            if (! $this->option('force')) {
                $this->components->error('Demo data has already been seeded. Use --force to re-seed.');

                return self::FAILURE;
            }

            $this->components->warn('Re-seeding demo data with --force.');
        }

        $this->components->info('Seeding demo data...');

        $this->call('db:seed', [
            '--class' => DemoDataSeeder::class,
            '--force' => true,
        ]);

        settings()->set('setup.demo_seeded_at', now()->toIso8601String());

        $this->components->info('Demo data seeded successfully.');

        return self::SUCCESS;
    }
}
