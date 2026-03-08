<?php

namespace App\Http\Middleware;

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
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $requiresChallenge = $user
            && $user->hasTwoFactorEnabled()
            && ! Session::get('two_factor_confirmed');

        if (! $requiresChallenge) {
            return $next($request);
        }

        return redirect()->route('two-factor.challenge');
    }
}
