<?php

use App\Models\Store;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('registers the signals:clear-demo command', function () {
    expect(Artisan::all())->toHaveKey('signals:clear-demo');
});

it('succeeds when no demo data exists', function () {
    $this->artisan('signals:clear-demo')
        ->assertSuccessful();
});

it('removes demo stores', function () {
    Store::create(['name' => 'London Warehouse', 'is_default' => false]);
    Store::create(['name' => 'Manchester Depot', 'is_default' => false]);
    Store::create(['name' => 'Edinburgh Office', 'is_default' => false]);
    Store::create(['name' => 'My Real Store', 'is_default' => true]);

    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful();

    expect(Store::where('name', 'London Warehouse')->exists())->toBeFalse();
    expect(Store::where('name', 'Manchester Depot')->exists())->toBeFalse();
    expect(Store::where('name', 'Edinburgh Office')->exists())->toBeFalse();
    expect(Store::where('name', 'My Real Store')->exists())->toBeTrue();
});

it('clears the demo_seeded_at setting', function () {
    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful();

    expect(settings('setup.demo_seeded_at'))->toBeEmpty();
});
