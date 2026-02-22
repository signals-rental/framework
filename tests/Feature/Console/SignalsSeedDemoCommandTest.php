<?php

use App\Models\Store;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('registers the signals:seed-demo command', function () {
    expect(Artisan::all())->toHaveKey('signals:seed-demo');
});

it('fails when setup is not complete', function () {
    config(['signals.setup_complete' => false]);

    $this->artisan('signals:seed-demo')
        ->assertFailed();
});

it('seeds demo stores', function () {
    $this->artisan('signals:seed-demo', ['--force' => true])
        ->assertSuccessful();

    expect(Store::where('name', 'London Warehouse')->exists())->toBeTrue();
    expect(Store::where('name', 'Manchester Depot')->exists())->toBeTrue();
    expect(Store::where('name', 'Edinburgh Office')->exists())->toBeTrue();
});

it('records demo_seeded_at timestamp', function () {
    $this->artisan('signals:seed-demo', ['--force' => true])
        ->assertSuccessful();

    expect(settings('setup.demo_seeded_at'))->not->toBeNull();
    expect(settings('setup.demo_seeded_at'))->not->toBeEmpty();
});

it('fails when demo data already seeded without force', function () {
    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:seed-demo')
        ->assertFailed();
});

it('allows re-seeding with force flag', function () {
    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:seed-demo', ['--force' => true])
        ->assertSuccessful();
});
