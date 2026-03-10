<?php

use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the system health page', function () {
    $this->get(route('admin.settings.system-health'))
        ->assertOk()
        ->assertSee('System Health');
});

it('displays health check results', function () {
    Volt::test('admin.settings.system-health')
        ->assertSet('checks', fn ($checks) => count($checks) === 6);
});

it('can refresh health checks', function () {
    Volt::test('admin.settings.system-health')
        ->call('refresh')
        ->assertSet('checks', fn ($checks) => count($checks) === 6);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.system-health'))
        ->assertForbidden();
});
