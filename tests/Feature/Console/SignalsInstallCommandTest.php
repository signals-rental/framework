<?php

use Illuminate\Support\Facades\Artisan;

it('registers the signals:install command', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('signals:install');
});

it('has the correct description', function () {
    $command = Artisan::all()['signals:install'];

    expect($command->getDescription())
        ->toBe('Configure Signals infrastructure: database, cache, storage, and websockets');
});

it('accepts a force option', function () {
    $command = Artisan::all()['signals:install'];
    $definition = $command->getDefinition();

    expect($definition->hasOption('force'))->toBeTrue();
});
