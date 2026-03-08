<?php

use App\Models\User;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

// ──────────────────────────────────────────────
// Login flow
// ──────────────────────────────────────────────

it('logs in normally when 2FA is not enabled', function () {
    $user = User::factory()->create();

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

it('redirects to 2FA challenge after password when 2FA is enabled', function () {
    $user = User::factory()->withTwoFactor()->create();

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    expect(Session::get('two_factor_user_id'))->toBe($user->id);
});

it('stores the user id in session before the challenge', function () {
    $user = User::factory()->withTwoFactor()->create();

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    expect(Session::get('two_factor_user_id'))->toBe($user->id);
});

// ──────────────────────────────────────────────
// Two-factor challenge — TOTP
// ──────────────────────────────────────────────

it('completes login with a valid TOTP code', function () {
    $user = User::factory()->withTwoFactor()->create();

    Session::put('two_factor_user_id', $user->id);

    $code = app(Google2FA::class)->getCurrentOtp((string) $user->two_factor_secret);

    Volt::test('auth.two-factor-challenge')
        ->set('code', $code)
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(Session::get('two_factor_confirmed'))->toBeTrue();
});

it('rejects an invalid TOTP code', function () {
    $user = User::factory()->withTwoFactor()->create();

    Session::put('two_factor_user_id', $user->id);

    Volt::test('auth.two-factor-challenge')
        ->set('code', '000000')
        ->call('authenticate')
        ->assertHasErrors(['code']);

    $this->assertGuest();
});

it('redirects to login when no two_factor_user_id in session', function () {
    Volt::test('auth.two-factor-challenge')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

// ──────────────────────────────────────────────
// Two-factor challenge — recovery codes
// ──────────────────────────────────────────────

it('completes login with a valid recovery code', function () {
    $user = User::factory()->withTwoFactor()->create();

    $codes = json_decode((string) $user->two_factor_recovery_codes, true);
    $validCode = $codes[0];

    Session::put('two_factor_user_id', $user->id);

    Volt::test('auth.two-factor-challenge')
        ->set('useRecovery', true)
        ->set('recoveryCode', $validCode)
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

it('removes used recovery code from the list', function () {
    $user = User::factory()->withTwoFactor()->create();

    $codes = json_decode((string) $user->two_factor_recovery_codes, true);
    $usedCode = $codes[0];

    Session::put('two_factor_user_id', $user->id);

    Volt::test('auth.two-factor-challenge')
        ->set('useRecovery', true)
        ->set('recoveryCode', $usedCode)
        ->call('authenticate');

    $remaining = json_decode((string) $user->fresh()->two_factor_recovery_codes, true);
    expect($remaining)->not->toContain($usedCode);
    expect(count($remaining))->toBe(7);
});

it('rejects an invalid recovery code', function () {
    $user = User::factory()->withTwoFactor()->create();

    Session::put('two_factor_user_id', $user->id);

    Volt::test('auth.two-factor-challenge')
        ->set('useRecovery', true)
        ->set('recoveryCode', 'FAKE-CODE')
        ->call('authenticate')
        ->assertHasErrors(['recoveryCode']);

    $this->assertGuest();
});
