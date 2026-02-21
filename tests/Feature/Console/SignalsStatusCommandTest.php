<?php

use Illuminate\Support\Facades\Artisan;

it('registers the signals:status command', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('signals:status');
});

it('has the correct description', function () {
    $command = Artisan::all()['signals:status'];

    expect($command->getDescription())
        ->toBe('Display Signals installation status and connection health');
});
