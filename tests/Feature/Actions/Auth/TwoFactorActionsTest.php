<?php

use App\Actions\Auth\ConfirmTwoFactor;
use App\Actions\Auth\DisableTwoFactor;
use App\Actions\Auth\EnableTwoFactor;
use App\Actions\Auth\RegenerateTwoFactorRecoveryCodes;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

// ──────────────────────────────────────────────
// EnableTwoFactor
// ──────────────────────────────────────────────

it('stores an encrypted secret on the user', function () {
    $user = User::factory()->create();

    expect($user->two_factor_secret)->toBeNull();

    app(EnableTwoFactor::class)($user);

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
});

it('returns an OTP auth URL', function () {
    $user = User::factory()->create();

    $url = app(EnableTwoFactor::class)($user);

    expect($url)->toStartWith('otpauth://totp/');
});

it('returns existing OTP URL without generating new secret when 2FA is already enabled', function () {
    $user = User::factory()->withTwoFactor()->create();
    $originalSecret = $user->two_factor_secret;

    $url = app(EnableTwoFactor::class)($user);

    $user->refresh();
    expect($user->two_factor_secret)->toBe($originalSecret);
    expect($url)->toStartWith('otpauth://totp/');
});

it('does not enable 2FA until confirmed', function () {
    $user = User::factory()->create();

    app(EnableTwoFactor::class)($user);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

// ──────────────────────────────────────────────
// ConfirmTwoFactor
// ──────────────────────────────────────────────

it('activates 2FA and generates recovery codes when code is valid', function () {
    $user = User::factory()->create();
    $secret = app(Google2FA::class)->generateSecretKey();
    $user->forceFill(['two_factor_secret' => $secret])->save();

    $code = app(Google2FA::class)->getCurrentOtp($secret);

    app(ConfirmTwoFactor::class)($user, $code);

    $user->refresh();
    expect($user->hasTwoFactorEnabled())->toBeTrue();
    $codes = json_decode((string) $user->two_factor_recovery_codes, true);
    expect($codes)->toHaveCount(8);
});

it('throws ValidationException for an invalid TOTP code', function () {
    $user = User::factory()->create();
    $secret = app(Google2FA::class)->generateSecretKey();
    $user->forceFill(['two_factor_secret' => $secret])->save();

    expect(fn () => app(ConfirmTwoFactor::class)($user, '000000'))
        ->toThrow(ValidationException::class);
});

// ──────────────────────────────────────────────
// DisableTwoFactor
// ──────────────────────────────────────────────

it('clears secret and recovery codes', function () {
    $user = User::factory()->withTwoFactor()->create();

    expect($user->hasTwoFactorEnabled())->toBeTrue();

    (new DisableTwoFactor)($user);

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull();
    expect($user->two_factor_recovery_codes)->toBeNull();
    expect($user->hasTwoFactorEnabled())->toBeFalse();
});

it('removes two_factor_confirmed from session when disabling', function () {
    $user = User::factory()->withTwoFactor()->create();

    Session::put('two_factor_confirmed', true);

    (new DisableTwoFactor)($user);

    expect(Session::get('two_factor_confirmed'))->toBeNull();
});

// ──────────────────────────────────────────────
// RegenerateTwoFactorRecoveryCodes
// ──────────────────────────────────────────────

it('generates a fresh set of 8 recovery codes', function () {
    $user = User::factory()->withTwoFactor()->create();

    $originalCodes = json_decode((string) $user->two_factor_recovery_codes, true);

    $newCodes = (new RegenerateTwoFactorRecoveryCodes)($user);

    expect($newCodes)->toHaveCount(8);
    expect($newCodes)->not->toEqual($originalCodes);

    $storedCodes = json_decode((string) $user->fresh()->two_factor_recovery_codes, true);
    expect($storedCodes)->toEqual($newCodes);
});

it('recovery codes follow the XXXX-XXXX format', function () {
    $user = User::factory()->withTwoFactor()->create();

    $codes = (new RegenerateTwoFactorRecoveryCodes)($user);

    foreach ($codes as $code) {
        expect($code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');
    }
});
