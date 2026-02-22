<?php

use App\Actions\Setup\CheckInfrastructure;

beforeEach(function () {
    $this->mock(CheckInfrastructure::class, function ($mock) {
        $mock->shouldReceive('__invoke')->andReturn([
            'passed' => true,
            'checks' => [
                'database' => ['passed' => true, 'message' => 'Connected'],
                'redis' => ['passed' => true, 'message' => 'Connected'],
                'reverb' => ['passed' => true, 'message' => 'Configured'],
            ],
        ]);
    });
});

it('returns 404 when signals is not installed', function () {
    config(['signals.installed' => false, 'signals.setup_complete' => false]);

    $this->get('/setup')->assertNotFound();
});

it('redirects to dashboard when setup is already complete', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $this->get('/setup')->assertRedirect(route('dashboard'));
});

it('allows access when installed but setup not complete', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => false]);

    $this->get('/setup')->assertOk();
});
