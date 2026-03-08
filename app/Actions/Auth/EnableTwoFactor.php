<?php

namespace App\Actions\Auth;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

class EnableTwoFactor
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Generate a new 2FA secret and store it on the user.
     *
     * This does NOT activate 2FA — the user must confirm with a valid code
     * via ConfirmTwoFactor before 2FA is considered enabled.
     *
     * @return string The OTP auth URL for QR code generation
     */
    public function __invoke(User $user): string
    {
        if ($user->hasTwoFactorEnabled()) {
            return $this->google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                (string) $user->two_factor_secret,
            );
        }

        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill(['two_factor_secret' => $secret])->save();

        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );
    }
}
