<?php

it('runs successfully', function () {
    $this->artisan('signals:welcome')
        ->assertExitCode(0);
});

it('displays Signals branding', function () {
    $this->artisan('signals:welcome')
        ->expectsOutputToContain('Signals')
        ->assertExitCode(0);
});

it('displays quick start instructions', function () {
    $this->artisan('signals:welcome')
        ->expectsOutputToContain('signals:install')
        ->expectsOutputToContain('signals:status')
        ->assertExitCode(0);
});
