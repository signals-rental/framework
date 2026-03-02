<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasSignalsBranding;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'signals:welcome', hidden: true)]
class SignalsWelcomeCommand extends Command
{
    use HasSignalsBranding;

    protected $signature = 'signals:welcome';

    protected $description = 'Display the Signals welcome message';

    public function handle(): int
    {
        $this->displaySignalsLogo();

        $this->line('  <fg=white;options=bold>Signals</> — Rental Management Framework');
        $this->line('  Professional rental software. Free. Open Source. Forever.');
        $this->newLine();
        $this->line('  Thank you for making the right choice for your rental business.');
        $this->newLine();

        $this->line('  <fg=green;options=bold>Get started:</>');
        $this->line('  <fg=white>php artisan signals:install</>    Configure your infrastructure');
        $this->line('  <fg=white>php artisan signals:status</>     Check connection health');
        $this->newLine();

        $this->line('  <fg=gray>Documentation:</>  <fg=white>https://docs.signals.rent</>');
        $this->line('  <fg=gray>GitHub:</>         <fg=white>https://github.com/signals-rental/framework</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
