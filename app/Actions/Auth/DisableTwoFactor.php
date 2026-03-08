<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Session;

class DisableTwoFactor
{
    /**
     * Disable two-factor authentication for the given user.
     */
    public function __invoke(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        Session::forget('two_factor_confirmed');
    }
}
