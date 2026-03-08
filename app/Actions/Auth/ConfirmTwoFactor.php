<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class ConfirmTwoFactor
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Verify the given TOTP code and activate 2FA for the user.
     *
     * Generates and stores a fresh set of 8 recovery codes on success.
     *
     * @throws ValidationException When the code is invalid
     */
    public function __invoke(User $user, string $code): void
    {
        if (! $user->two_factor_secret || ! $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            throw ValidationException::withMessages([
                'code' => [__('The provided two-factor authentication code was invalid.')],
            ]);
        }

        $user->forceFill([
            'two_factor_recovery_codes' => json_encode($user->generateRecoveryCodes()),
        ])->save();
    }
}
