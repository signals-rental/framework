<?php

use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $envPath = base_path('.env');
    $this->envExisted = file_exists($envPath);
    if ($this->envExisted) {
        $this->originalEnv = file_get_contents($envPath);
    }
});

afterEach(function () {
    $envPath = base_path('.env');
    if ($this->envExisted) {
        file_put_contents($envPath, $this->originalEnv);
    } elseif (file_exists($envPath)) {
        unlink($envPath);
    }

    Artisan::call('config:clear');
});

it('registers the signals:reset command', function () {
    expect(Artisan::all())->toHaveKey('signals:reset');
});

it('fails without the force flag', function () {
    $this->artisan('signals:reset')
        ->assertFailed();
});

it('truncates settings stores and users with force', function () {
    Setting::create(['group' => 'test', 'key' => 'key', 'value' => 'val']);
    Store::create(['name' => 'Test Store', 'is_default' => true]);
    User::factory()->create();

    expect(Setting::count())->toBeGreaterThan(0);
    expect(Store::count())->toBeGreaterThan(0);
    expect(User::count())->toBeGreaterThan(0);

    $this->artisan('signals:reset', ['--force' => true])
        ->assertSuccessful();

    expect(Setting::count())->toBe(0);
    expect(Store::count())->toBe(0);
    expect(User::count())->toBe(0);
});

it('marks setup as incomplete', function () {
    $this->artisan('signals:reset', ['--force' => true])
        ->assertSuccessful();

    expect(config('signals.setup_complete'))->toBeFalse();
});
