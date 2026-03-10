<?php

use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the seeders page', function () {
    $this->get(route('admin.settings.seeders'))
        ->assertOk()
        ->assertSee('Database Seeders');
});

it('shows seeder status as seeded when data exists', function () {
    Volt::test('admin.settings.seeders')
        ->assertSee('PermissionSeeder')
        ->assertSee('RoleSeeder')
        ->assertSee('StoreSeeder')
        ->assertSee('DemoDataSeeder');
});

it('detects permissions as seeded', function () {
    $component = Volt::test('admin.settings.seeders');

    $seeders = $component->get('seeders');
    expect($seeders['permissions']['seeded'])->toBeTrue();
    expect($seeders['roles']['seeded'])->toBeTrue();
});

it('detects stores as not seeded when no stores exist', function () {
    $component = Volt::test('admin.settings.seeders');

    $seeders = $component->get('seeders');
    expect($seeders['stores']['seeded'])->toBeFalse();
    expect($seeders['demo']['seeded'])->toBeFalse();
});

it('runs an individual seeder', function () {
    expect(Store::query()->exists())->toBeFalse();

    Volt::test('admin.settings.seeders')
        ->call('seed', 'stores')
        ->assertDispatched('seeder-completed');

    expect(Store::query()->exists())->toBeTrue();
    expect(Store::query()->where('name', 'Main Warehouse')->exists())->toBeTrue();
});

it('runs all default seeders', function () {
    // Clear permissions and roles to verify re-seeding works
    Permission::query()->delete();
    Role::query()->delete();

    Volt::test('admin.settings.seeders')
        ->call('seedAll')
        ->assertDispatched('seeder-completed');

    expect(Permission::query()->exists())->toBeTrue();
    expect(Role::query()->where('name', 'Admin')->exists())->toBeTrue();
});

it('hides run button for already seeded seeders', function () {
    Volt::test('admin.settings.seeders')
        ->assertDontSee('Run Default Seeders');
});

it('shows run button for unseeded seeders', function () {
    Permission::query()->delete();
    Role::query()->delete();

    Volt::test('admin.settings.seeders')
        ->assertSee('Run Default Seeders');
});

it('shows error for invalid seeder key', function () {
    Volt::test('admin.settings.seeders')
        ->call('seed', 'nonexistent')
        ->assertHasErrors(['seeder']);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.seeders'))
        ->assertForbidden();
});
