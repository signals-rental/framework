<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    /**
     * Enforce session timeout based on SecuritySettings.
     *
     * Tracks the last activity timestamp in the session. If the elapsed
     * time exceeds the configured timeout, the user is logged out.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $timeoutMinutes = max(1, (int) settings('security.session_timeout', 120));
        $lastActivity = $request->session()->get('last_activity');

        if ($lastActivity && (time() - $lastActivity) > ($timeoutMinutes * 60)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired.'], 401);
            }

            return redirect()->route('login')->with('status', __('Your session has expired due to inactivity.'));
        }

        $request->session()->put('last_activity', time());

        return $next($request);
    }
}
