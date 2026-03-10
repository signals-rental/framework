<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    /**
     * Redirect authenticated users with 2FA enabled to the challenge page
     * until they complete the second factor.
     *
     * Also enforces 2FA setup when required by security settings.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // If user has 2FA enabled, require challenge completion
        if ($user->hasTwoFactorEnabled() && ! Session::get('two_factor_confirmed')) {
            return redirect()->route('two-factor.challenge');
        }

        // If 2FA is required but not configured, redirect to profile settings
        if (! $user->hasTwoFactorEnabled() && $this->isTwoFactorRequired($user)) {
            if ($request->routeIs('settings.profile')) {
                return $next($request);
            }

            return redirect()->route('settings.profile');
        }

        return $next($request);
    }

    /**
     * Check if security settings require 2FA for this user.
     */
    private function isTwoFactorRequired(User $user): bool
    {
        if (settings('security.require_2fa_all', false)) {
            return true;
        }

        if (settings('security.require_2fa_admin', false) && $user->hasAdminAccess()) {
            return true;
        }

        return false;
    }
}
