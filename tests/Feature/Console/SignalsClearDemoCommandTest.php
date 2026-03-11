<?php

use App\Models\Store;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('registers the signals:clear-demo command', function () {
    expect(Artisan::all())->toHaveKey('signals:clear-demo');
});

it('warns when no demo data has been seeded', function () {
    $this->artisan('signals:clear-demo')
        ->assertSuccessful()
        ->expectsOutputToContain('No demo data');
});

it('removes demo stores', function () {
    Store::factory()->create(['name' => 'London Warehouse', 'is_default' => false]);
    Store::factory()->create(['name' => 'Manchester Depot', 'is_default' => false]);
    Store::factory()->create(['name' => 'Edinburgh Office', 'is_default' => false]);
    Store::factory()->create(['name' => 'My Real Store', 'is_default' => true]);

    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful();

    expect(Store::where('name', 'London Warehouse')->exists())->toBeFalse();
    expect(Store::where('name', 'Manchester Depot')->exists())->toBeFalse();
    expect(Store::where('name', 'Edinburgh Office')->exists())->toBeFalse();
    expect(Store::where('name', 'My Real Store')->exists())->toBeTrue();
});

it('cancels when user declines interactive confirmation', function () {
    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    Store::factory()->create(['name' => 'London Warehouse', 'is_default' => false]);

    $this->artisan('signals:clear-demo')
        ->expectsConfirmation('This will remove all demo data. Continue?', 'no')
        ->assertSuccessful()
        ->expectsOutputToContain('Cancelled');

    // Demo data should NOT have been removed
    expect(Store::where('name', 'London Warehouse')->exists())->toBeTrue();
    expect(settings('setup.demo_seeded_at'))->not->toBeEmpty();
});

it('clears the demo_seeded_at setting', function () {
    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful();

    expect(settings('setup.demo_seeded_at'))->toBeEmpty();
});
