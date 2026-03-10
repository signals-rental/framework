<?php

use App\Models\User;
use Livewire\Volt\Volt;

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
