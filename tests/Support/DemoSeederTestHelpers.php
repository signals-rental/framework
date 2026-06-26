<?php

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Invoke DemoDataSeeder::createDemoProducts() in isolation (a private method)
 * without triggering the multi-thousand-row member seed in run(). A silent
 * command is attached so the seeder's $this->command->info() calls succeed.
 */
function seedDemoProducts(): void
{
    $command = new Command;
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

    $seeder = (new DemoDataSeeder)->setContainer(app())->setCommand($command);

    (new ReflectionMethod(DemoDataSeeder::class, 'createDemoProducts'))->invoke($seeder);
}

/**
 * Invoke DemoDataSeeder::createDemoOpportunities() in isolation (a private
 * method) without triggering the multi-thousand-row member seed in run().
 */
function seedDemoOpportunities(): void
{
    $command = new Command;
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

    $seeder = (new DemoDataSeeder)->setContainer(app())->setCommand($command);

    (new ReflectionMethod(DemoDataSeeder::class, 'createDemoOpportunities'))->invoke($seeder);
}
