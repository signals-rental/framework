<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the security settings page', function () {
    $this->get(route('admin.settings.security'))
        ->assertOk()
        ->assertSee('Security');
});

it('loads security settings with defaults', function () {
    Volt::test('admin.settings.security')
        ->assertSet('passwordMinLength', 8)
        ->assertSet('passwordRequireUppercase', false)
        ->assertSet('passwordRequireNumber', false)
        ->assertSet('passwordRequireSpecial', false)
        ->assertSet('sessionTimeout', 120)
        ->assertSet('maxLoginAttempts', 5)
        ->assertSet('lockoutDuration', 15)
        ->assertSet('require2faAdmin', false)
        ->assertSet('require2faAll', false);
});

it('saves security settings', function () {
    Volt::test('admin.settings.security')
        ->set('passwordMinLength', 12)
        ->set('passwordRequireUppercase', true)
        ->set('passwordRequireNumber', true)
        ->set('passwordRequireSpecial', true)
        ->set('sessionTimeout', 60)
        ->set('maxLoginAttempts', 3)
        ->set('lockoutDuration', 30)
        ->set('require2faAdmin', true)
        ->set('require2faAll', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('security-settings-saved');

    expect(settings('security.password_min_length'))->toBe(12);
    expect(settings('security.password_require_uppercase'))->toBe(true);
    expect(settings('security.session_timeout'))->toBe(60);
    expect(settings('security.max_login_attempts'))->toBe(3);
    expect(settings('security.require_2fa_admin'))->toBe(true);
});

it('validates password min length range', function () {
    Volt::test('admin.settings.security')
        ->set('passwordMinLength', 3)
        ->call('save')
        ->assertHasErrors(['passwordMinLength']);
});

it('validates session timeout range', function () {
    Volt::test('admin.settings.security')
        ->set('sessionTimeout', 2)
        ->call('save')
        ->assertHasErrors(['sessionTimeout']);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.security'))
        ->assertForbidden();
});

it('loads sso enforced roles with empty default', function () {
    Volt::test('admin.settings.security')
        ->assertSet('ssoEnforcedRoles', []);
});

it('saves sso enforced roles', function () {
    $this->seed(RoleSeeder::class);

    Volt::test('admin.settings.security')
        ->set('ssoEnforcedRoles', ['Admin', 'Sales'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('security-settings-saved');

    expect(settings('security.sso_enforced_roles'))->toBe(['Admin', 'Sales']);
});

it('does not offer the owner as an enforceable role', function () {
    $this->seed(RoleSeeder::class);

    // Force an Owner Spatie role to exist to prove the page still excludes it.
    Role::findOrCreate('Owner', 'web');

    Volt::test('admin.settings.security')
        ->assertSee('value="Admin"', escape: false)
        ->assertDontSee('value="Owner"', escape: false);
});

it('rejects an unknown role for sso enforcement', function () {
    $this->seed(RoleSeeder::class);

    Volt::test('admin.settings.security')
        ->set('ssoEnforcedRoles', ['Nonexistent Role'])
        ->call('save')
        ->assertHasErrors(['ssoEnforcedRoles.0']);
});

it('drops a stale enforced role on load when its role was renamed or deleted', function () {
    $this->seed(RoleSeeder::class);

    // Persist a mix of a valid role and a now-deleted one.
    settings()->set('security.sso_enforced_roles', ['Admin', 'Deleted Role'], 'json');

    Volt::test('admin.settings.security')
        ->assertSet('ssoEnforcedRoles', ['Admin']);
});

it('saves successfully even when a stale enforced role is stored', function () {
    $this->seed(RoleSeeder::class);

    settings()->set('security.sso_enforced_roles', ['Admin', 'Deleted Role'], 'json');

    // The stale entry is sanitised on load, so saving is not blocked by `exists`.
    Volt::test('admin.settings.security')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('security-settings-saved');

    expect(settings('security.sso_enforced_roles'))->toBe(['Admin']);
});
